<?php
// app/models/Project.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Project {
    public static function getByUser(string $userId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function findById(string $id, ?string $userId = null): ?array {
        $pdo = Database::getConnection();
        if ($userId) {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$id]);
        }
        return $stmt->fetch() ?: null;
    }

    public static function create(string $userId, string $title, ?string $description = null, string $category = 'Gaming'): string {
        $pdo = Database::getConnection();
        $id = 'prj_' . bin2hex(random_bytes(10));
        $stmt = $pdo->prepare("INSERT INTO projects (id, user_id, title, description, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id, $userId, $title, $description, $category]);
        return $id;
    }

    public static function update(string $id, string $userId, string $title, ?string $description = null, string $category = 'Gaming'): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, category = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$title, $description, $category, $id, $userId]);
    }

    public static function delete(string $id, string $userId): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public static function recalculateCounts(string $projectId): void {
        $pdo = Database::getConnection();
        $vCount = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE project_id = ?");
        $vCount->execute([$projectId]);
        $videos = $vCount->fetchColumn();

        $cCount = $pdo->prepare("SELECT COUNT(c.id) FROM clips c JOIN videos v ON c.video_id = v.id WHERE v.project_id = ?");
        $cCount->execute([$projectId]);
        $clips = $cCount->fetchColumn();

        $upd = $pdo->prepare("UPDATE projects SET total_videos = ?, total_clips = ? WHERE id = ?");
        $upd->execute([$videos, $clips, $projectId]);
    }
}
