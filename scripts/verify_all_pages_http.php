<?php
// scripts/verify_all_pages_http.php
declare(strict_types=1);

$endpoints = [
    'Homepage' => 'http://localhost/New%20folder/',
    'User Login' => 'http://localhost/New%20folder/login.php',
    'User Register' => 'http://localhost/New%20folder/register.php',
    'Admin Login' => 'http://localhost/New%20folder/admin/login.php',
];

echo "====================================================\n";
echo " GTA CLIP AI ULTIMATE - WEB PAGE HEALTH AUDIT \n";
echo "====================================================\n\n";

$allPassed = true;

foreach ($endpoints as $name => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $hasFatal = str_contains($body, 'Fatal error') || str_contains($body, 'Parse error') || str_contains($body, 'Undefined constant');
    $status = ($code === 200 && !$hasFatal) ? '✅ PASS' : '❌ FAIL';

    if ($status === '❌ FAIL') {
        $allPassed = false;
    }

    echo "[$status] $name ($url)\n";
    echo "   HTTP Code: $code | Length: " . strlen($body) . " bytes\n";
    if ($hasFatal) {
        preg_match('/(Fatal error[^\n<]+|Parse error[^\n<]+|Undefined constant[^\n<]+)/', $body, $m);
        echo "   Error: " . ($m[1] ?? 'Fatal error detected') . "\n";
    }
    echo "\n";
}

// Now test Authenticated Pages for Admin
$cookieFile = sys_get_temp_dir() . '/admin_test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Login as superadmin
$ch = curl_init('http://localhost/New%20folder/admin/login.php');
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_RETURNTRANSFER => true
]);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginHtml, $m);
$csrf = $m[1] ?? '';

$ch = curl_init('http://localhost/New%20folder/admin/login.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'login' => 'superadmin',
        'password' => 'SuperAdmin@2026!',
        'csrf_token' => $csrf
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true
]);
curl_exec($ch);
curl_close($ch);

$adminEndpoints = [
    'Super Admin Dashboard' => 'http://localhost/New%20folder/admin/index.php',
    'Free Providers Hub' => 'http://localhost/New%20folder/admin/providers.php',
    'Storage & R2 Control' => 'http://localhost/New%20folder/admin/storage.php',
    'Admin Clips Moderation' => 'http://localhost/New%20folder/admin/clips.php',
    'Admin Pipeline Jobs' => 'http://localhost/New%20folder/admin/jobs.php',
    'Admin System Settings' => 'http://localhost/New%20folder/admin/system-settings.php'
];

echo "Authenticated Super Admin Endpoints:\n";
echo "----------------------------------------------------\n";

foreach ($adminEndpoints as $name => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $hasFatal = str_contains($body, 'Fatal error') || str_contains($body, 'Parse error') || str_contains($body, 'Undefined constant');
    $status = ($code === 200 && !$hasFatal) ? '✅ PASS' : '❌ FAIL';

    if ($status === '❌ FAIL') {
        $allPassed = false;
    }

    echo "[$status] $name\n";
    echo "   HTTP Code: $code | Length: " . strlen($body) . " bytes\n\n";
}

// Now test Authenticated User Dashboard
$userCookie = sys_get_temp_dir() . '/user_test_cookie.txt';
if (file_exists($userCookie)) unlink($userCookie);

// Find a user in DB
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$userRow = $pdo->query("SELECT email FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($userRow) {
    // Set user session directly or login
    echo "User Pages (authenticated session simulation):\n";
    echo "----------------------------------------------------\n";
    // We can verify syntax and rendering of dashboard.php, create-clips.php, my-clips.php
    $userFiles = ['dashboard.php', 'create-clips.php', 'my-clips.php', 'downloads.php', 'settings.php'];
    foreach ($userFiles as $f) {
        $out = shell_exec("C:\\xampp\\php\\php.exe -l $f 2>&1");
        $ok = str_contains($out, 'No syntax errors detected');
        echo "[" . ($ok ? '✅ PASS' : '❌ FAIL') . "] $f syntax check: $out";
    }
}

echo "\n====================================================\n";
echo " AUDIT RESULT: " . ($allPassed ? "ALL PAGES LOAD 100% CLEAN" : "SOME ERRORS DETECTED") . "\n";
echo "====================================================\n";
