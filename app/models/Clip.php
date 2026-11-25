<?php
// app/models/Clip.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Clip {
    public static function findById(string $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, c.file_size_bytes AS file_size, v.title AS video_title, v.youtube_id, v.channel_title
            FROM clips c
            LEFT JOIN videos v ON c.video_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $clip = $stmt->fetch();
        if ($clip) {
            self::populateUrls($clip);
        }
        return $clip ?: null;
    }

    private static function populateUrls(array &$clip): void {
        $clip['stream_url'] = url('api/stream.php?id=' . urlencode($clip['id']));
        $clip['video_url'] = self::formatUrl($clip['file_path']) ?: $clip['stream_url'];
        if (!empty($clip['thumbnail_path'])) {
            $clip['thumbnail_url'] = self::formatUrl($clip['thumbnail_path']);
        } else {
            $clip['thumbnail_url'] = url('api/stream.php?thumb=1&id=' . urlencode($clip['id']));
        }
    }

    public static function getByUser(string $userId, int $limit = 50, ?string $videoId = null): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT c.*, c.file_size_bytes AS file_size, v.title AS video_title, v.youtube_id, v.channel_title
            FROM clips c
            LEFT JOIN videos v ON c.video_id = v.id
            WHERE (c.user_id = ? OR v.user_id = ?) " . ($videoId ? "AND c.video_id = ? " : "") . "
            ORDER BY c.created_at DESC LIMIT ?
        ";
        $stmt = $pdo->prepare($sql);
        if ($videoId) {
            $stmt->bindValue(1, $userId);
            $stmt->bindValue(2, $userId);
            $stmt->bindValue(3, $videoId);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(1, $userId);
            $stmt->bindValue(2, $userId);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $clips = $stmt->fetchAll();
        foreach ($clips as &$c) {
            self::populateUrls($c);
        }
        return $clips;
    }

    public static function getByJob(string $jobId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, c.file_size_bytes AS file_size, v.title AS video_title, v.youtube_id, v.channel_title
            FROM clips c
            LEFT JOIN videos v ON c.video_id = v.id
            WHERE c.job_id = ?
            ORDER BY c.clip_index ASC
        ");
        $stmt->execute([$jobId]);
        $clips = $stmt->fetchAll();
        foreach ($clips as &$c) {
            self::populateUrls($c);
        }
        return $clips;
    }

    public static function getByVideo(string $videoId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, c.file_size_bytes AS file_size, v.title AS video_title, v.youtube_id, v.channel_title
            FROM clips c
            LEFT JOIN videos v ON c.video_id = v.id
            WHERE c.video_id = ?
            ORDER BY c.start_time ASC
        ");
        $stmt->execute([$videoId]);
        $clips = $stmt->fetchAll();
        foreach ($clips as &$c) {
            self::populateUrls($c);
        }
        return $clips;
    }

    public static function formatUrl(?string $path): string {
        if (empty($path)) return '';
        $clean = str_replace('\\', '/', $path);
        return url($clean);
    }

    public static function countByUser(string $userId): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clips c LEFT JOIN videos v ON c.video_id = v.id WHERE c.user_id = ? OR v.user_id = ?");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function countAll(): int {
        $pdo = Database::getConnection();
        return (int)$pdo->query("SELECT COUNT(*) FROM clips")->fetchColumn();
    }

    public static function getTotalStorageBytes(?string $userId = null): int {
        $pdo = Database::getConnection();
        if ($userId) {
            $stmt = $pdo->prepare("SELECT SUM(c.file_size_bytes) FROM clips c LEFT JOIN videos v ON c.video_id = v.id WHERE c.user_id = ? OR v.user_id = ?");
            $stmt->execute([$userId, $userId]);
            return (int)($stmt->fetchColumn() ?: 0);
        }
        return (int)($pdo->query("SELECT SUM(file_size_bytes) FROM clips")->fetchColumn() ?: 0);
    }

    public static function updateMetadata(string $id, array $data): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE clips SET
                title = COALESCE(?, title),
                description = COALESCE(?, description),
                hashtags = COALESCE(?, hashtags),
                editor_settings_json = COALESCE(?, editor_settings_json)
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['title'] ?? null,
            $data['description'] ?? null,
            isset($data['hashtags']) ? (is_array($data['hashtags']) ? json_encode($data['hashtags']) : $data['hashtags']) : null,
            isset($data['editor_settings_json']) ? (is_array($data['editor_settings_json']) ? json_encode($data['editor_settings_json']) : $data['editor_settings_json']) : null,
            $id
        ]);
    }

    public static function saveSeoMetadata(string $clipId, array $seo): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM clip_metadata WHERE clip_id = ?");
        $stmt->execute([$clipId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE clip_metadata SET
                    youtube_title = ?,
                    instagram_caption = ?,
                    description = ?,
                    hashtags = ?,
                    keywords = ?,
                    suggested_category = ?,
                    thumbnail_text = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([
                $seo['youtube_title'] ?? '',
                $seo['instagram_caption'] ?? '',
                $seo['description'] ?? '',
                json_encode($seo['hashtags'] ?? []),
                json_encode($seo['keywords'] ?? []),
                $seo['suggested_category'] ?? 'Gaming',
                $seo['thumbnail_text'] ?? '',
                $existing
            ]);
        }

        $id = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("
            INSERT INTO clip_metadata (
                id, clip_id, youtube_title, instagram_caption, description,
                hashtags, keywords, suggested_category, thumbnail_text, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        return $stmt->execute([
            $id,
            $clipId,
            $seo['youtube_title'] ?? '',
            $seo['instagram_caption'] ?? '',
            $seo['description'] ?? '',
            json_encode($seo['hashtags'] ?? []),
            json_encode($seo['keywords'] ?? []),
            $seo['suggested_category'] ?? 'Gaming',
            $seo['thumbnail_text'] ?? ''
        ]);
    }

    public static function getSeoMetadata(string $clipId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM clip_metadata WHERE clip_id = ? LIMIT 1");
        $stmt->execute([$clipId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['hashtags'] = json_decode($row['hashtags'] ?: '[]', true);
            $row['keywords'] = json_decode($row['keywords'] ?: '[]', true);
        }
        return $row ?: null;
    }
}
