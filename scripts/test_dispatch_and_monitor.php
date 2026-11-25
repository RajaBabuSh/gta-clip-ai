<?php
// scripts/test_dispatch_and_monitor.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

$pdo = Database::getConnection();
$video = $pdo->query("SELECT id FROM videos WHERE source_type = 'YOUTUBE' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "1. Dispatching Job via AiWorkerClient::dispatchJob...\n";
$dispatch = AiWorkerClient::dispatchJob($video['id'], 3);
echo "Dispatch Response: " . json_encode($dispatch) . "\n";

$jobId = $dispatch['data']['id'] ?? null;
if (!$jobId) {
    die("Failed to dispatch job\n");
}

echo "\n2. Polling Job $jobId over HTTP...\n";
$start = time();
$maxPoll = 180; // 3 minutes max

while (time() - $start < $maxPoll) {
    $statusData = AiWorkerClient::getJobStatus($jobId);
    $status = $statusData['status'] ?? 'unknown';
    $progress = $statusData['progress_percentage'] ?? 0;
    $step = $statusData['current_step'] ?? '';
    $stage = $statusData['current_stage'] ?? '';
    $rendered = $statusData['clips_rendered_count'] ?? 0;

    echo "[" . date('H:i:s') . "] Status: $status | Progress: $progress% | Stage: $stage | Rendered: $rendered | Step: " . substr($step, 0, 50) . "...\n";

    if (in_array(strtoupper($status), ['COMPLETED', 'PARTIALLY_COMPLETED', 'FAILED'])) {
        echo "\nJob finished with final status: $status!\n";
        break;
    }

    sleep(3);
}

// Check clips generated in MySQL
$clipsCount = (int)$pdo->query("SELECT COUNT(*) FROM clips WHERE job_id = '$jobId'")->fetchColumn();
echo "\nTotal Clips in Database for Job $jobId: $clipsCount\n";

if ($clipsCount > 0) {
    echo "✅ TEST PASSED: AI Video Clipping Pipeline executed and delivered real clips!\n";
} else {
    echo "⚠️ Pipeline finished without clips.\n";
}
