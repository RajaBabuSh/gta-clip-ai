<?php
// api/jobs.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Job.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? null;
$jobId = $_GET['id'] ?? null;

// 1. Delete Job Permanently
if ($action === 'delete') {
    // Authenticate user or admin
    $user = get_auth_user();
    $admin = get_auth_admin();
    if (!$user && !$admin) {
        json_response(['success' => false, 'error' => 'Authentication required. Please sign in.'], 401);
    }

    // Read payload (supports JSON or Form POST or Query Param)
    $input = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains(strtolower($contentType), 'application/json')) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    }

    $targetJobId = $input['job_id'] ?? $input['id'] ?? $_POST['job_id'] ?? $_POST['id'] ?? $jobId ?? null;
    $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'error' => 'Security token (CSRF) expired or invalid. Please refresh the page.'], 403);
    }

    if (empty($targetJobId)) {
        json_response(['success' => false, 'error' => 'Job ID is required.'], 400);
    }

    $result = Job::delete((string)$targetJobId, $user['id'] ?? null, (bool)$admin);
    if (!empty($result['success'])) {
        json_response($result, 200);
    } else {
        json_response($result, $result['code'] ?? 400);
    }
}

// 2. Cancel Running Job
if ($action === 'cancel' && $jobId) {
    AiWorkerClient::cancelJob($jobId);
    $res = Job::cancel($jobId);
    json_response(['success' => $res, 'message' => $res ? 'Job marked as cancelled.' : 'Failed to cancel job.']);
}

// 2. Poll Specific Job
if ($jobId) {
    $job = Job::findById($jobId);
    if (!$job) {
        json_response(['status' => 'error', 'message' => 'Job not found'], 404);
    }

    $rawStatus = strtoupper($job['status'] ?? 'PENDING');
    $renderedCount = (int)($job['clips_rendered_count'] ?? 0);
    $targetCount = (int)($job['clips_total_target'] ?? 10);
    $progress = (float)($job['progress_percentage'] ?? 0);

    // Respect worker completion status
    if ($rawStatus === 'COMPLETED') {
        $status = 'completed';
    } elseif ($rawStatus === 'PARTIALLY_COMPLETED') {
        $status = ($renderedCount >= $targetCount && $targetCount > 0) ? 'completed' : 'partially_completed';
    } else {
        $status = strtolower($rawStatus);
    }

    // Fetch clips if ready
    $clips = [];
    if (in_array($status, ['completed', 'partially_completed'])) {
        $clips = Clip::getByJob($jobId);
    }

    // Calculate heartbeat freshness and detect stalled workers
    $heartbeat = $job['worker_heartbeat_at'] ?? null;
    $heartbeatSecondsAgo = null;
    $isStalled = false;
    if ($heartbeat) {
        $heartbeatSecondsAgo = max(0, time() - strtotime($heartbeat));
        if ($heartbeatSecondsAgo > 120 && in_array(strtolower($status), ['pending', 'processing'])) {
            $isStalled = true;
        }
    }

    json_response([
        'id' => $job['id'],
        'status' => $status,
        'progress' => $progress,
        'current_step' => $job['current_step'],
        'current_stage' => $job['current_stage'] ?? 'Processing',
        'clips_rendered_count' => $renderedCount,
        'clips_total_target' => $targetCount,
        'clips_count' => $targetCount,
        'moments_detected_count' => (int)($job['moments_detected_count'] ?? 0),
        'clips_selected_count' => (int)($job['clips_selected_count'] ?? 0),
        'candidates_rejected_count' => (int)($job['candidates_rejected_count'] ?? 0),
        'metrics_json' => !empty($job['metrics_json']) ? json_decode($job['metrics_json'], true) : null,
        'checkpoint_chunk_index' => (int)($job['checkpoint_chunk_index'] ?? 0),
        'worker_heartbeat_at' => $heartbeat,
        'heartbeat_seconds_ago' => $heartbeatSecondsAgo,
        'is_stalled' => $isStalled,
        'video_id' => $job['video_id'],
        'clips' => $clips,
        'has_clips' => !empty($clips),
        'error_message' => $job['error_message'],
        'updated_at' => $job['updated_at']
    ]);
}

json_response(['error' => 'Job ID required'], 400);
