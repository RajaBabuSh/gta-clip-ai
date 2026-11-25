<?php
// scripts/test_delete_job.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Job.php';
require_once __DIR__ . '/../app/models/Clip.php';

$pdo = Database::getConnection();

echo "====================================================\n";
echo "TESTING JOB DELETION SYSTEM\n";
echo "====================================================\n\n";

// 1. Get or create a test user
$userStmt = $pdo->query("SELECT id FROM users LIMIT 1");
$userId = $userStmt->fetchColumn();
if (!$userId) {
    die("Error: No user found in database.\n");
}
$otherUserStmt = $pdo->prepare("SELECT id FROM users WHERE id != ? LIMIT 1");
$otherUserStmt->execute([$userId]);
$otherUserId = $otherUserStmt->fetchColumn();

// 2. Create a test source video
$testVideoId = 'test-vid-' . substr(bin2hex(random_bytes(4)), 0, 8);
$pdo->prepare("
    INSERT INTO videos (id, user_id, youtube_id, title, status, duration_seconds, file_path, created_at)
    VALUES (?, ?, 'dummy_yt_id', 'Test Video Keep Safe', 'ready', 120, 'storage/uploads/test_keep_safe.mp4', NOW())
")->execute([$testVideoId, $userId]);

// Ensure dummy source file exists
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/storage/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$dummySourceFile = $uploadDir . '/test_keep_safe.mp4';
file_put_contents($dummySourceFile, 'SOURCE_VIDEO_PAYLOAD_DO_NOT_DELETE');

// 3. Create a test processing job
$testJobId = generate_uuid();
$pdo->prepare("
    INSERT INTO processing_jobs (id, video_id, user_id, status, progress_percentage, clips_total_target, clips_rendered_count, created_at)
    VALUES (?, ?, ?, 'COMPLETED', 100, 1, 1, NOW())
")->execute([$testJobId, $testVideoId, $userId]);

// 4. Create a test clip with physical output file
$testClipId = generate_uuid();
$outputDir = $baseDir . '/storage/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}
$clipRelPath = 'storage/output/test_clip_' . $testJobId . '.mp4';
$clipAbsPath = $baseDir . '/' . $clipRelPath;
file_put_contents($clipAbsPath, 'TEST_CLIP_OUTPUT_CONTENT');

$thumbRelPath = 'storage/output/test_clip_' . $testJobId . '_thumb.jpg';
$thumbAbsPath = $baseDir . '/' . $thumbRelPath;
file_put_contents($thumbAbsPath, 'TEST_CLIP_THUMB_CONTENT');

$pdo->prepare("
    INSERT INTO clips (id, job_id, video_id, user_id, clip_index, title, file_path, thumbnail_path, duration, virality_score, created_at)
    VALUES (?, ?, ?, ?, 1, 'Test Clip for Deletion', ?, ?, 30.0, 92.5, NOW())
")->execute([$testClipId, $testJobId, $testVideoId, $userId, $clipRelPath, $thumbRelPath]);

echo "[SETUP] Created Job $testJobId with 1 clip and test files on disk.\n";
echo "Clip file exists before delete: " . (file_exists($clipAbsPath) ? "YES" : "NO") . "\n";
echo "Thumb file exists before delete: " . (file_exists($thumbAbsPath) ? "YES" : "NO") . "\n";
echo "Source file exists before delete: " . (file_exists($dummySourceFile) ? "YES" : "NO") . "\n\n";

// 5. Test Unauthorized Deletion
echo "--- TEST 1: Unauthorized Deletion by another user ---\n";
if ($otherUserId) {
    $unauthRes = Job::delete($testJobId, $otherUserId, false);
    echo "Unauthorized deletion result: " . json_encode($unauthRes) . "\n";
    if (empty($unauthRes['success']) && ($unauthRes['code'] ?? 0) === 403) {
        echo "✓ PASSED: Unauthorized user was blocked with 403 Forbidden.\n\n";
    } else {
        echo "✗ FAILED: Unauthorized user was not properly blocked.\n\n";
    }
} else {
    $fakeUser = generate_uuid();
    $unauthRes = Job::delete($testJobId, $fakeUser, false);
    echo "Unauthorized deletion result: " . json_encode($unauthRes) . "\n";
    if (empty($unauthRes['success']) && ($unauthRes['code'] ?? 0) === 403) {
        echo "✓ PASSED: Unauthorized user was blocked with 403 Forbidden.\n\n";
    } else {
        echo "✗ FAILED: Unauthorized user was not properly blocked.\n\n";
    }
}

// 6. Test Authorized Deletion
echo "--- TEST 2: Authorized Deletion by Job Owner ---\n";
$authRes = Job::delete($testJobId, $userId, false);
echo "Authorized deletion result: " . json_encode($authRes) . "\n";
if (!empty($authRes['success'])) {
    echo "✓ PASSED: Job deletion reported success.\n";
} else {
    die("✗ FAILED: Job deletion failed.\n");
}

// 7. Verify DB rows deleted
$checkJob = $pdo->prepare("SELECT COUNT(*) FROM processing_jobs WHERE id = ?");
$checkJob->execute([$testJobId]);
$jobCount = (int)$checkJob->fetchColumn();

$checkClip = $pdo->prepare("SELECT COUNT(*) FROM clips WHERE id = ?");
$checkClip->execute([$testClipId]);
$clipCount = (int)$checkClip->fetchColumn();

echo "\n--- DB Integrity Checks ---\n";
echo "Job row count in processing_jobs: $jobCount (Expected: 0) -> " . ($jobCount === 0 ? "✓ OK" : "✗ FAIL") . "\n";
echo "Clip row count in clips: $clipCount (Expected: 0) -> " . ($clipCount === 0 ? "✓ OK" : "✗ FAIL") . "\n";

// 8. Verify Disk File Cleanup
echo "\n--- Disk Storage Cleanup Checks ---\n";
$clipFileRemains = file_exists($clipAbsPath);
$thumbFileRemains = file_exists($thumbAbsPath);
$sourceFileRemains = file_exists($dummySourceFile);

echo "Clip file exists on disk: " . ($clipFileRemains ? "✗ YES (Should be deleted)" : "✓ NO (Successfully purged)") . "\n";
echo "Thumb file exists on disk: " . ($thumbFileRemains ? "✗ YES (Should be deleted)" : "✓ NO (Successfully purged)") . "\n";
echo "Source video file exists on disk: " . ($sourceFileRemains ? "✓ YES (Source preserved safely)" : "✗ NO (Accidentally deleted source!)") . "\n";

// Clean up dummy video row and dummy source file
$pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$testVideoId]);
if (file_exists($dummySourceFile)) {
    @unlink($dummySourceFile);
}

if ($jobCount === 0 && $clipCount === 0 && !$clipFileRemains && !$thumbFileRemains && $sourceFileRemains) {
    echo "\n====================================================\n";
    echo "ALL JOB DELETION TESTS PASSED 100% SUCCESSFULLY!\n";
    echo "====================================================\n";
} else {
    echo "\nSome checks failed.\n";
}
