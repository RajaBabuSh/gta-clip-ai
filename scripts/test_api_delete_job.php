<?php
// scripts/test_api_delete_job.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

$pdo = Database::getConnection();

echo "--- Testing api/jobs.php?action=delete via HTTP ---\n";

// 1. Fetch user credentials
$user = $pdo->query("SELECT id, email FROM users WHERE email = 'user@gtaclip.ai' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("No user found\n");
}

// 2. Fetch or create a test video
$video = $pdo->query("SELECT id FROM videos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$videoId = $video ? $video['id'] : null;
if (!$videoId) {
    $videoId = generate_uuid();
    $pdo->prepare("INSERT INTO videos (id, user_id, title, status) VALUES (?, ?, 'Test Video', 'ready')")->execute([$videoId, $user['id']]);
}

// 2. Create a test job for this user
$jobId = generate_uuid();
$pdo->prepare("
    INSERT INTO processing_jobs (id, video_id, user_id, status, progress_percentage, clips_total_target, clips_rendered_count, created_at)
    VALUES (?, ?, ?, 'COMPLETED', 100, 1, 0, NOW())
")->execute([$jobId, $videoId, $user['id']]);

echo "Created test job: $jobId for user {$user['email']}\n";

// 3. Login session via cURL cookie jar
$cookieJar = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieJar)) @unlink($cookieJar);

// Fetch CSRF token from login page
$ch = curl_init('http://localhost/New%20folder/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
]);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches);
$csrfToken = $matches[1] ?? '';

// Perform Login
$ch = curl_init('http://localhost/New%20folder/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'csrf_token' => $csrfToken,
        'login' => 'user@gtaclip.ai',
        'password' => 'User@12345',
    ]),
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_FOLLOWLOCATION => true,
]);
$loginResp = curl_exec($ch);
curl_close($ch);

// Extract CSRF token from dashboard
preg_match('/csrfToken\s*=\s*\'([^\']+)\'/', $loginResp, $dashMatches);
$dashCsrf = $dashMatches[1] ?? $csrfToken;

// 4. Send DELETE request to api/jobs.php?action=delete
$ch = curl_init('http://localhost/New%20folder/api/jobs.php?action=delete');
$payload = json_encode([
    'job_id' => $jobId,
    'csrf_token' => $dashCsrf
]);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $dashCsrf,
        'X-Requested-With: XMLHttpRequest'
    ],
    CURLOPT_COOKIEFILE => $cookieJar,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $resp\n";

// 5. Verify DB row is gone
$check = $pdo->prepare("SELECT COUNT(*) FROM processing_jobs WHERE id = ?");
$check->execute([$jobId]);
$exists = (int)$check->fetchColumn();

echo "Job exists in DB after API call: " . ($exists === 0 ? "✓ NO (Successfully deleted)" : "✗ YES (Failed)") . "\n";

if (file_exists($cookieJar)) @unlink($cookieJar);

if ($httpCode === 200 && $exists === 0) {
    echo "✓ API DELETE JOB TEST PASSED!\n";
} else {
    echo "✗ API DELETE JOB TEST FAILED!\n";
}
