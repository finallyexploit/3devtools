using System;
using System.IO;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Threading;

namespace loader_goddev;

public partial class App : Application
{
    private Mutex? _mutex;

    protected override void OnStartup(StartupEventArgs e)
    {
        RegisterGlobalHandlers();
        base.OnStartup(e);

        try
        {
            _mutex = new Mutex(true, @"Local\loader_goddev_single_instance", out var created);
            if (!created)
            {
                MessageBox.Show(
                    "Приложение уже запущено.",
                    ApiService.Brand,
                    MessageBoxButton.OK,
                    MessageBoxImage.Information);
                Shutdown();
                return;
            }

            var main = new MainWindow();
            if (main.StartupBlocked)
            {
                MessageBox.Show(
                    "Ошибка стартовых проверок." + Environment.NewLine + main.StartupBlockReason,
                    ApiService.Brand,
                    MessageBoxButton.OK,
                    MessageBoxImage.Error);
                Shutdown(-1);
                return;
            }

            MainWindow = main;
            main.Show();
        }
        catch (Exception ex)
        {
            LogError("startup", ex);
            MessageBox.Show(
                @"Приложение не запустилось. Лог сохранён в %LOCALAPPDATA%\GOD DEV\logs\app.log",
                ApiService.Brand,
                MessageBoxButton.OK,
                MessageBoxImage.Error);
            Shutdown(-1);
        }
    }

    protected override void OnExit(ExitEventArgs e)
    {
        try   { _mutex?.ReleaseMutex(); }
        catch { /* ignore */ }
        _mutex?.Dispose();
        base.OnExit(e);
    }

    private void RegisterGlobalHandlers()
    {
        DispatcherUnhandledException               += OnDispatcherUnhandledException;
        AppDomain.CurrentDomain.UnhandledException += OnUnhandledException;
        TaskScheduler.UnobservedTaskException       += OnUnobservedTaskException;
    }

    private void OnDispatcherUnhandledException(object sender, DispatcherUnhandledExceptionEventArgs e)
    {
        LogError("dispatcher", e.Exception);
        MessageBox.Show(e.Exception.Message, ApiService.Brand, MessageBoxButton.OK, MessageBoxImage.Error);
        e.Handled = true;
        Shutdown(-1);
    }

    private void OnUnhandledException(object? sender, UnhandledExceptionEventArgs e)
    {
        LogError("domain", e.ExceptionObject as Exception ?? new Exception("Unknown unhandled exception"));
    }

    private void OnUnobservedTaskException(object? sender, UnobservedTaskExceptionEventArgs e)
    {
        LogError("task", e.Exception);
        e.SetObserved();
    }

    internal static void LogError(string area, Exception ex)
    {
        try
        {
            var dir  = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "GOD DEV", "logs");
            Directory.CreateDirectory(dir);
            var file = Path.Combine(dir, "app.log");
            var text = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {area}{Environment.NewLine}" +
                       $"{ex}{Environment.NewLine}{new string('-', 70)}{Environment.NewLine}";
            File.AppendAllText(file, text);
        }
        catch { /* never throw from logger */ }
    }
}
