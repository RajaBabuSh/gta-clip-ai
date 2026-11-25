<?php
// app/models/YouTubeAccount.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/YouTubeOAuthService.php';

class YouTubeAccount {
    public static function getConnectedAccount(string $userId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM youtube_accounts 
            WHERE user_id = ? AND is_connected = 1 
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getDecryptedAccount(string $userId): ?array {
        $account = self::getConnectedAccount($userId);
        if (!$account) return null;

        $account['access_token'] = YouTubeOAuthService::decryptToken($account['access_token'] ?? '');
        if (!empty($account['refresh_token'])) {
            $account['refresh_token'] = YouTubeOAuthService::decryptToken($account['refresh_token']);
        }
        return $account;
    }

    public static function saveOrUpdateAccount(
        string $userId,
        string $channelId,
        string $channelTitle,
        ?string $thumbnailUrl,
        int $subscriberCount,
        string $accessToken,
        ?string $refreshToken,
        ?string $expiresAt,
        string $scope = 'https://www.googleapis.com/auth/youtube.upload'
    ): string {
        $pdo = Database::getConnection();

        // Encrypt tokens at rest (AES-256)
        $encAccessToken = YouTubeOAuthService::encryptToken($accessToken);
        $encRefreshToken = $refreshToken !== null ? YouTubeOAuthService::encryptToken($refreshToken) : null;

        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM youtube_accounts WHERE user_id = ? AND channel_id = ?");
        $stmt->execute([$userId, $channelId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare("
                UPDATE youtube_accounts SET
                    channel_title = ?,
                    channel_thumbnail_url = ?,
                    subscriber_count = ?,
                    access_token = ?,
                    refresh_token = COALESCE(?, refresh_token),
                    token_expires_at = ?,
                    scope = ?,
                    is_connected = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $channelTitle,
                $thumbnailUrl,
                $subscriberCount,
                $encAccessToken,
                $encRefreshToken,
                $expiresAt,
                $scope,
                $existingId
            ]);
            return (string)$existingId;
        }

        $id = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("
            INSERT INTO youtube_accounts (
                id, user_id, channel_id, channel_title, channel_thumbnail_url,
                subscriber_count, access_token, refresh_token, token_expires_at,
                scope, is_connected, connected_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $id,
            $userId,
            $channelId,
            $channelTitle,
            $thumbnailUrl,
            $subscriberCount,
            $encAccessToken,
            $encRefreshToken,
            $expiresAt,
            $scope
        ]);
        return $id;
    }

    public static function disconnect(string $userId): bool {
        $pdo = Database::getConnection();
        $account = self::getConnectedAccount($userId);
        if ($account) {
            // Revoke with Google if possible
            $token = YouTubeOAuthService::decryptToken($account['access_token'] ?? '');
            if (!empty($token)) {
                YouTubeOAuthService::revokeToken($token);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE youtube_accounts 
            SET is_connected = 0, access_token = '', refresh_token = NULL, updated_at = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }
}
