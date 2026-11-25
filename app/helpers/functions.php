<?php
// app/helpers/functions.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function url(string $path = ''): string {
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        // Strip any duplicated nested folder in absolute URLs
        return preg_replace('#/(?:new(?:\s|%20)folder/)+#i', '/New%20folder/', $path);
    }

    $cleanPath = '/' . ltrim($path, '/');
    
    // Normalize and strip any leading occurrences of New folder (case-insensitive, encoded or space)
    $normalized = preg_replace('#^/(?:new(?:%20|[\s+])folder/?)+#i', '/', $cleanPath);

    // Also strip configured BASE_PATH if present
    if (defined('BASE_PATH') && BASE_PATH !== '' && BASE_PATH !== '/') {
        $bpEsc = preg_quote(ltrim(BASE_PATH, '/'), '#');
        $normalized = preg_replace("#^/(?:$bpEsc/?)+#i", '/', $normalized);
    }

    $finalRel = ltrim($normalized, '/');
    return APP_URL . ($finalRel !== '' ? '/' . $finalRel : '');
}

function asset(string $path): string {
    return url('assets/' . ltrim($path, '/'));
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function format_bytes(int|float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function format_duration(float|int $seconds): string {
    $seconds = (int)round($seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%02d:%02d', $minutes, $secs);
}

function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) return 'recently';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function redirect(string $path): void {
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        $path = preg_replace('#/(?:new(?:\s|%20)folder/)+#i', '/New%20folder/', $path);
        header("Location: $path");
        exit;
    }
    $target = url($path);
    header("Location: $target");
    exit;
}

function generate_uuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function get_auth_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, status, plan_tier, monthly_clip_quota, clips_generated_this_month, avatar_url FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && $user['status'] === 'active') {
            $cachedUser = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log("Auth User Error: " . $e->getMessage());
    }
    return null;
}

function get_auth_admin(): ?array {
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $cachedAdmin = null;
    if ($cachedAdmin !== null) {
        return $cachedAdmin;
    }
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, force_password_change, status FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if ($admin && $admin['status'] === 'active') {
            $cachedAdmin = $admin;
            return $admin;
        }
    } catch (Exception $e) {
        error_log("Auth Admin Error: " . $e->getMessage());
    }
    return null;
}

function log_audit(string $action, ?string $userId = null, ?string $adminId = null, ?string $entityType = null, ?string $entityId = null, ?array $details = null): void {
    try {
        $pdo = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System';
        $fullDetails = array_merge([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $details ?? []);

        $stmt = $pdo->prepare("INSERT INTO audit_logs (id, user_id, admin_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            generate_uuid(),
            $userId,
            $adminId,
            $action,
            json_encode($fullDetails),
            $ip,
            $ua
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
