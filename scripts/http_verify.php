<?php
// scripts/http_verify.php
$baseUrl = 'http://localhost/New%20folder/';
$endpoints = [
    'index.php',
    'login.php',
    'register.php',
    'forgot-password.php',
    'create-clips.php',
    'my-clips.php',
    'downloads.php',
    'analytics.php',
    'storage.php',
    'obs.php',
    'admin/login.php'
];

echo "Testing HTTP Endpoints via Apache (port 80):\n\n";

foreach ($endpoints as $ep) {
    $url = $baseUrl . $ep;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status = ($code >= 200 && $code < 400) ? "[✓] PASS" : "[✕] FAIL";
    echo "$status $code -> $url\n";
}
