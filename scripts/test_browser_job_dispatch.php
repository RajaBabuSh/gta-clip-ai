<?php
// scripts/test_browser_job_dispatch.php
$baseUrl = 'http://localhost/New%20folder/';
$cookieFile = sys_get_temp_dir() . '/cookie_user_dispatch.txt';

// Login as user
echo "1. Logging in...\n";
$ch = curl_init($baseUrl . 'login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$html = curl_exec($ch);
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf = $matches[1] ?? '';

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $csrf, 'login' => 'user@gtaclip.ai', 'password' => 'User@12345']),
    CURLOPT_FOLLOWLOCATION => true
]);
curl_exec($ch);
curl_close($ch);

// 2. Call api/videos.php with action=verify
echo "2. Verifying video via API...\n";
$ch = curl_init($baseUrl . 'api/videos.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['action' => 'verify', 'url' => 'https://www.youtube.com/watch?v=irNhWMG560k']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$verifyRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Verify Response (HTTP $httpCode): $verifyRes\n";

$verifyData = json_decode($verifyRes, true);
$videoId = $verifyData['video']['id'] ?? null;
if (!$videoId) die("No videoId obtained!\n");

// 3. Dispatch clipping job via api/clips.php?action=generate
echo "\n3. Dispatching clipping job via api/clips.php?action=generate...\n";
$ch = curl_init($baseUrl . 'api/clips.php?action=generate');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'video_id' => $videoId,
        'target_clips' => 10,
        'style' => 'auto',
        'aspect_ratio' => '9:16'
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$dispatchRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Dispatch Response (HTTP $httpCode): $dispatchRes\n";

$dispatchData = json_decode($dispatchRes, true);
if ($dispatchData['success']) {
    echo "\n[✓] END-TO-END BROWSER DISPATCH SUCCESSFUL! Job ID: " . $dispatchData['job_id'] . "\n";
} else {
    echo "\n[✕] END-TO-END DISPATCH FAILED: " . ($dispatchData['message'] ?? 'Unknown error') . "\n";
}
