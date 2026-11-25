<?php
// app/models/Admin.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Admin {
    public static function findById(string $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, force_password_change, status, last_login_at, last_login_ip, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByEmailOrUsername(string $login): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ?");
        $stmt->execute([$login, $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function authenticate(string $login, string $password): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ?");
        $stmt->execute([$login, $login]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify($password, $admin['password_hash'])) {
            if ($admin['status'] !== 'active') {
                return null;
            }
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $upd = $pdo->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
            $upd->execute([$ip, $admin['id']]);

            self::logAction($admin['id'], 'admin.login', "Successful login from IP {$ip}");
            return $admin;
        }
        return null;
    }

    public static function getAll(): array {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT id, username, email, full_name, role, force_password_change, status, last_login_at, created_at FROM admins ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countAll(): int {
        $pdo = Database::getConnection();
        return (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    }

    public static function countSuperAdmins(): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin' AND status = 'active'");
        return (int)$stmt->fetchColumn();
    }

    public static function hasSuperAdmin(): bool {
        return self::countSuperAdmins() > 0;
    }

    public static function getSuperAdmin(): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, username, email, full_name, role, force_password_change, status, last_login_at, created_at, updated_at FROM admins WHERE role = 'superadmin' AND status = 'active' ORDER BY created_at ASC LIMIT 1");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public static function changePassword(string $adminId, string $newPassword): bool {
        $pdo = Database::getConnection();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, force_password_change = 0, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$hash, $adminId]);
        if ($ok) {
            self::logAction($adminId, 'admin.password_change', "Administrator password was successfully updated");
        }
        return $ok;
    }

    public static function setTemporaryPassword(string $adminId, string $tempPassword): bool {
        $pdo = Database::getConnection();
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, force_password_change = 1, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$hash, $adminId]);
        if ($ok) {
            self::logAction($adminId, 'admin.temp_password_generated', "Temporary password generated with force reset enabled");
        }
        return $ok;
    }

    public static function create(array $data): string {
        $pdo = Database::getConnection();
        $id = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO admins (id, username, email, password_hash, full_name, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['full_name'] ?? null,
            $data['role'] ?? 'admin',
            $data['status'] ?? 'active'
        ]);

        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            self::setPermissions($id, $data['permissions']);
        }

        return $id;
    }

    public static function update(string $id, array $data): bool {
        $pdo = Database::getConnection();
        $fields = [];
        $params = [];

        if (isset($data['username'])) {
            $fields[] = "username = ?";
            $params[] = $data['username'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        if (!empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute($params);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            self::setPermissions($id, $data['permissions']);
        }

        return $res;
    }

    public static function delete(string $id): bool {
        $pdo = Database::getConnection();
        // Prevent deleting the last active superadmin
        $admin = self::findById($id);
        if ($admin && $admin['role'] === 'superadmin' && self::countSuperAdmins() <= 1) {
            throw new Exception("Cannot delete the only remaining Super Administrator.");
        }
        // Remove admin_permissions
        $delPerm = $pdo->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
        $delPerm->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getPermissions(string $adminId, ?string $role = null): array {
        $pdo = Database::getConnection();
        if ($role === null) {
            $admin = self::findById($adminId);
            $role = $admin['role'] ?? 'viewer';
        }

        // If superadmin, has all permissions
        if ($role === 'superadmin') {
            $allPerms = $pdo->query("SELECT slug FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
            return $allPerms;
        }

        // 1. Role base permissions
        $stmtRole = $pdo->prepare("SELECT permission_slug FROM role_permissions WHERE role_slug = ?");
        $stmtRole->execute([$role]);
        $rolePerms = $stmtRole->fetchAll(PDO::FETCH_COLUMN);

        // 2. Specific admin explicit grants
        $stmtAdmin = $pdo->prepare("SELECT permission_slug FROM admin_permissions WHERE admin_id = ?");
        $stmtAdmin->execute([$adminId]);
        $adminPerms = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_merge($rolePerms, $adminPerms)));
    }

    public static function setPermissions(string $adminId, array $permissionSlugs): bool {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
            $del->execute([$adminId]);

            if (!empty($permissionSlugs)) {
                $ins = $pdo->prepare("INSERT INTO admin_permissions (admin_id, permission_slug) VALUES (?, ?)");
                foreach ($permissionSlugs as $slug) {
                    $ins->execute([$adminId, trim($slug)]);
                }
            }
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("setPermissions Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getAllRoles(): array {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllPermissions(): array {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM permissions ORDER BY category ASC, slug ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function logAction(?string $adminId, string $action, ?string $details = null, ?string $userId = null): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (id, admin_id, user_id, action, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                generate_uuid(),
                $adminId,
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255)
            ]);
        } catch (Exception $e) {
            error_log("logAction Error: " . $e->getMessage());
        }
    }

    public static function getAuditLogs(int $limit = 50, int $offset = 0, ?string $filterAction = null): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT a.*, adm.username as admin_username, u.email as user_email
            FROM audit_logs a
            LEFT JOIN admins adm ON a.admin_id = adm.id
            LEFT JOIN users u ON a.user_id = u.id
        ";
        $params = [];
        if (!empty($filterAction)) {
            $sql .= " WHERE a.action = ? ";
            $params[] = $filterAction;
        }
        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        
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
}
