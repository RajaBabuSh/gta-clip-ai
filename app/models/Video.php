<?php
// app/models/Video.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Video {
    public static function findById(string $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT v.*, v.duration_seconds AS duration, v.file_size_bytes AS file_size FROM videos v WHERE v.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByYoutubeId(string $youtubeId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT v.*, v.duration_seconds AS duration, v.file_size_bytes AS file_size FROM videos v WHERE v.youtube_id = ? ORDER BY v.created_at DESC LIMIT 1");
        $stmt->execute([$youtubeId]);
        return $stmt->fetch() ?: null;
    }

    public static function getByUser(string $userId, int $limit = 50): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT v.*, v.duration_seconds AS duration, v.file_size_bytes AS file_size FROM videos v WHERE v.user_id = ? ORDER BY v.created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getRecent(int $limit = 10): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT v.*, v.duration_seconds AS duration, v.file_size_bytes AS file_size FROM videos v ORDER BY v.created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countByUser(string $userId): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function countAll(): int {
        $pdo = Database::getConnection();
        return (int)$pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
    }

    public static function create(array $data): string {
        $pdo = Database::getConnection();
        $id = $data['id'] ?? generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO videos (
                id, user_id, project_id, source_type, platform, title,
                original_filename, stored_filename, file_path, file_size_bytes, mime_type,
                duration_seconds, width, height, fps, youtube_id,
                youtube_url, channel_title, thumbnail_url, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $id,
            $data['user_id'] ?? null,
            $data['project_id'] ?? null,
            strtoupper($data['source_type'] ?? 'YOUTUBE'),
            $data['platform'] ?? 'youtube',
            $data['title'],
            $data['original_filename'] ?? ($data['title'] . '.mp4'),
            $data['stored_filename'] ?? ($id . '.mp4'),
            $data['file_path'] ?? null,
            (int)($data['file_size_bytes'] ?? $data['file_size'] ?? 0),
            $data['mime_type'] ?? 'video/mp4',
            (float)($data['duration_seconds'] ?? $data['duration'] ?? 0),
            (int)($data['width'] ?? 1920),
            (int)($data['height'] ?? 1080),
            (float)($data['fps'] ?? 30.0),
            $data['youtube_id'] ?? null,
            $data['original_url'] ?? $data['youtube_url'] ?? null,
            $data['channel_title'] ?? 'Direct Upload',
            $data['thumbnail_url'] ?? null,
            $data['status'] ?? 'ready'
        ]);
        return $id;
    }

    public static function update(string $id, array $data): bool {
        if (empty($data)) return false;
        $pdo = Database::getConnection();
        $fields = [];
        $values = [];
        foreach ($data as $col => $val) {
            $fields[] = "`$col` = ?";
            $values[] = $val;
        }
        $values[] = $id;
        $sql = "UPDATE videos SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }
}
