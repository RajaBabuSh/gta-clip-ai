<?php
// app/services/YouTubeOAuthService.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SystemSetting.php';
require_once __DIR__ . '/../helpers/functions.php';

class YouTubeOAuthService {
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const YOUTUBE_CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';
    private const YOUTUBE_UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public const SCOPE_UPLOAD = 'https://www.googleapis.com/auth/youtube.upload';
    public const SCOPE_READONLY = 'https://www.googleapis.com/auth/youtube.readonly';

    /**
     * Get Google OAuth 2.0 Client ID
     */
    public static function getClientId(): string {
        $val = SystemSetting::get('google_client_id');
        if (!empty($val)) return trim((string)$val);
        return trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''));
    }

    /**
     * Get Google OAuth 2.0 Client Secret
     */
    public static function getClientSecret(): string {
        $val = SystemSetting::get('google_client_secret');
        if (!empty($val)) return trim((string)$val);
        return trim((string)($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: ''));
    }

    /**
     * Get configured or auto-detected OAuth 2.0 Redirect URI
     */
    public static function getRedirectUri(): string {
        $override = SystemSetting::get('google_redirect_uri');
        if (!empty($override)) return trim((string)$override);
        $envUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI');
        if (!empty($envUri)) return trim((string)$envUri);

        $base = defined('APP_URL') ? APP_URL : 'http://localhost/New%20folder';
        return rtrim($base, '/') . '/api/youtube.php?action=callback';
    }

    /**
     * Check if Google OAuth credentials are fully configured
     */
    public static function isConfigured(): bool {
        return !empty(self::getClientId()) && !empty(self::getClientSecret());
    }

    /**
     * Get or initialize the encryption key for token storage at rest
     */
    public static function getEncryptionKey(): string {
        $key = SystemSetting::get('oauth_encryption_key');
        if (!empty($key)) return (string)$key;

        $envKey = $_ENV['OAUTH_ENCRYPTION_KEY'] ?? getenv('OAUTH_ENCRYPTION_KEY');
        if (!empty($envKey)) return (string)$envKey;

        // Auto-generate a secure 256-bit key and persist it in system settings
        $generatedKey = bin2hex(random_bytes(32));
        SystemSetting::set('oauth_encryption_key', $generatedKey, 'security', 'AES-256 key for encrypting OAuth tokens at rest', 0);
        return $generatedKey;
    }

    /**
     * Encrypt sensitive token at rest using AES-256-CBC with HMAC-SHA256
     */
    public static function encryptToken(string $plainText): string {
        if ($plainText === '') return '';
        $key = hash('sha256', self::getEncryptionKey(), true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $ciphertext = openssl_encrypt($plainText, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

    /**
     * Decrypt sensitive token from at-rest storage
     */
    public static function decryptToken(string $encryptedData): string {
        if ($encryptedData === '') return '';
        $raw = base64_decode($encryptedData, true);
        if ($raw === false) return $encryptedData; // Fallback if plain text

        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($raw) < $ivLen + 32) return $encryptedData; // Not encrypted with this method

        $key = hash('sha256', self::getEncryptionKey(), true);
        $iv = substr($raw, 0, $ivLen);
        $hmac = substr($raw, $ivLen, 32);
        $ciphertext = substr($raw, $ivLen + 32);

        $expectedHmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            // HMAC mismatch - might be unencrypted legacy token
            return $encryptedData;
        }

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $encryptedData;
    }

    /**
     * Build the official Google OAuth 2.0 Authorization URL
     */
    public static function getAuthorizationUrl(string $state): string {
        $clientId = self::getClientId();
        if (empty($clientId)) {
            throw new Exception("Google Client ID is not configured. Please configure it in settings.");
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => self::getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE_UPLOAD . ' ' . self::SCOPE_READONLY,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state
        ];

        return self::OAUTH_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange Google OAuth authorization code for Access & Refresh tokens
     */
    public static function exchangeCode(string $code): array {
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();
        $redirectUri = self::getRedirectUri();

        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception("Google OAuth 2.0 credentials (Client ID or Client Secret) are missing.");
        }

        $postFields = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init(self::OAUTH_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Failed to connect to Google OAuth server: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !empty($data['error'])) {
            $errDesc = $data['error_description'] ?? $data['error'] ?? 'Unknown token error';
            throw new Exception("Google OAuth token exchange failed: " . $errDesc);
        }

        if (empty($data['access_token'])) {
            throw new Exception("Google OAuth response did not contain an access token.");
        }

        return $data;
    }

    /**
     * Refresh an expired Google access token using the stored refresh token
     */
    public static function refreshAccessToken(string $refreshToken): array {
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception("Google OAuth credentials missing for token refresh.");
        }

        $postFields = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init(self::OAUTH_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Failed to contact Google OAuth for token refresh: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !empty($data['error'])) {
            $errDesc = $data['error_description'] ?? $data['error'] ?? 'Refresh failed';
            throw new Exception("Google OAuth token refresh failed: " . $errDesc);
        }

        return $data;
    }

    /**
     * Fetch the authorized user's primary YouTube Channel profile
     */
    public static function fetchChannelProfile(string $accessToken): array {
        $url = self::YOUTUBE_CHANNELS_URL . '?part=snippet,contentDetails,statistics&mine=true';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Failed to query YouTube Data API: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !empty($data['error'])) {
            $msg = $data['error']['message'] ?? 'Unable to fetch YouTube channel details.';
            throw new Exception("YouTube Data API Error ({$httpCode}): " . $msg);
        }

        if (empty($data['items'])) {
            throw new Exception("No YouTube channel found associated with this Google Account. Please create a channel on YouTube first.");
        }

        $item = $data['items'][0];
        $snippet = $item['snippet'] ?? [];
        $stats = $item['statistics'] ?? [];
        $thumbs = $snippet['thumbnails'] ?? [];

        $thumbUrl = $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null;

        return [
            'channel_id' => $item['id'],
            'channel_title' => $snippet['title'] ?? 'My YouTube Channel',
            'channel_description' => $snippet['description'] ?? '',
            'channel_thumbnail_url' => $thumbUrl,
            'subscriber_count' => (int)($stats['subscriberCount'] ?? 0),
            'video_count' => (int)($stats['videoCount'] ?? 0)
        ];
    }

    /**
     * Revoke access/refresh token with Google
     */
    public static function revokeToken(string $token): bool {
        if (empty($token)) return true;
        try {
            $ch = curl_init(self::OAUTH_REVOKE_URL . '?token=' . urlencode($token));
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 10
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 200;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ensure account has a fresh valid access token (auto-refreshes if needed)
     */
    public static function getValidAccessToken(array $ytAccount): string {
        $accessToken = self::decryptToken($ytAccount['access_token'] ?? '');
        $refreshToken = self::decryptToken($ytAccount['refresh_token'] ?? '');
        $expiresAt = $ytAccount['token_expires_at'] ?? null;

        $isExpired = false;
        if (!empty($expiresAt)) {
            // Expire if less than 120 seconds remain
            $isExpired = (strtotime($expiresAt) - time()) < 120;
        }

        if ($isExpired && !empty($refreshToken)) {
            try {
                $refreshed = self::refreshAccessToken($refreshToken);
                $newAccessToken = $refreshed['access_token'];
                $expiresIn = (int)($refreshed['expires_in'] ?? 3600);
                $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                // Update database with encrypted token
                $encAccessToken = self::encryptToken($newAccessToken);
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("UPDATE youtube_accounts SET access_token = ?, token_expires_at = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$encAccessToken, $newExpiresAt, $ytAccount['id']]);

                return $newAccessToken;
            } catch (Exception $e) {
                error_log("YouTube token auto-refresh failed: " . $e->getMessage());
                // Fallback to existing token if refresh failed temporarily
                return $accessToken;
            }
        }

        return $accessToken;
    }

    /**
     * Perform official Google YouTube Resumable Upload
     */
    public static function uploadVideoResumable(
        string $accessToken,
        string $filePath,
        string $title,
        string $description,
        string $privacyStatus = 'private',
        array $tags = []
    ): array {
        if (!file_exists($filePath)) {
            throw new Exception("Source video file does not exist on disk: " . basename($filePath));
        }

        $fileSize = filesize($filePath);
        if ($fileSize <= 0) {
            throw new Exception("Video file is empty (0 bytes).");
        }

        // Validate privacy
        $validPrivacy = ['private', 'unlisted', 'public'];
        if (!in_array($privacyStatus, $validPrivacy, true)) {
            $privacyStatus = 'private';
        }

        // 1. Prepare video metadata
        $metadata = [
            'snippet' => [
                'title' => mb_substr($title, 0, 100),
                'description' => $description,
                'tags' => !empty($tags) ? $tags : ['Shorts', 'Gaming'],
                'categoryId' => '20' // Gaming
            ],
            'status' => [
                'privacyStatus' => $privacyStatus,
                'selfDeclaredMadeForKids' => false,
                'embeddable' => true
            ]
        ];

        $jsonMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        // 2. Initiate Resumable Upload Session
        $initUrl = self::YOUTUBE_UPLOAD_URL . '?uploadType=resumable&part=snippet,status';

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Length: ' . $fileSize,
            'X-Upload-Content-Type: video/mp4'
        ];

        $responseHeaders = [];
        $ch = curl_init($initUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonMetadata,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
            CURLOPT_TIMEOUT => 30
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($responseHeaders['location'])) {
            $errData = json_decode($body ?: '', true);
            $errMsg = $errData['error']['message'] ?? $curlErr ?? 'Failed to initiate resumable upload session with YouTube';
            throw new Exception("YouTube Upload Initialization Error ({$httpCode}): " . $errMsg);
        }

        $resumableUploadUrl = $responseHeaders['location'];

        // 3. Upload the media file stream to the Resumable Upload URL
        $fh = fopen($filePath, 'rb');
        if (!$fh) {
            throw new Exception("Could not open video file for reading.");
        }

        $uploadCh = curl_init($resumableUploadUrl);
        curl_setopt_array($uploadCh, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: video/mp4',
                'Content-Length: ' . $fileSize
            ],
            CURLOPT_TIMEOUT => 600 // Allow up to 10 minutes for upload
        ]);

        $uploadResponse = curl_exec($uploadCh);
        $uploadHttpCode = curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);
        $uploadCurlErr = curl_error($uploadCh);
        curl_close($uploadCh);
        fclose($fh);

        if ($uploadHttpCode !== 200 && $uploadHttpCode !== 201) {
            $errData = json_decode($uploadResponse ?: '', true);
            $errMsg = $errData['error']['message'] ?? $uploadCurlErr ?? "HTTP status {$uploadHttpCode}";
            throw new Exception("YouTube Upload Error ({$uploadHttpCode}): " . $errMsg);
        }

        $videoResource = json_decode($uploadResponse, true);
        if (empty($videoResource['id'])) {
            throw new Exception("YouTube upload succeeded but no video ID was returned.");
        }

        $videoId = $videoResource['id'];

        return [
            'success' => true,
            'video_id' => $videoId,
            'youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
            'shorts_url' => "https://www.youtube.com/shorts/{$videoId}",
            'privacy_status' => $privacyStatus,
            'title' => $videoResource['snippet']['title'] ?? $title
        ];
    }
}
