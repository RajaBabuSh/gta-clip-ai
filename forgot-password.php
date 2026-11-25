<?php
// forgot-password.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = "If an account exists with $email, password reset instructions have been dispatched.";
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/views/partials/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;">
  <div class="glass-card" style="width: 100%; max-width: 440px; padding: 40px; border-radius: 20px;">
    <div style="text-align: center; margin-bottom: 28px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 52px; height: 52px; font-size: 26px;">🔑</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Reset Password</h1>
      <p style="font-size: 14px; color: var(--text-muted); margin-top: 6px;">Enter your email to receive recovery instructions</p>
    </div>

    <?php if ($message): ?>
      <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Account Email</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
        Send Reset Link
      </button>

      <div style="text-align: center; margin-top: 24px; font-size: 13px;">
        <a href="<?= url('login.php') ?>" style="color: #c4b5fd; text-decoration: none;">← Back to Sign In</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
