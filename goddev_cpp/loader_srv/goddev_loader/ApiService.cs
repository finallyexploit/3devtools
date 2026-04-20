using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Net;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace loader_goddev;

internal sealed class ApiService
{
    public const string Brand = "GOD DEV";
    private const string ApiUrl = "https://soulware.space/api.php";
    private const string ShieldApiUrl = "https://soulware.space/api/spectreshield.php";
    private const string ShieldSecret = "0x95e03f"; // Должен совпадать с SHIELD_SECRET в PHP

    private readonly CookieContainer _cookies = new();
    private readonly JsonSerializerOptions _json = new() { PropertyNameCaseInsensitive = true };
    private readonly HttpClient _client;
    private string _csrfToken = string.Empty;
    private string _sessionToken = string.Empty;
    private ApiResponse? _currentUser;

    public ApiService()
    {
        _client = new HttpClient(new HttpClientHandler
        {
            UseCookies = true,
            CookieContainer = _cookies,
            AutomaticDecompression = DecompressionMethods.GZip | DecompressionMethods.Deflate,
        })
        {
            Timeout = TimeSpan.FromSeconds(30)
        };

        _client.DefaultRequestHeaders.UserAgent.ParseAdd($"{Brand}/2.1 WPF");
        _client.DefaultRequestHeaders.Accept.ParseAdd("application/json");
    }

    // ==================== СТАНДАРТНЫЕ МЕТОДЫ API ====================

    public async Task<ApiResponse?> LoginAsync(string username, string password)
    {
        var res = await PostAsync<ApiResponse>(ApiUrl, new
        {
            action = "login",
            username,
            password,
            hwid = BuildMachineToken(),
            client_ver = AppVersion.Value,
            client_ui = "wpf",
        });

        if (string.Equals(res?.status, "success", StringComparison.OrdinalIgnoreCase))
        {
            _csrfToken = res?.csrf_token ?? string.Empty;
            _currentUser = res;

            // После успешного входа - верификация через SpectreShield
            await VerifyLoginWithShieldAsync(username);
        }

        return res;
    }

    public async Task<ApiResponse?> LogoutAsync()
    {
        var res = await PostAsync<ApiResponse>(ApiUrl, new
        {
            action = "logout",
            csrf_token = _csrfToken,
        });

        _csrfToken = string.Empty;
        _sessionToken = string.Empty;
        _currentUser = null;
        return res;
    }

    public async Task<LicenseInfoResponse?> GetLicenseInfoAsync(string key)
    {
        var url = $"{ApiUrl}?action=get_license_info&license_key={Uri.EscapeDataString(key)}";
        return await GetAsync<LicenseInfoResponse>(url);
    }

    // ⚠️ Этот метод теперь DEPRECATED - используй ProcessLaunchShieldAsync
    [Obsolete("Use ProcessLaunchShieldAsync instead - launch logic moved to SpectreShield")]
    public static (bool ok, string message, string path) LaunchSoftware(LoaderSecurity security)
    {
        // Возвращаем заглушку - реальная логика на сервере
        return (false, "Use ProcessLaunchShieldAsync instead", string.Empty);
    }
    // ==================== SPECTRESHIELD ЗАЩИЩЕННЫЕ МЕТОДЫ ====================

    /// <summary>
    /// Проверяет лицензию через SpectreShield (критическая логика на сервере)
    /// </summary>
    public async Task<LicenseValidationResult?> ValidateLicenseShieldAsync(string licenseKey, LoaderSecurity security)
    {
        var result = await ExecuteShieldedOperationAsync<LicenseValidationResult>(
            "validate_license",
            security,
            new { license_key = licenseKey }
        );

        return result;
    }

    /// <summary>
    /// Проверяет HWID через SpectreShield
    /// </summary>
    public async Task<HwidCheckResult?> CheckHwidShieldAsync(LoaderSecurity security)
    {
        var result = await ExecuteShieldedOperationAsync<HwidCheckResult>(
            "check_hwid",
            security,
            new { }
        );

        return result;
    }

    /// <summary>
    /// Обрабатывает запуск программы через SpectreShield
    /// </summary>
    public async Task<LaunchProcessResult?> ProcessLaunchShieldAsync(string targetPath, string licenseKey, LoaderSecurity security)
    {
        var result = await ExecuteShieldedOperationAsync<LaunchProcessResult>(
            "process_launch",
            security,
            new
            {
                target_path = targetPath,
                license_key = licenseKey
            }
        );

        return result;
    }

    /// <summary>
    /// Проверяет целостность клиента через SpectreShield
    /// </summary>
    public async Task<IntegrityVerificationResult?> VerifyIntegrityShieldAsync(LoaderSecurity security)
    {
        var startup = security.LastStartup ?? security.RunStartupChecks();

        var checks = new Dictionary<string, bool>();
        foreach (var check in startup.Checks)
        {
            checks[check.Code] = check.Passed;
        }

        var result = await ExecuteShieldedOperationAsync<IntegrityVerificationResult>(
            "verify_integrity",
            security,
            new
            {
                checks = checks,
                badge = startup.BadgeText,
                debugger_detected = SecurityEnhancements.IsDebuggerAttached,
                sandbox_detected = SecurityEnhancements.IsRunningInSandbox(),
                vm_detected = false // Можно добавить проверку
            }
        );

        return result;
    }

    /// <summary>
    /// Генерирует лицензионный ключ через SpectreShield (только для админов)
    /// </summary>
    public async Task<GeneratedLicenseResult?> GenerateLicenseShieldAsync(
        string productType,
        int durationDays,
        LoaderSecurity security)
    {
        var result = await ExecuteShieldedOperationAsync<GeneratedLicenseResult>(
            "generate_license_key",
            security,
            new
            {
                product_type = productType,
                duration_days = durationDays
            }
        );

        return result;
    }

    /// <summary>
    /// Проверяет подпись сборки через SpectreShield
    /// </summary>
    public async Task<AssemblySignatureResult?> VerifyAssemblySignatureShieldAsync(
        string assemblyName,
        string assemblyHash,
        LoaderSecurity security)
    {
        var result = await ExecuteShieldedOperationAsync<AssemblySignatureResult>(
            "verify_assembly_signature",
            security,
            new
            {
                assembly_name = assemblyName,
                assembly_hash = assemblyHash
            }
        );

        return result;
    }

    /// <summary>
    /// Получает защищенную конфигурацию через SpectreShield
    /// </summary>
    public async Task<SecureConfigResult?> GetSecureConfigShieldAsync(LoaderSecurity security)
    {
        var userLevel = _currentUser?.is_admin == true ? "admin" : "default";

        var result = await ExecuteShieldedOperationAsync<SecureConfigResult>(
            "get_secure_config",
            security,
            new { config_type = userLevel }
        );

        return result;
    }

    /// <summary>
    /// Выполняет защищенный запрос к SpectreShield API
    /// </summary>
    private async Task<T?> ExecuteShieldedOperationAsync<T>(
        string action,
        LoaderSecurity security,
        object payload)
    {
        try
        {
            // Подготовка данных
            var hwid = BuildMachineToken();
            var startup = security.LastStartup ?? security.RunStartupChecks();
            var session = security.Sessions.Load();

            var requestPayload = new Dictionary<string, object>
            {
                ["hwid"] = hwid,
                ["username"] = _currentUser?.username ?? "",
                ["user_id"] = _currentUser?.UserId ?? 0,
                ["machine_info"] = new
                {
                    machine = Environment.MachineName,
                    user = Environment.UserName,
                    os = Environment.OSVersion.VersionString,
                    processors = Environment.ProcessorCount,
                    is_64bit = Environment.Is64BitOperatingSystem
                },
                ["startup_badge"] = startup.BadgeText,
                ["version"] = AppVersion.Value,
                ["session_token"] = _sessionToken,
                ["custom_payload"] = payload
            };

            // Вычисление подписи
            var timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
            var signature = CalculateShieldSignature(requestPayload, timestamp);
            var clientHash = CalculateClientHash(requestPayload);

            var request = new ShieldRequest
            {
                action = action,
                payload = requestPayload,
                signature = signature,
                timestamp = timestamp,
                client_hash = clientHash
            };

            var json = JsonSerializer.Serialize(request);
            var content = new StringContent(json, Encoding.UTF8, "application/json");

            var response = await _client.PostAsync(ShieldApiUrl, content);
            var responseJson = await response.Content.ReadAsStringAsync();

            var result = JsonSerializer.Deserialize<ShieldResponse<T>>(responseJson, _json);

            if (result?.status == "success")
            {
                // Сохраняем сессионный токен если есть
                if (result.data != null)
                {
                    var dataJson = JsonSerializer.Serialize(result.data);
                    var dataDoc = JsonDocument.Parse(dataJson);

                    if (dataDoc.RootElement.TryGetProperty("session_token", out var token))
                    {
                        _sessionToken = token.GetString() ?? "";
                    }
                }

                security.Audit.Info("shield", $"Operation {action} completed successfully");
                return result.data;
            }

            security.Audit.Warn("shield", $"Operation {action} failed: {result?.message ?? "unknown"} (Code: {result?.code ?? "none"})");
            Debug.WriteLine($"Shield error: {result?.code} - {result?.message}");
            return default;
        }
        catch (Exception ex)
        {
            security.Audit.Error("shield", ex);
            Debug.WriteLine($"Shield exception: {ex.Message}");
            return default;
        }
    }

    /// <summary>
    /// Верифицирует логин через SpectreShield (дополнительная защита)
    /// </summary>
    private async Task VerifyLoginWithShieldAsync(string username)
    {
        try
        {
            var security = LoaderSecurity.CreateDefault();

            var result = await ExecuteShieldedOperationAsync<LoginVerificationResult>(
                "verify_login",
                security,
                new { username = username }
            );

            if (result != null && result.allowed)
            {
                _sessionToken = result.session_token ?? "";
                Debug.WriteLine($"Shield login verification successful for {username}");
            }
            else
            {
                Debug.WriteLine($"Shield login verification failed: {result?.reason ?? "unknown"}");
            }
        }
        catch (Exception ex)
        {
            Debug.WriteLine($"Shield login verification error: {ex.Message}");
        }
    }

    // ==================== УДАЛЕННЫЕ МОДУЛИ ====================

    /// <summary>
    /// Выполняет критическую бизнес-операцию через удаленный модуль.
    /// </summary>
    public async Task<T?> ExecuteSecureOperationAsync<T>(
        string moduleName,
        LoaderSecurity security,
        params object[] args)
    {
        // Проверяем уровень безопасности окружения
        var securityLevel = SecurityEnhancements.GetEnvironmentSecurityLevel(security);

        if (securityLevel == EnvironmentSecurityLevel.HighRisk)
        {
            security.Audit.Warn("api", "High risk environment detected, aborting secure operation");
            return default;
        }

        if (securityLevel >= EnvironmentSecurityLevel.Suspicious)
        {
            security.Audit.Warn("api", $"Suspicious environment, using demo mode for {moduleName}");
        }

        var result = await RemoteModuleLoader.LoadAndExecuteAsync(moduleName, security, args);

        if (!result.IsSuccess)
        {
            Debug.WriteLine($"Module execution failed: {result.ErrorCode} - {result.ErrorMessage}");
            return default;
        }

        if (result.Result is T typedResult)
            return typedResult;

        try
        {
            return JsonSerializer.Deserialize<T>(
                JsonSerializer.Serialize(result.Result)
            );
        }
        catch
        {
            return default;
        }
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ====================

    private async Task<T?> PostAsync<T>(string url, object payload)
    {
        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Post, url)
            {
                Content = new StringContent(JsonSerializer.Serialize(payload), Encoding.UTF8, "application/json")
            };

            if (!string.IsNullOrWhiteSpace(_csrfToken))
                req.Headers.TryAddWithoutValidation("X-CSRF-Token", _csrfToken);

            using var res = await _client.SendAsync(req);
            var raw = await res.Content.ReadAsStringAsync();
            return JsonSerializer.Deserialize<T>(raw, _json);
        }
        catch
        {
            return default;
        }
    }

    private async Task<T?> GetAsync<T>(string url)
    {
        try
        {
            using var res = await _client.GetAsync(url);
            var raw = await res.Content.ReadAsStringAsync();
            return JsonSerializer.Deserialize<T>(raw, _json);
        }
        catch
        {
            return default;
        }
    }

    private static string BuildMachineToken()
    {
        try
        {
            var raw = string.Join("|",
                Environment.MachineName,
                Environment.UserName,
                Environment.OSVersion.VersionString,
                Environment.ProcessorCount,
                Environment.Is64BitOperatingSystem,
                Environment.SystemDirectory);

            var hash = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
            return Convert.ToHexString(hash).ToLowerInvariant()[..32];
        }
        catch
        {
            return "machine-token-unavailable";
        }
    }

    private static string CalculateShieldSignature(object payload, long timestamp)
    {
        var json = JsonSerializer.Serialize(payload);
        var data = json + "|" + timestamp + "|" + ShieldSecret;
        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(ShieldSecret));
        var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes(data));
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static string CalculateClientHash(object payload)
    {
        var json = JsonSerializer.Serialize(payload);
        using var sha = SHA256.Create();
        var hash = sha.ComputeHash(Encoding.UTF8.GetBytes(json));
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    // ==================== ВНУТРЕННИЕ КЛАССЫ ====================

    private class ShieldRequest
    {
        public string action { get; set; } = "";
        public object payload { get; set; } = new();
        public string signature { get; set; } = "";
        public long timestamp { get; set; }
        public string client_hash { get; set; } = "";
    }

    private class ShieldResponse<T>
    {
        public string status { get; set; } = "";
        public string? code { get; set; }
        public string? message { get; set; }
        public T? data { get; set; }
        public string? signature { get; set; }
        public long timestamp { get; set; }
        public string? shield_version { get; set; }
    }

    // ==================== ТИПЫ РЕЗУЛЬТАТОВ SPECTRESHIELD ====================

    public class LicenseValidationResult
    {
        public bool valid { get; set; }
        public string status { get; set; } = "";
        public string message { get; set; } = "";
        public string? product_type { get; set; }
        public string? product_name { get; set; }
        public string? expires_at { get; set; }
        public int? days_left { get; set; }
        public string? server_time { get; set; }
        public string? session_token { get; set; }
        public string? checksum { get; set; }
        public string? bound_hwid { get; set; }
    }

    public class HwidCheckResult
    {
        public bool valid { get; set; }
        public bool format_ok { get; set; }
        public bool blacklisted { get; set; }
        public string? first_seen { get; set; }
        public string? last_seen { get; set; }
        public int devices_count { get; set; }
        public string? server_token { get; set; }
    }

    public class LaunchProcessResult
    {
        public bool allowed { get; set; }
        public string reason { get; set; } = "";
        public string? launch_token { get; set; }
        public string? session_id { get; set; }
        public string? license_type { get; set; }
        public string? server_time { get; set; }
        public Dictionary<string, object>? launch_parameters { get; set; }
    }

    public class IntegrityVerificationResult
    {
        public bool passed { get; set; }
        public string badge { get; set; } = "";
        public List<string>? failed_checks { get; set; }
        public bool version_ok { get; set; }
        public string? min_version { get; set; }
        public Dictionary<string, bool>? security_flags { get; set; }
        public string? server_time { get; set; }
        public string? integrity_token { get; set; }
        public string? warning { get; set; }
    }

    public class LoginVerificationResult
    {
        public bool allowed { get; set; }
        public string reason { get; set; } = "";
        public string? session_token { get; set; }
        public string? refresh_token { get; set; }
        public int? user_id { get; set; }
        public string? username { get; set; }
        public string? email { get; set; }
        public decimal? balance { get; set; }
        public bool is_admin { get; set; }
        public object? license { get; set; }
        public int? expires_in { get; set; }
        public string? server_time { get; set; }
        public bool requires_2fa { get; set; }
        public string? ban_reason { get; set; }
        public int? wait_minutes { get; set; }
    }

    public class GeneratedLicenseResult
    {
        public string license_key { get; set; } = "";
        public string product_type { get; set; } = "";
        public int duration_days { get; set; }
        public string generated_at { get; set; } = "";
        public string checksum { get; set; } = "";
        public string key_format { get; set; } = "";
    }

    public class AssemblySignatureResult
    {
        public bool valid { get; set; }
        public string reason { get; set; } = "";
        public string assembly_name { get; set; } = "";
        public string? expected { get; set; }
        public string? received { get; set; }
        public string? signature_level { get; set; }
        public bool signature_verified { get; set; }
        public object? certificate_info { get; set; }
    }

    public class SecureConfigResult
    {
        public Dictionary<string, string>? api_endpoints { get; set; }
        public Dictionary<string, bool>? features { get; set; }
        public Dictionary<string, int>? timeouts { get; set; }
        public string? session_token { get; set; }
        public string? config_version { get; set; }
        public string? server_time { get; set; }
        public string? expires_at { get; set; }
    }
}