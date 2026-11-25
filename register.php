<?php
// register.php
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
        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['username'] ?? ''));
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (!$email) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (User::findByEmail($email)) {
            $error = 'An account with this email already exists.';
        } elseif (User::findByUsername($username)) {
            $error = 'This username is already taken.';
        } else {
            $userId = User::create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'plan_tier' => 'free',
                'monthly_clip_quota' => 50
            ]);

            log_audit('user_register', $userId, null, 'user', $userId);
            $_SESSION['user_id'] = $userId;
            flash('success', 'Account created successfully! Welcome to EiziClip.');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/views/partials/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;">
  <div class="glass-card" style="width: 100%; max-width: 480px; padding: 40px; border-radius: 20px;">
    <div style="text-align: center; margin-bottom: 28px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 52px; height: 52px; font-size: 26px;">⚡</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Get Started Free</h1>
      <p style="font-size: 14px; color: var(--text-muted); margin-top: 6px;">Extract viral 9:16 Shorts from your streams in minutes</p>
    </div>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('register.php') ?>">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="streamer_pro" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
        Create My Account
      </button>

      <div style="text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-muted);">
        Already have an account? <a href="<?= url('login.php') ?>" style="color: #c4b5fd; text-decoration: none; font-weight: 600;">Sign In</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
