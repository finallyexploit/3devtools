<?php
/**
 * Evermiss API Router (compatible PHP 7.2+)
 * Main entry point for all API requests. Place in /api/index.php
 */

require_once __DIR__ . '/config.php';

// Hide errors to clients; keep logging enabled
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Parse request
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$api_prefix = defined('API_BASE') ? API_BASE : '/api/';
$route = '';
if (strpos($request_uri, $api_prefix) === 0) {
    $route = substr($request_uri, strlen($api_prefix));
    $route = ltrim($route, '/');
}

// Parse query string
parse_str($_SERVER['QUERY_STRING'] ?? '', $params);

// Log request if helper exists
if (function_exists('log_request')) {
    log_request($request_method, $request_uri, $params, null);
}

// Routing
if ($request_method === 'GET' && $route === 'getavatar') {
    handle_getavatar($params);
} elseif ($request_method === 'POST' && $route === 'websocket') {
    handle_websocket_emulation();
} else {
    if (function_exists('send_404')) {
        send_404($request_method, $request_uri);
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        echo 'Not Found';
    }
}

// Handlers
function handle_getavatar($params) {
    if (!defined('AVATAR_FILE')) {
        if (function_exists('send_json')) {
            send_json(500, array('error' => 'Avatar not configured'));
            return;
        }
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        echo 'Avatar not configured';
        return;
    }
    send_file(AVATAR_FILE, 'image/png');
}

function handle_websocket_emulation() {
    $input = @file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data) || !isset($data['action'])) {
        send_json(400, array('error' => 'Missing action'));
    }

    $action = $data['action'];

    if ($action === 'connect') {
        send_json(200, array('status' => 'connected', 'frames' => 3));
    } elseif ($action === 'receive_frames') {
        if (!defined('MODULE_FILE') || !defined('KEY_FILE')) {
            send_json(500, array('error' => 'Data files not configured'));
        }

        $module_blob = @file_get_contents(MODULE_FILE);
        $key_blob = @file_get_contents(KEY_FILE);

        if ($module_blob === false || $key_blob === false) {
            send_json(500, array('error' => 'Missing data files'));
        }

        $auth_frame = array(
            'Type' => 'Auth',
            'Message' => defined('AUTH_MESSAGE') ? AUTH_MESSAGE : '',
            'Data' => defined('AUTH_DATA') ? AUTH_DATA : array()
        );

        $response = array(
            'frame1' => $auth_frame,
            'frame2_size' => strlen($module_blob),
            'frame2_b64' => base64_encode($module_blob),
            'frame3_size' => strlen($key_blob),
            'frame3_hex' => bin2hex($key_blob)
        );

        send_json(200, $response);
    } else {
        send_json(400, array('error' => 'Unknown action'));
    }
}

?>
