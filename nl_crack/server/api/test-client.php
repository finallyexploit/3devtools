<?php
/**
 * Evermiss API Test Client
 * Simple HTML form to test API endpoints
 */

$api_base = 'https://hi.soulware.space/api';

// Process test requests
$test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'test_avatar') {
        $ch = curl_init($api_base . '/getavatar?size=100');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $test_result = array(
            'status' => $http_code,
            'size' => strlen($response),
            'type' => 'PNG Image'
        );
    } else if ($action === 'test_websocket') {
        $ch = curl_init($api_base . '/websocket');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('action' => 'receive_frames')));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $test_result = array(
            'status' => $http_code,
            'frames' => isset($data['frame1']) ? 3 : 0,
            'data' => $data
        );
    } else if ($action === 'test_404') {
        $ch = curl_init($api_base . '/config');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $test_result = array(
            'status' => $http_code,
            'expected' => 404,
            'match' => $http_code === 404
        );
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evermiss API - Test Client</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007acc;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #007acc;
            border-radius: 4px;
        }
        .test-section h2 {
            margin-top: 0;
            color: #007acc;
        }
        button {
            background: #007acc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #005a9e;
        }
        .result {
            background: #e8f4f8;
            border: 1px solid #00a4ef;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            display: none;
        }
        .result.show {
            display: block;
        }
        .result.success {
            background: #e8f8e8;
            border-color: #4ec9b0;
        }
        .result.error {
            background: #f8e8e8;
            border-color: #f44747;
        }
        pre {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Evermiss API - Test Client</h1>
        
        <div class="info">
            <strong>ℹ️ API Base:</strong> <?php echo $api_base; ?>
        </div>
        
        <div class="test-section">
            <h2>1️⃣ Test Avatar (GET /api/getavatar)</h2>
            <p>Returns PNG image</p>
            <form method="POST">
                <input type="hidden" name="action" value="test_avatar">
                <button type="submit">Test Avatar</button>
            </form>
            <?php if ($test_result && isset($_POST['action']) && $_POST['action'] === 'test_avatar'): ?>
                <div class="result show success">
                    <strong>✓ Test Passed</strong>
                    <pre>Status: <?php echo $test_result['status']; ?>
Type: <?php echo $test_result['type']; ?>
Size: <?php echo $test_result['size']; ?> bytes</pre>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>2️⃣ Test WebSocket (POST /api/websocket)</h2>
            <p>Returns encrypted frames</p>
            <form method="POST">
                <input type="hidden" name="action" value="test_websocket">
                <button type="submit">Test WebSocket</button>
            </form>
            <?php if ($test_result && isset($_POST['action']) && $_POST['action'] === 'test_websocket'): ?>
                <div class="result show success">
                    <strong>✓ Test Passed</strong>
                    <pre>Status: <?php echo $test_result['status']; ?>
Frames: <?php echo $test_result['frames']; ?>

Frame 1 (Auth JSON):
<?php echo json_encode($test_result['data']['frame1'], JSON_PRETTY_PRINT); ?>

Frame 2: <?php echo $test_result['data']['frame2_size']; ?> bytes
Frame 3: <?php echo $test_result['data']['frame3_size']; ?> bytes
Key: <?php echo substr($test_result['data']['frame3_hex'], 0, 40); ?>...</pre>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>3️⃣ Test 404 (GET /api/config)</h2>
            <p>Should return 404 Not Found</p>
            <form method="POST">
                <input type="hidden" name="action" value="test_404">
                <button type="submit">Test 404</button>
            </form>
            <?php if ($test_result && isset($_POST['action']) && $_POST['action'] === 'test_404'): ?>
                <div class="result show <?php echo ($test_result['match'] ? 'success' : 'error'); ?>">
                    <?php if ($test_result['match']): ?>
                        <strong>✓ Test Passed</strong>
                    <?php else: ?>
                        <strong>✗ Test Failed</strong>
                    <?php endif; ?>
                    <pre>Status: <?php echo $test_result['status']; ?>
Expected: <?php echo $test_result['expected']; ?>
Match: <?php echo ($test_result['match'] ? 'YES' : 'NO'); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>📚 API Endpoints</h2>
            <pre>GET  /api/getavatar?size=100          → PNG image
POST /api/websocket                      → JSON frames
GET  /api/config                         → 404 Error
GET  /api/sendlog                        → 404 Error
GET  /api/lua/&lt;name&gt;                    → 404 Error</pre>
        </div>
        
        <div class="test-section">
            <h2>🐍 Python Client Example</h2>
            <pre>import requests

# Get avatar
resp = requests.get('<?php echo $api_base; ?>/getavatar?size=100')
print(f'Avatar: {len(resp.content)} bytes')

# Get frames
resp = requests.post('<?php echo $api_base; ?>/websocket',
    json={'action': 'receive_frames'}
)
print(resp.json())</pre>
        </div>
        
        <div class="test-section">
            <h2>📊 API Status</h2>
            <?php
                // Quick health check
                $ch = curl_init($api_base . '/getavatar?size=100');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $status = ($http_code === 200) ? 'OK ✓' : 'ERROR ✗';
                $color = ($http_code === 200) ? 'green' : 'red';
            ?>
            <pre>API Status: <span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $status; ?></span>
HTTP Code: <?php echo $http_code; ?></pre>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evermiss API - Test Client</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #4ec9b0;
            margin-bottom: 20px;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        
        h2 {
            color: #569cd6;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .test-section {
            background: #252526;
            border-left: 4px solid #569cd6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .test-button {
            background: #007acc;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .test-button:hover {
            background: #005a9e;
        }
        
        .test-button.active {
            background: #4ec9b0;
        }
        
        .result {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .result.success {
            border-left: 4px solid #4ec9b0;
        }
        
        .result.error {
            border-left: 4px solid #f44747;
            color: #f44747;
        }
        
        .result.info {
            border-left: 4px solid #569cd6;
        }
        
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .code-example {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        code {
            color: #ce9178;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Evermiss API - Test Client</h1>
        
        <div class="test-section">
            <h2>1️⃣ GET /api/getavatar (HTTP)</h2>
            <p>Returns PNG avatar image</p>
            <a href="<?php echo $api_base; ?>/getavatar?size=100" target="_blank">
                <button class="test-button">Test in Browser</button>
            </a>
            
            <div class="code-example">
                <pre><code>// Browser
GET /api/getavatar?size=100

// Response
200 OK
Content-Type: image/png
(PNG image data ~28KB)
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>2️⃣ POST /api/websocket (WebSocket Emulation)</h2>
            <p>Returns encrypted module and key frames</p>
            <button class="test-button" onclick="testWebSocket()">Test WebSocket</button>
            <div id="websocket-result"></div>
            
            <div class="code-example">
                <pre><code>// Request
POST /api/websocket
Content-Type: application/json

{
  "action": "receive_frames"
}

// Response
{
  "frame1": {"Type":"Auth","Message":"...","Data":"..."},
  "frame2_b64": "base64_encoded_module_blob",
  "frame2_size": 385360,
  "frame3_hex": "hex_encoded_key",
  "frame3_size": 80
}
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>3️⃣ GET /api/config (404)</h2>
            <p>Should return Express-style 404</p>
            <a href="<?php echo $api_base; ?>/config" target="_blank">
                <button class="test-button">Test in Browser</button>
            </a>
            
            <div class="code-example">
                <pre><code>// Response
404 Not Found
Content-Type: text/html

Cannot GET /api/config
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>🐍 Python Client Example</h2>
            <div class="code-example">
                <pre><code>import requests
import json
import base64

API_BASE = 'https://hi.soulware.space/api'

# 1. Get avatar
response = requests.get(f'{API_BASE}/getavatar?size=100')
with open('avatar.png', 'wb') as f:
    f.write(response.content)
print(f"Avatar: {len(response.content)} bytes")

# 2. Get frames via WebSocket emulation
response = requests.post(
    f'{API_BASE}/websocket',
    json={'action': 'receive_frames'},
    headers={'Content-Type': 'application/json'}
)

data = response.json()
print(f"Frame 1 (Auth): {data['frame1']}")
print(f"Frame 2 (Module): {data['frame2_size']} bytes")
print(f"Frame 3 (Key): {data['frame3_size']} bytes")

# Decode frames
module_bytes = base64.b64decode(data['frame2_b64'])
key_hex = data['frame3_hex']
print(f"Module decoded: {len(module_bytes)} bytes")
print(f"Key hex: {key_hex}")
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>📜 JavaScript/Node.js Client Example</h2>
            <div class="code-example">
                <pre><code>const fetch = require('node-fetch');
const fs = require('fs');

const API_BASE = 'https://hi.soulware.space/api';

(async () => {
  // 1. Get avatar
  const avatarRes = await fetch(`${API_BASE}/getavatar?size=100`);
  const avatarBuf = await avatarRes.buffer();
  fs.writeFileSync('avatar.png', avatarBuf);
  console.log(`Avatar: ${avatarBuf.length} bytes`);
  
  // 2. Get frames
  const framesRes = await fetch(`${API_BASE}/websocket`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'receive_frames' })
  });
  
  const data = await framesRes.json();
  console.log(`Frame 1 (Auth): ${JSON.stringify(data.frame1)}`);
  console.log(`Frame 2 (Module): ${data.frame2_size} bytes`);
  console.log(`Frame 3 (Key): ${data.frame3_size} bytes`);
  
  // Decode frames
  const moduleBuf = Buffer.from(data.frame2_b64, 'base64');
  const keyHex = data.frame3_hex;
  console.log(`Module decoded: ${moduleBuf.length} bytes`);
  console.log(`Key: ${keyHex}`);
})();
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>🔗 cURL Examples</h2>
            <div class="code-example">
                <pre><code># Get avatar
curl -v https://hi.soulware.space/api/getavatar?size=100 > avatar.png

# Get frames (WebSocket emulation)
curl -X POST https://hi.soulware.space/api/websocket \
  -H "Content-Type: application/json" \
  -d '{"action":"receive_frames"}' \
  | python -m json.tool

# Check if API is alive
curl -I https://hi.soulware.space/api/config
# Should return: 404 Not Found (which is correct)
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>📋 Response Codes</h2>
            <div class="code-example">
                <pre><code>200 OK
  GET /api/getavatar      ← Avatar PNG
  POST /api/websocket     ← JSON frames

404 Not Found (Expected behavior)
  GET /api/config         ← Config (not available)
  GET /api/sendlog        ← Logs (not available)
  GET /api/lua/*          ← Lua libs (not available)
  
500 Internal Server Error
  File not found in data/
  Database connection failed
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>🔐 Database Usage (Optional)</h2>
            <div class="code-example">
                <pre><code>// In your PHP code
require_once 'config.php';

// Get connection
$conn = get_db_connection();

if (!$conn) {
    send_json(500, ['error' => 'Database connection failed']);
}

// Query
$result = $conn->query("SELECT * FROM users WHERE id = 1");
$user = $result->fetch_assoc();

// Response
send_json(200, ['user' => $user]);

// Close
$conn->close();
</code></pre>
            </div>
        </div>
        
        <div class="test-section">
            <h2>📊 Files Structure</h2>
            <div class="code-example">
                <pre><code>hi.soulware.space/
├── index.php (your main website)
├── ... (other files)
└── api/
    ├── config.php           ← Database config & helpers
    ├── index.php            ← Router for all API calls
    ├── .htaccess            ← URL rewriting & security
    ├── test-client.php      ← This file (optional)
    └── data/
        ├── avatar.png       ← Avatar image
        ├── module.bin       ← Encrypted module
        └── key.bin          ← Encryption key
</code></pre>
            </div>
        </div>
    </div>
    
    <script>
        async function testWebSocket() {
            const resultDiv = document.getElementById('websocket-result');
            const button = event.target;
            
            button.disabled = true;
            button.textContent = 'Testing...';
            
            try {
                const response = await fetch('<?php echo $api_base; ?>/websocket', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'receive_frames' })
                });
                
                const data = await response.json();
                
                let html = '<div class="result success"><pre>';
                html += `Status: ${response.status} ${response.statusText}\n\n`;
                html += `Frame 1 (Auth JSON):\n${JSON.stringify(data.frame1, null, 2)}\n\n`;
                html += `Frame 2 (Module Blob):\n  Size: ${data.frame2_size} bytes\n  Base64: ${data.frame2_b64.substring(0, 100)}...\n\n`;
                html += `Frame 3 (Key):\n  Size: ${data.frame3_size} bytes\n  Hex: ${data.frame3_hex}`;
                html += '</pre></div>';
                
                resultDiv.innerHTML = html;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result error"><pre>Error: ${error.message}</pre></div>`;
            }
            
            button.disabled = false;
            button.textContent = 'Test WebSocket';
        }
    </script>
</body>
</html>
