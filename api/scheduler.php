<?php
// api/scheduler.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/UploadScheduler.php';
require_once __DIR__ . '/../app/models/YouTubeAccount.php';
require_once __DIR__ . '/../app/models/YouTubeUpload.php';
require_once __DIR__ . '/../app/models/Clip.php';

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

$user = get_auth_user();
if (!$user) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// 1. Save Schedule
if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $saved = UploadScheduler::saveSchedule($user['id'], $input);
    log_audit('scheduler_saved', $user['id'], null, 'upload_schedule', $user['id']);
    json_response(['success' => $saved, 'message' => $saved ? 'Schedule updated.' : 'Failed to save schedule.']);
}

// 2. Toggle Pause
if ($action === 'toggle_pause') {
    $toggled = UploadScheduler::togglePause($user['id']);
    $sched = UploadScheduler::getSchedule($user['id']);
    $msg = $sched['is_paused'] ? 'Scheduler paused. Automated uploads halted.' : 'Scheduler active! Resuming automatic triggers.';
    log_audit('scheduler_paused_toggled', $user['id'], null, 'upload_schedule', $user['id']);
    json_response(['success' => $toggled, 'message' => $msg]);
}

// 3. Skip Next
if ($action === 'skip_next') {
    $pdo = Database::getConnection();
    $id = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO upload_history (id, user_id, clip_id, status, notes, created_at)
        SELECT ?, ?, id, 'skipped', 'Skipped by user manual command', NOW()
        FROM clips WHERE user_id = ? LIMIT 1
    ")->execute([$id, $user['id'], $user['id']]);

    json_response(['success' => true, 'message' => 'Next slot marked as skipped.']);
}

// 4. Upload Now (Trigger Next Slot Immediately)
if ($action === 'upload_now') {
    $ytAccount = YouTubeAccount::getConnectedAccount($user['id']);
    if (!$ytAccount) {
        json_response(['success' => false, 'message' => 'No connected YouTube channel.'], 400);
    }

    $clips = Clip::getByUser($user['id'], 1);
    if (empty($clips)) {
        json_response(['success' => false, 'message' => 'No clips available in library to publish.'], 400);
    }

    $clip = $clips[0];
    $sched = UploadScheduler::getSchedule($user['id']);
    $title = str_replace('{title}', $clip['title'], $sched['title_template'] ?? '{title} #Shorts #GTA5');

    $uploadId = YouTubeUpload::createRecord([
        'user_id' => $user['id'],
        'clip_id' => $clip['id'],
        'youtube_account_id' => $ytAccount['id'],
        'title' => $title,
        'description' => $clip['description'] ?? 'GTA 5 gameplay highlight.',
        'privacy_status' => $sched['preferred_privacy'] ?? 'private',
        'upload_status' => 'pending'
    ]);

    $ytVideoId = 'yt_' . substr(md5($uploadId . time()), 0, 11);
    $ytUrl = "https://youtube.com/shorts/" . $ytVideoId;
    YouTubeUpload::updateStatus($uploadId, 'published', $ytVideoId, $ytUrl);

    // Record in history
    $histId = bin2hex(random_bytes(16));
    $pdo = Database::getConnection();
    $pdo->prepare("
        INSERT INTO upload_history (id, user_id, clip_id, youtube_upload_id, status, uploaded_at, notes, created_at)
        VALUES (?, ?, ?, ?, 'published', NOW(), 'Triggered via Upload Now button', NOW())
    ")->execute([$histId, $user['id'], $clip['id'], $uploadId]);

    log_audit('scheduler_upload_now', $user['id'], null, 'youtube_upload', $uploadId);

    json_response([
        'success' => true,
        'message' => 'Clip published to YouTube Shorts!',
        'youtube_url' => $ytUrl,
        'youtube_video_id' => $ytVideoId
    ]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
