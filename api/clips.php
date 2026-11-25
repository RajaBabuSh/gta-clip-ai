<?php
// api/clips.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/YouTubeAccount.php';
require_once __DIR__ . '/../app/models/YouTubeUpload.php';
require_once __DIR__ . '/../app/services/YouTubeOAuthService.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/services/GeminiService.php';

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

// 1. Dispatch AI Moment Detection & Clipping Job
if ($action === 'generate') {
    $user = get_auth_user();
    if (!$user) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $videoId = $input['video_id'] ?? null;
    $targetClips = (int)($input['target_clips'] ?? 10);
    $style = $input['style'] ?? 'auto';
    $aspectRatio = $input['aspect_ratio'] ?? '9:16';

    if (!$videoId) {
        json_response(['success' => false, 'message' => 'video_id is required'], 400);
    }

    $video = Video::findById($videoId);
    if (!$video) {
        json_response(['success' => false, 'message' => 'Video not found'], 404);
    }

    // Call AI Worker Client to dispatch job to FastAPI worker
    $dispatch = AiWorkerClient::dispatchJob($videoId, $targetClips, $style, $aspectRatio);
    $jobId = $dispatch['data']['id'] ?? $dispatch['data']['job_id'] ?? null;

    if ($dispatch['success'] && !empty($jobId)) {
        $pdo = Database::getConnection();
        $upd = $pdo->prepare("UPDATE processing_jobs SET user_id = ?, clips_total_target = ? WHERE id = ?");
        $upd->execute([$user['id'], $targetClips, $jobId]);

        User::incrementClipsCount($user['id'], $targetClips);
        log_audit('job_dispatched', $user['id'], null, 'job', $jobId, ['target_clips' => $targetClips]);

        json_response([
            'success' => true,
            'job_id' => $jobId,
            'status' => 'pending',
            'clips_total_target' => $targetClips,
            'message' => "Job dispatched successfully. AI is processing $targetClips clips."
        ]);
    } else {
        $errorMsg = $dispatch['data']['detail'] ?? $dispatch['error'] ?? 'Failed to dispatch job to AI GPU Worker.';
        error_log("Worker Dispatch Failure: " . json_encode($dispatch));
        json_response([
            'success' => false,
            'message' => $errorMsg,
            'debug' => $dispatch
        ], 500);
    }
}

// 2. List Clips (by job_id, video_id, or current user)
if ($action === 'list') {
    $jobId = $_GET['job_id'] ?? null;
    $videoId = $_GET['video_id'] ?? null;
    $user = get_auth_user();

    if ($jobId) {
        $clips = Clip::getByJob($jobId);
    } elseif ($videoId) {
        $clips = Clip::getByVideo($videoId);
    } elseif ($user) {
        $clips = Clip::getByUser($user['id'], 100);
    } else {
        $clips = [];
    }

    json_response([
        'success' => true,
        'count' => count($clips),
        'clips' => $clips
    ]);
}

// 3. Get Single Clip with SEO Metadata
if ($action === 'get') {
    $clipId = $_GET['id'] ?? null;
    if (!$clipId) json_response(['success' => false, 'message' => 'Clip ID required'], 400);

    $clip = Clip::findById($clipId);
    if (!$clip) json_response(['success' => false, 'message' => 'Clip not found'], 404);

    $seo = Clip::getSeoMetadata($clipId);
    if (!$seo) {
        $seo = GeminiService::generateSeoMetadata($clip['title'], $clip['description'] ?? '');
        Clip::saveSeoMetadata($clipId, $seo);
    }

    $clip['seo'] = $seo;
    json_response(['success' => true, 'clip' => $clip]);
}

// 4. Generate or Regenerate SEO Metadata with Gemini AI
if ($action === 'seo') {
    $clipId = $_GET['id'] ?? null;
    if (!$clipId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $clipId = $input['clip_id'] ?? null;
    }
    if (!$clipId) json_response(['success' => false, 'message' => 'Clip ID required'], 400);

    $clip = Clip::findById($clipId);
    if (!$clip) json_response(['success' => false, 'message' => 'Clip not found'], 404);

    $user = get_auth_user();
    $userId = $user['id'] ?? null;

    $seo = GeminiService::generateSeoMetadata(
        $clip['title'],
        $clip['description'] ?? '',
        [],
        $userId
    );
    Clip::saveSeoMetadata($clipId, $seo);

    json_response([
        'success' => true,
        'message' => 'SEO Metadata generated successfully.',
        'seo' => $seo
    ]);
}

// 5. Update Clip Metadata
if ($action === 'update') {
    $user = get_auth_user();
    if (!$user) json_response(['success' => false, 'message' => 'Unauthorized'], 401);

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $clipId = $input['clip_id'] ?? null;
    if (!$clipId) json_response(['success' => false, 'message' => 'clip_id required'], 400);

    $success = Clip::updateMetadata($clipId, [
        'title' => $input['title'] ?? null,
        'description' => $input['description'] ?? null
    ]);

    if (!empty($input['seo'])) {
        Clip::saveSeoMetadata($clipId, $input['seo']);
    }

    json_response(['success' => $success, 'message' => $success ? 'Clip updated.' : 'Failed to update clip.']);
}

// 6. Direct Upload to YouTube Channel
if ($action === 'upload_youtube') {
    $user = get_auth_user();
    if (!$user) json_response(['success' => false, 'message' => 'Unauthorized'], 401);

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $clipId = $input['clip_id'] ?? null;
    $privacy = $input['privacy_status'] ?? 'private';
    $customTitle = $input['title'] ?? null;
    $customDesc = $input['description'] ?? null;

    if (!$clipId) json_response(['success' => false, 'message' => 'clip_id required'], 400);

    $clip = Clip::findById($clipId);
    if (!$clip) json_response(['success' => false, 'message' => 'Clip not found'], 404);

    $ytAccount = YouTubeAccount::getConnectedAccount($user['id']);
    if (!$ytAccount) {
        json_response([
            'success' => false,
            'message' => 'No YouTube channel connected. Please connect your channel in YouTube Studio first.',
            'redirect' => url('youtube-studio.php')
        ], 400);
    }

    // Prepare upload record
    $finalTitle = $customTitle ?: ($clip['title'] . ' #Shorts #GTA5');
    $finalDesc = $customDesc ?: ($clip['description'] ?: 'GTA 5 gameplay highlight.');

    $uploadId = YouTubeUpload::createRecord([
        'user_id' => $user['id'],
        'clip_id' => $clipId,
        'youtube_account_id' => $ytAccount['id'],
        'title' => $finalTitle,
        'description' => $finalDesc,
        'privacy_status' => $privacy,
        'upload_status' => 'pending'
    ]);

    // Perform YouTube Data API upload via Python worker or directly
    // Resolve physical clip file
    $realFilePath = $clip['file_path'];
    if (!file_exists($realFilePath)) {
        $alt1 = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clip['file_path']);
        if (file_exists($alt1)) {
            $realFilePath = $alt1;
        } else {
            $alt2 = STORAGE_PATH . DIRECTORY_SEPARATOR . 'clips' . DIRECTORY_SEPARATOR . basename($clip['file_path']);
            if (file_exists($alt2)) {
                $realFilePath = $alt2;
            } else {
                YouTubeUpload::updateStatus($uploadId, 'failed', null, null, 'Video file does not exist on disk.');
                json_response(['success' => false, 'message' => 'Source clip file missing on disk.'], 500);
            }
        }
    }

    try {
        $validToken = YouTubeOAuthService::getValidAccessToken($ytAccount);
        $uploadResult = YouTubeOAuthService::uploadVideoResumable(
            $validToken,
            $realFilePath,
            $finalTitle,
            $finalDesc,
            $privacy
        );

        $ytVideoId = $uploadResult['video_id'];
        $ytUrl = $uploadResult['youtube_url'];
        YouTubeUpload::updateStatus($uploadId, 'published', $ytVideoId, $ytUrl);

        json_response([
            'success' => true,
            'message' => 'Successfully uploaded to YouTube Shorts using official Resumable Upload!',
            'youtube_video_id' => $ytVideoId,
            'youtube_url' => $ytUrl,
            'shorts_url' => $uploadResult['shorts_url'],
            'privacy_status' => $privacy
        ]);
    } catch (Exception $e) {
        error_log("YouTube Upload Error: " . $e->getMessage());
        YouTubeUpload::updateStatus($uploadId, 'failed', null, null, $e->getMessage());
        json_response([
            'success' => false,
            'message' => 'YouTube upload failed: ' . $e->getMessage()
        ], 500);
    }
}

// 7. Batch ZIP Download (by job_id or video_id)
if ($action === 'zip' || $action === 'download_zip') {
    $jobId = $_GET['job_id'] ?? null;
    $videoId = $_GET['video_id'] ?? null;

    if ($jobId) {
        $clips = Clip::getByJob($jobId);
        $titlePrefix = 'GTA_Clips_Job_' . substr($jobId, 0, 8);
    } elseif ($videoId) {
        $clips = Clip::getByVideo($videoId);
        $video = Video::findById($videoId);
        $titlePrefix = 'GTA_Clips_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $video['title'] ?? 'Highlights');
    } else {
        die("job_id or video_id required.");
    }

    if (empty($clips)) {
        die("No clips available for download.");
    }

    $zipFileName = $titlePrefix . '.zip';
    $tempZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

    $zip = new ZipArchive();
    if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Cannot create ZIP file.");
    }

    foreach ($clips as $idx => $clip) {
        $realPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clip['file_path']);
        if (file_exists($realPath)) {
            $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $clip['title'] ?: 'Clip');
            $entryName = sprintf("Clip_%02d_%s.mp4", $idx + 1, substr($cleanTitle, 0, 40));
            $zip->addFile($realPath, $entryName);
        }
    }
    $zip->close();

    if (file_exists($tempZipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . filesize($tempZipPath));
        readfile($tempZipPath);
        @unlink($tempZipPath);
        exit;
    } else {
        die("Failed to compile ZIP file.");
    }
}

// 8. Retry or Resume Interrupted Job
if ($action === 'retry' || $action === 'resume') {
    $jobId = $_GET['job_id'] ?? $_POST['job_id'] ?? null;
    if (!$jobId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $jobId = $input['job_id'] ?? null;
    }
    if (!$jobId) {
        json_response(['success' => false, 'message' => 'job_id required'], 400);
    }

    $endpoint = AiWorkerClient::getWorkerBaseUrl() . '/api/v1/clips/resume/' . urlencode($jobId);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        json_response(['success' => true, 'message' => 'Job resumed from last checkpoint.']);
    } else {
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE processing_jobs SET status = 'pending', current_step = 'Job queued for retry...', updated_at = NOW() WHERE id = ?")->execute([$jobId]);
        json_response(['success' => true, 'message' => 'Job reset to pending in queue.']);
    }
}

// 9. Quick Clip (Rolling Buffer Capture - e.g. Last 15s, 30s, 60s)
if ($action === 'quick_clip') {
    $user = get_auth_user();
    if (!$user) json_response(['success' => false, 'message' => 'Unauthorized'], 401);

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $videoId = $input['video_id'] ?? null;
    $currentTime = (float)($input['current_time'] ?? 30.0);
    $clipDuration = (float)($input['duration'] ?? 30.0);
    $aspectRatio = $input['aspect_ratio'] ?? '9:16';
    $layoutMode = $input['layout_mode'] ?? 'blurred_background';
    $title = $input['title'] ?? ('Quick Clip (' . round($clipDuration) . 's Highlight)');

    if (!$videoId) json_response(['success' => false, 'message' => 'video_id is required'], 400);

    $video = Video::findById($videoId);
    if (!$video) json_response(['success' => false, 'message' => 'Video not found'], 404);

    $startTime = max(0.0, $currentTime - $clipDuration);
    $endTime = $currentTime;
    if ($endTime <= $startTime) {
        $endTime = min((float)($video['duration_seconds'] ?? 60.0), $startTime + $clipDuration);
    }

    $workerRes = AiWorkerClient::postJson('/api/v1/clips/render-custom', [
        'video_id' => $videoId,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'title' => $title,
        'aspect_ratio' => $aspectRatio,
        'layout_mode' => $layoutMode,
        'burn_subtitles' => false,
        'user_id' => $user['id']
    ]);

    if ($workerRes['success'] && !empty($workerRes['data']['clip'])) {
        User::incrementClipsCount($user['id'], 1);
        json_response([
            'success' => true,
            'message' => 'Quick clip captured and rendered!',
            'clip' => $workerRes['data']['clip']
        ]);
    } else {
        json_response([
            'success' => false,
            'message' => $workerRes['data']['detail'] ?? $workerRes['error'] ?? 'Failed to render quick clip.'
        ], 500);
    }
}

// 10. Create Custom Clip from In/Out Markers
if ($action === 'create_custom') {
    $user = get_auth_user();
    if (!$user) json_response(['success' => false, 'message' => 'Unauthorized'], 401);

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $videoId = $input['video_id'] ?? null;
    $startTime = (float)($input['start_time'] ?? 0.0);
    $endTime = (float)($input['end_time'] ?? 30.0);
    $title = $input['title'] ?? 'Custom Highlight';
    $aspectRatio = $input['aspect_ratio'] ?? '9:16';
    $layoutMode = $input['layout_mode'] ?? 'blurred_background';
    $burnSubtitles = (bool)($input['burn_subtitles'] ?? false);
    $captionPreset = $input['caption_preset'] ?? 'mrbeast';

    if (!$videoId) json_response(['success' => false, 'message' => 'video_id is required'], 400);
    if ($endTime <= $startTime) json_response(['success' => false, 'message' => 'End time must be greater than start time'], 400);

    $video = Video::findById($videoId);
    if (!$video) json_response(['success' => false, 'message' => 'Video not found'], 404);

    $workerRes = AiWorkerClient::postJson('/api/v1/clips/render-custom', [
        'video_id' => $videoId,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'title' => $title,
        'aspect_ratio' => $aspectRatio,
        'layout_mode' => $layoutMode,
        'burn_subtitles' => $burnSubtitles,
        'caption_preset' => $captionPreset,
        'user_id' => $user['id']
    ]);

    if ($workerRes['success'] && !empty($workerRes['data']['clip'])) {
        User::incrementClipsCount($user['id'], 1);
        json_response([
            'success' => true,
            'message' => 'Custom clip rendered and added to library!',
            'clip' => $workerRes['data']['clip']
        ]);
    } else {
        json_response([
            'success' => false,
            'message' => $workerRes['data']['detail'] ?? $workerRes['error'] ?? 'Failed to render custom clip.'
        ], 500);
    }
}

// 11. Multi-Format Export (MP4, MP3, SRT, VTT)
if ($action === 'export_format') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $clipId = $input['clip_id'] ?? $_GET['clip_id'] ?? null;
    $videoId = $input['video_id'] ?? $_GET['video_id'] ?? null;
    $format = $input['format'] ?? $_GET['format'] ?? 'mp4';
    $aspectRatio = $input['aspect_ratio'] ?? '9:16';
    $layoutMode = $input['layout_mode'] ?? 'blurred_background';

    $workerRes = AiWorkerClient::postJson('/api/v1/clips/export-format', [
        'clip_id' => $clipId,
        'video_id' => $videoId,
        'format' => $format,
        'aspect_ratio' => $aspectRatio,
        'layout_mode' => $layoutMode
    ]);

    if ($workerRes['success'] && !empty($workerRes['data']['file_url'])) {
        json_response([
            'success' => true,
            'download_url' => url($workerRes['data']['file_url']),
            'filename' => $workerRes['data']['filename'] ?? ('export.' . $format),
            'format' => $format
        ]);
    } else {
        json_response([
            'success' => false,
            'message' => $workerRes['data']['detail'] ?? $workerRes['error'] ?? 'Export failed.'
        ], 500);
    }
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
