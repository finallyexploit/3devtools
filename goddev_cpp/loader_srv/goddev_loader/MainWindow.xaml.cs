using System;
using System.ComponentModel;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Animation;

namespace loader_goddev;

public partial class MainWindow : Window
{
    private enum Lang { En, Ru, Uk }
    private enum PageMode { Account, License }
    private enum ThemeMode { Standard, Black, White }
    private const double LoginWidth = 560;
    private const double LoginHeight = 620;
    private const double MainWidth = 1080;
    private const double MainHeight = 820;

    private readonly ApiService _api = new();
    private readonly LoaderSecurity _security = LoaderSecurity.CreateDefault();
    private readonly Dictionary<string, string[]> _t = new()
    {
        ["login_card_title"] = ["Secure access", "Безопасный вход", "Безпечний вхід"],
        ["login_card_hint"] = ["Enter your account details to continue.", "Введи данные аккаунта, чтобы продолжить.", "Введи дані акаунта, щоб продовжити."],
        ["username"] = ["Username", "Логин", "Логін"],
        ["password"] = ["Password", "Пароль", "Пароль"],
        ["remember_username"] = ["Remember username", "Запомнить логин", "Запам'ятати логін"],
        ["sign_in"] = ["Sign in", "Войти", "Увійти"],
        ["connecting"] = ["Connecting...", "Подключение...", "Підключення..."],
        ["security"] = ["security module", "модуль безопасности", "модуль безпеки"],
        ["ready"] = ["ready", "готово", "готово"],
        ["nav_account"] = ["Account", "Аккаунт", "Акаунт"],
        ["nav_license"] = ["License", "Лицензия", "Ліцензія"],
        ["page_account"] = ["Account details", "Данные аккаунта", "Дані акаунта"],
        ["page_account_sub"] = ["Current session and access details.", "Текущая сессия и детали доступа.", "Поточна сесія та деталі доступу."],
        ["page_license"] = ["License center", "Центр лицензии", "Центр ліцензії"],
        ["page_license_sub"] = ["Load a key to check the current record.", "Загрузи ключ, чтобы проверить текущую запись.", "Завантаж ключ, щоб перевірити поточний запис."],
        ["card_user"] = ["user", "пользователь", "користувач"],
        ["card_balance"] = ["balance", "баланс", "баланс"],
        ["card_license"] = ["license", "лицензия", "ліцензія"],
        ["license_key"] = ["License key", "Ключ лицензии", "Ключ ліцензії"],
        ["license_key_hint"] = ["Check the active key and read the current record.", "Проверь активный ключ и посмотри текущую запись.", "Перевір активний ключ і подивись поточний запис."],
        ["load_key"] = ["Load key", "Загрузить ключ", "Завантажити ключ"],
        ["security_title"] = ["Security module", "Модуль безопасности", "Модуль безпеки"],
        ["security_hint"] = ["Runtime, integrity and launch status.", "Статус запуска, целостности и сессии.", "Статус запуску, цілісності та сесії."],
        ["quick_actions"] = ["Quick actions", "Быстрые действия", "Швидкі дії"],
        ["quick_actions_sub"] = ["Basic controls for the current session.", "Базовые действия для текущей сессии.", "Базові дії для поточної сесії."],
        ["launch"] = ["Launch", "Запуск", "Запуск"],
        ["logout"] = ["Logout", "Выйти", "Вийти"],
        ["copy_info"] = ["Copy info", "Копировать инфо", "Копіювати інфо"],
        ["open_logs"] = ["Open logs", "Открыть логи", "Відкрити логи"],
        ["install_folder"] = ["Install folder", "Папка установки", "Папка встановлення"],
        ["guest"] = ["guest", "гость", "гість"],
        ["offline"] = ["offline", "не в сети", "не в мережі"],
        ["online"] = ["online", "в сети", "в мережі"],
        ["status"] = ["STATUS", "СТАТУС", "СТАТУС"],
        ["login_first"] = ["sign in first", "сначала войди", "спочатку увійди"],
        ["license_required"] = ["active license required", "нужна активная лицензия", "потрібна активна ліцензія"],
        ["license_missing"] = ["no active license found", "активная лицензия не найдена", "активну ліцензію не знайдено"],
        ["license_key_required"] = ["license key required", "нужен ключ лицензии", "потрібен ключ ліцензії"],
        ["loading_license"] = ["loading license info...", "загрузка лицензии...", "завантаження ліцензії..."],
        ["license_loaded"] = ["license info loaded", "данные лицензии загружены", "дані ліцензії завантажено"],
        ["logged_out"] = ["logged out", "выход выполнен", "вихід виконано"],
        ["integrity_fail"] = ["startup checks failed", "ошибка стартовых проверок", "помилка стартових перевірок"],
        ["no_session"] = ["no session loaded", "сессия не загружена", "сесія не завантажена"],
        ["security_verified"] = ["verified", "проверено", "перевірено"],
        ["security_review"] = ["review", "проверить", "перевірити"],
        ["security_blocked"] = ["blocked", "заблокировано", "заблоковано"],
        ["info_copied"] = ["info copied", "инфо скопировано", "інфо скопійовано"],
        ["logs_opened"] = ["logs folder opened", "папка логов открыта", "папку логів відкрито"],
        ["folder_opened"] = ["install folder opened", "папка установки открыта", "папку встановлення відкрито"],
        ["settings_saved"] = ["settings saved", "настройки сохранены", "налаштування збережено"],
        // Новые ключи для защищенных операций
        ["secure_operation"] = ["Secure operation", "Защищенная операция", "Захищена операція"],
        ["secure_executing"] = ["Executing secure operation...", "Выполнение защищенной операции...", "Виконання захищеної операції..."],
        ["secure_completed"] = ["Operation completed", "Операция завершена", "Операцію завершено"],
        ["secure_failed"] = ["Operation failed", "Ошибка операции", "Помилка операції"],
        ["demo_mode"] = ["Demo mode", "Демо-режим", "Демо-режим"],
        ["security_threat"] = ["Security threat detected", "Обнаружена угроза безопасности", "Виявлено загрозу безпеці"],
    };

    private Lang _lang = Lang.En;
    private PageMode _page = PageMode.Account;
    private ThemeMode _theme = ThemeMode.Standard;
    private UiSettings _settings = new();
    private SecurityStartupResult _startup = SecurityStartupResult.FromChecks(Array.Empty<SecurityCheck>());
    private ApiResponse? _user;
    private bool _loginBusy;
    private bool _uiReady;

    // Флаг для отслеживания режима безопасности
    private EnvironmentSecurityLevel _currentSecurityLevel = EnvironmentSecurityLevel.NeedsReview;
    private bool _isDemoMode;

    public bool StartupBlocked { get; private set; }
    public string StartupBlockReason => _startup.BlockingReason;

    public MainWindow()
    {
        InitializeComponent();

        _settings = _security.LoadSettings();
        _lang = ParseLang(_settings.Language);
        _theme = ParseTheme(_settings.Theme);

        ApplyTheme();
        ApplyLanguage();
        ApplyStoredUiState();
        ShowLoginInstant();

        _startup = _security.RunStartupChecks();
        UpdateStartupUi();

        if (!_startup.AllowUi)
        {
            StartupBlocked = true;
            return;
        }

        // Проверяем уровень безопасности окружения
        CheckEnvironmentSecurity();

        _uiReady = true;
    }

    /// <summary>
    /// Проверяет уровень безопасности окружения и устанавливает режим работы.
    /// </summary>
    private void CheckEnvironmentSecurity()
    {
        _currentSecurityLevel = SecurityEnhancements.GetEnvironmentSecurityLevel(_security);

        _isDemoMode = _currentSecurityLevel >= EnvironmentSecurityLevel.Suspicious;

        if (_currentSecurityLevel == EnvironmentSecurityLevel.HighRisk)
        {
            _security.Audit.Warn("security", "High risk environment detected");
            SetStatus(T("security_threat"), ResBrush("Danger"));
        }
        else if (_isDemoMode)
        {
            _security.Audit.Warn("security", $"Running in demo mode due to security level: {_currentSecurityLevel}");
            SetStatus(T("demo_mode") + " - " + _currentSecurityLevel, ResBrush("TextSoft"));
        }
    }

    private string T(string key) => _t.TryGetValue(key, out var value) ? value[(int)_lang] : key;
    private TEl? Ui<TEl>(string name) where TEl : class => FindName(name) as TEl;
    private Brush ResBrush(string key) => (Brush)FindResource(key);
    private string ThemeKey() => _theme switch { ThemeMode.Black => "black", ThemeMode.White => "white", _ => "standard" };
    private string LangKey() => _lang switch { Lang.Ru => "ru", Lang.Uk => "uk", _ => "en" };

    private static Lang ParseLang(string? value) => (value ?? string.Empty).Trim().ToLowerInvariant() switch
    {
        "ru" => Lang.Ru,
        "uk" => Lang.Uk,
        _ => Lang.En,
    };

    private static ThemeMode ParseTheme(string? value) => (value ?? string.Empty).Trim().ToLowerInvariant() switch
    {
        "black" => ThemeMode.Black,
        "white" => ThemeMode.White,
        _ => ThemeMode.Standard,
    };

    private void SetText(string name, string text)
    {
        if (Ui<TextBlock>(name) is { } tb) tb.Text = text;
        else if (Ui<TextBox>(name) is { } box) box.Text = text;
    }

    private void SetContent(string name, object text)
    {
        if (Ui<ContentControl>(name) is { } control)
            control.Content = text;
    }

    private void SetVisible(string name, Visibility value)
    {
        if (Ui<UIElement>(name) is { } el)
            el.Visibility = value;
    }

    private void SetEnabled(string name, bool value)
    {
        if (Ui<Control>(name) is { } control)
            control.IsEnabled = value;
    }

    private void SetChecked(string name, bool value)
    {
        if (Ui<CheckBox>(name) is { } check)
            check.IsChecked = value;
    }

    private bool GetChecked(string name) => Ui<CheckBox>(name)?.IsChecked == true;
    private string BoxText(string name) => Ui<TextBox>(name)?.Text?.Trim() ?? string.Empty;
    private string PassText(string name) => Ui<PasswordBox>(name)?.Password ?? string.Empty;

    private void SetBrush(string name, Brush brush)
    {
        if (Ui<TextBlock>(name) is { } tb) tb.Foreground = brush;
        else if (Ui<Control>(name) is { } c) c.Foreground = brush;
    }

    private void ApplyStoredUiState()
    {
        SetChecked("RememberUsernameCheck", _settings.RememberUsername);
        SetText("UsernameBox", _settings.RememberUsername ? _settings.Username : string.Empty);
    }

    private void SaveUiState(bool withStatus = false)
    {
        _security.SaveUiState(ThemeKey(), LangKey(), GetChecked("RememberUsernameCheck"), BoxText("UsernameBox"));
        _settings = _security.LoadSettings();
        if (withStatus)
            SetStatus(T("settings_saved"), ResBrush("TextSoft"));
        UpdateSecurityPanel();
    }

    private async void Window_Loaded(object sender, RoutedEventArgs e)
    {
        Ui<UIElement>("ShellBorder")?.BeginAnimation(
            OpacityProperty,
            new DoubleAnimation(0, 1, TimeSpan.FromMilliseconds(220)));

        await Task.Delay(40);
        Ui<TextBox>("UsernameBox")?.Focus();
        SetStatus(_startup.UiMessage, _startup.AllowUi ? ResBrush("Accent") : ResBrush("Danger"));

        // Периодическая проверка безопасности
        StartSecurityMonitoring();
    }

    /// <summary>
    /// Запускает фоновый мониторинг безопасности.
    /// </summary>
    private void StartSecurityMonitoring()
    {
        Task.Run(async () =>
        {
            while (true)
            {
                await Task.Delay(5000); // Проверка каждые 5 секунд

                await Dispatcher.InvokeAsync(() =>
                {
                    var newLevel = SecurityEnhancements.GetEnvironmentSecurityLevel(_security);

                    if (newLevel != _currentSecurityLevel)
                    {
                        _currentSecurityLevel = newLevel;
                        _isDemoMode = newLevel >= EnvironmentSecurityLevel.Suspicious;

                        _security.Audit.Warn("security", $"Security level changed to: {newLevel}");

                        if (newLevel == EnvironmentSecurityLevel.HighRisk)
                        {
                            SetStatus(T("security_threat"), ResBrush("Danger"));
                        }

                        UpdateSecurityPanel();
                    }
                });
            }
        });
    }

    private void Window_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
    {
        if (e.ButtonState == MouseButtonState.Pressed)
            DragMove();
    }

    private void Window_Closing(object? sender, CancelEventArgs e)
    {
        if (!_uiReady) return;
        SaveUiState();
    }

    private void UsernameBox_LostKeyboardFocus(object sender, KeyboardFocusChangedEventArgs e)
    {
        if (!_uiReady || !GetChecked("RememberUsernameCheck")) return;
        SaveUiState();
    }

    private void Minimize_Click(object sender, RoutedEventArgs e) => WindowState = WindowState.Minimized;
    private void Close_Click(object sender, RoutedEventArgs e) => Close();

    private void AuthKeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key != Key.Enter || _loginBusy) return;
        e.Handled = true;
        Login_Click(sender, new RoutedEventArgs(Button.ClickEvent));
    }

    private void LangButton_Click(object sender, RoutedEventArgs e)
    {
        if (sender is not Button btn || btn.Tag is not string tag) return;
        _lang = ParseLang(tag);
        ApplyLanguage();
        RenderCurrentPage();
        SaveUiState();
    }

    private void ThemeButton_Click(object sender, RoutedEventArgs e)
    {
        if (sender is not Button btn || btn.Tag is not string tag) return;
        _theme = ParseTheme(tag);
        ApplyTheme();
        UpdateButtonStates();
        SaveUiState();
        SetStatus(T("ready"), ResBrush("Accent"));
    }

    private void RememberUsernameChanged(object sender, RoutedEventArgs e) => SaveUiState();

    private void NavAccount_Click(object sender, RoutedEventArgs e)
    {
        if (_user is null) { SetStatus(T("login_first"), ResBrush("Danger")); return; }
        _page = PageMode.Account;
        RenderCurrentPage();
    }

    private void NavLicense_Click(object sender, RoutedEventArgs e)
    {
        if (_user is null) { SetStatus(T("login_first"), ResBrush("Danger")); return; }
        _page = PageMode.License;
        RenderCurrentPage();
    }

    private void ApplyLanguage()
    {
        SetText("LoginCardTitle", T("login_card_title"));
        SetText("LoginCardHint", T("login_card_hint"));
        SetText("UsernameLabel", T("username"));
        SetText("PasswordLabel", T("password"));
        SetContent("RememberUsernameCheck", T("remember_username"));
        SetContent("LoginButton", _loginBusy ? T("connecting") : T("sign_in"));
        SetText("LoginMetric1Title", T("security"));

        SetContent("NavAccountButton", T("nav_account"));
        SetContent("NavLicenseButton", T("nav_license"));
        SetText("CardUserTitle", T("card_user"));
        SetText("CardBalanceTitle", T("card_balance"));
        SetText("CardLicenseTitle", T("card_license"));
        SetText("LicenseKeyLabel", T("license_key"));
        SetText("LicenseKeyHint", T("license_key_hint"));
        SetContent("FetchLicenseButton", T("load_key"));

        SetText("SecurityTitle", T("security_title"));
        SetText("SecurityHint", T("security_hint"));
        SetText("ActionsTitle", T("quick_actions"));
        SetText("ActionsHint", T("quick_actions_sub"));
        SetContent("LaunchButton", T("launch"));
        SetContent("LogoutButton", T("logout"));
        SetContent("CopyInfoButton", T("copy_info"));
        SetContent("OpenLogsButton", T("open_logs"));
        SetContent("OpenInstallFolderButton", T("install_folder"));
        SetText("StatusPrefixText", T("status"));

        SetText("HeaderUserText", _user?.username ?? T("guest"));
        SetText("HeaderHintText", _user is null ? T("offline") : T("online"));
        SetText("UserBadgeText", _user is null ? "GD" : MakeBadge(_user.username));

        UpdateStartupUi();
        UpdateButtonStates();
    }

    private void UpdateStartupUi()
    {
        var stateText = _startup.BadgeText switch
        {
            "verified" => T("security_verified"),
            "blocked" => T("security_blocked"),
            _ => T("security_review"),
        };

        SetText("LoginMetric1Value", stateText);
        SetBrush("LoginMetric1Value", _startup.BadgeText == "blocked"
            ? ResBrush("Danger")
            : _startup.BadgeText == "verified"
                ? ResBrush("Accent")
                : ResBrush("TextSoft"));

        UpdateSecurityPanel();
    }

    private void UpdateButtonStates()
    {
        ApplyChipState(Ui<Button>("ThemeStandardButton"), _theme == ThemeMode.Standard, true);
        ApplyChipState(Ui<Button>("ThemeBlackButton"), _theme == ThemeMode.Black, true);
        ApplyChipState(Ui<Button>("ThemeWhiteButton"), _theme == ThemeMode.White, true);
        ApplyChipState(Ui<Button>("LangEnButton"), _lang == Lang.En, true);
        ApplyChipState(Ui<Button>("LangRuButton"), _lang == Lang.Ru, true);
        ApplyChipState(Ui<Button>("LangUkButton"), _lang == Lang.Uk, true);
        ApplyChipState(Ui<Button>("NavAccountButton"), _page == PageMode.Account, false);
        ApplyChipState(Ui<Button>("NavLicenseButton"), _page == PageMode.License, false);
    }

    private void ApplyChipState(Button? button, bool active, bool accent)
    {
        if (button is null) return;
        button.Foreground = active ? ResBrush(accent ? "Accent" : "Text") : ResBrush("TextSoft");
        button.Background = active ? ResBrush(accent ? "AccentSoft" : "Surface") : Brushes.Transparent;
        button.BorderBrush = active ? ResBrush(accent ? "AccentStroke" : "Stroke") : Brushes.Transparent;
    }

    private void ApplyTheme()
    {
        switch (_theme)
        {
            case ThemeMode.Black:
                SetThemePalette(
                    ("Bg", "#000000"), ("Shell", "#030303"), ("Panel", "#060606"), ("PanelAlt", "#090909"),
                    ("Surface", "#0B0B0B"), ("SurfaceAlt", "#0F0F0F"), ("Stroke", "#1B1B1B"), ("StrokeSoft", "#121212"),
                    ("Accent", "#79FF79"), ("AccentSoft", "#0B1A0B"), ("AccentStroke", "#1F3C1F"), ("Text", "#F5F5F5"),
                    ("TextSoft", "#A3A3A3"), ("TextMuted", "#727272"), ("FocusStroke", "#2B4730"), ("TopButtonHover", "#101010"),
                    ("ButtonHoverStroke", "#2A2A2A"), ("ButtonGloss", "#14FFFFFF"), ("TabActiveBg", "#0D0D0D"),
                    ("ShellEdge", "#151515"), ("BrandTileBg", "#080808"), ("HeaderTileBg", "#0A0A0A"),
                    ("LoginMarkBg", "#0B0B0B"), ("StatusBg", "#070707"), ("Danger", "#FF6F85"), ("Success", "#5CF5A8"));
                SetGradient("ChromeLine", "#79FF79", "#2A2A2A", "#171717", "#FFFFFF");
                SetGradient("MainButtonFill", "#111111", "#0B0B0B");
                SetGradient("PrimaryButtonFill", "#0D1A0D", "#102410");
                break;

            case ThemeMode.White:
                SetThemePalette(
                    ("Bg", "#F2F4F7"), ("Shell", "#FFFFFF"), ("Panel", "#F8FAFC"), ("PanelAlt", "#F3F5F8"),
                    ("Surface", "#FFFFFF"), ("SurfaceAlt", "#FBFCFD"), ("Stroke", "#D7DDE5"), ("StrokeSoft", "#E7EBF0"),
                    ("Accent", "#1FAE43"), ("AccentSoft", "#E9F8EE"), ("AccentStroke", "#B8E0C2"), ("Text", "#0E141B"),
                    ("TextSoft", "#5C6773"), ("TextMuted", "#8A96A3"), ("FocusStroke", "#8FB6A0"), ("TopButtonHover", "#F0F4F8"),
                    ("ButtonHoverStroke", "#C5CFD9"), ("ButtonGloss", "#09000000"), ("TabActiveBg", "#EFF4F8"),
                    ("ShellEdge", "#D7DEE7"), ("BrandTileBg", "#F2F5F8"), ("HeaderTileBg", "#F5F7FA"),
                    ("LoginMarkBg", "#F5F7FA"), ("StatusBg", "#F8FAFC"), ("Danger", "#E05A73"), ("Success", "#17984A"));
                SetGradient("ChromeLine", "#1FAE43", "#7FD193", "#DCE7DE", "#FFFFFF");
                SetGradient("MainButtonFill", "#FFFFFF", "#F3F6F8");
                SetGradient("PrimaryButtonFill", "#E9F8EE", "#DDF4E4");
                break;

            default:
                SetThemePalette(
                    ("Bg", "#05070A"), ("Shell", "#080B11"), ("Panel", "#0C1118"), ("PanelAlt", "#0F141C"),
                    ("Surface", "#101722"), ("SurfaceAlt", "#0C121A"), ("Stroke", "#1C2734"), ("StrokeSoft", "#141D28"),
                    ("Accent", "#7DFF76"), ("AccentSoft", "#112318"), ("AccentStroke", "#254531"), ("Text", "#F2F7FB"),
                    ("TextSoft", "#95A6B9"), ("TextMuted", "#65768A"), ("FocusStroke", "#325167"), ("TopButtonHover", "#121A25"),
                    ("ButtonHoverStroke", "#32475B"), ("ButtonGloss", "#28FFFFFF"), ("TabActiveBg", "#111925"),
                    ("ShellEdge", "#121A24"), ("BrandTileBg", "#101823"), ("HeaderTileBg", "#0E151F"),
                    ("LoginMarkBg", "#0F1722"), ("StatusBg", "#0A1119"), ("Danger", "#FF6F85"), ("Success", "#5CF5A8"));
                SetGradient("ChromeLine", "#49B7FF", "#8A5CFF", "#FF5A7A", "#7DFF76");
                SetGradient("MainButtonFill", "#171F2A", "#101720");
                SetGradient("PrimaryButtonFill", "#112317", "#153020");
                break;
        }
    }

    private void SetThemePalette(params (string Key, string Hex)[] items)
    {
        foreach (var (key, hex) in items)
            SetColor(key, hex);
    }

    private void SetColor(string key, string hex)
    {
        var color = (Color)ColorConverter.ConvertFromString(hex)!;
        Resources[key] = new SolidColorBrush(color);
    }

    private void SetGradient(string key, params string[] colors)
    {
        var brush = new LinearGradientBrush
        {
            StartPoint = new Point(0, 0),
            EndPoint = key == "MainButtonFill" ? new Point(0, 1) : new Point(1, 0)
        };

        var step = colors.Length <= 1 ? 1d : 1d / (colors.Length - 1);
        for (var i = 0; i < colors.Length; i++)
            brush.GradientStops.Add(new GradientStop((Color)ColorConverter.ConvertFromString(colors[i])!, i * step));

        Resources[key] = brush;
    }

    private void RenderCurrentPage()
    {
        UpdateButtonStates();
        RefreshSessionUi();
        UpdateSecurityPanel();
        if (_user is null) return;
        if (_page == PageMode.License) RenderLicensePage(); else RenderAccountPage();
    }

    private void RenderAccountPage()
    {
        if (_user is null) return;

        SetText("PageTitleText", T("page_account"));
        SetText("PageSubtitleText", T("page_account_sub"));
        SetText("InfoTitle", T("page_account"));
        SetText("InfoHint", T("page_account_sub"));
        SetVisible("LicensePanel", Visibility.Visible);

        SetInfoText(string.Join(Environment.NewLine, new[]
        {
            $"User ID    : {_user.UserId}",
            $"Username   : {_user.username}",
            $"Balance    : ${_user.BalanceValue:F2}",
            $"Admin      : {(_user.is_admin ? $"yes, level {_user.admin_level}" : "no")}",
            $"Can ban    : {(_user.can_ban ? "yes" : "no")}",
            $"License    : {(_user.has_license ? (_user.license_type ?? "active") : "none")}",
            $"Expires    : {(_user.has_license ? _user.expires_at ?? "-" : "-")}",
            $"Demo mode  : {(_isDemoMode ? "yes" : "no")}",
        }));
    }

    private void RenderLicensePage()
    {
        if (_user is null) return;

        SetText("PageTitleText", T("page_license"));
        SetText("PageSubtitleText", T("page_license_sub"));
        SetText("InfoTitle", T("page_license"));
        SetText("InfoHint", T("page_license_sub"));
        SetVisible("LicensePanel", Visibility.Visible);

        if (!_user.has_license)
        {
            SetInfoText(T("license_missing"));
            SetStatus(T("license_missing"), ResBrush("Danger"));
            return;
        }

        SetInfoText(string.Join(Environment.NewLine, new[]
        {
            $"Type       : {_user.license_type}",
            $"Expires    : {_user.expires_at}",
            string.Empty,
            T("page_license_sub"),
        }));
    }

    private void RefreshSessionUi()
    {
        SetText("CardUserValue", _user?.username ?? "-");
        SetText("CardBalanceValue", _user is null ? "$0.00" : "$" + _user.BalanceValue.ToString("F2"));
        SetText("CardLicenseValue", _user?.has_license == true ? (_user.license_type ?? "active") : "none");
        SetText("HeaderUserText", _user?.username ?? T("guest"));
        SetText("HeaderHintText", _user is null ? T("offline") : T("online"));
        SetText("UserBadgeText", _user is null ? "GD" : MakeBadge(_user.username));
    }

    private async void Login_Click(object sender, RoutedEventArgs e)
    {
        var username = BoxText("UsernameBox");
        var password = PassText("PasswordBox");

        var userCheck = _security.Input.ValidateUsername(username);
        if (!userCheck.ok)
        {
            SetStatus(userCheck.message, ResBrush("Danger"));
            return;
        }

        var passCheck = _security.Input.ValidatePassword(password);
        if (!passCheck.ok)
        {
            SetStatus(passCheck.message, ResBrush("Danger"));
            return;
        }

        if (!_security.LoginAttempts.CanAttempt(out var attemptMessage))
        {
            SetStatus(attemptMessage, ResBrush("Danger"));
            return;
        }

        ToggleLogin(false);
        SetStatus(T("connecting"), ResBrush("TextSoft"));

        var res = await _api.LoginAsync(username, password);

        ToggleLogin(true);

        if (!string.Equals(res?.status, "success", StringComparison.OrdinalIgnoreCase))
        {
            var message = res?.message ?? "request failed";
            _security.SaveLoginFailure(username, message);
            UpdateSecurityPanel();
            SetStatus(message, ResBrush("Danger"));
            return;
        }

        _user = res;
        _page = PageMode.Account;
        if (Ui<PasswordBox>("PasswordBox") is { } passwordBox)
            passwordBox.Password = string.Empty;

        _security.SaveLoginSuccess(username, ThemeKey(), LangKey(), GetChecked("RememberUsernameCheck"));
        _settings = _security.LoadSettings();

        RefreshSessionUi();
        ApplyLanguage();
        RenderCurrentPage();
        await ShowAppAsync();
        SetStatus(res.message ?? T("ready"), ResBrush("Success"));

        // После успешного входа проверяем возможность выполнения защищенных операций
        CheckEnvironmentSecurity();
    }

    private async void FetchLicense_Click(object sender, RoutedEventArgs e)
    {
        if (_user is null) { SetStatus(T("login_first"), ResBrush("Danger")); return; }
        if (!_user.has_license) { SetStatus(T("license_missing"), ResBrush("Danger")); return; }

        var validation = _security.LicenseKeys.Validate(BoxText("LicenseKeyBox"));
        if (!validation.ok)
        {
            SetStatus(validation.message, ResBrush("Danger"));
            return;
        }

        SetText("LicenseKeyBox", validation.normalized);
        SetEnabled("FetchLicenseButton", false);
        SetStatus(T("loading_license"), ResBrush("TextSoft"));

        var info = await _api.GetLicenseInfoAsync(validation.normalized);

        SetEnabled("FetchLicenseButton", true);

        if (!string.Equals(info?.status, "success", StringComparison.OrdinalIgnoreCase) || info.license is null)
        {
            SetStatus(info?.message ?? "license not found", ResBrush("Danger"));
            return;
        }

        SetInfoText(string.Join(Environment.NewLine, new[]
        {
            $"Product    : {info.license.product_name}",
            $"Type       : {info.license.product_type}",
            $"Key        : {info.license.license_key}",
            $"Status     : {info.license.status}",
            $"Expires    : {info.license.expires_at}",
            $"Activated  : {info.license.activated_at}",
        }));

        SetStatus(T("license_loaded"), ResBrush("Success"));
    }

    private void Launch_Click(object sender, RoutedEventArgs e)
    {
        if (_user is null) { SetStatus(T("no_session"), ResBrush("Danger")); return; }
        if (!_user.has_license) { SetStatus(T("license_required"), ResBrush("Danger")); return; }

        var (ok, message, path) = ApiService.LaunchSoftware(_security);
        if (ok && !string.IsNullOrWhiteSpace(path))
            _security.Launches.SaveInstallFolder(Path.GetDirectoryName(path) ?? string.Empty);

        UpdateSecurityPanel();
        SetStatus(message, ResBrush(ok ? "Success" : "Danger"));
    }

    private void CopyInfo_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            var text = string.Join(Environment.NewLine + Environment.NewLine, new[]
            {
                BoxText("InfoBox"),
                BoxText("SecuritySummaryBox")
            });
            Clipboard.SetText(text);
            SetStatus(T("info_copied"), ResBrush("Success"));
        }
        catch (Exception ex)
        {
            SetStatus(ex.Message, ResBrush("Danger"));
        }
    }

    private void OpenLogs_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            Directory.CreateDirectory(_security.Folders.LogsDir);
            Process.Start(new ProcessStartInfo(_security.Folders.LogsDir) { UseShellExecute = true });
            SetStatus(T("logs_opened"), ResBrush("Success"));
        }
        catch (Exception ex)
        {
            SetStatus(ex.Message, ResBrush("Danger"));
        }
    }

    private void OpenInstallFolder_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            var folder = _security.Launches.GetInstallFolderToOpen();
            if (!Directory.Exists(folder))
            {
                SetStatus("install folder not found", ResBrush("Danger"));
                return;
            }

            Process.Start(new ProcessStartInfo(folder) { UseShellExecute = true });
            SetStatus(T("folder_opened"), ResBrush("Success"));
        }
        catch (Exception ex)
        {
            SetStatus(ex.Message, ResBrush("Danger"));
        }
    }

    private async void Logout_Click(object sender, RoutedEventArgs e) => await SignOutAsync();

    private void ToggleLogin(bool enabled)
    {
        _loginBusy = !enabled;
        SetEnabled("UsernameBox", enabled);
        SetEnabled("PasswordBox", enabled);
        SetEnabled("LoginButton", enabled);
        SetContent("LoginButton", enabled ? T("sign_in") : T("connecting"));
    }

    private void SetStatus(string text, Brush brush)
    {
        SetText("StatusText", string.IsNullOrWhiteSpace(text) ? "-" : text.Trim());
        SetBrush("StatusText", brush);
    }

    private void SetInfoText(string text)
    {
        if (Ui<TextBox>("InfoBox") is { } box)
        {
            box.Text = text;
            box.ScrollToHome();
        }
    }

    private void UpdateSecurityPanel()
    {
        var text = _security.BuildSecuritySummary(_user);

        // Добавляем информацию о режиме безопасности
        text += Environment.NewLine + Environment.NewLine + "=== Security Status ===";
        text += Environment.NewLine + $"Environment  : {_currentSecurityLevel}";
        text += Environment.NewLine + $"Demo mode    : {(_isDemoMode ? "active" : "inactive")}";
        text += Environment.NewLine + $"Debugger     : {(SecurityEnhancements.IsDebuggerPresent ? "detected" : "none")}";   

        if (Ui<TextBox>("SecuritySummaryBox") is { } box)
        {
            box.Text = text;
            box.ScrollToHome();
        }

        var label = _startup.BadgeText switch
        {
            "verified" => T("security_verified"),
            "blocked" => T("security_blocked"),
            _ => T("security_review"),
        };

        // Добавляем индикатор демо-режима
        if (_isDemoMode)
        {
            label += $" [{T("demo_mode")}]";
        }

        SetText("SecurityStateText", label);
        SetBrush("SecurityStateText", _isDemoMode
            ? ResBrush("TextSoft")
            : _startup.BadgeText switch
            {
                "verified" => ResBrush("Accent"),
                "blocked" => ResBrush("Danger"),
                _ => ResBrush("TextSoft"),
            });
    }

    private void ShowLoginInstant()
    {
        SetVisible("HeaderSessionPanel", Visibility.Collapsed);
        SetVisible("ThemeSwitcherPanel", Visibility.Collapsed);
        SetWindowSize(LoginWidth, LoginHeight);
        SetVisible("LoginView", Visibility.Visible);
        if (Ui<UIElement>("LoginView") is { } login) login.Opacity = 1;
        SetVisible("AppView", Visibility.Collapsed);
        if (Ui<UIElement>("AppView") is { } app) app.Opacity = 0;
    }

    private async Task ShowAppAsync()
    {
        if (Ui<UIElement>("LoginView") is { } login)
        {
            await FadeOutAsync(login);
            login.Visibility = Visibility.Collapsed;
        }

        SetVisible("HeaderSessionPanel", Visibility.Visible);
        SetVisible("ThemeSwitcherPanel", Visibility.Visible);
        SetWindowSize(MainWidth, MainHeight);
        SetVisible("AppView", Visibility.Visible);

        if (Ui<UIElement>("AppView") is { } app)
        {
            app.Opacity = 0;
            await FadeInAsync(app);
        }
    }

    private async Task ShowLoginAsync()
    {
        if (Ui<UIElement>("AppView") is { } app)
        {
            await FadeOutAsync(app);
            app.Visibility = Visibility.Collapsed;
        }

        SetVisible("HeaderSessionPanel", Visibility.Collapsed);
        SetVisible("ThemeSwitcherPanel", Visibility.Collapsed);
        SetWindowSize(LoginWidth, LoginHeight);
        SetVisible("LoginView", Visibility.Visible);

        if (Ui<UIElement>("LoginView") is { } login)
        {
            login.Opacity = 0;
            await FadeInAsync(login);
        }

        Ui<TextBox>("UsernameBox")?.Focus();
    }

    private void SetWindowSize(double width, double height)
    {
        MinWidth = width;
        MinHeight = height;
        Width = width;
        Height = height;
        CenterWindow();
    }

    private void CenterWindow()
    {
        var area = SystemParameters.WorkArea;
        Left = area.Left + (area.Width - Width) / 2;
        Top = area.Top + (area.Height - Height) / 2;
    }

    private async Task SignOutAsync()
    {
        try { await _api.LogoutAsync(); } catch { }

        _user = null;
        _page = PageMode.Account;
        var remember = GetChecked("RememberUsernameCheck");
        var rememberedName = remember ? BoxText("UsernameBox") : string.Empty;

        SetText("UsernameBox", rememberedName);
        if (Ui<PasswordBox>("PasswordBox") is { } p) p.Password = string.Empty;
        SetText("LicenseKeyBox", string.Empty);
        SetText("CardUserValue", "-");
        SetText("CardBalanceValue", "$0.00");
        SetText("CardLicenseValue", "none");
        SetText("HeaderUserText", T("guest"));
        SetText("HeaderHintText", T("offline"));
        SetText("UserBadgeText", "GD");
        SetInfoText(string.Empty);

        SaveUiState();
        ApplyLanguage();
        UpdateSecurityPanel();
        await ShowLoginAsync();
        SetStatus(T("logged_out"), ResBrush("TextSoft"));

        // Сбрасываем режим демо после выхода
        CheckEnvironmentSecurity();
    }

    private static Task FadeInAsync(UIElement target) => FadeAsync(target, 0, 1);
    private static Task FadeOutAsync(UIElement target) => FadeAsync(target, 1, 0);

    private static Task FadeAsync(UIElement target, double from, double to)
    {
        var tcs = new TaskCompletionSource<bool>(TaskCreationOptions.RunContinuationsAsynchronously);
        var anim = new DoubleAnimation(from, to, TimeSpan.FromMilliseconds(200));
        anim.Completed += (_, _) => tcs.TrySetResult(true);
        target.BeginAnimation(OpacityProperty, anim);
        return tcs.Task;
    }

    private static string MakeBadge(string? name)
    {
        if (string.IsNullOrWhiteSpace(name)) return "GD";
        var clean = name.Trim();
        return clean.Length == 1
            ? clean[..1].ToUpperInvariant()
            : (clean[..1] + clean[^1]).ToUpperInvariant();
    }

    // ==================== МЕТОДЫ ДЛЯ ЗАЩИЩЕННЫХ ОПЕРАЦИЙ ====================

    /// <summary>
    /// Пример вызова защищенной бизнес-логики через удаленный модуль.
    /// </summary>
    private async void ExecuteSecureBusinessLogic_Click(object sender, RoutedEventArgs e)
    {
        if (_user == null)
        {
            SetStatus(T("login_first"), ResBrush("Danger"));
            return;
        }

        if (_currentSecurityLevel == EnvironmentSecurityLevel.HighRisk)
        {
            SetStatus(T("security_threat"), ResBrush("Danger"));
            return;
        }

        SetStatus(T("secure_executing"), ResBrush("TextSoft"));

        try
        {
            // Вызов защищенного модуля с сервера
            var result = await _api.ExecuteSecureOperationAsync<BusinessLogicResult>(
                "core_logic",
                _security,
                new { UserId = _user.UserId, Action = "process_data", Username = _user.username }
            );

            if (result != null && result.Success)
            {
                SetStatus($"{T("secure_completed")}: {result.Message}", ResBrush("Success"));

                // Отображаем результат в InfoBox
                var resultText = $"=== Secure Operation Result ==={Environment.NewLine}";
                resultText += $"Success: {result.Success}{Environment.NewLine}";
                resultText += $"Message: {result.Message}{Environment.NewLine}";
                resultText += $"Mode: {(result.IsDemo ? "Demo (limited)" : "Full")}{Environment.NewLine}";
                if (result.Data != null)
                {
                    resultText += $"Data: {System.Text.Json.JsonSerializer.Serialize(result.Data)}{Environment.NewLine}";
                }
                SetInfoText(resultText);
            }
            else
            {
                SetStatus($"{T("secure_failed")}: {result?.Message ?? "unknown error"}", ResBrush("Danger"));
            }
        }
        catch (Exception ex)
        {
            SetStatus($"Error: {ex.Message}", ResBrush("Danger"));
            _security.Audit.Error("secure_operation", ex);
        }
    }

    /// <summary>
    /// Проверяет, можно ли выполнять защищенные операции.
    /// </summary>
    public bool CanExecuteSecureOperations()
    {
        return _user != null
            && _user.has_license
            && _currentSecurityLevel <= EnvironmentSecurityLevel.NeedsReview
            && !_isDemoMode;
    }

    /// <summary>
    /// Возвращает текущий статус безопасности для отображения.
    /// </summary>
    public string GetSecurityStatusText()
    {
        return _currentSecurityLevel switch
        {
            EnvironmentSecurityLevel.Trusted => "Full access",
            EnvironmentSecurityLevel.NeedsReview => "Limited access",
            EnvironmentSecurityLevel.Suspicious => "Demo mode",
            EnvironmentSecurityLevel.Restricted => "Restricted",
            EnvironmentSecurityLevel.HighRisk => "Blocked",
            _ => "Unknown"
        };
    }
}

/// <summary>
/// Вспомогательный класс для результата бизнес-логики.
/// </summary>
public class BusinessLogicResult
{
    public bool Success { get; set; }
    public string Message { get; set; } = string.Empty;
    public object? Data { get; set; }
    public bool IsDemo { get; set; }
}