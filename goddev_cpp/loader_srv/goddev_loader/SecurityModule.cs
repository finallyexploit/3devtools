using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace loader_goddev;

public sealed class LoaderSecurity
{
    public SecurityFolders Folders { get; }
    public SecuritySettingsStore Settings { get; }
    public SecurityAuditLogger Audit { get; }
    public LoginAttemptGuard LoginAttempts { get; }
    public IntegrityGuard Integrity { get; }
    public InputPolicies Input { get; }
    public ProtectedStore ProtectedStore { get; }
    public SessionJournal Sessions { get; }
    public SecurityStartupResult? LastStartup { get; private set; }

    public static LoaderSecurity CreateDefault(string brand = ApiService.Brand)
        => new(new SecurityFolders(brand));

    public LoaderSecurity(SecurityFolders folders)
    {
        Folders = folders;
        Audit = new SecurityAuditLogger(folders);
        Settings = new SecuritySettingsStore(folders, Audit);
        LoginAttempts = new LoginAttemptGuard(folders, Audit);
        ProtectedStore = new ProtectedStore(folders, Audit);
        Integrity = new IntegrityGuard(folders, Audit);
        Input = new InputPolicies();
        Sessions = new SessionJournal(folders, Audit);
    }

    public SecurityStartupResult RunStartupChecks()
    {
        var checks = new List<SecurityCheck>
        {
            Integrity.CheckExecutablePath(),
            Integrity.CheckWorkingDirectories(),
            Integrity.CheckSettingsStore(Settings),
            Integrity.CheckProtectedStore(ProtectedStore),
            // ⚠️ CheckAuthenticodeSignature и CheckOptionalManifest ПЕРЕНЕСЕНЫ НА СЕРВЕР (SpectreShield)
        };

        var result = SecurityStartupResult.FromChecks(checks);
        LastStartup = result;
        Audit.WriteStartup(result);
        return result;
    }

    public UiSettings LoadSettings() => Settings.Load();

    public void SaveUiState(string theme, string language, bool rememberUsername, string username)
    {
        var settings = Settings.Load();
        settings.Theme = theme;
        settings.Language = language;
        settings.RememberUsername = rememberUsername;
        settings.Username = rememberUsername ? username.Trim() : string.Empty;
        Settings.Save(settings);
    }

    public void SaveLoginSuccess(string username, string theme, string language, bool rememberUsername)
    {
        LoginAttempts.Reset();
        SaveUiState(theme, language, rememberUsername, username);

        var session = Sessions.Load();
        session.Username = username.Trim();
        session.LastLoginUtc = DateTimeOffset.UtcNow;
        session.LastStatus = "success";
        session.Machine = Environment.MachineName;
        session.AppVersion = AppVersion.Value;
        Sessions.Save(session);

        Audit.Info("auth", $"login success for '{username.Trim()}'");
    }

    public void SaveLoginFailure(string username, string reason)
    {
        LoginAttempts.RegisterFailure();
        Sessions.MarkFailure(username, reason);
        Audit.Warn("auth", $"login failed for '{username.Trim()}': {reason}");
    }

    // ⚠️ SaveLaunchSuccess и SaveLaunchFailure ПЕРЕНЕСЕНЫ НА СЕРВЕР (SpectreShield)

    public string BuildSecuritySummary(ApiResponse? user)
    {
        var settings = Settings.Load();
        var session = Sessions.Load();
        var startup = LastStartup ?? RunStartupChecks();

        var savedUser = settings.RememberUsername
            ? string.IsNullOrWhiteSpace(settings.Username) ? session.Username ?? "-" : settings.Username
            : "-";

        var lines = new List<string>
        {
            $"Startup state : {startup.BadgeText}",
            $"Checks passed : {startup.PassedChecks}/{startup.Checks.Count}",
            $"Warnings      : {startup.WarningCount}",
            $"Blocked       : {(startup.AllowUi ? "no" : "yes")}",
            string.Empty,
            "Session",
            $"Saved user    : {savedUser}",
            $"Last login    : {FormatUtc(session.LastLoginUtc)}",
            string.Empty,
            "Release",
            $"Remember user : {(settings.RememberUsername ? "on" : "off")}",
            $"Language      : {settings.Language}",
            $"Theme         : {settings.Theme}",
            string.Empty,
            "Access",
            $"Current user  : {user?.username ?? "-"}",
            $"License       : {(user?.has_license == true ? user.license_type ?? "active" : "none")}",
            $"App version   : {AppVersion.Value}",
        };

        return string.Join(Environment.NewLine, lines);
    }

    public static string FormatUtc(DateTimeOffset? value)
        => value is null || value == DateTimeOffset.MinValue
            ? "-"
            : value.Value.ToLocalTime().ToString("yyyy-MM-dd HH:mm:ss");
}

public static class AppVersion
{
    public static string Value
    {
        get
        {
            try
            {
                var v = typeof(AppVersion).Assembly.GetName().Version;
                return v is null ? "1.0.0" : $"{v.Major}.{v.Minor}.{Math.Max(v.Build, 0)}";
            }
            catch
            {
                return "1.0.0";
            }
        }
    }
}

public sealed class SecurityFolders
{
    public string Brand { get; }
    public string RootDir { get; }
    public string LogsDir { get; }
    public string CacheDir { get; }
    public string DataDir { get; }
    public string SettingsFile { get; }
    public string AttemptsFile { get; }
    public string SessionFile { get; }
    public string AuditFile { get; }
    public string AppLogFile { get; }
    public string ProtectedBlobFile { get; }

    public SecurityFolders(string brand)
    {
        Brand = brand;
        RootDir = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            brand);
        LogsDir = Path.Combine(RootDir, "logs");
        CacheDir = Path.Combine(RootDir, "cache");
        DataDir = Path.Combine(RootDir, "data");
        SettingsFile = Path.Combine(RootDir, "settings.json");
        AttemptsFile = Path.Combine(DataDir, "attempts.json");
        SessionFile = Path.Combine(DataDir, "session.json");
        AuditFile = Path.Combine(LogsDir, "audit.log");
        AppLogFile = Path.Combine(LogsDir, "app.log");
        ProtectedBlobFile = Path.Combine(DataDir, "protected.bin");
    }

    public void EnsureAll()
    {
        Directory.CreateDirectory(RootDir);
        Directory.CreateDirectory(LogsDir);
        Directory.CreateDirectory(CacheDir);
        Directory.CreateDirectory(DataDir);
    }
}

public sealed class UiSettings
{
    public string Theme { get; set; } = "standard";
    public string Language { get; set; } = "en";
    public bool RememberUsername { get; set; }
    public string Username { get; set; } = string.Empty;
    public string InstallDirectory { get; set; } = string.Empty;
    public DateTimeOffset UpdatedUtc { get; set; } = DateTimeOffset.UtcNow;

    public void Normalize()
    {
        Theme = string.IsNullOrWhiteSpace(Theme) ? "standard" : Theme.Trim().ToLowerInvariant();
        Language = string.IsNullOrWhiteSpace(Language) ? "en" : Language.Trim().ToLowerInvariant();
        Username = (Username ?? string.Empty).Trim();
        InstallDirectory = (InstallDirectory ?? string.Empty).Trim();
        UpdatedUtc = UpdatedUtc == default ? DateTimeOffset.UtcNow : UpdatedUtc;

        if (Theme is not ("standard" or "black" or "white"))
            Theme = "standard";

        if (Language is not ("en" or "ru" or "uk"))
            Language = "en";

        if (!RememberUsername)
            Username = string.Empty;
    }
}

public sealed class SecuritySettingsStore
{
    private static readonly JsonSerializerOptions Json = new() { WriteIndented = true };
    private readonly SecurityFolders _folders;
    private readonly SecurityAuditLogger _audit;

    public SecuritySettingsStore(SecurityFolders folders, SecurityAuditLogger audit)
    {
        _folders = folders;
        _audit = audit;
    }

    public UiSettings Load()
    {
        try
        {
            _folders.EnsureAll();
            if (!File.Exists(_folders.SettingsFile))
                return new UiSettings();

            var text = File.ReadAllText(_folders.SettingsFile, Encoding.UTF8);
            var value = JsonSerializer.Deserialize<UiSettings>(text, Json) ?? new UiSettings();
            value.Normalize();
            return value;
        }
        catch (Exception ex)
        {
            _audit.Warn("settings", ex.Message);
            return new UiSettings();
        }
    }

    public void Save(UiSettings settings)
    {
        try
        {
            _folders.EnsureAll();
            settings.Normalize();
            settings.UpdatedUtc = DateTimeOffset.UtcNow;
            var json = JsonSerializer.Serialize(settings, Json);
            File.WriteAllText(_folders.SettingsFile, json, Encoding.UTF8);
        }
        catch (Exception ex)
        {
            _audit.Error("settings", ex);
        }
    }

    public SecurityCheck SelfTest()
    {
        try
        {
            var settings = Load();
            Save(settings);
            return SecurityCheck.Pass("settings.store", "settings store", "read/write settings is available");
        }
        catch (Exception ex)
        {
            return SecurityCheck.Warn("settings.store", "settings store", ex.Message);
        }
    }
}

public sealed class ProtectedStore
{
    private readonly SecurityFolders _folders;
    private readonly SecurityAuditLogger _audit;

    public ProtectedStore(SecurityFolders folders, SecurityAuditLogger audit)
    {
        _folders = folders;
        _audit = audit;
    }

    public void SaveText(string value)
    {
        try
        {
            _folders.EnsureAll();
            var data = Encoding.UTF8.GetBytes(value ?? string.Empty);
            var blob = ProtectedData.Protect(data, null, DataProtectionScope.CurrentUser);
            File.WriteAllBytes(_folders.ProtectedBlobFile, blob);
        }
        catch (Exception ex)
        {
            _audit.Error("protected.save", ex);
        }
    }

    public string LoadText()
    {
        try
        {
            if (!File.Exists(_folders.ProtectedBlobFile))
                return string.Empty;

            var blob = File.ReadAllBytes(_folders.ProtectedBlobFile);
            var data = ProtectedData.Unprotect(blob, null, DataProtectionScope.CurrentUser);
            return Encoding.UTF8.GetString(data);
        }
        catch (Exception ex)
        {
            _audit.Warn("protected.load", ex.Message);
            return string.Empty;
        }
    }

    public void Clear()
    {
        try
        {
            if (File.Exists(_folders.ProtectedBlobFile))
                File.Delete(_folders.ProtectedBlobFile);
        }
        catch (Exception ex)
        {
            _audit.Warn("protected.clear", ex.Message);
        }
    }

    public SecurityCheck SelfTest()
    {
        try
        {
            const string probe = "loader-security-probe";
            SaveText(probe);
            var restored = LoadText();
            return restored == probe
                ? SecurityCheck.Pass("protected.store", "protected store", "dpapi self-test completed")
                : SecurityCheck.Warn("protected.store", "protected store", "dpapi returned unexpected data");
        }
        catch (Exception ex)
        {
            return SecurityCheck.Warn("protected.store", "protected store", ex.Message);
        }
    }
}

public sealed class LoginAttemptState
{
    public int FailedCount { get; set; }
    public DateTimeOffset LockedUntilUtc { get; set; }
    public DateTimeOffset LastFailureUtc { get; set; }

    public void Normalize()
    {
        if (FailedCount < 0) FailedCount = 0;
        if (LockedUntilUtc == default) LockedUntilUtc = DateTimeOffset.MinValue;
        if (LastFailureUtc == default) LastFailureUtc = DateTimeOffset.MinValue;
    }
}

public sealed class LoginAttemptGuard
{
    private static readonly JsonSerializerOptions Json = new() { WriteIndented = true };
    private readonly SecurityFolders _folders;
    private readonly SecurityAuditLogger _audit;

    public LoginAttemptGuard(SecurityFolders folders, SecurityAuditLogger audit)
    {
        _folders = folders;
        _audit = audit;
    }

    public LoginAttemptState Load()
    {
        try
        {
            _folders.EnsureAll();
            if (!File.Exists(_folders.AttemptsFile))
                return new LoginAttemptState();

            var text = File.ReadAllText(_folders.AttemptsFile, Encoding.UTF8);
            var state = JsonSerializer.Deserialize<LoginAttemptState>(text, Json) ?? new LoginAttemptState();
            state.Normalize();
            return state;
        }
        catch
        {
            return new LoginAttemptState();
        }
    }

    public void Save(LoginAttemptState state)
    {
        try
        {
            _folders.EnsureAll();
            state.Normalize();
            var text = JsonSerializer.Serialize(state, Json);
            File.WriteAllText(_folders.AttemptsFile, text, Encoding.UTF8);
        }
        catch (Exception ex)
        {
            _audit.Warn("attempts.save", ex.Message);
        }
    }

    public bool CanAttempt(out string message)
    {
        var state = Load();
        var now = DateTimeOffset.UtcNow;

        if (state.LockedUntilUtc > now)
        {
            var remaining = state.LockedUntilUtc - now;
            message = $"too many attempts, wait {Math.Max(1, (int)Math.Ceiling(remaining.TotalMinutes))} min";
            return false;
        }

        message = string.Empty;
        return true;
    }

    public void RegisterFailure()
    {
        var state = Load();
        state.FailedCount++;
        state.LastFailureUtc = DateTimeOffset.UtcNow;

        if (state.FailedCount >= 8)
            state.LockedUntilUtc = DateTimeOffset.UtcNow.AddMinutes(15);
        else if (state.FailedCount >= 5)
            state.LockedUntilUtc = DateTimeOffset.UtcNow.AddMinutes(5);

        Save(state);
    }

    public void Reset()
    {
        Save(new LoginAttemptState());
    }
}

public enum SecurityCheckLevel
{
    Info,
    Warning,
    Error,
}

public sealed class SecurityCheck
{
    public string Code { get; init; } = string.Empty;
    public string Title { get; init; } = string.Empty;
    public string Message { get; init; } = string.Empty;
    public bool Passed { get; init; }
    public bool Blocking { get; init; }
    public SecurityCheckLevel Level { get; init; }

    public static SecurityCheck Pass(string code, string title, string message)
        => new() { Code = code, Title = title, Message = message, Passed = true, Blocking = false, Level = SecurityCheckLevel.Info };

    public static SecurityCheck Warn(string code, string title, string message)
        => new() { Code = code, Title = title, Message = message, Passed = false, Blocking = false, Level = SecurityCheckLevel.Warning };

    public static SecurityCheck Block(string code, string title, string message)
        => new() { Code = code, Title = title, Message = message, Passed = false, Blocking = true, Level = SecurityCheckLevel.Error };
}

public sealed class SecurityStartupResult
{
    public bool AllowUi { get; init; }
    public string BadgeText { get; init; } = "review";
    public string UiMessage { get; init; } = "startup review";
    public IReadOnlyList<SecurityCheck> Checks { get; init; } = Array.Empty<SecurityCheck>();
    public int PassedChecks { get; init; }
    public int WarningCount { get; init; }
    public int ErrorCount { get; init; }
    public string BlockingReason { get; init; } = string.Empty;

    public static SecurityStartupResult FromChecks(IReadOnlyList<SecurityCheck> checks)
    {
        var passed = checks.Count(x => x.Passed);
        var warnings = checks.Count(x => x.Level == SecurityCheckLevel.Warning);
        var errors = checks.Count(x => x.Level == SecurityCheckLevel.Error);
        var block = checks.FirstOrDefault(x => x.Blocking);

        if (block is not null)
        {
            return new SecurityStartupResult
            {
                AllowUi = false,
                BadgeText = "blocked",
                UiMessage = block.Message,
                Checks = checks,
                PassedChecks = passed,
                WarningCount = warnings,
                ErrorCount = errors,
                BlockingReason = block.Message,
            };
        }

        if (warnings > 0)
        {
            return new SecurityStartupResult
            {
                AllowUi = true,
                BadgeText = "review",
                UiMessage = "startup checks completed with warnings",
                Checks = checks,
                PassedChecks = passed,
                WarningCount = warnings,
                ErrorCount = errors,
            };
        }

        return new SecurityStartupResult
        {
            AllowUi = true,
            BadgeText = "verified",
            UiMessage = "startup checks passed",
            Checks = checks,
            PassedChecks = passed,
            WarningCount = warnings,
            ErrorCount = errors,
        };
    }
}

public sealed class IntegrityGuard
{
    private readonly SecurityFolders _folders;
    private readonly SecurityAuditLogger _audit;

    public IntegrityGuard(SecurityFolders folders, SecurityAuditLogger audit)
    {
        _folders = folders;
        _audit = audit;
    }

    public SecurityCheck CheckExecutablePath()
    {
        try
        {
            var path = Environment.ProcessPath;
            if (string.IsNullOrWhiteSpace(path))
                return SecurityCheck.Warn("path.current", "current path", "process path is unavailable");

            var full = Path.GetFullPath(path);
            if (full.StartsWith("\\\\", StringComparison.Ordinal))
                return SecurityCheck.Block("path.current", "current path", "network paths are not allowed");

            if (PathTools.IsTemporaryLike(full))
                return SecurityCheck.Warn("path.current", "current path", "application is running from a temporary-like location");

            if (!full.EndsWith(".exe", StringComparison.OrdinalIgnoreCase))
                return SecurityCheck.Warn("path.current", "current path", "unexpected process extension");

            return SecurityCheck.Pass("path.current", "current path", full);
        }
        catch (Exception ex)
        {
            return SecurityCheck.Warn("path.current", "current path", ex.Message);
        }
    }

    public SecurityCheck CheckWorkingDirectories()
    {
        try
        {
            _folders.EnsureAll();
            var probe = Path.Combine(_folders.CacheDir, "probe.tmp");
            File.WriteAllText(probe, DateTime.UtcNow.ToString("O"), Encoding.UTF8);
            File.Delete(probe);
            return SecurityCheck.Pass("path.storage", "working directories", "local folders are writable");
        }
        catch (Exception ex)
        {
            return SecurityCheck.Block("path.storage", "working directories", ex.Message);
        }
    }

    public SecurityCheck CheckSettingsStore(SecuritySettingsStore store)
        => store.SelfTest();

    public SecurityCheck CheckProtectedStore(ProtectedStore store)
        => store.SelfTest();

    // ⚠️ CheckAuthenticodeSignature, CheckOptionalManifest, WriteManifest ПЕРЕНЕСЕНЫ НА СЕРВЕР (SpectreShield)
}

public sealed class InputPolicies
{
    private const int MinUsername = 3;
    private const int MaxUsername = 64;
    private const int MinPassword = 6;
    private const int MaxPassword = 128;

    public (bool ok, string message) ValidateUsername(string value)
    {
        value = (value ?? string.Empty).Trim();
        if (value.Length < MinUsername)
            return (false, $"username must be at least {MinUsername} chars");
        if (value.Length > MaxUsername)
            return (false, $"username must be up to {MaxUsername} chars");
        if (value.Any(ch => char.IsControl(ch)))
            return (false, "username contains invalid chars");
        return (true, string.Empty);
    }

    public (bool ok, string message) ValidatePassword(string value)
    {
        value ??= string.Empty;
        if (value.Length < MinPassword)
            return (false, $"password must be at least {MinPassword} chars");
        if (value.Length > MaxPassword)
            return (false, $"password must be up to {MaxPassword} chars");
        if (value.Any(ch => ch == '\r' || ch == '\n' || ch == '\0'))
            return (false, "password contains invalid chars");
        return (true, string.Empty);
    }
}

public sealed class SessionSnapshot
{
    public string Username { get; set; } = string.Empty;
    public string LastStatus { get; set; } = string.Empty;
    public string Machine { get; set; } = string.Empty;
    public string AppVersion { get; set; } = string.Empty;
    public DateTimeOffset LastLoginUtc { get; set; }
    public DateTimeOffset UpdatedUtc { get; set; }

    public void Normalize()
    {
        Username = (Username ?? string.Empty).Trim();
        LastStatus = (LastStatus ?? string.Empty).Trim();
        Machine = (Machine ?? string.Empty).Trim();
        AppVersion = (AppVersion ?? string.Empty).Trim();
        UpdatedUtc = UpdatedUtc == default ? DateTimeOffset.UtcNow : UpdatedUtc;
    }
}

public sealed class SessionJournal
{
    private static readonly JsonSerializerOptions Json = new() { WriteIndented = true };
    private readonly SecurityFolders _folders;
    private readonly SecurityAuditLogger _audit;

    public SessionJournal(SecurityFolders folders, SecurityAuditLogger audit)
    {
        _folders = folders;
        _audit = audit;
    }

    public SessionSnapshot Load()
    {
        try
        {
            _folders.EnsureAll();
            if (!File.Exists(_folders.SessionFile))
                return new SessionSnapshot();

            var text = File.ReadAllText(_folders.SessionFile, Encoding.UTF8);
            var snapshot = JsonSerializer.Deserialize<SessionSnapshot>(text, Json) ?? new SessionSnapshot();
            snapshot.Normalize();
            return snapshot;
        }
        catch (Exception ex)
        {
            _audit.Warn("session.load", ex.Message);
            return new SessionSnapshot();
        }
    }

    public void Save(SessionSnapshot snapshot)
    {
        try
        {
            _folders.EnsureAll();
            snapshot.Normalize();
            snapshot.UpdatedUtc = DateTimeOffset.UtcNow;
            var text = JsonSerializer.Serialize(snapshot, Json);
            File.WriteAllText(_folders.SessionFile, text, Encoding.UTF8);
        }
        catch (Exception ex)
        {
            _audit.Warn("session.save", ex.Message);
        }
    }

    public void MarkFailure(string username, string reason)
    {
        var snapshot = Load();
        snapshot.Username = (username ?? string.Empty).Trim();
        snapshot.LastStatus = reason;
        snapshot.UpdatedUtc = DateTimeOffset.UtcNow;
        Save(snapshot);
    }
}

public sealed class SecurityAuditLogger
{
    private readonly SecurityFolders _folders;

    public SecurityAuditLogger(SecurityFolders folders)
    {
        _folders = folders;
    }

    public void WriteStartup(SecurityStartupResult result)
    {
        var lines = new List<string>
        {
            $"startup state={result.BadgeText} allowUi={result.AllowUi} passed={result.PassedChecks}/{result.Checks.Count} warnings={result.WarningCount} errors={result.ErrorCount}",
        };

        foreach (var check in result.Checks)
        {
            lines.Add($"{check.Code} | {check.Level} | {(check.Passed ? "ok" : "fail")} | {check.Message}");
        }

        Write("startup", string.Join(" | ", lines));
    }

    public void Info(string area, string text) => Write(area, text);
    public void Warn(string area, string text) => Write(area, "warn: " + text);
    public void Error(string area, Exception ex) => Write(area, "error: " + ex);

    private void Write(string area, string text)
    {
        try
        {
            _folders.EnsureAll();
            var line = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {area} | {text}{Environment.NewLine}";
            File.AppendAllText(_folders.AuditFile, line, Encoding.UTF8);
        }
        catch
        {
            // ignore logger failures
        }
    }
}

public static class PathTools
{
    public static bool IsTemporaryLike(string path)
    {
        path = (path ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(path))
            return false;

        var full = path.ToLowerInvariant();
        var temp = System.IO.Path.GetTempPath().ToLowerInvariant();
        if (full.StartsWith(temp, StringComparison.Ordinal))
            return true;

        var user = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile).ToLowerInvariant();
        var downloads = Path.Combine(user, "downloads").ToLowerInvariant();
        if (full.StartsWith(downloads, StringComparison.Ordinal))
            return true;

        return full.Contains("\\temp\\", StringComparison.Ordinal)
            || full.Contains("\\tmp\\", StringComparison.Ordinal)
            || full.Contains("\\cache\\", StringComparison.Ordinal);
    }
}

public static class HashTools
{
    public static string Sha256File(string path)
    {
        using var stream = File.OpenRead(path);
        var hash = SHA256.HashData(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    public static string Sha256Text(string text)
    {
        var hash = SHA256.HashData(Encoding.UTF8.GetBytes(text ?? string.Empty));
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    public static string Sha256Bytes(byte[] data)
    {
        var hash = SHA256.HashData(data);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }
}