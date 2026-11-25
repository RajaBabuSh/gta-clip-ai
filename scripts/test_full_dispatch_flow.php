<?php
// scripts/test_full_dispatch_flow.php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/Job.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

echo "1. Testing YouTube Verification...\n";
$url = 'https://www.youtube.com/watch?v=irNhWMG560k';
$workerResult = AiWorkerClient::verifyVideo($url);
print_r($workerResult);

if (!$workerResult['success'] || empty($workerResult['data'])) {
    die("Verification failed!\n");
}

$data = $workerResult['data'];
echo "\n2. Finding or creating video in MySQL...\n";
$existing = Video::findByYoutubeId($data['youtube_id']);
if ($existing) {
    echo "Found existing video: " . $existing['id'] . " (" . $existing['title'] . ")\n";
    $videoId = $existing['id'];
} else {
    $videoId = Video::create([
        'title' => $data['title'],
        'channel_title' => $data['channel_title'],
        'source_type' => 'YOUTUBE',
        'youtube_id' => $data['youtube_id'],
        'youtube_url' => $data['youtube_url'],
        'duration_seconds' => $data['duration_seconds'] ?? 0,
        'thumbnail_url' => $data['thumbnail_url'],
        'status' => 'ready'
    ]);
    echo "Created video in MySQL with ID: $videoId\n";
}

echo "\n3. Testing Job Dispatch to AI Worker...\n";
$dispatch = AiWorkerClient::dispatchJob($videoId, 10, 'auto', '9:16');
print_r($dispatch);

$jobId = $dispatch['data']['id'] ?? $dispatch['data']['job_id'] ?? null;
if ($dispatch['success'] && !empty($jobId)) {
    echo "\n[✓] Job successfully dispatched! Job ID: $jobId\n";
    
    // Check in MySQL
    $job = Job::findById($jobId);
    if ($job) {
        echo "[✓] Job record verified in MySQL processing_jobs table!\n";
        echo "    Status: " . $job['status'] . "\n";
        echo "    Step: " . $job['current_step'] . "\n";
    } else {
        echo "[!] Job not found in MySQL\n";
    }
} else {
    echo "\n[✕] Job dispatch failed!\n";
}
