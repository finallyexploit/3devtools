<?php
declare(strict_types=1);

// protect by hauntz
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
}

$host = (string)(getenv('GODDEV_DB_HOST') ?: 'localhost');
$dbname = (string)(getenv('GODDEV_DB_NAME') ?: 'goddev_db');
$username = (string)(getenv('GODDEV_DB_USER') ?: 'goddev_db');
$password = (string)(getenv('GODDEV_DB_PASS') ?: 'g0dDev_1337');

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbname);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database error. Contact support.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function isBanned(PDO $pdo, $userId = null, $hwid = null): array {
    if ($userId) {
        $s = $pdo->prepare('SELECT is_banned, ban_reason FROM users WHERE id = ?');
        $s->execute([(int)$userId]);
        $u = $s->fetch();
        if ($u && !empty($u['is_banned'])) return ['banned' => true, 'reason' => (string)($u['ban_reason'] ?? '')];
    }
    if ($hwid) {
        $s = $pdo->prepare('SELECT id FROM bans WHERE hwid = ? AND (expires_at IS NULL OR expires_at > NOW())');
        $s->execute([(string)$hwid]);
        if ($s->fetch()) return ['banned' => true, 'reason' => 'HWID banned'];
    }
    return ['banned' => false];
}
