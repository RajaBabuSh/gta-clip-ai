<?php
// admin/login.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';

$loggedInAdmin = get_auth_admin();
$superAdmin = Admin::getSuperAdmin();
$superAdminExists = ($superAdmin !== null);
$error = null;
$success = null;
$loginPrefill = $_POST['login'] ?? ($superAdmin['username'] ?? '');

// Temporary password displayed securely ONLY ONCE in the current session
$tempPasswordOnce = $_SESSION['admin_temp_password_once'] ?? null;
if ($tempPasswordOnce) {
    unset($_SESSION['admin_temp_password_once']);
}

// Full dynamic Admin Login URL
$adminLoginUrl = url('admin/login.php');
if (!str_starts_with($adminLoginUrl, 'http://') && !str_starts_with($adminLoginUrl, 'https://')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $adminLoginUrl = $proto . '://' . $host . $adminLoginUrl;
}

// Brute-force throttling
$maxAttempts = (int)SystemSetting::get('max_login_attempts', 5);
$lockoutSeconds = 300; // 5 minutes

if (!isset($_SESSION['admin_login_fails'])) {
    $_SESSION['admin_login_fails'] = 0;
    $_SESSION['admin_lockout_until'] = 0;
}

// Automatically clear expired lockout state
if (!empty($_SESSION['admin_lockout_until']) && $_SESSION['admin_lockout_until'] <= time()) {
    $_SESSION['admin_login_fails'] = 0;
    $_SESSION['admin_lockout_until'] = 0;
}

if ($_SESSION['admin_lockout_until'] > time()) {
    $remaining = $_SESSION['admin_lockout_until'] - time();
    $error = "Too many failed login attempts. Portal temporarily locked for {$remaining} seconds.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $formAction = $_POST['form_action'] ?? 'login';

        if ($formAction === 'change_password') {
            // Modal Change Password Request
            $targetLogin = trim($_POST['cp_login'] ?? '');
            $currentPwd = $_POST['cp_current_password'] ?? '';
            $newPwd = $_POST['cp_new_password'] ?? '';
            $confirmPwd = $_POST['cp_confirm_password'] ?? '';

            if (empty($targetLogin) || empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
                $error = 'All change password fields are required.';
            } elseif (strlen($newPwd) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif ($newPwd !== $confirmPwd) {
                $error = 'New password and confirmation do not match.';
            } else {
                $adminRecord = Admin::findByEmailOrUsername($targetLogin);
                if (!$adminRecord || !password_verify($currentPwd, $adminRecord['password_hash'])) {
                    $error = 'Invalid administrative credentials. Current password verification failed.';
                    Admin::logAction(null, 'admin.password_change_failed', "Password update failed for identifier '{$targetLogin}'");
                } else {
                    if (Admin::changePassword($adminRecord['id'], $newPwd)) {
                        $success = "Password updated successfully for administrator '{$adminRecord['username']}'! You may now sign in.";
                        $loginPrefill = $adminRecord['username'];
                        // Re-fetch super admin status
                        $superAdmin = Admin::getSuperAdmin();
                    } else {
                        $error = 'Database error while updating administrator password.';
                    }
                }
            }
        } else {
            // Standard Administrator Authentication
            $login = trim($_POST['login'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($login) || empty($password)) {
                $error = 'Please provide both admin username/email and password.';
            } else {
                $admin = Admin::authenticate($login, $password);
                if ($admin) {
                    $_SESSION['admin_login_fails'] = 0;
                    $_SESSION['admin_lockout_until'] = 0;
                    
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_last_activity'] = time();

                    // Check if forced password change is required (first login after temp password)
                    if (!empty($admin['force_password_change'])) {
                        $_SESSION['admin_force_password_change'] = true;
                        flash('warning', 'Temporary password active. For security, please establish your permanent personal password.');
                        redirect('admin/change-password.php');
                    } else {
                        flash('success', "Welcome back, {$admin['username']}! Master session established.");
                        redirect('admin/index.php');
                    }
                } else {
                    $_SESSION['admin_login_fails']++;
                    if ($_SESSION['admin_login_fails'] >= $maxAttempts) {
                        $_SESSION['admin_lockout_until'] = time() + $lockoutSeconds;
                        $error = "Portal locked for 5 minutes due to {$maxAttempts} consecutive failed attempts.";
                    } else {
                        $attemptsLeft = $maxAttempts - $_SESSION['admin_login_fails'];
                        $error = "Invalid administrative credentials. ({$attemptsLeft} attempts remaining)";
                    }
                    Admin::logAction(null, 'admin.login_failed', "Failed login attempt for identifier '{$login}'");
                }
            }
        }
    }
}

$pageTitle = 'Super Admin Control Center';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<style>
/* Dark Purple SaaS Theme Styling for Admin Login */
.admin-login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: #07090e;
  position: relative;
  overflow-x: hidden;
}

.admin-login-wrapper::before {
  content: "";
  position: fixed;
  top: -20%;
  left: 20%;
  width: 60vw;
  height: 60vh;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(6, 182, 212, 0.04) 50%, transparent 70%);
  pointer-events: none;
  z-index: 1;
}

/* TOP-RIGHT Admin Login Credentials Card */
.admin-credentials-card {
  position: fixed;
  top: 24px;
  right: 24px;
  width: 390px;
  max-width: calc(100vw - 48px);
  z-index: 1000;
  background: rgba(15, 18, 35, 0.94);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(139, 92, 246, 0.4);
  border-radius: 18px;
  box-shadow: 0 15px 45px rgba(0, 0, 0, 0.65), 0 0 30px rgba(139, 92, 246, 0.22);
  padding: 20px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.admin-credentials-card:hover {
  border-color: rgba(139, 92, 246, 0.65);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.75), 0 0 40px rgba(139, 92, 246, 0.35);
}

.cred-row {
  margin-bottom: 12px;
}

.cred-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #94a3b8;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cred-box {
  background: rgba(8, 11, 22, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cred-value {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  color: #f1f5f9;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.copy-btn {
  background: rgba(139, 92, 246, 0.18);
  border: 1px solid rgba(139, 92, 246, 0.4);
  color: #c4b5fd;
  border-radius: 6px;
  padding: 4px 9px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.copy-btn:hover {
  background: rgba(139, 92, 246, 0.35);
  border-color: #8b5cf6;
  color: #ffffff;
  transform: translateY(-1px);
}

.copy-btn:active {
  transform: translateY(0);
}

.copy-btn.copied {
  background: rgba(16, 185, 129, 0.3);
  border-color: #10b981;
  color: #6ee7b7;
}

/* Modal Styling */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(4, 6, 12, 0.85);
  backdrop-filter: blur(12px);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-content {
  background: rgba(15, 18, 35, 0.98);
  border: 1px solid rgba(139, 92, 246, 0.4);
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8), 0 0 45px rgba(139, 92, 246, 0.3);
  border-radius: 20px;
  width: 100%;
  max-width: 480px;
  padding: 32px;
  position: relative;
  animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalFadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

@media (max-width: 991px) {
  .admin-credentials-card {
    position: relative;
    top: auto;
    right: auto;
    width: 100%;
    max-width: 440px;
    margin: 0 auto 24px auto;
  }
  .admin-login-wrapper {
    flex-direction: column;
    padding-top: 20px;
  }
}
</style>

<div class="admin-login-wrapper">

  <!-- TOP-RIGHT: Admin Login Credentials Card -->
  <div class="admin-credentials-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 18px;">🛡️</span>
        <div>
          <h2 style="font-size: 14px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; margin: 0;">Admin Login Credentials</h2>
          <span style="font-size: 10.5px; color: #a5b4fc; font-weight: 600;">SUPER ADMIN GATEWAY</span>
        </div>
      </div>
      <span class="badge" style="background: rgba(139, 92, 246, 0.22); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.4); font-size: 10px; font-weight: 800; text-transform: uppercase;">
        <?= $superAdminExists ? 'Configured' : 'Setup Needed' ?>
      </span>
    </div>

    <!-- 1. Admin Login URL -->
    <div class="cred-row">
      <div class="cred-label">
        <span>Admin Login URL</span>
        <button type="button" class="copy-btn" id="btn-copy-url" onclick="copyLoginUrl()">
          <span class="copy-icon">📋</span> <span class="copy-text">Copy Login URL</span>
        </button>
      </div>
      <div class="cred-box">
        <span class="cred-value" id="admin-url-display" title="<?= htmlspecialchars($adminLoginUrl) ?>">
          <?= htmlspecialchars($adminLoginUrl) ?>
        </span>
      </div>
    </div>

    <!-- 2. Admin Username / Email -->
    <div class="cred-row">
      <div class="cred-label">
        <span>Admin Username / Email</span>
        <button type="button" class="copy-btn" id="btn-copy-user" onclick="copyAdminUsername()">
          <span class="copy-icon">👤</span> <span class="copy-text">Copy Username</span>
        </button>
      </div>
      <div class="cred-box">
        <div>
          <div style="font-size: 12.5px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 6px;">
            <span id="admin-user-display"><?= htmlspecialchars($superAdmin['username'] ?? 'superadmin') ?></span>
            <span style="font-size: 9px; padding: 1px 5px; border-radius: 4px; background: rgba(239, 68, 68, 0.25); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); font-weight: 800;">SUPERADMIN</span>
          </div>
          <div style="font-size: 11px; color: #94a3b8; margin-top: 1px;">
            <?= htmlspecialchars($superAdmin['email'] ?? 'admin@gtaclip.ai') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Admin Password Setup Status -->
    <div class="cred-row">
      <div class="cred-label">
        <span>Password Setup Status</span>
        <span style="font-size: 10px; color: #64748b;">SHA-256 / Bcrypt</span>
      </div>
      <div class="cred-box" style="flex-direction: column; align-items: flex-start; gap: 4px;">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <?php if ($superAdminExists && !empty($superAdmin['force_password_change'])): ?>
            <span style="color: #fbbf24; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 5px;">
              <span>⚠️</span> Temporary Password (Reset Required)
            </span>
          <?php elseif ($superAdminExists): ?>
            <span style="color: #34d399; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 5px;">
              <span>🔒</span> Active & Encrypted (Bcrypt)
            </span>
          <?php else: ?>
            <span style="color: #f87171; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 5px;">
              <span>○</span> No Administrator Account
            </span>
          <?php endif; ?>
          <span style="font-size: 10.5px; color: #94a3b8;">
            <?= $superAdminExists ? 'Updated ' . date('M j, Y', strtotime($superAdmin['updated_at'] ?? 'now')) : '' ?>
          </span>
        </div>
        <div style="font-size: 10.5px; color: #64748b; line-height: 1.3;">
          Plaintext passwords and hashes are never exposed in accordance with system security standards.
        </div>
      </div>
    </div>

    <!-- 4. One-Time Secure Temporary Password Display (If newly generated) -->
    <?php if (!empty($tempPasswordOnce)): ?>
      <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 10px; padding: 12px; margin-bottom: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <span style="font-size: 11px; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;">
            <span>⚡</span> Temporary Setup Password:
          </span>
          <span style="font-size: 9.5px; color: #fde68a; background: rgba(0,0,0,0.3); padding: 1px 6px; border-radius: 4px;">SHOWN ONCE</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <input type="password" id="temp-pwd-box" readonly value="<?= htmlspecialchars($tempPasswordOnce) ?>" style="flex: 1; background: rgba(0,0,0,0.5); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 6px; color: #fde68a; font-family: monospace; font-size: 13px; padding: 6px 10px;" />
          <button type="button" class="copy-btn" onclick="toggleTempPwdMask()" title="Show/Hide Password" style="padding: 6px 9px;">👁️</button>
          <button type="button" class="copy-btn" id="btn-copy-temp-pwd" onclick="copyTempPassword()" style="background: rgba(245, 158, 11, 0.25); border-color: #f59e0b; color: #fef08a;">Copy</button>
        </div>
        <div style="font-size: 10.5px; color: #fde68a; margin-top: 6px; line-height: 1.3;">
          ⚠️ This temporary password is only visible once right now. Store it securely. You must change it on first login.
        </div>
      </div>
    <?php endif; ?>

    <!-- 5. Action Buttons -->
    <div style="display: flex; gap: 8px; margin-top: 14px; pt-2; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="openChangePasswordModal()" style="flex: 1; padding: 8px 12px; font-size: 12px; font-weight: 700; background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.4); color: #c4b5fd;">
        🔑 Change Password
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="fillLoginForm()" style="flex: 1; padding: 8px 12px; font-size: 12px; font-weight: 700; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-color: #8b5cf6;">
        ⚡ Fill In Form
      </button>
    </div>

    <?php if ($loggedInAdmin): ?>
      <div style="margin-top: 14px; padding: 10px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); display: flex; align-items: center; justify-content: space-between;">
        <span style="font-size: 11px; color: #6ee7b7;">
          Logged in: <strong><?= htmlspecialchars($loggedInAdmin['username']) ?></strong>
        </span>
        <a href="<?= url('admin/index.php') ?>" style="font-size: 11px; font-weight: 700; color: #34d399; text-decoration: none;">Control Center ↗</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- CENTER: Administrator Login Card -->
  <div class="glass-card" style="width: 100%; max-width: 440px; padding: 40px; border-radius: 20px; border-color: rgba(139, 92, 246, 0.35); box-shadow: 0 0 45px rgba(139, 92, 246, 0.2); position: relative; z-index: 10;">
    <div style="text-align: center; margin-bottom: 28px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 56px; height: 56px; font-size: 28px; background: linear-gradient(135deg, #8b5cf6, #ef4444); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 25px rgba(139, 92, 246, 0.4);">🛡️</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">Super Admin Portal</h1>
      <p style="font-size: 12.5px; color: #c4b5fd; margin-top: 4px; font-weight: 700; letter-spacing: 0.5px;">ENTERPRISE CONTROL CENTER</p>
    </div>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
        <?php if (str_contains($error, 'locked') || str_contains($error, 'failed')): ?>
          <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid rgba(239,68,68,0.3);">
            <a href="<?= url('admin/recovery.php') ?>" style="color: #fde68a; font-weight: 700; text-decoration: underline; font-size: 12px;">
              🔑 Launch Super Admin Recovery Vault →
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if (!$superAdminExists): ?>
      <div style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); color: #c7d2fe; padding: 14px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; text-align: center;">
        No Super Administrator detected.
        <div style="margin-top: 8px;">
          <a href="<?= url('admin/install.php') ?>" class="btn btn-sm btn-primary" style="padding: 6px 14px;">Run Setup Wizard</a>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" id="adminLoginForm">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="login">

      <div class="form-group mb-3">
        <label class="form-label" style="display: flex; justify-content: space-between;">
          <span>Administrator Email / Username</span>
          <span style="font-size: 11px; color: var(--text-dim); cursor: pointer;" onclick="fillLoginForm()">Use Default</span>
        </label>
        <input type="text" name="login" id="login_input" class="form-control" placeholder="admin@domain.com or superadmin" required autofocus value="<?= htmlspecialchars($loginPrefill) ?>">
      </div>

      <div class="form-group mb-3">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label class="form-label" style="margin-bottom: 0;">Secure Password</label>
          <button type="button" onclick="openChangePasswordModal()" style="background: none; border: none; color: #a5b4fc; font-size: 12px; cursor: pointer; text-decoration: underline; padding: 0;">
            Change Password?
          </button>
        </div>
        <input type="password" name="password" id="password_input" class="form-control" placeholder="••••••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px; background: linear-gradient(135deg, #8b5cf6, #ef4444); border-color: #8b5cf6; font-weight: 700; box-shadow: 0 0 25px rgba(139, 92, 246, 0.35);">
        Authenticate Secure Session
      </button>

      <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-dim);">
        <a href="<?= url('/') ?>" style="color: var(--text-muted); text-decoration: none;">← Return to Main Site</a>
        <span>IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></span>
      </div>
    </form>
  </div>

</div>

<!-- Modal: Change Administrator Password -->
<div class="modal-overlay" id="changePasswordModal" onclick="closeChangePasswordModal(event)">
  <div class="modal-content" onclick="event.stopPropagation()">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 22px;">🔑</span>
        <div>
          <h3 style="font-size: 18px; font-weight: 800; color: #ffffff; margin: 0;">Change Admin Password</h3>
          <p style="font-size: 12px; color: #94a3b8; margin: 2px 0 0 0;">Update Super Administrator security credentials</p>
        </div>
      </div>
      <button type="button" onclick="closeChangePasswordModal(null)" style="background: rgba(255,255,255,0.08); border: none; color: #cbd5e1; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;">✕</button>
    </div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="change_password">

      <div class="form-group mb-3">
        <label class="form-label">Administrator Username or Email</label>
        <input type="text" name="cp_login" id="cp_login" class="form-control" required value="<?= htmlspecialchars($superAdmin['username'] ?? 'superadmin') ?>">
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Current / Temporary Password</label>
        <input type="password" name="cp_current_password" class="form-control" placeholder="Existing password" required autocomplete="current-password">
      </div>

      <div class="form-group mb-3">
        <label class="form-label">New Secure Password (min 8 chars)</label>
        <input type="password" name="cp_new_password" class="form-control" placeholder="••••••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="cp_confirm_password" class="form-control" placeholder="••••••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <div style="display: flex; gap: 12px;">
        <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal(null)" style="flex: 1; padding: 10px;">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" style="flex: 2; padding: 10px; font-weight: 700; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-color: #8b5cf6;">
          Save New Password
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Clipboard Copy Helpers with Visual Feedback
function copyToClipboard(text, btnElement, successLabel) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      showCopiedFeedback(btnElement, successLabel);
    }).catch(() => {
      fallbackCopy(text, btnElement, successLabel);
    });
  } else {
    fallbackCopy(text, btnElement, successLabel);
  }
}

function fallbackCopy(text, btnElement, successLabel) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    showCopiedFeedback(btnElement, successLabel);
  } catch (err) {
    alert('Copy command not supported in this browser. Please manually copy: ' + text);
  }
  document.body.removeChild(ta);
}

function showCopiedFeedback(btn, successLabel) {
  if (!btn) return;
  const originalHtml = btn.innerHTML;
  btn.classList.add('copied');
  btn.innerHTML = '<span>✓</span> <span>' + (successLabel || 'Copied!') + '</span>';
  setTimeout(() => {
    btn.classList.remove('copied');
    btn.innerHTML = originalHtml;
  }, 2000);
}

function copyLoginUrl() {
  const url = document.getElementById('admin-url-display').innerText.trim();
  const btn = document.getElementById('btn-copy-url');
  copyToClipboard(url, btn, 'URL Copied!');
}

function copyAdminUsername() {
  const user = document.getElementById('admin-user-display').innerText.trim();
  const btn = document.getElementById('btn-copy-user');
  copyToClipboard(user, btn, 'User Copied!');
}

function copyTempPassword() {
  const pwdInput = document.getElementById('temp-pwd-box');
  const btn = document.getElementById('btn-copy-temp-pwd');
  if (pwdInput) {
    copyToClipboard(pwdInput.value, btn, 'Copied!');
  }
}

function toggleTempPwdMask() {
  const pwdInput = document.getElementById('temp-pwd-box');
  if (pwdInput) {
    pwdInput.type = (pwdInput.type === 'password') ? 'text' : 'password';
  }
}

function fillLoginForm() {
  const user = document.getElementById('admin-user-display').innerText.trim();
  const loginInput = document.getElementById('login_input');
  if (loginInput && user) {
    loginInput.value = user;
    const pwdInput = document.getElementById('password_input');
    if (pwdInput) pwdInput.focus();
  }
}

// Modal Open/Close
function openChangePasswordModal() {
  const modal = document.getElementById('changePasswordModal');
  if (modal) modal.style.display = 'flex';
}

function closeChangePasswordModal(e) {
  if (e && e.target !== e.currentTarget) return;
  const modal = document.getElementById('changePasswordModal');
  if (modal) modal.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeChangePasswordModal(null);
  }
});
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
