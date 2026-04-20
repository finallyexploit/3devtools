<?php
declare(strict_types=1);

// api.php — GOD DEV Backend
// JSON body (loader), form POST (site), GET params

ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (shouldShowVideoInsteadOfApi()) {
    showHostedVideo();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-site');
header("Permissions-Policy: accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()");
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Embedder-Policy: unsafe-none');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

$allowedOrigins = ['https://soulware.space', 'https://www.soulware.space'];
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    if (!in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Origin denied'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Access-Control-Allow-Origin: {$origin}");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // protect by hauntz
    session_name('GODDEVSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['initiated'])) {
    // protect by hauntz
    session_regenerate_id(true);
    $_SESSION['initiated'] = time();
}

if ($pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}

$jsonRaw = file_get_contents('php://input') ?: '';
$jsonInput = null;
if ($jsonRaw !== '') {
    $decoded = json_decode($jsonRaw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $jsonInput = $decoded;
    }
}

$input = [];
if (is_array($jsonInput) && !empty($jsonInput)) {
    $input = $jsonInput;
} elseif (!empty($_POST)) {
    $input = $_POST;
}

$action = normalizeAction((string)($input['action'] ?? $_GET['action'] ?? ''));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$getActions = ['get_license_info', 'check_subscription', 'get_user_info'];
$postActions = [
    'login', 'register', 'forgot_password_request', 'reset_password_by_code', 'update_profile', 'update_avatar', 'change_password', 'buy', 'buy_extend', 'logout',
    'check_license', 'activate_license', 'get_user_info', 'ban_user', 'delete_account',
    'create_ticket', 'get_tickets', 'get_ticket', 'reply_ticket', 'update_ticket_status',
    'admin_get_users', 'admin_update_user', 'admin_get_dashboard', 'save_config', 'load_config', 'list_configs', 'delete_config'
];

if ($action === '') {
    send(['status' => 'error', 'message' => 'Unknown action']);
}
if ($method === 'POST' && !isTrustedRequest($allowedOrigins)) {
    send(['status' => 'error', 'message' => 'Request denied']);
}
if (requestBodyTooLarge()) {
    send(['status' => 'error', 'message' => 'Payload too large']);
}

if ($method === 'GET' && !in_array($action, $getActions, true)) {
    send(['status' => 'error', 'message' => 'Invalid action']);
}
if ($method === 'POST' && !in_array($action, $postActions, true)) {
    send(['status' => 'error', 'message' => 'Invalid action']);
}
if (!in_array($method, ['GET', 'POST'], true)) {
    send(['status' => 'error', 'message' => 'Invalid method']);
}
if ($method === 'POST' && csrfRequiredForAction($action) && !verifyCsrfToken((string)($input['csrf_token'] ?? ''))) {
    send(['status' => 'error', 'message' => 'Security check failed']);
}

$allowedWhileBanned = ['logout', 'get_user_info'];
if (isset($_SESSION['user_id']) && !in_array($action, $allowedWhileBanned, true)) {
    $activeBan = getActiveBan($pdo, (int)$_SESSION['user_id'], '');
    if ($activeBan['banned']) {
        send([
            'status' => 'banned',
            'message' => formatBanMessage($activeBan),
            'reason' => $activeBan['reason'],
            'expires_at' => $activeBan['expires_at'],
            'telegram' => '@sakurov1337',
        ]);
    }
}

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'get_license_info': send(handleGetLicenseInfo($pdo, $_GET));
            case 'check_subscription': send(handleCheckSubscription($pdo, $_GET));
            case 'get_user_info': send(handleGetUserInfo($pdo, []));
        }
    }

    switch ($action) {
        case 'login': send(handleLogin($pdo, $input));
        case 'register': send(handleRegister($pdo, $input));
        case 'forgot_password_request': send(handleForgotPasswordRequest($pdo, $input));
        case 'reset_password_by_code': send(handleResetPasswordByCode($pdo, $input));
        case 'update_profile': send(handleUpdateProfile($pdo, $input));
        case 'update_avatar': send(handleUpdateAvatar($pdo));
        case 'change_password': send(handleChangePassword($pdo, $input));
        case 'buy': send(handleBuy($pdo, $input));
        case 'buy_extend': send(handleBuyExtend($pdo, $input));
        case 'logout': send(handleLogout());
        case 'check_license': send(handleCheckLicense($pdo, $input));
        case 'activate_license': send(handleActivateLicense($pdo, $input));
        case 'get_user_info': send(handleGetUserInfo($pdo, $input));
        case 'ban_user': send(handleBanUser($pdo, $input));
        case 'delete_account': send(handleDeleteAccount($pdo));
        case 'create_ticket': send(handleCreateTicket($pdo, $input));
        case 'get_tickets': send(handleGetTickets($pdo, $input));
        case 'get_ticket': send(handleGetTicket($pdo, $input));
        case 'reply_ticket': send(handleReplyTicket($pdo, $input));
        case 'update_ticket_status': send(handleUpdateTicketStatus($pdo, $input));
        case 'admin_get_users': send(handleAdminGetUsers($pdo, $input));
        case 'admin_update_user': send(handleAdminUpdateUser($pdo, $input));
        case 'admin_get_dashboard': send(handleAdminGetDashboard($pdo, $input));
        case 'save_config': send(handleSaveConfig($pdo, $input));
        case 'load_config': send(handleLoadConfig($pdo, $input));
        case 'list_configs': send(handleListConfigs($pdo, $input));
        case 'delete_config': send(handleDeleteConfig($pdo, $input));
        default: send(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    error_log('GODDEV API fatal: ' . $e->getMessage());
    send(['status' => 'error', 'message' => 'Server error']);
}

function send(array $payload): void {
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

function stripCtl(string $value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? '';
    return trim($value);
}

function normalizeAction(string $value): string {
    return preg_replace('/[^a-z_]/', '', strtolower(stripCtl($value))) ?: '';
}

function isTrustedRequest(array $allowedOrigins): bool {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') return in_array($origin, $allowedOrigins, true);

    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) return false;

    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        $refHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?: ''));
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($refHost !== '' && $host !== '' && $refHost !== $host && $refHost !== 'www.' . $host && 'www.' . $refHost !== $host) {
            return false;
        }
    }

    return true;
}

function clientIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function requestBodyTooLarge(): bool {
    $max = 5 * 1024 * 1024;
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $len > 0 && $len > $max;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        // protect by hauntz
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrfRequiredForAction(string $action): bool {
    if (!isset($_SESSION['user_id'])) return false;
    return in_array($action, [
        'update_profile', 'update_avatar', 'change_password', 'buy', 'buy_extend', 'logout',
        'activate_license', 'delete_account', 'create_ticket', 'reply_ticket', 'update_ticket_status',
        'ban_user', 'admin_update_user'
    ], true);
}

function verifyCsrfToken(string $token): bool {
    $header = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $provided = $header !== '' ? $header : $token;
    $stored = (string)($_SESSION['csrf_token'] ?? '');
    return $provided !== '' && $stored !== '' && hash_equals($stored, $provided);
}

function currentUserAgent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
}

function normUsername(string $value, int $min = 3, int $max = 50): string {
    $value = stripCtl($value);
    if ($value === '' || mb_strlen($value) < $min || mb_strlen($value) > $max) return '';
    return preg_match('/^[\p{L}\p{N}_.@-]+$/u', $value) ? $value : '';
}

function normEmail(string $value): string {
    $value = strtolower(stripCtl($value));
    if ($value === '' || strlen($value) > 100 || !filter_var($value, FILTER_VALIDATE_EMAIL)) return '';
    return $value;
}

function normPassword(string $value, int $min = 6, int $max = 255): string {
    $value = str_replace(["\r", "\n", "\0"], '', $value);
    $len = strlen($value);
    return ($len >= $min && $len <= $max) ? $value : '';
}

function normHwid(string $value): string {
    $value = strtoupper(trim($value));
    if ($value === '') return '';
    return preg_match('/^[A-Z0-9_-]{8,64}$/', $value) ? $value : '';
}

function normLicenseKey(string $value): string {
    $value = strtoupper(trim($value));
    return preg_match('/^[A-Z0-9-]{8,64}$/', $value) ? $value : '';
}

function normText(string $value, int $max): string {
    $value = stripCtl($value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return mb_substr($value, 0, $max);
}

function normEnum(string $value, array $allowed, string $default): string {
    return in_array($value, $allowed, true) ? $value : $default;
}

function normBool($value): int {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
}

function normPositiveInt($value): int {
    if (is_int($value)) return max(0, $value);
    if (is_string($value) && ctype_digit($value)) return (int)$value;
    return 0;
}

function normMoney($value, float $default = 0.0): float {
    if (is_numeric($value)) {
        $n = (float)$value;
        if ($n >= 0 && $n <= 1000000) return round($n, 2);
    }
    return $default;
}

function rateLimit(string $bucket, int $limit, int $windowSeconds): bool {
    $ip = clientIp();
    $dir = sys_get_temp_dir() . '/goddev_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    $file = $dir . '/' . sha1($bucket . '|' . $ip) . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $stored = json_decode((string)@file_get_contents($file), true);
        if (is_array($stored)) $hits = $stored;
    }
    $hits = array_values(array_filter($hits, static fn($ts) => is_int($ts) && ($ts > $now - $windowSeconds)));
    if (count($hits) >= $limit) return true;
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    @chmod($file, 0600);
    return false;
}

function dbHasColumn(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function ensureUploadsProtection(): void {
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return;
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        // protect by hauntz
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\.(php|phtml|phar|cgi|pl|py|jsp|asp|aspx|sh)$\">\n  Require all denied\n</FilesMatch>\nAddType text/plain .php .phtml .phar .cgi .pl .py .jsp .asp .aspx .sh\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options \"nosniff\"\n  Header set Content-Security-Policy \"default-src 'none'; img-src 'self' data: blob:; style-src 'none'; script-src 'none'; sandbox\"\n</IfModule>\n");
    }
}

function requireAuth(): ?array {
    if (!isset($_SESSION['user_id'])) return ['status' => 'error', 'message' => 'Not authenticated'];
    csrfToken();
    return null;
}

function requireAdmin(PDO $pdo): ?array {
    if (!isset($_SESSION['user_id']) || !isAdminUser($pdo, (int)$_SESSION['user_id'])) {
        return ['status' => 'error', 'message' => 'Access denied'];
    }
    return null;
}

function handleLogin(PDO $pdo, array $data): array {
    if (rateLimit('login', 12, 300)) {
        return ['status' => 'error', 'message' => 'Too many attempts. Try again later'];
    }

    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $hwid = normHwid((string)($data['hwid'] ?? ''));

    if ($username === '' || $password === '') {
        return ['status' => 'error', 'message' => 'Username and password required'];
    }
    if (mb_strlen($username) > 100 || strlen($password) > 255) {
        return ['status' => 'error', 'message' => 'Invalid credentials'];
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password'])) {
        logAction($pdo, null, $username, $hwid, 'failed_login');
        return ['status' => 'error', 'message' => 'Invalid credentials'];
    }

    $ban = getActiveBan($pdo, (int)$user['id'], $hwid);
    if ($ban['banned']) {
        return [
            'status' => 'banned',
            'message' => formatBanMessage($ban),
            'reason' => $ban['reason'],
            'expires_at' => $ban['expires_at'],
            'telegram' => '@sakurov1337',
        ];
    }

    if ($hwid !== '' && empty($user['hwid'])) {
        $pdo->prepare('UPDATE users SET hwid = ? WHERE id = ?')->execute([$hwid, (int)$user['id']]);
    } elseif ($hwid !== '' && !empty($user['hwid']) && hash_equals((string)$user['hwid'], $hwid) === false) {
        return ['status' => 'error', 'message' => 'HWID mismatch. Account locked to another device.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    logAction($pdo, (int)$user['id'], (string)$user['username'], $hwid, 'login');

    $stmt = $pdo->prepare("SELECT product_type, expires_at FROM licenses WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
    $stmt->execute([(int)$user['id']]);
    $lic = $stmt->fetch();

    return [
        'status' => 'success',
        'message' => 'Login successful',
        'username' => $user['username'],
        'user_id' => (int)$user['id'],
        'balance' => (float)$user['balance'],
        'is_admin' => (bool)$user['is_admin'],
        'admin_level' => (int)$user['admin_level'],
        'can_ban' => (bool)$user['can_ban'],
        'has_license' => $lic ? true : false,
        'license_type' => $lic['product_type'] ?? null,
        'expires_at' => $lic['expires_at'] ?? null,
        'csrf_token' => csrfToken(),
    ];
}

function handleRegister(PDO $pdo, array $data): array {
    if (rateLimit('register', 8, 900)) {
        return ['status' => 'error', 'message' => 'Too many attempts. Try again later'];
    }

    $username = normUsername((string)($data['username'] ?? ''));
    $email = normEmail((string)($data['email'] ?? ''));
    $password = normPassword((string)($data['password'] ?? ''));

    if ($username === '' || $email === '' || $password === '') {
        return ['status' => 'error', 'message' => 'Invalid registration data'];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['status' => 'error', 'message' => 'Username or email already taken'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, email, password, balance) VALUES (?,?,?,0)')->execute([$username, $email, $hash]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    $_SESSION['username'] = $username;
    return ['status' => 'success', 'message' => 'Registration successful', 'csrf_token' => csrfToken()];
}

function handleUpdateProfile(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $nickname = normUsername((string)($data['nickname'] ?? ''));
    if ($nickname === '') return ['status' => 'error', 'message' => 'Invalid nickname'];

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
    $stmt->execute([$nickname, (int)$_SESSION['user_id']]);
    if ($stmt->fetch()) return ['status' => 'error', 'message' => 'Username already taken'];

    $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$nickname, (int)$_SESSION['user_id']]);
    $_SESSION['username'] = $nickname;
    return ['status' => 'success', 'message' => 'Saved'];
}

function handleUpdateAvatar(PDO $pdo): array {
    if ($auth = requireAuth()) return $auth;
    ensureUploadsProtection();
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'No file uploaded'];
    }

    $file = $_FILES['avatar'];
    $maxBytes = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) return ['status' => 'error', 'message' => 'Max file size: 2 MB'];
    if (!is_uploaded_file((string)$file['tmp_name'])) return ['status' => 'error', 'message' => 'Upload failed'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, (string)$file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) return ['status' => 'error', 'message' => 'Only JPG/PNG/GIF/WEBP allowed'];
    $imgInfo = @getimagesize((string)$file['tmp_name']);
    if ($imgInfo === false) return ['status' => 'error', 'message' => 'Invalid image'];
    $w = (int)($imgInfo[0] ?? 0);
    $h = (int)($imgInfo[1] ?? 0);
    if ($w <= 0 || $h <= 0 || $w > 6000 || $h > 6000 || ($w * $h) > 16000000) return ['status' => 'error', 'message' => 'Image is too large'];

    $filename = 'user_' . (int)$_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['status' => 'error', 'message' => 'Upload directory error'];
    }

    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $old = $stmt->fetch();

    $target = $dir . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        return ['status' => 'error', 'message' => 'Upload failed'];
    }
    @chmod($target, 0644);

    $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$filename, (int)$_SESSION['user_id']]);

    if ($old && !empty($old['avatar']) && $old['avatar'] !== 'default.png' && preg_match('/^[a-zA-Z0-9._-]{1,255}$/', (string)$old['avatar'])) {
        $oldPath = $dir . $old['avatar'];
        if (is_file($oldPath)) @unlink($oldPath);
    }

    return ['status' => 'success', 'filename' => $filename, 'csrf_token' => csrfToken()];
}

function handleChangePassword(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $old = (string)($data['old_password'] ?? '');
    $new = normPassword((string)($data['new_password'] ?? ''));
    $conf = (string)($data['confirm_password'] ?? '');

    if ($old === '' || $new === '' || $conf === '') return ['status' => 'error', 'message' => 'All fields required'];
    if (!hash_equals($new, $conf)) return ['status' => 'error', 'message' => 'Passwords do not match'];

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($old, (string)$u['password'])) return ['status' => 'error', 'message' => 'Wrong current password'];

    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // protect by hauntz
    return ['status' => 'success', 'message' => 'Password changed', 'csrf_token' => csrfToken()];
}


function handleForgotPasswordRequest(PDO $pdo, array $data): array {
    if (rateLimit('forgot_password_request', 6, 900)) {
        return ['status' => 'error', 'message' => 'Too many attempts. Try again later'];
    }

    $email = normEmail((string)($data['email'] ?? ''));
    if ($email === '') return ['status' => 'error', 'message' => 'Enter a valid email'];

    try {
        ensurePasswordResetTable($pdo);
        $pdo->prepare('DELETE FROM password_reset_codes WHERE expires_at <= NOW() OR used_at IS NOT NULL')->execute();
    } catch (Throwable $e) {
        forgotPasswordLog('setup failed: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Password reset setup error'];
    }

    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['status' => 'success', 'message' => 'If the email exists, the code has been sent'];
    }

    $code = createPasswordResetCode();
    $hash = password_hash($code, PASSWORD_DEFAULT);

    try {
        $pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
        $pdo->prepare('INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')
            ->execute([(int)$user['id'], (string)$user['email'], $hash]);
    } catch (Throwable $e) {
        forgotPasswordLog('code save failed: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Could not save reset code'];
    }

    if (!sendPasswordResetEmail((string)$user['email'], (string)$user['username'], $code)) {
        $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ? AND used_at IS NULL')->execute([$email]);
        return ['status' => 'error', 'message' => 'Could not send email'];
    }

    logAction($pdo, (int)$user['id'], (string)$user['username'], '', 'forgot_password_request');
    return ['status' => 'success', 'message' => 'If the email exists, the code has been sent'];
}

function handleResetPasswordByCode(PDO $pdo, array $data): array {
    if (rateLimit('reset_password_by_code', 10, 900)) {
        return ['status' => 'error', 'message' => 'Too many attempts. Try again later'];
    }

    $email = normEmail((string)($data['email'] ?? ''));
    $code = preg_replace('/\D+/', '', (string)($data['code'] ?? '')) ?: '';
    $new = normPassword((string)($data['new_password'] ?? ''));
    $conf = (string)($data['confirm_password'] ?? '');

    if ($email === '' || $code === '' || $new === '' || $conf === '') {
        return ['status' => 'error', 'message' => 'All fields required'];
    }
    if (strlen($code) !== 6) return ['status' => 'error', 'message' => 'Enter the 6-digit code'];
    if (!hash_equals($new, $conf)) return ['status' => 'error', 'message' => 'Passwords do not match'];

    ensurePasswordResetTable($pdo);

    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return ['status' => 'error', 'message' => 'Invalid or expired code'];

    $stmt = $pdo->prepare('SELECT id, code_hash FROM password_reset_codes WHERE email = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 5');
    $stmt->execute([$email]);
    $rows = $stmt->fetchAll();

    $matchId = 0;
    foreach ($rows as $row) {
        if (password_verify($code, (string)$row['code_hash'])) {
            $matchId = (int)$row['id'];
            break;
        }
    }
    if ($matchId <= 0) return ['status' => 'error', 'message' => 'Invalid or expired code'];

    $hash = password_hash($new, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, (int)$user['id']]);
        $pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    logAction($pdo, (int)$user['id'], (string)$user['username'], '', 'password_reset');
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // protect by hauntz
    return ['status' => 'success', 'message' => 'Password changed', 'csrf_token' => csrfToken()];
}

function ensurePasswordResetTable(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_codes (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        email VARCHAR(100) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_password_reset_user_id (user_id),
        KEY idx_password_reset_email (email),
        KEY idx_password_reset_expires_at (expires_at),
        KEY idx_password_reset_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ready = true;
}

function createPasswordResetCode(): string {
    return (string)random_int(100000, 999999);
}

function forgotPasswordLog(string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $logFile = __DIR__ . '/forgot_password.log';
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    @chmod($logFile, 0600);
    error_log('Forgot password: ' . $message);
}

function forgotPasswordTextBody(string $username, string $code, string $site): string {
    $user = trim($username) !== '' ? $username : 'user';
    return "SOULWARE

"
        . "Hello, {$user}.

"
        . "Use this code to reset your password:

"
        . $code . "

"
        . "The code is valid for 15 minutes.

"
        . "If you did not request this reset, just ignore this email.

"
        . "Open site: {$site}
";
}

function sendPasswordResetEmail(string $to, string $username, string $code): bool {
    $safeUser = htmlspecialchars($username !== '' ? $username : 'user', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $site = (string)(getenv('GODDEV_SITE_URL') ?: 'https://soulware.space');
    $safeSite = htmlspecialchars($site, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'Soulware password reset code';
    $text = forgotPasswordTextBody($username, $code, $site);
    $html = <<<HTML
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Soulware password reset</title>
</head>
<body style="margin:0;padding:0;background-color:#05070a;background:#05070a;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">Soulware reset code {$safeCode}. Valid for 15 minutes.</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#05070a" style="width:100%;border-collapse:collapse;background-color:#05070a;background:#05070a;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;border-collapse:collapse;">
          <tr>
            <td style="padding:0 0 14px 0;font-family:Arial Black,Arial,sans-serif;font-size:30px;line-height:1;color:#ffffff;letter-spacing:-0.02em;">SOULWARE</td>
          </tr>
          <tr>
            <td style="padding:0 0 18px 0;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0a0d12" style="width:100%;border-collapse:separate;background-color:#0a0d12;background:#0a0d12;border:1px solid #202533;border-radius:18px;">
                <tr>
                  <td width="50%" align="center" bgcolor="#171f08" style="padding:16px 10px;border:1px solid #34431a;border-radius:14px;font-family:'Courier New',Courier,monospace;font-size:16px;line-height:1;color:#c8f135;font-weight:bold;letter-spacing:0.22em;text-transform:uppercase;">Sign in</td>
                  <td width="50%" align="center" style="padding:16px 10px;font-family:'Courier New',Courier,monospace;font-size:16px;line-height:1;color:#788097;letter-spacing:0.22em;text-transform:uppercase;">Reset</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td bgcolor="#090c12" style="background-color:#090c12;background:#090c12;border:1px solid #1b2230;border-radius:24px;padding:28px 24px 24px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                <tr>
                  <td style="padding:0 0 18px 0;">
                    <span style="display:inline-block;padding:9px 14px;background:#141b08;border:1px solid #334216;border-radius:999px;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1;color:#c8f135;letter-spacing:0.18em;text-transform:uppercase;">Password reset</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 0 10px 0;font-family:Arial Black,Arial,sans-serif;font-size:40px;line-height:0.95;color:#ffffff;letter-spacing:-0.03em;text-transform:uppercase;">Restore access</td>
                </tr>
                <tr>
                  <td style="padding:0 0 22px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.75;color:#b2b8c9;">Hello, {$safeUser}. Use the code below to reset your password. The code stays active for 15 minutes.</td>
                </tr>
                <tr>
                  <td style="padding:0 0 20px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0c1018" style="width:100%;border-collapse:collapse;background-color:#0c1018;background:#0c1018;border:1px solid #273043;border-radius:18px;">
                      <tr>
                        <td style="padding:16px 18px 8px 18px;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1;color:#7f879b;letter-spacing:0.18em;text-transform:uppercase;">Verification code</td>
                      </tr>
                      <tr>
                        <td align="center" style="padding:10px 18px 22px 18px;font-family:'Courier New',Courier,monospace;font-size:42px;line-height:1;color:#c8f135;font-weight:bold;letter-spacing:0.34em;">{$safeCode}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 0 20px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#180b0d" style="width:100%;border-collapse:collapse;background-color:#180b0d;background:#180b0d;border:1px solid #4a1d24;border-radius:14px;">
                      <tr>
                        <td style="padding:14px 16px;font-family:'Courier New',Courier,monospace;font-size:12px;line-height:1.7;color:#ff7b88;">If you did not request this reset, just ignore this email. Your current password will stay active.</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 0 12px 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="border-collapse:collapse;">
                      <tr>
                        <td align="center" bgcolor="#c8f135" style="background-color:#c8f135;background:#c8f135;border-radius:12px;">
                          <a href="{$safeSite}" target="_blank" style="display:inline-block;padding:18px 28px;font-family:'Courier New',Courier,monospace;font-size:15px;line-height:1;color:#05070a;text-decoration:none;font-weight:bold;letter-spacing:0.18em;text-transform:uppercase;">Open Soulware →</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;color:#7f879b;text-align:center;">If the button is not visible, open this link:<br><a href="{$safeSite}" target="_blank" style="color:#c8f135;text-decoration:none;">{$safeSite}</a></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 6px 0 6px;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.7;color:#667085;text-align:center;letter-spacing:0.14em;text-transform:uppercase;">Soulware.space · automated security email</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    return sendHtmlMail($to, $subject, $html, $text);
}


function mailCfg(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = __DIR__ . '/mail_config.php';
    $cfg = is_file($file) ? (array)require $file : [];
    return $cfg;
}

function smtpRead($fp): string {
    $out = '';
    while (($line = fgets($fp, 515)) !== false) {
        $out .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    return $out;
}

function smtpOk(string $resp, array $codes): bool {
    return in_array((int)substr($resp, 0, 3), $codes, true);
}

function smtpCmd($fp, string $cmd, array $ok): bool {
    if (fwrite($fp, $cmd . "\r\n") === false) return false;
    return smtpOk(smtpRead($fp), $ok);
}

function mimeBase64(string $value): string {
    $raw = str_replace(["\r\n", "\r"], "\n", $value);
    return rtrim(chunk_split(base64_encode($raw), 76, "\r\n"));
}

function smtpDotStuff(string $value): string {
    return preg_replace('/(^|\r\n)\./', '$1..', $value) ?? $value;
}

function smtpSendHtmlMail(string $to, string $subject, string $html, string $textBody, string $fromEmail, string $fromName, string $replyTo, array $cfg): bool {
    $host = trim((string)($cfg['host'] ?? ''));
    $port = (int)($cfg['port'] ?? 465);
    $user = trim((string)($cfg['username'] ?? ''));
    $pass = (string)($cfg['password'] ?? '');
    $secure = strtolower(trim((string)($cfg['secure'] ?? 'ssl')));
    $timeout = max(5, (int)($cfg['timeout'] ?? 15));
    $auth = array_key_exists('auth', $cfg) ? (bool)$cfg['auth'] : true;

    if ($host === '') {
        forgotPasswordLog('SMTP config is incomplete');
        return false;
    }
    if ($auth && ($user === '' || $pass === '' || $pass === 'CHANGE_ME')) {
        forgotPasswordLog('SMTP config is incomplete');
        return false;
    }

    $scheme = $secure === 'ssl' ? 'ssl://' : '';
    $peer = $scheme . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            // protect by hauntz
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);
    $fp = @stream_socket_client($peer, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        forgotPasswordLog('SMTP connect failed [' . $host . ':' . $port . ' ' . ($secure ?: 'plain') . ']: ' . $errstr . ' #' . $errno);
        return false;
    }

    stream_set_timeout($fp, $timeout);
    $hello = smtpRead($fp);
    if (!smtpOk($hello, [220])) {
        forgotPasswordLog('SMTP hello failed: ' . trim($hello));
        fclose($fp);
        return false;
    }

    $ehloHost = preg_replace('/[^a-z0-9.-]+/i', '', ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    if (!smtpCmd($fp, 'EHLO ' . $ehloHost, [250])) {
        forgotPasswordLog('SMTP EHLO failed');
        fclose($fp);
        return false;
    }

    if ($secure === 'tls') {
        if (!smtpCmd($fp, 'STARTTLS', [220]) || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            forgotPasswordLog('SMTP STARTTLS failed');
            fclose($fp);
            return false;
        }
        if (!smtpCmd($fp, 'EHLO ' . $ehloHost, [250])) {
            forgotPasswordLog('SMTP EHLO after TLS failed');
            fclose($fp);
            return false;
        }
    }

    if ($auth) {
        if (!smtpCmd($fp, 'AUTH LOGIN', [334]) || !smtpCmd($fp, base64_encode($user), [334]) || !smtpCmd($fp, base64_encode($pass), [235])) {
            forgotPasswordLog('SMTP auth failed');
            fclose($fp);
            return false;
        }
    }

    if (!smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]) || !smtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]) || !smtpCmd($fp, 'DATA', [354])) {
        forgotPasswordLog('SMTP envelope failed');
        fclose($fp);
        return false;
    }

    $boundary = 'b' . bin2hex(random_bytes(12));
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $messageIdHost = preg_replace('/[^a-z0-9.-]+/i', '', parse_url((string)(getenv('GODDEV_SITE_URL') ?: 'https://soulware.space'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $normalize = static function (string $value): string {
        $value = str_replace(["
", "
"], "
", $value);
        return str_replace("
", "
", $value);
    };

    $msg = implode("
", [
        'Date: ' . date(DATE_RFC2822),
        'MIME-Version: 1.0',
        'From: ' . $encodedName . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Reply-To: <' . $replyTo . '>',
        'Subject: ' . $encodedSubject,
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
        '',
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $normalize($textBody),
        '',
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        preg_replace('/(?m)^\./', '..', $normalize($html)),
        '',
        '--' . $boundary . '--',
        '',
    ]) . "
.
";

    if (fwrite($fp, $msg) === false) {
        forgotPasswordLog('SMTP body write failed');
        fclose($fp);
        return false;
    }

    $resp = smtpRead($fp);
    @fwrite($fp, "QUIT
");
    fclose($fp);
    if (!smtpOk($resp, [250])) {
        forgotPasswordLog('SMTP send failed: ' . trim($resp));
        return false;
    }
    return true;
}

function sendHtmlMail(string $to, string $subject, string $html, string $textBody = ''): bool {
    $cfg = mailCfg();
    $fromEmail = normEmail((string)($cfg['from_email'] ?? getenv('GODDEV_MAIL_FROM') ?: 'no-reply@soulware.space')) ?: 'no-reply@soulware.space';
    $fromName = trim((string)($cfg['from_name'] ?? getenv('GODDEV_MAIL_NAME') ?: 'GOD DEV')) ?: 'GOD DEV';
    $replyTo = normEmail((string)($cfg['reply_to'] ?? getenv('GODDEV_MAIL_REPLY_TO') ?: $fromEmail)) ?: $fromEmail;
    $textBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "
", $html)));

    if (!empty($cfg['enabled']) || strtolower((string)getenv('GODDEV_MAIL_DRIVER')) === 'smtp') {
        return smtpSendHtmlMail($to, $subject, $html, $textBody, $fromEmail, $fromName, $replyTo, $cfg);
    }

    if (!function_exists('mail')) {
        forgotPasswordLog('mail() is not available in PHP');
        return false;
    }

    $boundary = 'b' . bin2hex(random_bytes(12));
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $normalize = static function (string $value): string {
        $value = str_replace(["
", "
"], "
", $value);
        return str_replace("
", "
", $value);
    };
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $encodedName . ' <' . $fromEmail . '>',
        'Reply-To: <' . $replyTo . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $body = implode("
", [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $normalize($textBody),
        '',
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $normalize($html),
        '',
        '--' . $boundary . '--',
        '',
    ]);

    $sent = @mail($to, $subject, $body, implode("
", $headers), '-f ' . $fromEmail);
    if (!$sent) forgotPasswordLog('mail() returned false for ' . $to . ' from ' . $fromEmail);
    return $sent;
}

function productCatalog(): array {
    return [
        'starter' => ['price' => 9.0, 'name' => 'GOD DEV Starter (7 days)', 'days' => 7],
        'standard' => ['price' => 15.0, 'name' => 'GOD DEV Standard (30 days)', 'days' => 30],
        'pro' => ['price' => 40.0, 'name' => 'GOD DEV Pro (90 days)', 'days' => 90],
    ];
}

function resolveProductStrict(array $data): ?array {
    $catalog = productCatalog();
    $type = normEnum(trim((string)($data['product_type'] ?? '')), array_keys($catalog), '');

    if ($type === '') {
        $price = normMoney($data['price'] ?? 0);
        foreach ($catalog as $k => $meta) {
            if (abs($meta['price'] - $price) < 0.0001) {
                $type = $k;
                break;
            }
        }
    }

    if ($type === '' || !isset($catalog[$type])) return null;
    return ['type' => $type, 'price' => $catalog[$type]['price'], 'name' => $catalog[$type]['name']];
}

function handleBuy(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    $product = resolveProductStrict($data);
    if (!$product) return ['status' => 'error', 'message' => 'Invalid product'];

    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || (float)$u['balance'] < $product['price']) return ['status' => 'error', 'message' => 'Insufficient balance'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE user_id=? AND product_type=? AND status='active' AND expires_at>NOW()");
    $stmt->execute([(int)$_SESSION['user_id'], $product['type']]);
    if ((int)$stmt->fetchColumn() > 0) {
        return ['status' => 'has_active', 'message' => 'Active subscription exists', 'product_type' => $product['type'], 'product_name' => $product['name'], 'price' => $product['price']];
    }

    return processPurchase($pdo, (int)$_SESSION['user_id'], $product['price'], $product['type'], $product['name'], false);
}

function handleBuyExtend(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    $product = resolveProductStrict($data);
    if (!$product) return ['status' => 'error', 'message' => 'Invalid product'];

    $extend = (($data['extend'] ?? 'new') === 'extend');
    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || (float)$u['balance'] < $product['price']) return ['status' => 'error', 'message' => 'Insufficient balance'];

    return processPurchase($pdo, (int)$_SESSION['user_id'], $product['price'], $product['type'], $product['name'], $extend);
}

function processPurchase(PDO $pdo, int $userId, float $price, string $type, string $name, bool $extend): array {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
        $stmt->execute([$price, $userId, $price]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'Insufficient balance'];
        }
        $key = createLicense($pdo, $userId, $type, $name, $extend);
        $pdo->prepare("INSERT INTO purchases (user_id, product_name, amount, status) VALUES (?,?,?,'completed')")
            ->execute([$userId, $name, $price]);
        $pdo->commit();
        return ['status' => 'success', 'message' => $extend ? 'Extended!' : 'Purchase successful', 'license_key' => $key, 'extended' => $extend];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Purchase error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Transaction failed'];
    }
}

function handleLogout(): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
        }
        session_destroy();
    }
    return ['status' => 'success', 'message' => 'Logged out'];
}

function handleCheckLicense(PDO $pdo, array $data): array {
    $key = normLicenseKey((string)($data['license_key'] ?? ''));
    $hwid = normHwid((string)($data['hwid'] ?? ''));
    if ($key === '') return ['status' => 'error', 'message' => 'License key required'];

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND status = 'active' AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    if (!$lic) return ['status' => 'error', 'message' => 'Invalid or expired license'];
    if (!empty($lic['hwid_locked']) && $hwid !== '' && !hash_equals((string)$lic['hwid_locked'], $hwid)) {
        return ['status' => 'error', 'message' => 'License locked to another HWID'];
    }

    $pdo->prepare('UPDATE licenses SET last_used = NOW() WHERE id = ?')->execute([(int)$lic['id']]);
    return ['status' => 'success', 'product_name' => $lic['product_name'], 'product_type' => $lic['product_type'], 'expires_at' => $lic['expires_at']];
}

function handleActivateLicense(PDO $pdo, array $data): array {
    $key = normLicenseKey((string)($data['license_key'] ?? ''));
    $hwid = normHwid((string)($data['hwid'] ?? ''));
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId <= 0 || $key === '') return ['status' => 'error', 'message' => 'License key required'];

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND status = 'active' AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    if (!$lic) return ['status' => 'error', 'message' => 'Invalid or expired license'];
    if (!empty($lic['user_id']) && (int)$lic['user_id'] !== $userId) return ['status' => 'error', 'message' => 'License belongs to another user'];

    $pdo->prepare('UPDATE licenses SET user_id=?, hwid_locked=?, activated_at=NOW() WHERE id=?')->execute([$userId, $hwid ?: null, (int)$lic['id']]);
    return ['status' => 'success', 'message' => 'Activated'];
}

function buildUserSelect(PDO $pdo, string $alias = ''): string {
    $p = $alias !== '' ? $alias . '.' : '';
    $cols = [
        $p . 'id', $p . 'username', $p . 'email', $p . 'balance', $p . 'avatar',
        $p . 'is_admin', $p . 'admin_level', $p . 'can_ban', $p . 'is_banned', $p . 'ban_reason', $p . 'created_at'
    ];
    foreach (['preferred_currency', 'can_manage_users', 'can_manage_tickets', 'can_edit_balance', 'admin_note'] as $col) {
        if (dbHasColumn($pdo, 'users', $col)) $cols[] = $p . $col;
    }
    return implode(',', $cols);
}

function handleGetUserInfo(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $hwid = normHwid((string)($data['hwid'] ?? ''));
    $stmt = $pdo->prepare('SELECT ' . buildUserSelect($pdo) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return ['status' => 'error', 'message' => 'User not found'];

    $ban = getActiveBan($pdo, (int)$_SESSION['user_id'], $hwid);
    $user['is_banned'] = $ban['banned'] ? 1 : (int)($user['is_banned'] ?? 0);
    $user['ban_reason'] = $ban['banned'] ? $ban['reason'] : ($user['ban_reason'] ?? null);
    $user['ban_expires_at'] = $ban['banned'] ? $ban['expires_at'] : null;

    $stmt = $pdo->prepare('SELECT * FROM licenses WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $licenses = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll();

    return ['status' => 'success', 'user' => $user, 'licenses' => $licenses, 'purchases' => $purchases, 'ban' => $ban['banned'] ? $ban : null, 'csrf_token' => csrfToken()];
}

function handleCheckSubscription(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    $type = normEnum(trim((string)($data['product_type'] ?? '')), ['starter', 'standard', 'pro'], '');
    if ($type === '') return ['status' => 'error', 'message' => 'Missing params'];
    $stmt = $pdo->prepare("SELECT id FROM licenses WHERE user_id=? AND product_type=? AND status='active' AND expires_at>NOW() LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id'], $type]);
    return ['status' => 'success', 'has_active' => (bool)$stmt->fetch()];
}

function handleBanUser(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    $stmt = $pdo->prepare('SELECT can_ban, admin_level FROM users WHERE id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    if (!$admin || !(bool)$admin['can_ban']) return ['status' => 'error', 'message' => 'No permission'];

    $userId = normPositiveInt($data['user_id'] ?? 0);
    $hwid = normHwid((string)($data['hwid'] ?? ''));
    $reason = normText((string)($data['reason'] ?? ''), 500) ?: 'Administrative action';
    $dur = isset($data['duration']) && (string)$data['duration'] !== '' ? normPositiveInt($data['duration']) : 0;
    $expires = $dur > 0 ? date('Y-m-d H:i:s', strtotime("+{$dur} days")) : null;

    if ($userId <= 0 && $hwid === '') return ['status' => 'error', 'message' => 'User or HWID required'];
    if ($userId === (int)$_SESSION['user_id']) return ['status' => 'error', 'message' => 'You cannot ban yourself'];

    try {
        $pdo->beginTransaction();

        if ($userId > 0) {
            $stmt = $pdo->prepare('SELECT id, hwid, admin_level FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if (!$target) {
                $pdo->rollBack();
                return ['status' => 'error', 'message' => 'User not found'];
            }
            if ((int)$target['admin_level'] >= (int)$admin['admin_level'] && (bool)$target['admin_level']) {
                $pdo->rollBack();
                return ['status' => 'error', 'message' => 'Target admin level is too high'];
            }

            $pdo->prepare('UPDATE users SET is_banned=1, ban_reason=? WHERE id=?')->execute([$reason, $userId]);
            $pdo->prepare('INSERT INTO bans (user_id, reason, banned_by, expires_at) VALUES (?,?,?,?)')->execute([$userId, $reason, (int)$_SESSION['user_id'], $expires]);

            if (!empty($target['hwid'])) {
                $pdo->prepare('INSERT INTO bans (hwid, reason, banned_by, expires_at) VALUES (?,?,?,?)')->execute([$target['hwid'], $reason, (int)$_SESSION['user_id'], $expires]);
            }
        }

        if ($hwid !== '') {
            $pdo->prepare('INSERT INTO bans (hwid, reason, banned_by, expires_at) VALUES (?,?,?,?)')->execute([$hwid, $reason, (int)$_SESSION['user_id'], $expires]);
        }

        $pdo->commit();
        return ['status' => 'success', 'message' => 'Banned'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Ban error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Ban failed'];
    }
}

function handleDeleteAccount(PDO $pdo): array {
    if ($auth = requireAuth()) return $auth;
    $uid = (int)$_SESSION['user_id'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();

        if ($u && !empty($u['avatar']) && $u['avatar'] !== 'default.png' && preg_match('/^[a-zA-Z0-9._-]{1,255}$/', (string)$u['avatar'])) {
            $p = __DIR__ . '/uploads/' . $u['avatar'];
            if (is_file($p)) @unlink($p);
        }

        foreach ([
            'DELETE FROM purchases WHERE user_id=?',
            'UPDATE licenses SET user_id=NULL WHERE user_id=?',
            'DELETE FROM user_sessions WHERE user_id=?',
            'DELETE FROM loader_logs WHERE user_id=?',
            'DELETE FROM bans WHERE user_id=?',
            'DELETE FROM users WHERE id=?'
        ] as $q) {
            $pdo->prepare($q)->execute([$uid]);
        }
        $pdo->commit();
        return handleLogout();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Delete account error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Delete failed'];
    }
}

function handleGetLicenseInfo(PDO $pdo, array $data): array {
    $key = normLicenseKey((string)($data['license_key'] ?? ''));
    if ($key === '') return ['status' => 'error', 'message' => 'Key required'];
    $stmt = $pdo->prepare('SELECT license_key, product_name, product_type, expires_at, status, activated_at FROM licenses WHERE license_key=? LIMIT 1');
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    return $lic ? ['status' => 'success', 'license' => $lic] : ['status' => 'error', 'message' => 'Not found'];
}

function handleCreateTicket(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    if (rateLimit('create_ticket', 10, 600)) return ['status' => 'error', 'message' => 'Too many tickets. Try later'];

    $subject = normText((string)($data['subject'] ?? ''), 140);
    $category = strtolower(normText((string)($data['category'] ?? 'general'), 50));
    $priority = normEnum(trim((string)($data['priority'] ?? 'normal')), ['low', 'normal', 'high'], 'normal');
    $message = normText((string)($data['message'] ?? ''), 5000);

    if ($subject === '' || $message === '') return ['status' => 'error', 'message' => 'Subject and message required'];
    $category = preg_replace('/[^a-z0-9_-]/i', '', $category) ?: 'general';

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, category, priority, status, last_reply_at) VALUES (?,?,?,?, 'open', NOW())")
            ->execute([(int)$_SESSION['user_id'], $subject, $category, $priority]);
        $ticketId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO support_ticket_messages (ticket_id, user_id, sender_role, message) VALUES (?,?, 'user', ?)")
            ->execute([$ticketId, (int)$_SESSION['user_id'], $message]);
        $pdo->commit();
        return ['status' => 'success', 'message' => 'Ticket created', 'ticket_id' => $ticketId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Ticket create error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Ticket create failed'];
    }
}

function handleGetTickets(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $isAdmin = isAdminUser($pdo, (int)$_SESSION['user_id']);
    $status = normEnum(trim((string)($data['status'] ?? '')), ['open', 'answered', 'closed'], '');
    $where = [];
    $params = [];

    if (!$isAdmin) {
        $where[] = 't.user_id = ?';
        $params[] = (int)$_SESSION['user_id'];
    }
    if ($status !== '') {
        $where[] = 't.status = ?';
        $params[] = $status;
    }

    $sql = "SELECT t.*, u.username,
                   (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id) AS messages_count,
                   (SELECT m.created_at FROM support_ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message_at
            FROM support_tickets t
            JOIN users u ON u.id = t.user_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " ORDER BY FIELD(t.status,'open','answered','closed'), COALESCE(t.last_reply_at, t.created_at) DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ['status' => 'success', 'tickets' => $stmt->fetchAll()];
}

function handleGetTicket(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $ticketId = normPositiveInt($data['ticket_id'] ?? 0);
    if ($ticketId <= 0) return ['status' => 'error', 'message' => 'Ticket ID required'];

    $isAdmin = isAdminUser($pdo, (int)$_SESSION['user_id']);
    $stmt = $pdo->prepare('SELECT t.*, u.username, u.email FROM support_tickets t JOIN users u ON u.id=t.user_id WHERE t.id = ? LIMIT 1');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) return ['status' => 'error', 'message' => 'Ticket not found'];
    if (!$isAdmin && (int)$ticket['user_id'] !== (int)$_SESSION['user_id']) return ['status' => 'error', 'message' => 'Access denied'];

    $stmt = $pdo->prepare("SELECT m.*, COALESCE(u.username, 'System') AS username FROM support_ticket_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.id ASC");
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();

    return ['status' => 'success', 'ticket' => $ticket, 'messages' => $messages, 'is_admin' => $isAdmin];
}

function handleReplyTicket(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    if (rateLimit('reply_ticket', 40, 300)) return ['status' => 'error', 'message' => 'Too many replies. Try later'];

    $ticketId = normPositiveInt($data['ticket_id'] ?? 0);
    $message = normText((string)($data['message'] ?? ''), 5000);
    if ($ticketId <= 0 || $message === '') return ['status' => 'error', 'message' => 'Ticket and message required'];

    $isAdmin = isAdminUser($pdo, (int)$_SESSION['user_id']);
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? LIMIT 1');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) return ['status' => 'error', 'message' => 'Ticket not found'];
    if (!$isAdmin && (int)$ticket['user_id'] !== (int)$_SESSION['user_id']) return ['status' => 'error', 'message' => 'Access denied'];
    if (($ticket['status'] ?? '') === 'closed' && !$isAdmin) return ['status' => 'error', 'message' => 'Ticket is closed'];

    $senderRole = $isAdmin ? 'admin' : 'user';
    $nextStatus = $isAdmin ? 'answered' : 'open';

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, user_id, sender_role, message) VALUES (?,?,?,?)')
            ->execute([$ticketId, (int)$_SESSION['user_id'], $senderRole, $message]);
        $pdo->prepare('UPDATE support_tickets SET status = ?, updated_at = NOW(), last_reply_at = NOW() WHERE id = ?')
            ->execute([$nextStatus, $ticketId]);
        $pdo->commit();
        return ['status' => 'success', 'message' => 'Reply sent'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Ticket reply error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Reply failed'];
    }
}

function handleUpdateTicketStatus(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;

    $ticketId = normPositiveInt($data['ticket_id'] ?? 0);
    $status = normEnum(trim((string)($data['status'] ?? '')), ['open', 'answered', 'closed'], '');
    if ($ticketId <= 0 || $status === '') return ['status' => 'error', 'message' => 'Invalid params'];

    $isAdmin = isAdminUser($pdo, (int)$_SESSION['user_id']);
    $stmt = $pdo->prepare('SELECT user_id FROM support_tickets WHERE id = ? LIMIT 1');
    $stmt->execute([$ticketId]);
    $ownerId = $stmt->fetchColumn();
    if (!$ownerId) return ['status' => 'error', 'message' => 'Ticket not found'];
    if (!$isAdmin && ((int)$ownerId !== (int)$_SESSION['user_id'] || $status !== 'closed')) return ['status' => 'error', 'message' => 'Access denied'];

    $pdo->prepare('UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $ticketId]);
    return ['status' => 'success', 'message' => 'Status updated'];
}

function handleAdminGetUsers(PDO $pdo, array $data): array {
    if ($auth = requireAdmin($pdo)) return $auth;

    $search = normText((string)($data['search'] ?? ''), 80);
    $sql = 'SELECT ' . buildUserSelect($pdo, 'u') . ",
                   (SELECT COUNT(*) FROM licenses l WHERE l.user_id=u.id AND l.status='active' AND l.expires_at > NOW()) AS active_licenses,
                   (SELECT COUNT(*) FROM purchases p WHERE p.user_id=u.id) AS purchases_count,
                   (SELECT COUNT(*) FROM support_tickets t WHERE t.user_id=u.id AND t.status <> 'closed') AS open_tickets
            FROM users u";
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE u.username LIKE ? OR u.email LIKE ? OR u.id = ?';
        $params = ['%' . $search . '%', '%' . $search . '%', ctype_digit($search) ? (int)$search : 0];
    }
    $sql .= ' ORDER BY u.id DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ['status' => 'success', 'users' => $stmt->fetchAll()];
}

function handleAdminUpdateUser(PDO $pdo, array $data): array {
    if ($auth = requireAdmin($pdo)) return $auth;

    $userId = normPositiveInt($data['user_id'] ?? 0);
    if ($userId <= 0) return ['status' => 'error', 'message' => 'User ID required'];
    if ($userId === (int)$_SESSION['user_id']) return ['status' => 'error', 'message' => 'You cannot edit yourself here'];

    $stmt = $pdo->prepare('SELECT ' . buildUserSelect($pdo) . ', hwid FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return ['status' => 'error', 'message' => 'User not found'];

    $adminRowStmt = $pdo->prepare('SELECT admin_level FROM users WHERE id = ? LIMIT 1');
    $adminRowStmt->execute([(int)$_SESSION['user_id']]);
    $adminRow = $adminRowStmt->fetch();
    if ((int)($user['admin_level'] ?? 0) >= (int)($adminRow['admin_level'] ?? 0) && (int)($user['admin_level'] ?? 0) > 0) {
        return ['status' => 'error', 'message' => 'Target admin level is too high'];
    }

    $username = normUsername((string)($data['username'] ?? $user['username']));
    $email = normEmail((string)($data['email'] ?? $user['email']));
    $balance = normMoney($data['balance'] ?? $user['balance'], (float)$user['balance']);
    $isAdmin = array_key_exists('is_admin', $data) ? normBool($data['is_admin']) : (int)$user['is_admin'];
    $adminLevel = array_key_exists('admin_level', $data) ? min(10, normPositiveInt($data['admin_level'])) : (int)$user['admin_level'];
    $canBan = array_key_exists('can_ban', $data) ? normBool($data['can_ban']) : (int)$user['can_ban'];
    $isBanned = array_key_exists('is_banned', $data) ? normBool($data['is_banned']) : (int)$user['is_banned'];
    $banReason = normText((string)($data['ban_reason'] ?? ($isBanned ? ((string)($user['ban_reason'] ?? 'Administrative action')) : '')), 500);

    if ($username === '' || $email === '') return ['status' => 'error', 'message' => 'Invalid username or email'];

    $dupe = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
    $dupe->execute([$username, $email, $userId]);
    if ($dupe->fetch()) return ['status' => 'error', 'message' => 'Username or email already taken'];

    $fields = [
        'username' => $username,
        'email' => $email,
        'balance' => $balance,
        'is_admin' => $isAdmin,
        'admin_level' => $adminLevel,
        'can_ban' => $canBan,
        'is_banned' => $isBanned,
        'ban_reason' => $banReason !== '' ? $banReason : null,
    ];
    foreach (['preferred_currency', 'can_manage_users', 'can_manage_tickets', 'can_edit_balance', 'admin_note'] as $optional) {
        if (dbHasColumn($pdo, 'users', $optional) && array_key_exists($optional, $data)) {
            if ($optional === 'preferred_currency') {
                $fields[$optional] = normEnum((string)$data[$optional], ['usd', 'eur', 'rub', 'uah', 'byn', 'ils'], 'usd');
            } elseif (in_array($optional, ['can_manage_users', 'can_manage_tickets', 'can_edit_balance'], true)) {
                $fields[$optional] = normBool($data[$optional]);
            } else {
                $fields[$optional] = normText((string)$data[$optional], 1000) ?: null;
            }
        }
    }

    $set = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $set[] = "{$col} = ?";
        $params[] = $val;
    }
    $params[] = $userId;

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

        if ($isBanned) {
            $exists = $pdo->prepare('SELECT id FROM bans WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1');
            $exists->execute([$userId]);
            if (!$exists->fetch()) {
                $pdo->prepare('INSERT INTO bans (user_id, reason, banned_by, expires_at) VALUES (?,?,?,NULL)')
                    ->execute([$userId, $banReason ?: 'Administrative action', (int)$_SESSION['user_id']]);
                if (!empty($user['hwid'])) {
                    $pdo->prepare('INSERT INTO bans (hwid, reason, banned_by, expires_at) VALUES (?,?,?,NULL)')
                        ->execute([$user['hwid'], $banReason ?: 'Administrative action', (int)$_SESSION['user_id']]);
                }
            }
        } else {
            $pdo->prepare('UPDATE bans SET expires_at = NOW() WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())')->execute([$userId]);
            if (!empty($user['hwid'])) {
                $pdo->prepare('UPDATE bans SET expires_at = NOW() WHERE hwid = ? AND (expires_at IS NULL OR expires_at > NOW())')->execute([$user['hwid']]);
            }
        }

        $pdo->commit();
        return ['status' => 'success', 'message' => 'User updated'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin update user error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'User update failed'];
    }
}

function handleAdminGetDashboard(PDO $pdo, array $data): array {
    if ($auth = requireAdmin($pdo)) return $auth;

    return ['status' => 'success', 'stats' => [
        'users_total' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'users_banned' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn(),
        'licenses_active' => (int)$pdo->query("SELECT COUNT(*) FROM licenses WHERE status='active' AND expires_at > NOW()")->fetchColumn(),
        'tickets_open' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status <> 'closed'")->fetchColumn(),
        'tickets_waiting' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn(),
        'sales_total' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM purchases WHERE status='completed'")->fetchColumn(),
    ]];
}

function getActiveBan(PDO $pdo, ?int $userId = null, string $hwid = ''): array {
    $clauses = [];
    $params = [];

    if ($userId) {
        $clauses[] = 'user_id = ?';
        $params[] = $userId;
    }
    if ($hwid !== '') {
        $clauses[] = 'hwid = ?';
        $params[] = $hwid;
    }
    if (!$clauses) return ['banned' => false];

    $sql = 'SELECT * FROM bans WHERE (' . implode(' OR ', $clauses) . ') AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ban = $stmt->fetch();
    if (!$ban) return ['banned' => false];

    return [
        'banned' => true,
        'reason' => $ban['reason'] ?: 'Administrative action',
        'expires_at' => $ban['expires_at'] ?: null,
        'created_at' => $ban['created_at'] ?: null,
        'hwid' => $ban['hwid'] ?: null,
        'user_id' => $ban['user_id'] ?: null,
    ];
}
// ==================== ОБЛАЧНЫЕ КОНФИГИ ====================

function handleSaveConfig(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    
    $configName = normText((string)($data['config_name'] ?? ''), 100);
    $configData = (string)($data['config_data'] ?? '');
    $slot = normPositiveInt($data['slot'] ?? 0);
    
    if ($configName === '' && $slot <= 0) {
        return ['status' => 'error', 'message' => 'Config name or slot required'];
    }
    if ($configData === '') {
        return ['status' => 'error', 'message' => 'Config data is empty'];
    }
    
    // Лимит: не более 50 конфигов на пользователя
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cloud_configs WHERE user_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    if ((int)$stmt->fetchColumn() >= 50) {
        return ['status' => 'error', 'message' => 'Maximum 50 configs per user'];
    }
    
    // Шифруем данные конфига (простейший XOR, можно заменить на openssl)
    $encrypted = base64_encode($configData ^ str_repeat(chr(0xAA), strlen($configData)));
    
    if ($slot > 0) {
        // Обновляем существующий слот
        $stmt = $pdo->prepare('UPDATE cloud_configs SET config_name = ?, config_data = ?, updated_at = NOW() WHERE user_id = ? AND slot = ?');
        $stmt->execute([$configName, $encrypted, (int)$_SESSION['user_id'], $slot]);
        if ($stmt->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'Config saved to slot ' . $slot];
        }
    }
    
    // Создаём новый конфиг
    $stmt = $pdo->prepare('INSERT INTO cloud_configs (user_id, config_name, config_data, slot) VALUES (?, ?, ?, COALESCE((SELECT MAX(slot) + 1 FROM cloud_configs WHERE user_id = ?), 1))');
    $stmt->execute([(int)$_SESSION['user_id'], $configName, $encrypted, (int)$_SESSION['user_id']]);
    
    return ['status' => 'success', 'message' => 'Config saved', 'config_id' => (int)$pdo->lastInsertId()];
}

function handleLoadConfig(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    
    $configId = normPositiveInt($data['config_id'] ?? 0);
    $slot = normPositiveInt($data['slot'] ?? 0);
    
    if ($configId <= 0 && $slot <= 0) {
        return ['status' => 'error', 'message' => 'Config ID or slot required'];
    }
    
    if ($configId > 0) {
        $stmt = $pdo->prepare('SELECT config_data, config_name FROM cloud_configs WHERE id = ? AND user_id = ?');
        $stmt->execute([$configId, (int)$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT config_data, config_name FROM cloud_configs WHERE user_id = ? AND slot = ?');
        $stmt->execute([(int)$_SESSION['user_id'], $slot]);
    }
    
    $config = $stmt->fetch();
    if (!$config) {
        return ['status' => 'error', 'message' => 'Config not found'];
    }
    
    // Расшифровываем
    $decrypted = base64_decode($config['config_data']) ^ str_repeat(chr(0xAA), strlen(base64_decode($config['config_data'])));
    
    return [
        'status' => 'success',
        'config_name' => $config['config_name'],
        'config_data' => $decrypted
    ];
}

function handleListConfigs(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    
    $stmt = $pdo->prepare('SELECT id, slot, config_name, created_at, updated_at FROM cloud_configs WHERE user_id = ? ORDER BY slot ASC');
    $stmt->execute([(int)$_SESSION['user_id']]);
    
    return ['status' => 'success', 'configs' => $stmt->fetchAll()];
}

function handleDeleteConfig(PDO $pdo, array $data): array {
    if ($auth = requireAuth()) return $auth;
    
    $configId = normPositiveInt($data['config_id'] ?? 0);
    $slot = normPositiveInt($data['slot'] ?? 0);
    
    if ($configId <= 0 && $slot <= 0) {
        return ['status' => 'error', 'message' => 'Config ID or slot required'];
    }
    
    if ($configId > 0) {
        $stmt = $pdo->prepare('DELETE FROM cloud_configs WHERE id = ? AND user_id = ?');
        $stmt->execute([$configId, (int)$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM cloud_configs WHERE user_id = ? AND slot = ?');
        $stmt->execute([(int)$_SESSION['user_id'], $slot]);
    }
    
    return ['status' => 'success', 'message' => 'Config deleted'];
}
function formatBanMessage(array $ban): string {
    $message = 'Вы забанены. Причина: ' . ($ban['reason'] ?: 'Не указана');
    $message .= !empty($ban['expires_at']) ? '. Срок: до ' . $ban['expires_at'] : '. Срок: навсегда';
    $message .= '. По разбану: @sakurov1337';
    return $message;
}

function logAction(PDO $pdo, $userId, $username, string $hwid, string $action): void {
    try {
        $pdo->prepare('INSERT INTO loader_logs (user_id,username,hwid,action,ip_address,user_agent) VALUES (?,?,?,?,?,?)')
            ->execute([$userId, normText((string)$username, 50) ?: null, $hwid ?: null, normText($action, 50) ?: 'action', clientIp(), currentUserAgent()]);
    } catch (Throwable $e) {
        error_log('Loader log error: ' . $e->getMessage());
    }
}

function isAdminUser(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (bool)$row['is_admin'] : false;
}

function createLicense(PDO $pdo, int $userId, string $type, string $name, bool $extend = false): ?string {
    $catalog = productCatalog();
    $days = $catalog[$type]['days'] ?? 30;

    if ($extend) {
        $stmt = $pdo->prepare("SELECT id, expires_at FROM licenses WHERE user_id=? AND product_type=? AND status='active' ORDER BY expires_at DESC LIMIT 1");
        $stmt->execute([$userId, $type]);
        $ex = $stmt->fetch();
        if ($ex) {
            $base = max(strtotime((string)$ex['expires_at']), time());
            $newExp = date('Y-m-d H:i:s', $base + ($days * 86400));
            $pdo->prepare('UPDATE licenses SET expires_at=?, duration_days=duration_days+? WHERE id=?')->execute([$newExp, $days, (int)$ex['id']]);
            return null;
        }
    }

    $key = 'GOD-' . strtoupper(implode('-', array_map(static fn() => substr(bin2hex(random_bytes(4)), 0, 4), range(1, 4))));
    $pdo->prepare("INSERT INTO licenses (license_key,user_id,product_name,product_type,duration_days,expires_at,status) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY),'active')")
        ->execute([$key, $userId, $name, $type, $days, $days]);
    return $key;
}


function shouldShowVideoInsteadOfApi(): bool {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return false;
    if (isset($_GET['action'])) return false;

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '');
    if ($path === '' || !preg_match('#/api\.php$#i', $path)) return false;

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept !== '' && strpos($accept, 'text/html') === false && strpos($accept, '*/*') === false) return false;

    $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xrw === 'xmlhttprequest') return false;
    if (isset($_SERVER['HTTP_ORIGIN']) || isset($_SERVER['HTTP_AUTHORIZATION'])) return false;

    return true;
}

function showHostedVideo(): void {
    $file = __DIR__ . '/videos/prikol.mp4';
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Video not found';
        exit;
    }

    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    header("Content-Security-Policy: default-src 'self'; media-src 'self'; img-src 'self' data:; style-src 'unsafe-inline'; script-src 'none'; frame-ancestors 'none'; base-uri 'none'");

    echo <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>prikol.mp4</title>
<style>
html,body{margin:0;height:100%;background:#000}
body{display:flex;align-items:center;justify-content:center;overflow:hidden}
video{width:100%;height:100%;max-width:100vw;max-height:100vh;background:#000}
</style>
</head>
<body>
<video controls autoplay playsinline preload="metadata" controlsList="nodownload noplaybackrate" disablePictureInPicture>
    <source src="/videos/prikol.mp4" type="video/mp4">
    Твой браузер не поддерживает видео.
</video>
<script>
(() => {
    const stop = e => {
        e.preventDefault();
        e.stopPropagation();
        return false;
    };

    document.addEventListener('contextmenu', stop, {capture:true});
    document.addEventListener('dragstart', stop, {capture:true});
    document.addEventListener('selectstart', stop, {capture:true});
    document.addEventListener('copy', stop, {capture:true});
    document.addEventListener('cut', stop, {capture:true});

    document.addEventListener('keydown', e => {
        const k = (e.key || '').toLowerCase();
        if (
            k === 'f12' ||
            (e.ctrlKey && e.shiftKey && ['i','j','c','k'].includes(k)) ||
            (e.ctrlKey && ['u','s'].includes(k))
        ) {
            return stop(e);
        }
    }, {capture:true});

    const closeDevtools = () => {
        const gapW = window.outerWidth - window.innerWidth;
        const gapH = window.outerHeight - window.innerHeight;
        if (gapW > 160 || gapH > 160) {
            document.body.innerHTML = '';
            location.replace('about:blank');
        }
    };

    setInterval(() => {
        closeDevtools();
        try { console.clear(); } catch (_) {}
    }, 700);
})();
</script>
</body>
</html>
HTML;
    exit;
}
