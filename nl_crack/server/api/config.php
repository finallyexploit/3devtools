<?php
/**
 * Evermiss API Configuration
 * Database and server settings
 */

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'goddev_db');
define('DB_PASS', 'g0dDev_1337');
define('DB_NAME', 'goddev_db');

// Server settings
define('API_BASE', '/api');
define('DATA_DIR', __DIR__ . '/data');

// Avatar configuration
define('AVATAR_FILE', DATA_DIR . '/avatar.png');
define('MODULE_FILE', DATA_DIR . '/module.bin');
define('KEY_FILE', DATA_DIR . '/key.bin');

// WebSocket configuration
define('AUTH_MESSAGE', 'fz8XfUGGBvylN7IW');
define('AUTH_DATA', '5aAxpFpna5QqvYMv');

// Spoofed serial
define('SPOOFED_SERIAL', 
  'g6w/cgN2AuDsLw3xrzboM1kbkLy+osvg0Y/j0LJnQf04GHbV8s5V4yReEk1mh3ZA' .
  '2G72fHG3oOh7zlGEfR1nKw717WiwRwsrgSDfJtaTQz14VDDkayLBNV1DaT/qSyx8Fr' .
  'g1nXU0crRu1P/G+EPvH6nWNPYLZdUMIeqVCToEFhJnqiuRoAyypjFNiKnLEMiy5j2' .
  'YvBcLCOC8yC3FPt/GGsvUldBqkmQGkBjIsXsSkut05txVxq7VDx1i9adKE4zalTzNH' .
  'r0Vtd6DTr8aeH8NYHWPGWAsnTBkZlkNuRuhBTtgRTcIKxzGATTN4k8/JaXCpxri7Iq' .
  'sylvZgXQw+5zldLjAHqcAWw3OD5iQn8DtOoon+DrHm3k3FY6wIrCM1FzTdjAIcTvXS' .
  'iWOURHiwA4sJ8ExR4dyBZMydo8aBAYjrRxcD9oDa/VVJT4cZfDkyWvRjI3WMyEajF2' .
  'JhiGcjpjztmD8fyt9C16VXwLfoYuJnrX1/Dv8SZfCU6U2UhwJlxO5mkg+/IctveCd' .
  'xy8IIiXTKwA5vmiEpXRuUu17SCdmJhFLZ+Jr6cTmrob4exSEggGRk6BTaVomOq4I6I' .
  'pkVUBIUVup+4JvWFseL5UkPOQqHIO5Rxnj1jY+PjAWFPeeXSZsP8/ceEnX8J13tfb' .
  '7PAqRSrpQ1Wv/y+OjaqMoPg9PiRE='
);

// Database connection function
function get_db_connection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("DB Connection Error: " . $conn->connect_error);
            return null;
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("DB Exception: " . $e->getMessage());
        return null;
    }
}

// Logging function
function log_request($method, $path, $params = [], $body = null) {
    $timestamp = date('H:i:s');
    error_log("[$timestamp] $method $path");
    
    if (!empty($params)) {
        error_log("  params: " . json_encode($params));
    }
    
    if ($body !== null) {
        if (is_array($body)) {
            error_log("  body: " . json_encode($body));
        } else {
            error_log("  body: $body");
        }
    }
}

// Express-style 404 response
function send_404($method, $path) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Powered-By: Express');
    
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Error</title></head><body><pre>Cannot ' . $method . ' ' . $path . '</pre></body></html>';
    exit;
}

// JSON response helper
function send_json($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('X-Powered-By: Express');
    header('Connection: keep-alive');
    header('Keep-Alive: timeout=5');
    
    echo json_encode($data);
    exit;
}

// Binary file response helper
function send_file($filepath, $content_type = 'application/octet-stream') {
    if (!file_exists($filepath)) {
        send_404($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }
    
    $filesize = filesize($filepath);
    
    http_response_code(200);
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . $filesize);
    header('X-Powered-By: Express');
    header('Connection: keep-alive');
    header('Keep-Alive: timeout=5');
    
    readfile($filepath);
    exit;
}

?>
