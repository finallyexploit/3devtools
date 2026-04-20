using System;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace loader_goddev;

/// <summary>
/// Менеджер удаленной загрузки защищенных модулей.
/// Модули загружаются с сервера только при наличии валидной сессии и целостности окружения.
/// </summary>
public static class RemoteModuleLoader
{
    private static readonly HttpClient _client = new()
    {
        Timeout = TimeSpan.FromSeconds(30)
    };

    private const string ModuleApiUrl = "https://soulware.space/api/module.php";

    /// <summary>
    /// Загружает и исполняет защищенный модуль с сервера.
    /// </summary>
    /// <param name="moduleName">Имя модуля (например, "core_logic")</param>
    /// <param name="security">Экземпляр LoaderSecurity для проверок</param>
    /// <param name="args">Аргументы для передачи в модуль</param>
    public static async Task<ModuleExecutionResult> LoadAndExecuteAsync(
      string moduleName,
      LoaderSecurity security,
      params object[] args)
    {
        try
        {
            // Шаг 1: Проверка целостности окружения
            var integrityCheck = BuildIntegrityCheck(security);
            if (!integrityCheck.IsValid)
            {
                security.Audit.Warn("module", $"Integrity check failed for {moduleName}");
                return ModuleExecutionResult.CreateFail("integrity_check_failed", "Окружение не прошло проверку целостности");
            }

            // Шаг 2: Запрос модуля с сервера
            // ИСПРАВЛЕНО: используем токен из ProtectedStore вместо LastSessionToken
            var sessionToken = security.ProtectedStore.LoadText();
            if (string.IsNullOrEmpty(sessionToken))
            {
                sessionToken = Convert.ToBase64String(Guid.NewGuid().ToByteArray());
                security.ProtectedStore.SaveText(sessionToken);
            }

            var request = new ModuleRequest
            {
                Module = moduleName,
                SessionToken = sessionToken,
                IntegrityHash = integrityCheck.Hash,
                EnvironmentInfo = integrityCheck.Info,
                Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };

            var json = JsonSerializer.Serialize(request);
            var content = new StringContent(json, Encoding.UTF8, "application/json");

            var response = await _client.PostAsync(ModuleApiUrl, content);
            var responseJson = await response.Content.ReadAsStringAsync();

            if (!response.IsSuccessStatusCode)
            {
                security.Audit.Warn("module", $"Server rejected module request: {response.StatusCode}");
                return await LoadPublicFallbackAsync(moduleName, security);
            }

            var moduleResponse = JsonSerializer.Deserialize<ModuleResponse>(responseJson);
            if (!VerifyResponseSignature(moduleResponse, security))
            {
                security.Audit.Warn("module", $"Invalid signature for {moduleName}");
                return ModuleExecutionResult.CreateFail("invalid_signature", "signature not upd");
            }

            var assemblyBytes = DecryptModule(moduleResponse!.Data, moduleResponse.Iv, security);
            if (assemblyBytes == null || assemblyBytes.Length == 0)
            {
                return ModuleExecutionResult.CreateFail("decryption_failed", "module failed");
            }

            var computedHash = ComputeSha256Bytes(assemblyBytes);
            if (!string.Equals(computedHash, moduleResponse.AssemblyHash, StringComparison.OrdinalIgnoreCase))
            {
                security.Audit.Warn("module", $"Hash mismatch for {moduleName}");
                return ModuleExecutionResult.CreateFail("hash_mismatch", "mismatch hash XDDXDXDXD");
            }
            var assembly = Assembly.Load(assemblyBytes);// ComputeSha256Bytes dont use plz
            var entryPoint = FindEntryPoint(assembly, moduleName);
            if (entryPoint == null)
            {
                return ModuleExecutionResult.CreateFail("no_entry_point", "no entry point");
            }
            var result = InvokeEntryPoint(entryPoint, args);

            security.Audit.Info("module", $"Successfully executed {moduleName}");
            return ModuleExecutionResult.CreateSuccess(result);
        }
        catch (Exception ex)
        {
            security.Audit.Error("module", ex);
            return ModuleExecutionResult.CreateFail("exception", ex.Message);
        }
    }

    private static IntegrityCheckResult BuildIntegrityCheck(LoaderSecurity security)
    {
        var startup = security.LastStartup ?? security.RunStartupChecks();
        var session = security.Sessions.Load();

        var info = new
        {
            startup.BadgeText,
            PassedChecks = startup.PassedChecks,
            TotalChecks = startup.Checks.Count,
            Machine = Environment.MachineName,
            UserName = Environment.UserName,
            OsVersion = Environment.OSVersion.VersionString,
            Is64Bit = Environment.Is64BitOperatingSystem,
            AppVersion = AppVersion.Value,
            SessionAge = (DateTimeOffset.UtcNow - session.LastLoginUtc).TotalMinutes
        };

        var json = JsonSerializer.Serialize(info);
        var hash = ComputeSha256Text(json + GetMachineSalt(security));

        return new IntegrityCheckResult
        {
            IsValid = startup.AllowUi && startup.BadgeText != "blocked",
            Hash = hash,
            Info = json
        };
    }

    private static string GetMachineSalt(LoaderSecurity security)
    {
        var protectedData = security.ProtectedStore.LoadText();
        if (string.IsNullOrEmpty(protectedData))
        {
            protectedData = Convert.ToBase64String(Guid.NewGuid().ToByteArray());
            security.ProtectedStore.SaveText(protectedData);
        }
        return ComputeSha256Text(protectedData + Environment.MachineName);
    }

    private static async Task<ModuleExecutionResult> LoadPublicFallbackAsync(string moduleName, LoaderSecurity security)
    {
        try
        {
            var url = $"https://soulware.space/api/public/{moduleName}.json";
            var json = await _client.GetStringAsync(url);
            var fallback = JsonSerializer.Deserialize<PublicModuleResponse>(json);

            if (fallback == null)
            {
                return ModuleExecutionResult.CreateFail("no_fallback", "Публичный модуль недоступен");
            }

            security.Audit.Info("module", $"Loaded public fallback for {moduleName}");

            return ModuleExecutionResult.CreateSuccess(new
            {
                Mode = "demo",
                Message = fallback.Message,
                Data = fallback.Data
            });
        }
        catch
        {
            return ModuleExecutionResult.CreateFail("fallback_failed", "Не удалось загрузить даже демо-режим");
        }
    }

    private static bool VerifyResponseSignature(ModuleResponse? response, LoaderSecurity security)
    {
        if (response == null) return false;

        var dataToSign = $"{response.AssemblyHash}|{response.Iv}|{response.Timestamp}";
        var computedSignature = ComputeSha256Text(dataToSign + GetMachineSalt(security));

        return string.Equals(computedSignature, response.Signature, StringComparison.OrdinalIgnoreCase);
    }

    private static byte[] DecryptModule(string base64Data, string base64Iv, LoaderSecurity security)
    {
        try
        {
            var data = Convert.FromBase64String(base64Data);
            var iv = Convert.FromBase64String(base64Iv);

            // Ключ формируется из защищенных данных машины
            var keyMaterial = GetMachineSalt(security);
            var key = SHA256.HashData(Encoding.UTF8.GetBytes(keyMaterial));

            using var aes = Aes.Create();
            aes.Key = key;
            aes.IV = iv;
            aes.Mode = CipherMode.CBC;
            aes.Padding = PaddingMode.PKCS7;

            using var decryptor = aes.CreateDecryptor();
            return decryptor.TransformFinalBlock(data, 0, data.Length);
        }
        catch
        {
            return Array.Empty<byte>();
        }
    }

    private static MethodInfo? FindEntryPoint(Assembly assembly, string moduleName)
    {
        foreach (var type in assembly.GetExportedTypes())
        {
            var attr = type.GetCustomAttribute<RemoteModuleAttribute>();
            if (attr != null && attr.ModuleName == moduleName)
            {
                return type.GetMethod("Execute", BindingFlags.Public | BindingFlags.Static)
                    ?? type.GetMethod("Execute", BindingFlags.Public | BindingFlags.Instance);
            }
        }
        var fallbackType = assembly.GetExportedTypes()
            .FirstOrDefault(t => t.Name.Equals(moduleName, StringComparison.OrdinalIgnoreCase) ||
                                 t.Name.EndsWith("Module", StringComparison.OrdinalIgnoreCase));

        return fallbackType?.GetMethod("Execute", BindingFlags.Public | BindingFlags.Static)
            ?? fallbackType?.GetMethod("Execute", BindingFlags.Public | BindingFlags.Instance);
    }

    private static object? InvokeEntryPoint(MethodInfo method, object[] args)
    {
        object? instance = null;
        if (!method.IsStatic)
        {
            instance = Activator.CreateInstance(method.DeclaringType!);
        }

        var parameters = method.GetParameters();
        if (parameters.Length != args.Length)
        {
            // Пытаемся подогнать аргументы
            var convertedArgs = new object?[parameters.Length];
            for (int i = 0; i < parameters.Length && i < args.Length; i++)
            {
                convertedArgs[i] = Convert.ChangeType(args[i], parameters[i].ParameterType);
            }
            return method.Invoke(instance, convertedArgs);
        }

        return method.Invoke(instance, args);
    }

    private static string ComputeSha256Text(string text)
    {
        var hash = SHA256.HashData(Encoding.UTF8.GetBytes(text ?? string.Empty));
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static string ComputeSha256Bytes(byte[] data)
    {
        var hash = SHA256.HashData(data);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }
}

/// <summary>
/// Атрибут для маркировки точки входа в удаленном модуле.
/// </summary>
[AttributeUsage(AttributeTargets.Class)]
public class RemoteModuleAttribute : Attribute
{
    public string ModuleName { get; }
    public RemoteModuleAttribute(string moduleName) => ModuleName = moduleName;
}

/// <summary>
/// Результат выполнения удаленного модуля.
/// </summary>
public class ModuleExecutionResult
{
    public bool IsSuccess { get; set; }
    public string? ErrorCode { get; set; }
    public string? ErrorMessage { get; set; }
    public object? Result { get; set; }

    public static ModuleExecutionResult CreateSuccess(object? result = null) => new()
    {
        IsSuccess = true,
        Result = result
    };

    public static ModuleExecutionResult CreateFail(string code, string message) => new()
    {
        IsSuccess = false,
        ErrorCode = code,
        ErrorMessage = message
    };
}

// Вспомогательные классы для сериализации
internal class ModuleRequest
{
    public string Module { get; set; } = string.Empty;
    public string SessionToken { get; set; } = string.Empty;
    public string IntegrityHash { get; set; } = string.Empty;
    public string EnvironmentInfo { get; set; } = string.Empty;
    public long Timestamp { get; set; }
}

internal class ModuleResponse
{
    public string Status { get; set; } = string.Empty;
    public string Data { get; set; } = string.Empty;
    public string Iv { get; set; } = string.Empty;
    public string AssemblyHash { get; set; } = string.Empty;
    public string Signature { get; set; } = string.Empty;
    public long Timestamp { get; set; }
}

internal class PublicModuleResponse
{
    public string Message { get; set; } = string.Empty;
    public object? Data { get; set; }
}

internal class IntegrityCheckResult
{
    public bool IsValid { get; set; }
    public string Hash { get; set; } = string.Empty;
    public string Info { get; set; } = string.Empty;
}