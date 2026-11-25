<?php
// login.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/User.php';

AuthMiddleware::requireGuest();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $error = 'Please enter your username/email and password.';
        } else {
            $user = User::authenticate($login, $password);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                log_audit('user_login', $user['id'], null, 'user', $user['id']);
                flash('success', 'Welcome back, ' . htmlspecialchars($user['full_name'] ?: $user['username']) . '!');
                $redirectUrl = $_SESSION['intended_url'] ?? 'dashboard.php';
                unset($_SESSION['intended_url']);
                if (empty($redirectUrl) || str_contains($redirectUrl, 'login.php') || str_contains($redirectUrl, 'logout.php')) {
                    $redirectUrl = 'dashboard.php';
                }
                redirect($redirectUrl);
            } else {
                $error = 'Invalid credentials or account suspended.';
            }
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/views/partials/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;">
  <div class="glass-card" style="width: 100%; max-width: 440px; padding: 40px; border-radius: 20px;">
    <div style="text-align: center; margin-bottom: 32px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 52px; height: 52px; font-size: 26px;">⚡</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Welcome Back</h1>
      <p style="font-size: 14px; color: var(--text-muted); margin-top: 6px;">Sign in to your EiziClip account</p>
    </div>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('login.php') ?>">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Email or Username</label>
        <input type="text" name="login" class="form-control" placeholder="user@gtaclip.ai" required autofocus value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
      </div>

      <div class="form-group">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <label class="form-label" style="margin-bottom: 0;">Password</label>
          <a href="<?= url('forgot-password.php') ?>" style="font-size: 12px; color: var(--secondary); text-decoration: none;">Forgot password?</a>
        </div>
        <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
        Sign In to Studio
      </button>

      <div style="text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-muted);">
        Don't have an account? <a href="<?= url('register.php') ?>" style="color: #c4b5fd; text-decoration: none; font-weight: 600;">Create Free Account</a>
      </div>

      <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center; font-size: 12px; color: var(--text-dim);">
        Default test login: <code style="color:var(--secondary);">user@gtaclip.ai</code> / <code style="color:var(--secondary);">User@12345</code>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
