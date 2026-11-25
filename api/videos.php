<?php
// api/videos.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/Project.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$input = !empty($rawInput) ? json_decode($rawInput, true) : [];

$action = $_GET['action'] ?? ($input['action'] ?? null);

$user = get_auth_user();
$userId = $user['id'] ?? null;

// Helper to parse YouTube video IDs from all common formats
function extract_yt_id(string $url): ?string {
    $url = trim($url);
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
        return $url;
    }
    $patterns = [
        '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})#i',
        '#(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})#i',
        '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})#i',
        '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})#i',
        '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})#i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// 1. Verify Stream URL (YouTube, Twitch, Kick)
if ($action === 'verify') {
    $url = trim($input['url'] ?? $_POST['url'] ?? '');

    if (empty($url)) {
        json_response(['success' => false, 'message' => 'Please provide a valid stream or video URL.'], 400);
    }

    $youtubeId = extract_yt_id($url);

    // 1. Instant check: Is this video already in our MySQL database?
    if ($youtubeId) {
        $existing = Video::findByYoutubeId($youtubeId);
        if ($existing) {
            if ($userId && empty($existing['user_id'])) {
                Video::update($existing['id'], ['user_id' => $userId]);
                $existing['user_id'] = $userId;
            }
            json_response([
                'success' => true,
                'data' => $existing,
                'video' => $existing,
                'video_id' => $existing['id'],
                'message' => 'Verified stream retrieved from your library.'
            ]);
        }
    }

    // 2. Call AI Worker verification service
    $workerResult = AiWorkerClient::verifyVideo($url);

    if ($workerResult['success'] && !empty($workerResult['data'])) {
        $data = $workerResult['data'];
        $foundYtId = $data['youtube_id'] ?? $youtubeId;
        
        // Check if video already exists in database
        $existing = null;
        if (!empty($foundYtId)) {
            $existing = Video::findByYoutubeId($foundYtId);
        }

        if ($existing) {
            if ($userId && empty($existing['user_id'])) {
                Video::update($existing['id'], ['user_id' => $userId]);
                $existing['user_id'] = $userId;
            }
            json_response([
                'success' => true,
                'data' => $existing,
                'video' => $existing,
                'video_id' => $existing['id'],
                'message' => 'Existing verified stream retrieved.'
            ]);
        }

        // Create new video entry in MySQL
        $videoId = Video::create([
            'id' => $data['id'] ?? generate_uuid(),
            'user_id' => $userId,
            'title' => $data['title'] ?? 'Live Stream Recording',
            'channel_title' => $data['channel_title'] ?? 'Streamer',
            'source_type' => 'YOUTUBE',
            'youtube_id' => $foundYtId,
            'youtube_url' => $data['youtube_url'] ?? ($foundYtId ? "https://www.youtube.com/watch?v={$foundYtId}" : $url),
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'thumbnail_url' => $data['thumbnail_url'] ?? ($foundYtId ? "https://i.ytimg.com/vi/{$foundYtId}/hqdefault.jpg" : null),
            'status' => 'READY'
        ]);

        $video = Video::findById($videoId);
        $payload = $video ?: $data;
        json_response([
            'success' => true,
            'data' => $payload,
            'video' => $payload,
            'video_id' => $videoId,
            'message' => 'Stream verified and registered.'
        ]);
    }

    // 3. Resilient Fallback: Direct YouTube oEmbed verification if AI worker is unreachable or busy
    if ($youtubeId) {
        $canonical = "https://www.youtube.com/watch?v={$youtubeId}";
        $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($canonical) . "&format=json";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 6,
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ]
        ]);
        $oembedRaw = @file_get_contents($oembedUrl, false, $ctx);
        if ($oembedRaw) {
            $oembedData = json_decode($oembedRaw, true);
            if (!empty($oembedData['title'])) {
                $title = $oembedData['title'];
                $channel = $oembedData['author_name'] ?? 'Creator';
                $thumb = $oembedData['thumbnail_url'] ?? "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg";
                
                // Quick probe for duration using yt-dlp if available with strict timeout
                $duration = 0;
                $probeCmd = 'python -m yt_dlp --dump-json --skip-download --no-playlist --socket-timeout 5 --no-warnings "' . $canonical . '" 2>NUL';
                $probeOut = @shell_exec($probeCmd);
                if ($probeOut) {
                    $probeJson = json_decode($probeOut, true);
                    if (!empty($probeJson['duration'])) {
                        $duration = (float)$probeJson['duration'];
                    }
                }

                // Check if existing before create
                $existing = Video::findByYoutubeId($youtubeId);
                if ($existing) {
                    if ($userId && empty($existing['user_id'])) {
                        Video::update($existing['id'], ['user_id' => $userId]);
                        $existing['user_id'] = $userId;
                    }
                    $video = $existing;
                    $videoId = $existing['id'];
                } else {
                    $videoId = Video::create([
                        'id' => generate_uuid(),
                        'user_id' => $userId,
                        'title' => $title,
                        'channel_title' => $channel,
                        'source_type' => 'YOUTUBE',
                        'youtube_id' => $youtubeId,
                        'youtube_url' => $canonical,
                        'duration_seconds' => $duration,
                        'thumbnail_url' => $thumb,
                        'status' => 'READY'
                    ]);
                    $video = Video::findById($videoId);
                }

                json_response([
                    'success' => true,
                    'data' => $video,
                    'video' => $video,
                    'video_id' => $videoId,
                    'message' => 'YouTube stream verified successfully.'
                ]);
            }
        }
    }

    // 4. If all verification attempts failed
    $detailMsg = $workerResult['data']['detail'] ?? $workerResult['error'] ?? null;
    if (!$detailMsg || str_contains($detailMsg, 'Connection refused') || str_contains($detailMsg, 'Failed to connect')) {
        $detailMsg = 'Could not reach AI worker service. Please ensure the local AI worker is running on port 8000 or the URL is a publicly accessible YouTube video.';
    }
    json_response([
        'success' => false,
        'message' => $detailMsg
    ], 400);
}

// 2. Confirm & Ingest Verified Video
if ($action === 'confirm') {
    $videoData = $input['video'] ?? [];
    $projectId = !empty($input['project_id']) ? $input['project_id'] : null;

    $videoId = $videoData['id'] ?? null;
    $existing = null;
    if ($videoId) {
        $existing = Video::findById($videoId);
    }
    if (!$existing && !empty($videoData['youtube_id'])) {
        $existing = Video::findByYoutubeId($videoData['youtube_id']);
    }

    if ($existing) {
        $updates = [];
        if ($projectId) {
            $updates['project_id'] = $projectId;
        }
        if ($userId && empty($existing['user_id'])) {
            $updates['user_id'] = $userId;
        }
        if (!empty($updates)) {
            Video::update($existing['id'], $updates);
        }
        json_response([
            'success' => true,
            'video_id' => $existing['id'],
            'data' => $existing,
            'video' => $existing,
            'message' => 'Video confirmed successfully.'
        ]);
    }

    // Fallback: create record if not exists
    $newId = $videoId ?: generate_uuid();
    Video::create([
        'id' => $newId,
        'user_id' => $userId,
        'project_id' => $projectId,
        'title' => $videoData['title'] ?? 'Live Stream Recording',
        'channel_title' => $videoData['channel_title'] ?? 'Streamer',
        'source_type' => 'YOUTUBE',
        'youtube_id' => $videoData['youtube_id'] ?? null,
        'youtube_url' => $videoData['youtube_url'] ?? '',
        'duration_seconds' => $videoData['duration_seconds'] ?? $videoData['duration'] ?? 0,
        'thumbnail_url' => $videoData['thumbnail_url'] ?? null,
        'status' => 'ready'
    ]);

    json_response([
        'success' => true,
        'video_id' => $newId,
        'message' => 'Video registered successfully.'
    ]);
}

// 3. Direct Video File Upload
if ($action === 'upload') {
    if (!$userId) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $file = $_FILES['file'] ?? $_FILES['video_file'] ?? null;
    if (empty($file)) {
        json_response(['success' => false, 'message' => 'No file received.'], 400);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'File upload error code: ' . $file['error']], 400);
    }

    $maxBytes = defined('MAX_UPLOAD_SIZE_BYTES') ? MAX_UPLOAD_SIZE_BYTES : (500 * 1024 * 1024);
    if ($file['size'] > $maxBytes) {
        json_response(['success' => false, 'message' => 'File exceeds maximum upload size (500 MB).'], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'mov', 'mkv', 'webm'])) {
        json_response(['success' => false, 'message' => 'Unsupported format. Allowed: MP4, MOV, MKV, WEBM'], 400);
    }

    if (!is_dir(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0777, true);
    }

    $newFilename = 'upload_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = UPLOADS_PATH . DIRECTORY_SEPARATOR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        json_response(['success' => false, 'message' => 'Failed to store uploaded file.'], 500);
    }

    $title = trim($_POST['title'] ?? '') ?: pathinfo($file['name'], PATHINFO_FILENAME);
    $projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;

    $videoId = Video::create([
        'user_id' => $userId,
        'project_id' => $projectId,
        'title' => $title,
        'channel_title' => $user['username'] ?? 'User Upload',
        'source_type' => 'upload',
        'file_path' => 'backend/storage/uploads/' . $newFilename,
        'file_size' => $file['size'],
        'duration' => 60, // preliminary until probed
        'status' => 'ready'
    ]);

    json_response([
        'success' => true,
        'video_id' => $videoId,
        'message' => 'File uploaded successfully.'
    ]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
