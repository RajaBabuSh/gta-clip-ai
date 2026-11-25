<?php
// api/youtube.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/YouTubeAccount.php';
require_once __DIR__ . '/../app/models/YouTubeUpload.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/services/YouTubeOAuthService.php';

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

$user = get_auth_user();

// 1. Authorize: Initiate Official Google OAuth 2.0 flow
if ($action === 'authorize') {
    if (!$user) {
        flash('danger', 'Please log in to connect your YouTube channel.');
        redirect('login.php');
    }

    if (!YouTubeOAuthService::isConfigured()) {
        flash('warning', 'YouTube OAuth setup required: Please configure your Google Client ID and Client Secret in Admin YouTube Settings.');
        redirect('youtube-studio.php?error=oauth_not_configured');
    }

    // Generate cryptographically secure state
    $state = bin2hex(random_bytes(32));
    $_SESSION['youtube_oauth_state'] = $state;
    $_SESSION['youtube_oauth_user_id'] = $user['id'];

    try {
        $authUrl = YouTubeOAuthService::getAuthorizationUrl($state);
        header('Location: ' . $authUrl);
        exit;
    } catch (Exception $e) {
        flash('danger', 'Could not initiate Google OAuth: ' . $e->getMessage());
        redirect('youtube-studio.php');
    }
}

// 2. OAuth Callback: Official Google Redirect URI
if ($action === 'callback') {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    if ($error) {
        flash('danger', 'Google authorization declined or cancelled: ' . htmlspecialchars($error));
        redirect('youtube-studio.php');
    }

    $savedState = $_SESSION['youtube_oauth_state'] ?? null;
    unset($_SESSION['youtube_oauth_state']);

    if (empty($state) || empty($savedState) || !hash_equals($savedState, $state)) {
        flash('danger', 'Security verification failed (OAuth CSRF state mismatch). Please try again.');
        redirect('youtube-studio.php');
    }

    if (empty($code)) {
        flash('danger', 'Missing authorization code from Google.');
        redirect('youtube-studio.php');
    }

    // Determine user
    $targetUserId = $_SESSION['youtube_oauth_user_id'] ?? ($user['id'] ?? null);
    unset($_SESSION['youtube_oauth_user_id']);

    if (!$targetUserId) {
        flash('danger', 'Session expired during OAuth. Please log in and retry.');
        redirect('login.php');
    }

    try {
        // Exchange authorization code for tokens
        $tokens = YouTubeOAuthService::exchangeCode($code);
        $accessToken = $tokens['access_token'];
        $refreshToken = $tokens['refresh_token'] ?? null;
        $expiresIn = (int)($tokens['expires_in'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        $scope = $tokens['scope'] ?? YouTubeOAuthService::SCOPE_UPLOAD;

        // Fetch official YouTube channel profile
        $profile = YouTubeOAuthService::fetchChannelProfile($accessToken);

        // Store tokens encrypted at rest in MySQL
        $accId = YouTubeAccount::saveOrUpdateAccount(
            $targetUserId,
            $profile['channel_id'],
            $profile['channel_title'],
            $profile['channel_thumbnail_url'],
            $profile['subscriber_count'],
            $accessToken,
            $refreshToken,
            $expiresAt,
            $scope
        );

        log_audit('youtube_connected', $targetUserId, null, 'youtube_account', $accId, [
            'channel_id' => $profile['channel_id'],
            'channel_title' => $profile['channel_title']
        ]);

        flash('success', "YouTube channel '{$profile['channel_title']}' connected successfully via official Google OAuth 2.0!");
        redirect('youtube-studio.php?connected=1');
    } catch (Exception $e) {
        error_log("YouTube OAuth Callback Error: " . $e->getMessage());
        flash('danger', 'Failed to complete YouTube channel authorization: ' . $e->getMessage());
        redirect('youtube-studio.php');
    }
}

// All subsequent endpoints require logged in user
if (!$user) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// 3. Status Endpoint
if ($action === 'status') {
    $acc = YouTubeAccount::getConnectedAccount($user['id']);
    json_response([
        'success' => true,
        'configured' => YouTubeOAuthService::isConfigured(),
        'connected' => !empty($acc),
        'account' => $acc ? [
            'id' => $acc['id'],
            'channel_id' => $acc['channel_id'],
            'channel_title' => $acc['channel_title'],
            'channel_thumbnail_url' => $acc['channel_thumbnail_url'],
            'subscriber_count' => (int)$acc['subscriber_count'],
            'connected_at' => $acc['connected_at']
        ] : null
    ]);
}

// 4. Disconnect Channel
if ($action === 'disconnect') {
    YouTubeAccount::disconnect($user['id']);
    log_audit('youtube_disconnected', $user['id'], null, 'youtube_account', $user['id']);
    json_response([
        'success' => true,
        'message' => 'YouTube channel has been disconnected and security tokens purged.'
    ]);
}

// 5. Resumable Upload of Clip to YouTube Data API v3
if ($action === 'upload') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $clipId = trim((string)($input['clip_id'] ?? ''));
    $title = trim((string)($input['title'] ?? ''));
    $desc = trim((string)($input['description'] ?? ''));
    $privacy = trim((string)($input['privacy_status'] ?? 'private'));
    $tagsRaw = $input['tags'] ?? [];

    if (empty($clipId)) {
        json_response(['success' => false, 'message' => 'clip_id parameter is required.'], 400);
    }

    $clip = Clip::findById($clipId);
    if (!$clip) {
        json_response(['success' => false, 'message' => 'Requested clip record not found.'], 404);
    }

    $ytAccount = YouTubeAccount::getConnectedAccount($user['id']);
    if (!$ytAccount) {
        json_response([
            'success' => false,
            'message' => 'No active YouTube channel connected. Please authorize your channel first.',
            'redirect' => url('youtube-studio.php')
        ], 400);
    }

    // Resolve physical clip file
    $filePath = $clip['file_path'];
    if (!file_exists($filePath)) {
        // Try relative to ROOT_PATH
        $altPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
        if (file_exists($altPath)) {
            $filePath = $altPath;
        } else {
            // Try storage/clips
            $altPath2 = STORAGE_PATH . DIRECTORY_SEPARATOR . 'clips' . DIRECTORY_SEPARATOR . basename($filePath);
            if (file_exists($altPath2)) {
                $filePath = $altPath2;
            } else {
                json_response(['success' => false, 'message' => 'Physical MP4 video file not found on disk.'], 404);
            }
        }
    }

    // Default privacy to Private (Requirement 10)
    $validPrivacy = ['private', 'unlisted', 'public'];
    if (!in_array($privacy, $validPrivacy, true)) {
        $privacy = 'private';
    }

    $finalTitle = !empty($title) ? $title : ($clip['title'] . ' #Shorts');
    $finalDesc = !empty($desc) ? $desc : ($clip['description'] ?? 'GTA 5 gameplay highlight');

    // Parse tags
    $tags = [];
    if (is_array($tagsRaw)) {
        $tags = $tagsRaw;
    } elseif (is_string($tagsRaw)) {
        $tags = array_filter(array_map('trim', explode(',', $tagsRaw)));
    }

    // Create pending upload record in MySQL
    $uploadId = YouTubeUpload::createRecord([
        'user_id' => $user['id'],
        'clip_id' => $clipId,
        'youtube_account_id' => $ytAccount['id'],
        'title' => $finalTitle,
        'description' => $finalDesc,
        'privacy_status' => $privacy,
        'upload_status' => 'uploading'
    ]);

    try {
        // Get valid decrypted access token (auto-refreshes if needed)
        $validToken = YouTubeOAuthService::getValidAccessToken($ytAccount);

        // Perform official Google Resumable Upload
        $uploadResult = YouTubeOAuthService::uploadVideoResumable(
            $validToken,
            $filePath,
            $finalTitle,
            $finalDesc,
            $privacy,
            $tags
        );

        $ytVideoId = $uploadResult['video_id'];
        $ytUrl = $uploadResult['youtube_url'];

        // Mark as published in MySQL
        YouTubeUpload::updateStatus($uploadId, 'published', $ytVideoId, $ytUrl);
        log_audit('youtube_upload_published', $user['id'], null, 'youtube_upload', $uploadId, [
            'youtube_video_id' => $ytVideoId,
            'privacy' => $privacy
        ]);

        json_response([
            'success' => true,
            'message' => 'Short successfully uploaded to YouTube using official Resumable Upload!',
            'youtube_video_id' => $ytVideoId,
            'youtube_url' => $ytUrl,
            'shorts_url' => $uploadResult['shorts_url'],
            'privacy_status' => $privacy
        ]);
    } catch (Exception $e) {
        error_log("YouTube Resumable Upload Failed: " . $e->getMessage());
        YouTubeUpload::updateStatus($uploadId, 'failed', null, null, $e->getMessage());
        json_response([
            'success' => false,
            'message' => 'YouTube upload failed: ' . $e->getMessage()
        ], 500);
    }
}

// 6. Save Google OAuth Settings (Admin only)
if ($action === 'save_settings') {
    $admin = get_auth_admin();
    if (!$admin) {
        json_response(['success' => false, 'message' => 'Administrator privileges required.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $clientId = trim((string)($input['google_client_id'] ?? ''));
    $clientSecret = trim((string)($input['google_client_secret'] ?? ''));
    $redirectUri = trim((string)($input['google_redirect_uri'] ?? ''));

    if (empty($clientId) || empty($clientSecret)) {
        json_response(['success' => false, 'message' => 'Google Client ID and Client Secret are both required.'], 400);
    }

    SystemSetting::set('google_client_id', $clientId, 'youtube', 'Google OAuth 2.0 Client ID', 0);
    SystemSetting::set('google_client_secret', $clientSecret, 'youtube', 'Google OAuth 2.0 Client Secret', 0);
    if (!empty($redirectUri)) {
        SystemSetting::set('google_redirect_uri', $redirectUri, 'youtube', 'Authorized Redirect URI Override', 0);
    }

    log_audit('youtube_settings_updated', null, $admin['id'], 'system_settings', null);

    json_response([
        'success' => true,
        'message' => 'Google OAuth 2.0 credentials saved successfully.'
    ]);
}

json_response(['success' => false, 'message' => 'Invalid YouTube API action.'], 400);
