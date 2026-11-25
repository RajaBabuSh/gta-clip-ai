<?php
// admin/change-password.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';

$admin = AdminMiddleware::requireAdmin();
$adminRecord = Admin::findById($admin['id']);
$isForced = !empty($adminRecord['force_password_change']) || !empty($_SESSION['admin_force_password_change']);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } else {
            // Re-verify current password from DB
            $dbAdmin = Admin::findByEmailOrUsername($adminRecord['username']);
            if (!$dbAdmin || !password_verify($currentPassword, $dbAdmin['password_hash'])) {
                $error = 'The current (or temporary) password you entered is incorrect.';
                Admin::logAction($admin['id'], 'admin.password_change_failed', 'Failed password change attempt: incorrect current password');
            } else {
                if (Admin::changePassword($admin['id'], $newPassword)) {
                    unset($_SESSION['admin_force_password_change'], $_SESSION['admin_temp_password_once']);
                    flash('success', 'Your administrator password has been updated successfully! Security credentials updated.');
                    redirect('admin/index.php');
                } else {
                    $error = 'Failed to update password in database. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Update Admin Password';
$activeNav = 'settings';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php if (!$isForced): ?>
    <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>
  <?php endif; ?>

  <main class="app-main" style="<?= $isForced ? 'margin:0; width:100%; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px;' : '' ?>">
    <?php if (!$isForced): ?>
      <header class="app-topbar">
        <div>
          <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
          <span style="font-size:14px; font-weight:700; color:white;">Change Administrator Password</span>
        </div>
        <div class="topbar-actions">
          <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
        </div>
      </header>
    <?php endif; ?>

    <div class="page-container" style="max-width: 600px; width: 100%; margin: <?= $isForced ? '0 auto' : '20px auto' ?>;">
      <div class="glass-card" style="padding: 36px; border-radius: 20px; border: 1px solid rgba(139, 92, 246, 0.35); box-shadow: 0 15px 45px rgba(0,0,0,0.6), 0 0 35px rgba(139, 92, 246, 0.2);">
        <div style="text-align: center; margin-bottom: 28px;">
          <div class="brand-icon" style="margin: 0 auto 16px auto; width: 56px; height: 56px; font-size: 28px; background: linear-gradient(135deg, #8b5cf6, #ef4444); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);">🔑</div>
          <h1 style="font-size: 22px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">Update Administrator Password</h1>
          <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;">
            Administrator: <strong style="color: #c4b5fd;"><?= htmlspecialchars($adminRecord['username']) ?></strong> 
            <span class="badge" style="background: rgba(139, 92, 246, 0.25); color: #c4b5fd; font-size: 10px; margin-left: 6px;"><?= strtoupper(htmlspecialchars($adminRecord['role'])) ?></span>
          </p>
        </div>

        <?php if ($isForced): ?>
          <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #fde68a; padding: 14px 18px; border-radius: 10px; font-size: 13px; margin-bottom: 24px; line-height: 1.4;">
            <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
              <span>⚠️</span> Mandatory Password Setup Required
            </div>
            Your administrator account was initialized with a temporary password. To protect your system, you must establish a permanent secure password before accessing the control center.
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <?= csrf_field() ?>

          <div class="form-group mb-3">
            <label class="form-label">Current / Temporary Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Enter existing password" required autofocus autocomplete="current-password">
          </div>

          <div class="form-group mb-3">
            <label class="form-label">New Secure Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password">
            <div style="margin-top: 6px; font-size: 11px; color: var(--text-dim);">
              Use 8+ characters with a mixture of letters, numbers, and symbols.
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required minlength="8" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 700; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-color: #8b5cf6; box-shadow: 0 0 20px rgba(139, 92, 246, 0.35);">
            Save & Establish New Password
          </button>

          <?php if (!$isForced): ?>
            <div style="margin-top: 20px; text-align: center;">
              <a href="<?= url('admin/index.php') ?>" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">← Return to Control Center</a>
            </div>
          <?php else: ?>
            <div style="margin-top: 20px; text-align: center;">
              <a href="<?= url('admin/logout.php') ?>" style="color: #f87171; font-size: 12px; text-decoration: none;">Sign Out of Temporary Session</a>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
