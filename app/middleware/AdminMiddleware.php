<?php
// app/middleware/AdminMiddleware.php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class AdminMiddleware {
    public static function requireAdmin(): array {
        $admin = get_auth_admin();
        if (!$admin) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response(['error' => 'Unauthorized', 'message' => 'Admin authentication required.'], 401);
            }
            flash('danger', 'Administrator privileges required. Please sign in.');
            redirect('admin/login.php');
        }

        // Check session inactivity timeout
        $sessionLifetime = (int)SystemSetting::get('session_lifetime_minutes', 120);
        if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > ($sessionLifetime * 60))) {
            unset($_SESSION['admin_id'], $_SESSION['admin_last_activity']);
            flash('warning', 'Admin session expired due to inactivity. Please log in again.');
            redirect('admin/login.php');
        }
        $_SESSION['admin_last_activity'] = time();

        // Enforce mandatory password update if temporary password is active
        if (!empty($admin['force_password_change']) || !empty($_SESSION['admin_force_password_change'])) {
            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($script !== 'change-password.php' && $script !== 'logout.php') {
                flash('warning', 'Temporary password active. You must set a new permanent password to continue.');
                redirect('admin/change-password.php');
            }
        }

        return $admin;
    }

    public static function requireSuperAdmin(): array {
        $admin = self::requireAdmin();
        if ($admin['role'] !== 'superadmin') {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response(['error' => 'Forbidden', 'message' => 'Super Administrator access required.'], 403);
            }
            flash('danger', 'Access denied. This section requires Super Administrator privileges.');
            redirect('admin/index.php');
        }
        return $admin;
    }

    public static function requirePermission(string $permissionSlug): array {
        $admin = self::requireAdmin();
        if (!self::hasPermission($permissionSlug, $admin)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response(['error' => 'Forbidden', 'message' => "Permission '{$permissionSlug}' required."], 403);
            }
            flash('danger', "Access denied. You do not have permission ({$permissionSlug}) to access this resource.");
            redirect('admin/index.php');
        }
        return $admin;
    }

    public static function hasPermission(string $permissionSlug, ?array $admin = null): bool {
        if ($admin === null) {
            $admin = get_auth_admin();
        }
        if (!$admin) {
            return false;
        }
        if ($admin['role'] === 'superadmin') {
            return true;
        }

        static $cachedPermissions = [];
        $adminId = $admin['id'];
        if (!isset($cachedPermissions[$adminId])) {
            $cachedPermissions[$adminId] = Admin::getPermissions($adminId, $admin['role']);
        }

        return in_array($permissionSlug, $cachedPermissions[$adminId], true);
    }

    public static function requireAdminGuest(): void {
        if (get_auth_admin()) {
            redirect('admin/index.php');
        }
    }
}
