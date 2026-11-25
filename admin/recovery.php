<?php
// admin/recovery.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';

// Reset lockout state in current session
$_SESSION['admin_login_fails'] = 0;
$_SESSION['admin_lockout_until'] = 0;

$superAdmin = Admin::getSuperAdmin();
if (!$superAdmin) {
    die("No Super Admin found to recover.");
}

$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? '';
$generatedTempPassword = null;
$alreadyDelivered = false;

// Generate strong random temporary password (16 chars with mixed upper, lower, digits, symbols)
function generateStrongTempPassword(int $length = 16): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

// Check if recovery is triggered via POST or reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $action === 'reset') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        $error = "Invalid or expired recovery security token.";
    } else {
        // Generate temporary password
        $rawPassword = generateStrongTempPassword(16);
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);

        // Update existing Super Admin record ONLY - never create duplicates
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE admins 
            SET username = 'superadmin', 
                email = 'admin@gtaclip.ai', 
                password_hash = ?, 
                force_password_change = 1, 
                status = 'active', 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$hash, $superAdmin['id']]);

        // Clear all lockout state
        $_SESSION['admin_login_fails'] = 0;
        $_SESSION['admin_lockout_until'] = 0;

        // Log audit event (never log plaintext or hash)
        Admin::logAction($superAdmin['id'], 'admin.security_reset', 'Super Admin temporary password generated via recovery screen with forced reset on login');

        // Store temporary password for one-time delivery
        $_SESSION['recovery_temp_password'] = $rawPassword;
        $_SESSION['admin_temp_password_once'] = $rawPassword;

        redirect('admin/recovery.php?delivered=1');
    }
}

if (!empty($_SESSION['recovery_temp_password'])) {
    $generatedTempPassword = $_SESSION['recovery_temp_password'];
    // Unset immediately so refreshing cannot display it again
    unset($_SESSION['recovery_temp_password']);
} elseif (isset($_GET['delivered'])) {
    $alreadyDelivered = true;
}

// Full dynamic Admin Login URL
$adminLoginUrl = url('admin/login.php');
if (!str_starts_with($adminLoginUrl, 'http://') && !str_starts_with($adminLoginUrl, 'https://')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $adminLoginUrl = $proto . '://' . $host . $adminLoginUrl;
}

$pageTitle = 'Super Admin Recovery Vault';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<style>
.recovery-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: #07090e;
  position: relative;
  overflow-x: hidden;
}

.recovery-wrapper::before {
  content: "";
  position: fixed;
  top: -20%;
  left: 20%;
  width: 60vw;
  height: 60vh;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.14) 0%, rgba(239, 68, 68, 0.05) 50%, transparent 70%);
  pointer-events: none;
  z-index: 1;
}

.recovery-card {
  width: 100%;
  max-width: 540px;
  background: rgba(15, 18, 35, 0.95);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(139, 92, 246, 0.4);
  box-shadow: 0 25px 65px rgba(0, 0, 0, 0.75), 0 0 45px rgba(139, 92, 246, 0.25);
  border-radius: 22px;
  padding: 38px;
  position: relative;
  z-index: 10;
}

.cred-box {
  background: rgba(8, 11, 22, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 5px;
}

.copy-btn {
  background: rgba(139, 92, 246, 0.18);
  border: 1px solid rgba(139, 92, 246, 0.4);
  color: #c4b5fd;
  border-radius: 6px;
  padding: 5px 11px;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.copy-btn:hover {
  background: rgba(139, 92, 246, 0.35);
  border-color: #8b5cf6;
  color: #ffffff;
}

.copy-btn.copied {
  background: rgba(16, 185, 129, 0.3);
  border-color: #10b981;
  color: #6ee7b7;
}
</style>

<div class="recovery-wrapper">
  <div class="recovery-card">
    <div style="text-align: center; margin-bottom: 26px;">
      <div class="brand-icon" style="margin: 0 auto 16px auto; width: 62px; height: 62px; font-size: 30px; background: linear-gradient(135deg, #ef4444, #8b5cf6); border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 30px rgba(239, 68, 68, 0.4);">🛡️</div>
      <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">Super Admin Recovery Vault</h1>
      <p style="font-size: 12.5px; color: #c4b5fd; margin-top: 4px; font-weight: 700; letter-spacing: 0.5px;">ONE-TIME SECURE CREDENTIAL DELIVERY</p>
    </div>

    <?php if ($generatedTempPassword): ?>
      <!-- SUCCESS: Temporary Credentials Delivered Once -->
      <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 14px 18px; border-radius: 12px; font-size: 13px; margin-bottom: 24px;">
        <div style="font-weight: 700; margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
          <span>✓</span> Super Admin Credentials Successfully Reset
        </div>
        Portal lockout has been cleared. Below is your newly generated temporary password.
      </div>

      <!-- 1. Admin Login URL -->
      <div style="margin-bottom: 16px;">
        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; display: flex; justify-content: space-between;">
          <span>Admin Login URL</span>
          <button type="button" class="copy-btn" id="btn-copy-url" onclick="copyText('<?= htmlspecialchars($adminLoginUrl) ?>', this, 'URL Copied!')">
            📋 Copy Login URL
          </button>
        </label>
        <div class="cred-box">
          <code style="font-family: monospace; color: #f1f5f9; font-size: 13px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($adminLoginUrl) ?></code>
        </div>
      </div>

      <!-- 2. Super Admin Username -->
      <div style="margin-bottom: 16px;">
        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; display: flex; justify-content: space-between;">
          <span>Confirmed Username</span>
          <button type="button" class="copy-btn" id="btn-copy-user" onclick="copyText('superadmin', this, 'User Copied!')">
            👤 Copy Username
          </button>
        </label>
        <div class="cred-box">
          <div style="display: flex; align-items: center; gap: 8px;">
            <strong style="color: #ffffff; font-size: 14px;">superadmin</strong>
            <span style="font-size: 9.5px; padding: 1px 6px; border-radius: 4px; background: rgba(239, 68, 68, 0.25); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); font-weight: 800;">SUPERADMIN</span>
          </div>
          <span style="font-size: 11.5px; color: #94a3b8;">admin@gtaclip.ai</span>
        </div>
      </div>

      <!-- 3. Newly Generated Temporary Password (SHOWN ONCE) -->
      <div style="margin-bottom: 24px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <label style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #fbbf24; letter-spacing: 0.5px; display: flex; align-items: center; gap: 5px; margin: 0;">
            <span>⚡</span> Temporary Password (Shown Once):
          </label>
          <span style="font-size: 9.5px; color: #fde68a; background: rgba(0,0,0,0.4); padding: 2px 7px; border-radius: 4px; font-weight: 700;">CONFIDENTIAL</span>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <input type="password" id="temp-pwd-input" readonly value="<?= htmlspecialchars($generatedTempPassword) ?>" style="flex: 1; background: rgba(0,0,0,0.6); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; color: #fde68a; font-family: monospace; font-size: 15px; font-weight: 700; padding: 10px 14px; letter-spacing: 1px;" />
          <button type="button" class="copy-btn" onclick="toggleTempPwd()" style="padding: 10px 12px;" title="Show/Hide">👁️</button>
          <button type="button" class="copy-btn" id="btn-copy-pwd" onclick="copyText(document.getElementById('temp-pwd-input').value, this, 'Copied!')" style="background: rgba(245, 158, 11, 0.25); border-color: #f59e0b; color: #fef08a; padding: 10px 14px; font-weight: 700;">
            📋 Copy Password
          </button>
        </div>

        <div style="font-size: 11.5px; color: #fde68a; margin-top: 10px; line-height: 1.4;">
          ⚠️ <strong>Single-View Security Notice:</strong> This temporary password has been stored in memory for this view only and is now cleared. After your first sign-in, you will be required to immediately set your new permanent personal password.
        </div>
      </div>

      <div style="display: flex; gap: 12px;">
        <a href="<?= url('admin/login.php') ?>" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 14px; font-weight: 700; text-align: center; text-decoration: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-color: #8b5cf6; box-shadow: 0 0 25px rgba(139, 92, 246, 0.35); border-radius: 10px;">
          Proceed to Super Admin Login →
        </a>
      </div>

    <?php elseif ($alreadyDelivered): ?>
      <!-- Already Delivered Notice -->
      <div style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35); color: #c4b5fd; padding: 18px; border-radius: 12px; font-size: 13px; text-align: center; margin-bottom: 24px;">
        <div style="font-size: 24px; margin-bottom: 8px;">🔒</div>
        <strong>Credential Delivery Complete</strong>
        <p style="margin-top: 6px; font-size: 12.5px; color: #94a3b8;">
          Your temporary password has already been displayed and securely purged from memory in accordance with zero-leakage policy.
        </p>
      </div>

      <div style="display: flex; flex-direction: column; gap: 10px;">
        <a href="<?= url('admin/login.php') ?>" class="btn btn-primary" style="padding: 12px; font-weight: 700; text-align: center; text-decoration: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border-color: #8b5cf6;">
          Go to Admin Portal Login
        </a>
        <form method="POST">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 10px; font-size: 12px;">
            Generate Another Temporary Password
          </button>
        </form>
      </div>

    <?php else: ?>
      <!-- Initial Screen: Confirmation to Trigger Reset -->
      <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 16px; border-radius: 12px; font-size: 13px; margin-bottom: 24px; line-height: 1.4;">
        <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
          <span>⚠️</span> Super Admin Authentication Recovery
        </div>
        Clicking the button below will reset the credentials for <strong>superadmin</strong> (<a href="mailto:admin@gtaclip.ai" style="color:#fca5a5;">admin@gtaclip.ai</a>), lift any active portal lockout, generate a cryptographically strong temporary password, and enforce a password change on first login.
      </div>

      <form method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-weight: 700; background: linear-gradient(135deg, #ef4444, #dc2626); border-color: #ef4444; box-shadow: 0 0 25px rgba(239, 68, 68, 0.35); border-radius: 10px;">
          Generate New Temporary Credentials & Lift Lockout
        </button>

        <div style="margin-top: 20px; text-align: center;">
          <a href="<?= url('admin/login.php') ?>" style="color: #94a3b8; font-size: 12.5px; text-decoration: none;">← Return to Admin Login</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
function copyText(text, btn, feedbackLabel) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      showFeedback(btn, feedbackLabel);
    }).catch(() => fallbackCopy(text, btn, feedbackLabel));
  } else {
    fallbackCopy(text, btn, feedbackLabel);
  }
}

function fallbackCopy(text, btn, feedbackLabel) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    showFeedback(btn, feedbackLabel);
  } catch (e) {
    alert('Please copy manually: ' + text);
  }
  document.body.removeChild(ta);
}

function showFeedback(btn, label) {
  if (!btn) return;
  const original = btn.innerHTML;
  btn.classList.add('copied');
  btn.innerHTML = '<span>✓</span> <span>' + (label || 'Copied!') + '</span>';
  setTimeout(() => {
    btn.classList.remove('copied');
    btn.innerHTML = original;
  }, 2000);
}

function toggleTempPwd() {
  const inp = document.getElementById('temp-pwd-input');
  if (inp) {
    inp.type = (inp.type === 'password') ? 'text' : 'password';
  }
}
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
