using System;
using System.Diagnostics;
using System.Linq;
using System.Runtime.InteropServices;
using System.Threading;

namespace loader_goddev;

/// <summary>
/// Дополнительные проверки безопасности для защиты от отладки и анализа.
/// </summary>
public static class SecurityEnhancements
{
    private static readonly Timer _antiDebugTimer;
    private static bool _debuggerDetected;

    static SecurityEnhancements()
    {
        _antiDebugTimer = new Timer(CheckForDebugger, null, 1000, 2000);
    }

    public static bool IsDebuggerAttached => Debugger.IsAttached || _debuggerDetected;

    public static bool IsRunningInSandbox()
    {
        // Проверка на виртуализацию/песочницу
        var indicators = new[]
        {
            // Проверка на известные процессы песочниц
            Process.GetProcesses().Any(p =>
                p.ProcessName.Contains("vmsrvc", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("vbox", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("vmware", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("sandbox", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("wireshark", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("procmon", StringComparison.OrdinalIgnoreCase) ||
                p.ProcessName.Contains("processhacker", StringComparison.OrdinalIgnoreCase)),

            // Проверка на маленький объем RAM (типично для VM)
            GC.GetGCMemoryInfo().TotalAvailableMemoryBytes < 2L * 1024 * 1024 * 1024,

            // Проверка на 1 ядро CPU
            Environment.ProcessorCount < 2,

            // Проверка на отладчик в окружении
            !string.IsNullOrEmpty(Environment.GetEnvironmentVariable("COR_ENABLE_PROFILING")),
            !string.IsNullOrEmpty(Environment.GetEnvironmentVariable("CORECLR_ENABLE_PROFILING")),
        };

        return indicators.Any(x => x);
    }

    private static void CheckForDebugger(object? state)
    {
        try
        {
            _debuggerDetected = Debugger.IsAttached || IsDebuggerPresentNative();
        }
        catch
        {
            // Игнорируем ошибки
        }
    }

    [DllImport("kernel32.dll", SetLastError = true, ExactSpelling = true)]
    private static extern bool IsDebuggerPresent();

    private static bool IsDebuggerPresentNative()
    {
        try
        {
            return IsDebuggerPresent();
        }
        catch
        {
            return false;
        }
    }

    public static EnvironmentSecurityLevel GetEnvironmentSecurityLevel(LoaderSecurity security)
    {
        if (IsDebuggerAttached)
            return EnvironmentSecurityLevel.HighRisk;

        if (IsRunningInSandbox())
            return EnvironmentSecurityLevel.Suspicious;

        var startup = security.LastStartup ?? security.RunStartupChecks();

        return startup.BadgeText switch
        {
            "verified" => EnvironmentSecurityLevel.Trusted,
            "review" => EnvironmentSecurityLevel.NeedsReview,
            _ => EnvironmentSecurityLevel.Restricted
        };
    }

    public static void SelfDestruct()
    {
        // Безопасное завершение при обнаружении угрозы
        try
        {
            var security = LoaderSecurity.CreateDefault();
            security.Audit.Warn("security", "Self-destruct triggered - security threat detected");
        }
        catch { }

        Environment.Exit(-1);
    }
}

public enum EnvironmentSecurityLevel
{
    Trusted,      // Полный доступ
    NeedsReview,  // Ограниченный доступ
    Suspicious,   // Только публичные данные
    Restricted,   // Только демо-режим
    HighRisk      // Принудительное завершение
}