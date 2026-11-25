<?php
// admin/system-settings.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';

$admin = AdminMiddleware::requirePermission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $settings = [
        'allow_registration' => !empty($_POST['allow_registration']) ? '1' : '0',
        'require_email_verification' => !empty($_POST['require_email_verification']) ? '1' : '0',
        'max_upload_size_mb' => (string)max(10, (int)($_POST['max_upload_size_mb'] ?? 2048)),
        'session_lifetime_minutes' => (string)max(15, (int)($_POST['session_lifetime_minutes'] ?? 120)),
        'max_login_attempts' => (string)max(3, (int)($_POST['max_login_attempts'] ?? 5)),
        'maintenance_mode' => !empty($_POST['maintenance_mode']) ? '1' : '0',
        'maintenance_message' => trim($_POST['maintenance_message'] ?? 'Platform under maintenance.')
    ];

    foreach ($settings as $k => $v) {
        SystemSetting::set($k, $v, 'system');
    }

    Admin::logAction($admin['id'], 'system_settings.update', 'Updated core system and security parameters');
    flash('success', 'System settings saved successfully.');
    redirect('admin/system-settings.php');
}

$allowReg = SystemSetting::get('allow_registration', '1');
$requireEmailVerif = SystemSetting::get('require_email_verification', '0');
$maxUploadMb = SystemSetting::get('max_upload_size_mb', '2048');
$sessionMins = SystemSetting::get('session_lifetime_minutes', '120');
$maxLogins = SystemSetting::get('max_login_attempts', '5');
$maintenanceMode = SystemSetting::get('maintenance_mode', '0');
$maintenanceMsg = SystemSetting::get('maintenance_message', 'GTA CLIP AI ULTIMATE is currently undergoing scheduled maintenance.');

$activeNav = 'system-settings';
$pageTitle = 'Core System Settings';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">System Settings & Security</span>
      </div>
    
      <div class="topbar-actions">
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container" style="max-width:880px;">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Platform Controls & Security Parameters</h1>
          <p class="page-desc">Govern creator account registration, storage thresholds, maintenance mode, and session policies.</p>
        </div>
      </div>

      <form method="POST">
        <?= csrf_field() ?>

        <!-- Maintenance Mode Card -->
        <div class="glass-card" style="padding:24px; margin-bottom:24px; border-left:4px solid <?= $maintenanceMode === '1' ? '#ef4444' : '#10b981' ?>;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div>
              <h2 style="font-size:16px; font-weight:700; color:#fff;">Platform Maintenance Mode</h2>
              <div style="font-size:12px; color:var(--text-muted);">When enabled, creator users see a maintenance screen while administrators maintain full access.</div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; cursor:pointer; color:<?= $maintenanceMode === '1' ? '#f87171' : '#6ee7b7' ?>;">
              <input type="checkbox" name="maintenance_mode" value="1" <?= $maintenanceMode === '1' ? 'checked' : '' ?>>
              <span><?= $maintenanceMode === '1' ? 'MAINTENANCE ACTIVE' : 'SYSTEM ONLINE' ?></span>
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">Maintenance Notice Message</label>
            <input type="text" name="maintenance_message" class="form-control" value="<?= htmlspecialchars($maintenanceMsg) ?>">
          </div>
        </div>

        <!-- Registration & Access Card -->
        <div class="glass-card" style="padding:24px; margin-bottom:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">User Access & Registration Policies</h2>

          <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:20px;">
            <label style="display:flex; align-items:center; gap:10px; font-size:14px; color:#fff; cursor:pointer;">
              <input type="checkbox" name="allow_registration" value="1" <?= $allowReg === '1' ? 'checked' : '' ?>>
              <div>
                <strong>Allow Public Sign-Ups</strong>
                <div style="font-size:12px; color:var(--text-muted);">If unchecked, only administrators can invite or create new creator accounts.</div>
              </div>
            </label>

            <label style="display:flex; align-items:center; gap:10px; font-size:14px; color:#fff; cursor:pointer;">
              <input type="checkbox" name="require_email_verification" value="1" <?= $requireEmailVerif === '1' ? 'checked' : '' ?>>
              <div>
                <strong>Require Email Verification</strong>
                <div style="font-size:12px; color:var(--text-muted);">Require users to verify email before accessing the clipping studio.</div>
              </div>
            </label>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
              <label class="form-label">Max Direct Upload Size (MB)</label>
              <input type="number" name="max_upload_size_mb" class="form-control" value="<?= htmlspecialchars($maxUploadMb) ?>" min="50" max="10240">
            </div>
            <div>
              <label class="form-label">Max Failed Logins Lockout</label>
              <input type="number" name="max_login_attempts" class="form-control" value="<?= htmlspecialchars($maxLogins) ?>" min="3" max="20">
            </div>
          </div>
        </div>

        <!-- Session Policy Card -->
        <div class="glass-card" style="padding:24px; margin-bottom:28px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Session Security</h2>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
              <label class="form-label">Admin Session Lifetime (Minutes)</label>
              <input type="number" name="session_lifetime_minutes" class="form-control" value="<?= htmlspecialchars($sessionMins) ?>" min="15" max="1440">
              <span style="font-size:11px; color:var(--text-muted);">Inactive admin sessions will expire after this interval.</span>
            </div>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" class="btn btn-primary" style="padding:12px 32px; font-weight:700;">
            💾 Save System Settings
          </button>
        </div>
      </form>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
