<?php
// scripts/test_resume_checkpoint.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

$pdo = Database::getConnection();

// Create a simulated job at chunk 2 checkpoint
$jobId = 'test-resume-' . substr(bin2hex(random_bytes(4)), 0, 8);
$video = $pdo->query("SELECT id FROM videos WHERE source_type = 'YOUTUBE' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$moments = [
    [
        'clip_index' => 1,
        'start_time' => 5.0,
        'end_time' => 20.0,
        'duration' => 15.0,
        'virality_score' => 96.0,
        'peak_time' => 10.0,
        'text' => 'Cops chasing',
        'words' => []
    ]
];

$pdo->prepare("
    INSERT INTO processing_jobs (id, video_id, status, progress_percentage, current_stage, current_step, checkpoint_chunk_index, checkpoint_data, clips_total_target, created_at)
    VALUES (?, ?, 'FAILED', 40, 'Whisper Transcription', 'Simulated failure at chunk 2', 2, ?, 5, NOW())
")->execute([$jobId, $video['id'], json_encode($moments)]);

echo "Created simulated failed job: $jobId at checkpoint chunk 2\n";

// Call resume endpoint on FastAPI worker
$url = AiWorkerClient::getWorkerBaseUrl() . '/api/v1/clips/resume/' . urlencode($jobId);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Worker Resume Response (HTTP $code): $resp\n";

// Poll database for 10 seconds to verify it resumed
sleep(3);
$stmt = $pdo->prepare("SELECT id, status, progress_percentage, current_stage, current_step, checkpoint_chunk_index FROM processing_jobs WHERE id = ?");
$stmt->execute([$jobId]);
$resumedJob = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Resumed Job State in MySQL:\n";
print_r($resumedJob);

if ($resumedJob['status'] === 'PROCESSING' || $resumedJob['status'] === 'PENDING' || $resumedJob['status'] === 'COMPLETED' || $resumedJob['status'] === 'PARTIALLY_COMPLETED') {
    echo "✓ RESUME FROM CHECKPOINT TEST PASSED!\n";
} else {
    echo "✗ RESUME TEST FAILED!\n";
}

// Clean up test job
$pdo->prepare("DELETE FROM processing_jobs WHERE id = ?")->execute([$jobId]);
