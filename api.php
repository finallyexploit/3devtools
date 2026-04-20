<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// api.php — GOD DEV Backend
header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://soulware.space', 'https://www.soulware.space'];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

// ─── Читаем input ─────────────────────────────────────
$jsonRaw = file_get_contents('php://input');
$jsonInput = json_decode($jsonRaw, true);

// Лоадер шлёт JSON, сайт шлёт form или GET
$input = [];
if (is_array($jsonInput) && !empty($jsonInput)) {
    $input = $jsonInput;
} elseif (!empty($_POST)) {
    $input = $_POST;
}

// action может быть в JSON body, в POST или в GET
$action = $input['action'] ?? $_GET['action'] ?? '';

$response = ['status' => 'error', 'message' => 'Unknown action'];

// GET-запросы
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_license_info':      $response = handleGetLicenseInfo($pdo, $_GET); break;
        case 'check_subscription':    $response = handleCheckSubscription($pdo, $_GET); break;
        case 'get_user_info':         $response = handleGetUserInfo($pdo, []); break;
        default: $response = ['status' => 'error', 'message' => 'Invalid action'];
    }
// POST + JSON (лоадер) + form POST (сайт)
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($action)) {
    switch ($action) {
        case 'login':            $response = handleLogin($pdo, $input);           break;
        case 'register':         $response = handleRegister($pdo, $input);        break;
        case 'update_profile':   $response = handleUpdateProfile($pdo, $input);   break;
        case 'update_avatar':    $response = handleUpdateAvatar($pdo);            break;
        case 'change_password':  $response = handleChangePassword($pdo, $input);  break;
        case 'buy':              $response = handleBuy($pdo, $input);             break;
        case 'buy_extend':       $response = handleBuyExtend($pdo, $input);       break;
        case 'logout':           $response = handleLogout();                      break;
        case 'check_license':    $response = handleCheckLicense($pdo, $input);   break;
        case 'activate_license': $response = handleActivateLicense($pdo, $input);break;
        case 'get_user_info':    $response = handleGetUserInfo($pdo, $input);     break;
        case 'ban_user':         $response = handleBanUser($pdo, $input);         break;
        case 'delete_account':   $response = handleDeleteAccount($pdo);           break;
        default: $response = ['status' => 'error', 'message' => 'Invalid action: ' . $action];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ════════════════════════════════════════════════════════
//  HANDLERS
// ════════════════════════════════════════════════════════

function handleLogin($pdo, $data) {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $hwid     = trim($data['hwid'] ?? '');

    if (empty($username) || empty($password))
        return ['status' => 'error', 'message' => 'Username and password required'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        logAction($pdo, null, $username, $hwid, 'failed_login');
        return ['status' => 'error', 'message' => 'Invalid credentials'];
    }

    $ban = isBanned($pdo, $user['id'], $hwid);
    if ($ban['banned'])
        return ['status' => 'error', 'message' => 'Banned: ' . $ban['reason']];

    // HWID lock
    if ($hwid && empty($user['hwid'])) {
        $pdo->prepare("UPDATE users SET hwid = ? WHERE id = ?")->execute([$hwid, $user['id']]);
    } elseif ($hwid && $user['hwid'] && $user['hwid'] !== $hwid) {
        return ['status' => 'error', 'message' => 'HWID mismatch. Account locked to another device.'];
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    logAction($pdo, $user['id'], $user['username'], $hwid, 'login');

    // Активная лицензия для лоадера
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $lic = $stmt->fetch();

    return [
        'status'       => 'success',
        'message'      => 'Login successful',
        'username'     => $user['username'],
        'user_id'      => $user['id'],
        'balance'      => $user['balance'],
        'is_admin'     => (bool)$user['is_admin'],
        'admin_level'  => (int)$user['admin_level'],
        'can_ban'      => (bool)$user['can_ban'],
        'has_license'  => $lic ? true : false,
        'license_type' => $lic ? $lic['product_type'] : null,
        'expires_at'   => $lic ? $lic['expires_at'] : null,
    ];
}

function handleRegister($pdo, $data) {
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email']    ?? '');
    $password = $data['password']      ?? '';

    if (empty($username) || empty($email) || empty($password))
        return ['status' => 'error', 'message' => 'All fields required'];
    if (strlen($username) < 3 || strlen($username) > 50)
        return ['status' => 'error', 'message' => 'Username: 3–50 characters'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return ['status' => 'error', 'message' => 'Invalid email'];
    if (strlen($password) < 6)
        return ['status' => 'error', 'message' => 'Password: min 6 characters'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch())
        return ['status' => 'error', 'message' => 'Username or email already taken'];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, email, password, balance) VALUES (?,?,?,0)")->execute([$username, $email, $hash]);

    $_SESSION['user_id']  = $pdo->lastInsertId();
    $_SESSION['username'] = $username;
    return ['status' => 'success', 'message' => 'Registration successful'];
}

function handleUpdateProfile($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];

    $nickname = trim($data['nickname'] ?? '');
    if (empty($nickname))
        return ['status' => 'error', 'message' => 'Nickname required'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$nickname, $_SESSION['user_id']]);
    if ($stmt->fetch())
        return ['status' => 'error', 'message' => 'Username already taken'];

    $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$nickname, $_SESSION['user_id']]);
    $_SESSION['username'] = $nickname;
    return ['status' => 'success', 'message' => 'Saved'];
}

function handleUpdateAvatar($pdo) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK)
        return ['status' => 'error', 'message' => 'No file uploaded'];

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES['avatar']['size'] > $maxBytes)
        return ['status' => 'error', 'message' => 'Max file size: 2 MB'];

    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed))
        return ['status' => 'error', 'message' => 'Only JPG/PNG/GIF/WEBP allowed'];

    $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $dir      = __DIR__ . '/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Удаляем старый
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $old = $stmt->fetch();
    if ($old && $old['avatar'] && $old['avatar'] !== 'default.png') {
        $op = $dir . $old['avatar'];
        if (file_exists($op)) unlink($op);
    }

    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $filename))
        return ['status' => 'error', 'message' => 'Upload failed'];

    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$filename, $_SESSION['user_id']]);
    return ['status' => 'success', 'filename' => $filename];
}

function handleChangePassword($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];

    $old  = $data['old_password']     ?? '';
    $new  = $data['new_password']     ?? '';
    $conf = $data['confirm_password'] ?? '';

    if (empty($old) || empty($new) || empty($conf))
        return ['status' => 'error', 'message' => 'All fields required'];
    if ($new !== $conf)
        return ['status' => 'error', 'message' => 'Passwords do not match'];
    if (strlen($new) < 6)
        return ['status' => 'error', 'message' => 'Min 6 characters'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!password_verify($old, $u['password']))
        return ['status' => 'error', 'message' => 'Wrong current password'];

    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
    return ['status' => 'success', 'message' => 'Password changed'];
}

function handleBuy($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];

    $price = (float)($data['price'] ?? 0);
    [$type, $name] = resolveProduct($price, $data['product_type'] ?? '', $data['product_name'] ?? '');

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u['balance'] < $price)
        return ['status' => 'error', 'message' => 'Insufficient balance'];

    // Проверка активной подписки того же типа
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE user_id=? AND product_type=? AND status='active' AND expires_at>NOW()");
    $stmt->execute([$_SESSION['user_id'], $type]);
    if ((int)$stmt->fetchColumn() > 0)
        return ['status' => 'has_active', 'message' => 'Active subscription exists', 'product_type' => $type, 'product_name' => $name, 'price' => $price];

    return processPurchase($pdo, $_SESSION['user_id'], $price, $type, $name, false);
}

function handleBuyExtend($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];

    $price  = (float)($data['price'] ?? 0);
    $extend = ($data['extend'] ?? 'new') === 'extend';
    [$type, $name] = resolveProduct($price, $data['product_type'] ?? '', $data['product_name'] ?? '');

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u['balance'] < $price)
        return ['status' => 'error', 'message' => 'Insufficient balance'];

    return processPurchase($pdo, $_SESSION['user_id'], $price, $type, $name, $extend);
}

function resolveProduct($price, $type, $name) {
    if (empty($type)) {
        if ($price == 9)       { $type = 'starter';  $name = 'GOD DEV Starter (7 days)';   }
        elseif ($price == 15)  { $type = 'standard'; $name = 'GOD DEV Standard (30 days)'; }
        elseif ($price == 40)  { $type = 'pro';      $name = 'GOD DEV Pro (90 days)';      }
        else                   { $type = 'standard'; $name = 'GOD DEV License';             }
    }
    if (empty($name)) $name = 'GOD DEV ' . ucfirst($type);
    return [$type, $name];
}

function processPurchase($pdo, $userId, $price, $type, $name, $extend) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$price, $userId]);
        $key = createLicense($pdo, $userId, $type, $name, $extend);
        $pdo->prepare("INSERT INTO purchases (user_id, product_name, amount, status) VALUES (?,?,?,'completed')")->execute([$userId, $name, $price]);
        $pdo->commit();
        return ['status' => 'success', 'message' => $extend ? 'Extended!' : 'Purchase successful', 'license_key' => $key, 'extended' => $extend];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
}

function handleLogout() {
    session_destroy();
    return ['status' => 'success', 'message' => 'Logged out'];
}

function handleCheckLicense($pdo, $data) {
    $key  = trim($data['license_key'] ?? '');
    $hwid = trim($data['hwid']        ?? '');
    if (empty($key))
        return ['status' => 'error', 'message' => 'License key required'];

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND status = 'active' AND expires_at > NOW()");
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    if (!$lic)
        return ['status' => 'error', 'message' => 'Invalid or expired license'];
    if ($lic['hwid_locked'] && $lic['hwid_locked'] !== $hwid)
        return ['status' => 'error', 'message' => 'License locked to another HWID'];

    $pdo->prepare("UPDATE licenses SET last_used = NOW() WHERE id = ?")->execute([$lic['id']]);
    return ['status' => 'success', 'product_name' => $lic['product_name'], 'product_type' => $lic['product_type'], 'expires_at' => $lic['expires_at']];
}

function handleActivateLicense($pdo, $data) {
    $key    = trim($data['license_key'] ?? '');
    $hwid   = trim($data['hwid']        ?? '');
    $userId = $_SESSION['user_id']       ?? null;
    if (empty($key))
        return ['status' => 'error', 'message' => 'License key required'];

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND status = 'active' AND expires_at > NOW()");
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    if (!$lic) return ['status' => 'error', 'message' => 'Invalid or expired license'];
    if ($lic['user_id'] && $userId && $lic['user_id'] != $userId)
        return ['status' => 'error', 'message' => 'License belongs to another user'];

    $pdo->prepare("UPDATE licenses SET user_id=?, hwid_locked=?, activated_at=NOW() WHERE id=?")->execute([$userId, $hwid, $lic['id']]);
    return ['status' => 'success', 'message' => 'Activated'];
}

function handleGetUserInfo($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];

    $stmt = $pdo->prepare("SELECT id,username,email,balance,avatar,is_admin,admin_level,can_ban,created_at FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $licenses = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll();

    return ['status' => 'success', 'user' => $user, 'licenses' => $licenses, 'purchases' => $purchases];
}

function handleCheckSubscription($pdo, $data) {
    $uid  = $_SESSION['user_id']      ?? null;
    $type = $data['product_type']     ?? '';
    if (!$uid || empty($type))
        return ['status' => 'error', 'message' => 'Missing params'];
    $stmt = $pdo->prepare("SELECT id FROM licenses WHERE user_id=? AND product_type=? AND status='active' AND expires_at>NOW()");
    $stmt->execute([$uid, $type]);
    return ['status' => 'success', 'has_active' => (bool)$stmt->fetch()];
}

function handleBanUser($pdo, $data) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];
    $stmt = $pdo->prepare("SELECT can_ban FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    if (!$admin['can_ban'])
        return ['status' => 'error', 'message' => 'No permission'];

    $userId  = $data['user_id'] ?? null;
    $hwid    = $data['hwid']    ?? null;
    $reason  = $data['reason']  ?? 'No reason';
    $dur     = isset($data['duration']) ? (int)$data['duration'] : null;
    $expires = $dur ? date('Y-m-d H:i:s', strtotime("+{$dur} days")) : null;

    if ($userId) {
        $pdo->prepare("UPDATE users SET is_banned=1,ban_reason=? WHERE id=?")->execute([$reason, $userId]);
        $pdo->prepare("INSERT INTO bans (user_id,reason,banned_by,expires_at) VALUES (?,?,?,?)")->execute([$userId, $reason, $_SESSION['user_id'], $expires]);
    }
    if ($hwid) {
        $pdo->prepare("INSERT INTO bans (hwid,reason,banned_by,expires_at) VALUES (?,?,?,?)")->execute([$hwid, $reason, $_SESSION['user_id'], $expires]);
    }
    return ['status' => 'success', 'message' => 'Banned'];
}

function handleDeleteAccount($pdo) {
    if (!isset($_SESSION['user_id']))
        return ['status' => 'error', 'message' => 'Not authenticated'];
    $uid = $_SESSION['user_id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if ($u && $u['avatar'] && $u['avatar'] !== 'default.png') {
            $p = __DIR__ . '/uploads/' . $u['avatar'];
            if (file_exists($p)) unlink($p);
        }
        foreach (["DELETE FROM purchases WHERE user_id=?", "UPDATE licenses SET user_id=NULL WHERE user_id=?", "DELETE FROM user_sessions WHERE user_id=?", "DELETE FROM loader_logs WHERE user_id=?", "DELETE FROM bans WHERE user_id=?", "DELETE FROM users WHERE id=?"] as $q)
            $pdo->prepare($q)->execute([$uid]);
        $pdo->commit();
        session_destroy();
        return ['status' => 'success', 'message' => 'Account deleted'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function handleGetLicenseInfo($pdo, $data) {
    $key = trim($data['license_key'] ?? '');
    if (empty($key)) return ['status' => 'error', 'message' => 'Key required'];
    $stmt = $pdo->prepare("SELECT license_key,product_name,product_type,expires_at,status,activated_at FROM licenses WHERE license_key=?");
    $stmt->execute([$key]);
    $lic = $stmt->fetch();
    return $lic ? ['status' => 'success', 'license' => $lic] : ['status' => 'error', 'message' => 'Not found'];
}

// ════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════

function logAction($pdo, $userId, $username, $hwid, $action) {
    try {
        $pdo->prepare("INSERT INTO loader_logs (user_id,username,hwid,action,ip_address,user_agent) VALUES (?,?,?,?,?,?)")
            ->execute([$userId, $username, $hwid, $action, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Exception $e) {}
}

function createLicense($pdo, $userId, $type, $name, $extend = false) {
    $durations = ['starter' => 7, 'standard' => 30, 'pro' => 90];
    $days = $durations[$type] ?? 30;

    if ($extend) {
        $stmt = $pdo->prepare("SELECT id, expires_at FROM licenses WHERE user_id=? AND product_type=? AND status='active' ORDER BY expires_at DESC LIMIT 1");
        $stmt->execute([$userId, $type]);
        $ex = $stmt->fetch();
        if ($ex) {
            $base = max(strtotime($ex['expires_at']), time());
            $newExp = date('Y-m-d H:i:s', $base + $days * 86400);
            $pdo->prepare("UPDATE licenses SET expires_at=?, duration_days=duration_days+? WHERE id=?")->execute([$newExp, $days, $ex['id']]);
            return null;
        }
    }

    $key = 'GOD-' . strtoupper(implode('-', array_map(fn() => substr(bin2hex(random_bytes(4)), 0, 4), range(1, 4))));
    $pdo->prepare("INSERT INTO licenses (license_key,user_id,product_name,product_type,duration_days,expires_at,status) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY),'active')")
        ->execute([$key, $userId, $name, $type, $days, $days]);
    return $key;
}
?>
