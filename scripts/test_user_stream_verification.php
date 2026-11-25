<?php
// scripts/test_user_stream_verification.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

echo "=== VERIFYING YOUTUBE STREAM VERIFICATION & RE-ROUTING FLOW ===\n\n";

// 1. Worker Health
$health = AiWorkerClient::checkHealth();
echo "[1] Worker Health: " . ($health['online'] ? "ONLINE (HTTP 200)" : "OFFLINE") . "\n";

// 2. Test URL Parsing & Verification
$testUrl = "https://www.youtube.com/live/irNhWMG560k?si=rEmqQMOICANxtEh6";
echo "[2] Testing URL: $testUrl\n";

$result = AiWorkerClient::verifyVideo($testUrl);
echo "    Verification Success: " . ($result['success'] ? "YES" : "NO") . "\n";
if (!empty($result['data'])) {
    echo "    Title: " . ($result['data']['title'] ?? 'N/A') . "\n";
    echo "    Channel: " . ($result['data']['channel_title'] ?? 'N/A') . "\n";
    echo "    YouTube ID: " . ($result['data']['youtube_id'] ?? 'N/A') . "\n";
}

// 3. Test HTTP API Endpoint directly
$ch = curl_init('http://localhost/New%20folder/api/videos.php?action=verify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['url' => $testUrl]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "[3] HTTP api/videos.php?action=verify Status: $code\n";
$json = json_decode($resp, true);
echo "    API Success: " . (($json['success'] ?? false) ? "YES" : "NO") . "\n";
echo "    API Message: " . ($json['message'] ?? 'None') . "\n";

// 4. Test Duplicated Path Redirect
$ch2 = curl_init('http://localhost/New%20folder/New%20folder/dashboard.php');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false
]);
curl_exec($ch2);
$redirectCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch2, CURLINFO_REDIRECT_URL);
curl_close($ch2);

echo "[4] Duplicated Path Redirect Check:\n";
echo "    Status Code: $redirectCode (Expected: 308 or 301)\n";
echo "    Redirect Destination: $redirectUrl\n";

echo "\n=== ALL VERIFICATION CHECKS COMPLETED ===\n";
