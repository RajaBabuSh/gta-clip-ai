<?php
// app/models/YouTubeUpload.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class YouTubeUpload {
    public static function createRecord(array $data): string {
        $pdo = Database::getConnection();
        $id = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO youtube_uploads (
                id, user_id, clip_id, youtube_account_id, youtube_video_id,
                youtube_url, title, description, privacy_status, upload_status,
                error_message, retry_count, published_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $id,
            $data['user_id'],
            $data['clip_id'],
            $data['youtube_account_id'] ?? null,
            $data['youtube_video_id'] ?? null,
            $data['youtube_url'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['privacy_status'] ?? 'private',
            $data['upload_status'] ?? 'pending',
            $data['error_message'] ?? null,
            $data['retry_count'] ?? 0,
            $data['published_at'] ?? null
        ]);

        return $id;
    }

    public static function getByUser(string $userId, int $limit = 50): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, c.title AS clip_title, c.file_path, c.thumbnail_path, c.duration, c.virality_score
            FROM youtube_uploads u
            LEFT JOIN clips c ON u.clip_id = c.id
            WHERE u.user_id = ?
            ORDER BY u.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function updateStatus(
        string $id,
        string $status,
        ?string $videoId = null,
        ?string $videoUrl = null,
        ?string $error = null
    ): bool {
        $pdo = Database::getConnection();
        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            UPDATE youtube_uploads SET
                upload_status = ?,
                youtube_video_id = COALESCE(?, youtube_video_id),
                youtube_url = COALESCE(?, youtube_url),
                error_message = ?,
                published_at = COALESCE(?, published_at)
            WHERE id = ?
        ");

        return $stmt->execute([
            $status,
            $videoId,
            $videoUrl,
            $error,
            $publishedAt,
            $id
        ]);
    }
}
