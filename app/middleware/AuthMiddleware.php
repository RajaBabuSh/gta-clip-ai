<?php
// app/middleware/AuthMiddleware.php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

class AuthMiddleware {
    public static function requireUser(): array {
        $user = get_auth_user();
        if (!$user) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response(['error' => 'Unauthorized', 'message' => 'Please sign in to continue.'], 401);
            }
            $reqUri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
            if (!str_contains($reqUri, 'login.php') && !str_contains($reqUri, 'logout.php')) {
                $_SESSION['intended_url'] = $reqUri;
            }
            flash('warning', 'Please sign in to access your dashboard.');
            redirect('login.php');
        }
        return $user;
    }

    public static function requireGuest(): void {
        if (get_auth_user()) {
            redirect('dashboard.php');
        }
    }
}
