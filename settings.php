<?php
// settings.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/User.php';

$user = AuthMiddleware::requireUser();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Session expired. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        
        $pdo = Database::getConnection();
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$fullName, password_hash($newPassword, PASSWORD_BCRYPT), $user['id']]);
                $message = 'Profile and password updated successfully!';
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $stmt->execute([$fullName, $user['id']]);
            $message = 'Profile updated successfully!';
        }
        $user = User::findById($user['id']);
    }
}

$activeNav = 'settings';
$pageTitle = 'Account Settings';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Settings / </span>
        <span style="font-size:14px; font-weight:700; color:white;">User Preferences</span>
      </div>
      <div class="topbar-actions">
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container" style="max-width: 800px; margin: 0 auto;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Account Settings</h1>
          <p class="page-desc">Manage your creator profile, security credentials and subscription quotas.</p>
        </div>
      </div>

      <?php if ($message): ?>
        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <!-- Profile Form -->
      <div class="glass-card" style="padding: 32px; margin-bottom: 28px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Creator Profile</h2>
        <form method="POST">
          <?= csrf_field() ?>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name / Display Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Change Password (leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••••••••">
          </div>

          <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
            Update Profile Settings
          </button>
        </form>
      </div>

      <!-- Plan Quota Card -->
      <div class="glass-card" style="padding: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div class="badge badge-purple" style="margin-bottom: 8px;"><?= strtoupper($user['plan_tier']) ?> PLAN</div>
            <h2 style="font-size: 20px; font-weight: 800;">Monthly Clipping Quota</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
              <?= $user['clips_generated_this_month'] ?> of <?= $user['monthly_clip_quota'] ?> monthly clips used
            </p>
          </div>
          <button class="btn btn-secondary" onclick="showToast('Contact sales or admin for enterprise tier upgrades!', 'info')">
            Manage Subscription
          </button>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
