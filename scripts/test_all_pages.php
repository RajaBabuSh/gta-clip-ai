<?php
$cookieFile = sys_get_temp_dir() . '/test_cookie.txt';
@unlink($cookieFile);

// Step 1: GET login.php to obtain session cookie and csrf_token
$ch = curl_init('http://127.0.0.1/New%20folder/login.php');
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_RETURNTRANSFER => true
]);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches);
$csrfToken = $matches[1] ?? '';
echo "CSRF Token acquired: " . substr($csrfToken, 0, 10) . "..." . PHP_EOL;

// Step 2: POST login
$ch = curl_init('http://127.0.0.1/New%20folder/login.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'csrf_token' => $csrfToken,
        'login' => 'user@gtaclip.ai',
        'password' => 'User@12345'
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

echo "Login Response HTTP Code: $httpCode (Redirects to: $location)" . PHP_EOL;

// Step 3: Test all pages
$pages = [
    'dashboard.php' => 'Dashboard',
    'add-video.php' => 'Add New',
    'create-clips.php?video_id=68b543a6-1b1d-4962-a6d1-d2d5dd74b1be' => 'AI Peak Ingestion',
    'live-monitor.php?job_id=600a303f-42f4-4da4-9795-23d043dff5a7' => 'Generated Viral Clips Gallery',
    'my-clips.php' => 'Clips',
    'youtube-studio.php' => 'YouTube Studio',
    'scheduler.php' => 'Automated Upload Scheduler',
    'upload-history.php' => 'YouTube Upload History',
    'downloads.php' => 'Download Center',
    'api-usage.php' => 'API Usage',
    'settings.php' => 'Settings'
];

$allPassed = true;
foreach ($pages as $p => $needle) {
    $ch = curl_init("http://127.0.0.1/New%20folder/$p");
    curl_setopt_array($ch, [
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $pageHtml = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $found = str_contains($pageHtml, $needle);
    $statusText = ($code === 200 && $found) ? "✅ PASS (HTTP 200, '$needle' verified)" : "❌ FAIL (HTTP $code, needle '$needle' not found)";
    if (!$found || $code !== 200) $allPassed = false;
    echo "  - $p -> $statusText" . PHP_EOL;
    curl_close($ch);
}

// Step 4: Verify clips are directly visible in live-monitor.php output
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$latestJobWithClips = $pdo->query("SELECT job_id FROM clips ORDER BY created_at DESC LIMIT 1")->fetchColumn();
$testJobId = $latestJobWithClips ?: 'e01d5f87-e54e-4da2-99fa-ada16e311a42';

$ch = curl_init("http://127.0.0.1/New%20folder/live-monitor.php?job_id=" . urlencode($testJobId));
curl_setopt_array($ch, [
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_RETURNTRANSFER => true
]);
$monitorHtml = curl_exec($ch);
curl_close($ch);

$hasVideoTag = str_contains($monitorHtml, '<video');
$hasClipTitle = str_contains($monitorHtml, 'Cops Had No Idea What Was Coming');
$hasDownloadBtn = str_contains($monitorHtml, 'Download MP4');
$hasZipBtn = str_contains($monitorHtml, 'Download All ZIP');

echo PHP_EOL . "=== LIVE-MONITOR.PHP RESULTS GALLERY VERIFICATION ===" . PHP_EOL;
echo "  - Video player tags (<video>): " . ($hasVideoTag ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "  - Actual Clip 1 Title: " . ($hasClipTitle ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "  - Download MP4 button: " . ($hasDownloadBtn ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "  - Download All ZIP button: " . ($hasZipBtn ? "✅ PASS" : "❌ FAIL") . PHP_EOL;

@unlink($cookieFile);

if ($allPassed && $hasVideoTag && $hasClipTitle) {
    echo PHP_EOL . "🎉 ALL PAGES AND RESULTS GALLERY VERIFIED SUCCESSFULLY!" . PHP_EOL;
}
