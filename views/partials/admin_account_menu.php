<?php
// views/partials/admin_account_menu.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/functions.php';

$authAdmin = get_auth_admin();
if ($authAdmin):
    $adminRole = strtoupper($authAdmin['role'] ?? 'ADMIN');
    $adminInitial = strtoupper(substr($authAdmin['username'] ?? 'A', 0, 1));
    $isSuper = ($authAdmin['role'] ?? '') === 'superadmin';
?>
<!-- Top-Right Admin Account Menu -->
<div class="admin-account-dropdown" style="position: relative; display: inline-block;">
  <button 
    type="button" 
    id="adminAccountMenuBtn"
    onclick="toggleAdminAccountMenu(event)"
    style="display: flex; align-items: center; gap: 10px; background: rgba(18, 24, 43, 0.85); border: 1px solid rgba(139, 92, 246, 0.35); padding: 5px 12px 5px 6px; border-radius: 9999px; cursor: pointer; color: #f8fafc; transition: all 0.2s ease; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"
    onmouseover="this.style.borderColor='rgba(139, 92, 246, 0.7)'; this.style.boxShadow='0 0 15px rgba(139, 92, 246, 0.35)';"
    onmouseout="this.style.borderColor='rgba(139, 92, 246, 0.35)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.3)';"
  >
    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, <?= $isSuper ? '#ef4444, #8b5cf6' : '#8b5cf6, #06b6d4' ?>); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; color: white; border: 1.5px solid rgba(255,255,255,0.2); box-shadow: 0 0 10px <?= $isSuper ? 'rgba(239, 68, 68, 0.4)' : 'rgba(139, 92, 246, 0.4)' ?>;">
      <?= htmlspecialchars($adminInitial) ?>
    </div>
    <div style="text-align: left; line-height: 1.25;">
      <div style="font-size: 12px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 6px;">
        <?= htmlspecialchars($authAdmin['username'] ?? 'admin') ?>
        <?php if ($isSuper): ?>
          <span style="font-size: 10px;" title="Super Administrator">👑</span>
        <?php endif; ?>
      </div>
      <div style="display: flex; align-items: center; gap: 4px;">
        <span style="font-size: 9.5px; font-weight: 800; letter-spacing: 0.5px; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; <?= $isSuper ? 'background:rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4);' : 'background:rgba(139, 92, 246, 0.25); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.4);' ?>">
          <?= htmlspecialchars($adminRole) ?>
        </span>
      </div>
    </div>
    <span style="font-size: 10px; color: #94a3b8; margin-left: 2px;">▼</span>
  </button>

  <!-- Dropdown Card -->
  <div 
    id="adminAccountDropdownMenu" 
    class="glass-card" 
    style="display: none; position: absolute; top: calc(100% + 8px); right: 0; width: 270px; padding: 14px; z-index: 10000; box-shadow: 0 20px 45px rgba(0,0,0,0.7), 0 0 25px rgba(139, 92, 246, 0.25); border: 1px solid rgba(139, 92, 246, 0.35); border-radius: 14px; background: rgba(14, 18, 32, 0.96); backdrop-filter: blur(20px);"
  >
    <div style="border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px; margin-bottom: 10px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="font-size: 13px; font-weight: 700; color: white;">
          <?= htmlspecialchars($authAdmin['full_name'] ?? $authAdmin['username'] ?? 'Administrator') ?>
        </div>
        <span style="font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 4px; <?= $isSuper ? 'background:rgba(239,68,68,0.25); color:#fca5a5; border:1px solid rgba(239,68,68,0.4);' : 'background:rgba(139,92,246,0.25); color:#c4b5fd; border:1px solid rgba(139,92,246,0.4);' ?>">
          <?= htmlspecialchars($adminRole) ?>
        </span>
      </div>
      <div style="font-size: 11px; color: #94a3b8; margin-top: 3px; word-break: break-all;">
        <?= htmlspecialchars($authAdmin['email'] ?? '') ?>
      </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 4px;">
      <a href="<?= url('admin/index.php') ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #f1f5f9; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='rgba(139,92,246,0.18)'" onmouseout="this.style.background='transparent'">
        <span>🛡️</span> Control Center Home
      </a>
      <a href="<?= url('admin/change-password.php') ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #f1f5f9; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='rgba(139,92,246,0.18)'" onmouseout="this.style.background='transparent'">
        <span>🔑</span> Change Password
      </a>
      <?php if ($isSuper): ?>
        <a href="<?= url('admin/admins.php') ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #f1f5f9; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='rgba(139,92,246,0.18)'" onmouseout="this.style.background='transparent'">
          <span>👑</span> Admins & Staff
        </a>
      <?php endif; ?>
      <a href="<?= url('admin/logs.php') ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #f1f5f9; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='rgba(139,92,246,0.18)'" onmouseout="this.style.background='transparent'">
        <span>📜</span> Security Audit Logs
      </a>
      <a href="<?= url('dashboard.php') ?>" target="_blank" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #a5b4fc; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='rgba(139,92,246,0.18)'" onmouseout="this.style.background='transparent'">
        <span>🚀</span> User Studio ↗
      </a>
    </div>

    <div style="border-top: 1px solid rgba(255,255,255,0.08); margin-top: 10px; padding-top: 8px;">
      <a href="<?= url('admin/logout.php') ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; color: #f87171; text-decoration: none; font-weight: 600; transition: background 0.15s;" onmouseover="this.style.background='rgba(239,68,68,0.15)'" onmouseout="this.style.background='transparent'">
        <span>🚪</span> Terminate Master Session
      </a>
    </div>
  </div>
</div>

<script>
function toggleAdminAccountMenu(e) {
  e.stopPropagation();
  const menu = document.getElementById('adminAccountDropdownMenu');
  if (menu) {
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
  }
}
document.addEventListener('click', function(e) {
  const container = document.querySelector('.admin-account-dropdown');
  if (container && !container.contains(e.target)) {
    const menu = document.getElementById('adminAccountDropdownMenu');
    if (menu) menu.style.display = 'none';
  }
});
</script>
<?php endif; ?>
