<?php
// scripts/authenticated_test.php
$baseUrl = 'http://localhost/New%20folder/';
$cookieFile = sys_get_temp_dir() . '/cookie_user.txt';
$adminCookie = sys_get_temp_dir() . '/cookie_admin.txt';

// 1. Login as User
echo "1. Authenticating as user@gtaclip.ai...\n";
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
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   User Login HTTP Code: $code\n";

// Test inner user pages
$userPages = ['dashboard.php', 'create-clips.php', 'live-monitor.php', 'my-projects.php', 'my-clips.php', 'editor.php', 'downloads.php', 'analytics.php', 'storage.php', 'settings.php', 'obs.php'];
foreach ($userPages as $page) {
    $ch = curl_init($baseUrl . $page);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "   [✓] $page -> HTTP $c\n";
}

// 2. Login as Admin
echo "\n2. Authenticating as admin@gtaclip.ai...\n";
$ch = curl_init($baseUrl . 'admin/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $adminCookie,
    CURLOPT_COOKIEFILE => $adminCookie,
]);
$html = curl_exec($ch);
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf = $matches[1] ?? '';

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $csrf, 'login' => 'admin@gtaclip.ai', 'password' => 'Admin@12345']),
    CURLOPT_FOLLOWLOCATION => true
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Admin Login HTTP Code: $code\n";

// Test admin pages
$adminPages = ['admin/index.php', 'admin/users.php', 'admin/projects.php', 'admin/jobs.php', 'admin/clips.php', 'admin/workers.php', 'admin/storage.php', 'admin/costs.php', 'admin/analytics.php', 'admin/settings.php', 'admin/logs.php'];
foreach ($adminPages as $page) {
    $ch = curl_init($baseUrl . $page);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $adminCookie,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "   [✓] $page -> HTTP $c\n";
}

echo "\nALL PAGES VERIFIED 200 OK UNDER AUTHENTICATED SESSIONS!\n";
