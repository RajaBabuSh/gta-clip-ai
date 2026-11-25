<?php
// app/models/User.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class User {
    public static function findById(string $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByEmail(string $email): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByUsername(string $username): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): string {
        $pdo = Database::getConnection();
        $id = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO users (id, username, email, password_hash, full_name, plan_tier, monthly_clip_quota, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $id,
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['full_name'] ?? $data['username'],
            $data['plan_tier'] ?? 'free',
            $data['monthly_clip_quota'] ?? 50
        ]);
        return $id;
    }

    public static function authenticate(string $login, string $password): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                return null;
            }
            return $user;
        }
        return null;
    }

    public static function getAll(int $limit = 50, int $offset = 0): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function search(string $query = '', ?string $status = null, ?string $tier = null, int $limit = 50, int $offset = 0): array {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM users WHERE 1=1 ";
        $params = [];

        if (!empty($query)) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?) ";
            $like = "%{$query}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($status)) {
            $sql .= " AND status = ? ";
            $params[] = $status;
        }

        if (!empty($tier)) {
            $sql .= " AND plan_tier = ? ";
            $params[] = $tier;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $stmt = $pdo->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countFiltered(string $query = '', ?string $status = null, ?string $tier = null): int {
        $pdo = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM users WHERE 1=1 ";
        $params = [];

        if (!empty($query)) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?) ";
            $like = "%{$query}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($status)) {
            $sql .= " AND status = ? ";
            $params[] = $status;
        }

        if (!empty($tier)) {
            $sql .= " AND plan_tier = ? ";
            $params[] = $tier;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function countAll(): int {
        $pdo = Database::getConnection();
        return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public static function updateStatus(string $id, string $status): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function updateQuota(string $id, int $quota): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET monthly_clip_quota = ? WHERE id = ?");
        return $stmt->execute([$quota, $id]);
    }

    public static function update(string $id, array $data): bool {
        $pdo = Database::getConnection();
        $fields = [];
        $params = [];

        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['plan_tier'])) {
            $fields[] = "plan_tier = ?";
            $params[] = $data['plan_tier'];
        }
        if (isset($data['monthly_clip_quota'])) {
            $fields[] = "monthly_clip_quota = ?";
            $params[] = (int)$data['monthly_clip_quota'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function resetPassword(string $id, string $newPassword): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
    }

    public static function delete(string $id, bool $cascade = false): bool {
        $pdo = Database::getConnection();
        if ($cascade) {
            // Find and delete clips and physical files
            $clipStmt = $pdo->prepare("SELECT id, video_path, thumbnail_path FROM clips WHERE user_id = ?");
            $clipStmt->execute([$id]);
            $clips = $clipStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clips as $c) {
                if (!empty($c['video_path']) && file_exists($c['video_path'])) {
                    @unlink($c['video_path']);
                }
                if (!empty($c['thumbnail_path']) && file_exists($c['thumbnail_path'])) {
                    @unlink($c['thumbnail_path']);
                }
            }

            // Delete database rows
            $pdo->prepare("DELETE FROM clips WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM processing_jobs WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM projects WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM videos WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM cloud_cost_records WHERE user_id = ?")->execute([$id]);
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function incrementClipsCount(string $userId, int $count = 1): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET clips_generated_this_month = clips_generated_this_month + ? WHERE id = ?");
        $stmt->execute([$count, $userId]);
    }
}
