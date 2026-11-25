<?php
// admin/install.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Admin.php';

$superAdminExists = Admin::hasSuperAdmin();
$error = null;
$success = null;

if ($superAdminExists) {
    // If super admin exists, lock installer
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = 'Installation is locked. A Super Administrator already exists.';
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $error = 'Security token invalid. Please refresh the page.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'All required fields must be completed.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid administrative email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    $adminId = Admin::create([
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $fullName,
                        'password' => $password,
                        'role' => 'superadmin',
                        'status' => 'active'
                    ]);

                    Admin::logAction($adminId, 'installer.bootstrap', "Initial Super Administrator created ({$email})");
                    $success = 'Super Administrator account created successfully! You may now sign in.';
                    $superAdminExists = true;
                } catch (Exception $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Super Admin System Setup';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: #07090e;">
  <div class="glass-card" style="width: 100%; max-width: 500px; padding: 40px; border-radius: 20px; border-color: rgba(99, 102, 241, 0.3); box-shadow: 0 0 40px rgba(99, 102, 241, 0.15);">
    <div style="text-align: center; margin-bottom: 24px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 64px; height: 64px; font-size: 32px; background: linear-gradient(135deg, #6366f1, #ef4444);">🚀</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Super Admin Bootstrap</h1>
      <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">GTA CLIP AI ULTIMATE Setup Wizard</p>
    </div>

    <?php if ($success): ?>
      <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 16px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: center;">
        <?= htmlspecialchars($success) ?>
        <div style="margin-top: 14px;">
          <a href="<?= url('admin/login.php') ?>" class="btn btn-primary" style="padding: 8px 20px;">Proceed to Admin Login</a>
        </div>
      </div>
    <?php elseif ($superAdminExists): ?>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 18px; border-radius: 12px; font-size: 14px; text-align: center; margin-bottom: 20px;">
        <div style="font-size: 24px; margin-bottom: 8px;">🔒</div>
        <strong>Installer Locked</strong>
        <p style="margin-top: 6px; font-size: 13px; color: var(--text-muted);">
          A Super Administrator already exists in the system. To protect security, one-time bootstrap has been disabled.
        </p>
        <div style="margin-top: 16px;">
          <a href="<?= url('admin/login.php') ?>" class="btn btn-primary" style="padding: 8px 24px;">Go to Admin Portal</a>
        </div>
      </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <?= csrf_field() ?>

        <div class="form-group mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="System Administrator" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Super Admin Username <span style="color:#ef4444;">*</span></label>
          <input type="text" name="username" class="form-control" placeholder="superadmin" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Admin Email <span style="color:#ef4444;">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="admin@domain.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Password (min 8 chars) <span style="color:#ef4444;">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••••••" required minlength="8">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Confirm Password <span style="color:#ef4444;">*</span></label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••••••" required minlength="8">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 12px; background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;">
          Create Super Admin & Initialize Platform
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
