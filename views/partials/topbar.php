<?php
// views/partials/topbar.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/YouTubeAccount.php';
require_once __DIR__ . '/../../app/services/GeminiService.php';

$topbarUser = get_auth_user();
$topbarYt = $topbarUser ? YouTubeAccount::getConnectedAccount($topbarUser['id']) : null;
$geminiActive = GeminiService::isEnabled();
$topbarTitle = $topbarTitle ?? ($pageTitle ?? 'Studio');
?>
<header class="app-topbar">
  <div style="display:flex; align-items:center; gap:8px;">
    <span style="font-size:13px; font-weight:600; color:var(--text-muted);">EiziClip /</span>
    <span style="font-size:14px; font-weight:700; color:white;"><?= htmlspecialchars($topbarTitle) ?></span>
  </div>

  <div class="topbar-actions" style="display:flex; align-items:center; gap:16px;">
    <!-- Live AI & Integration Status Pills -->
    <div style="display:none; @media(min-width:768px){display:flex}; align-items:center; gap:10px;">
      <!-- Gemini Status -->
      <a href="<?= url('api-usage.php') ?>" style="display:flex; align-items:center; gap:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:5px 10px; border-radius:20px; text-decoration:none; font-size:11px;">
        <span style="width:7px; height:7px; border-radius:50%; background:<?= $geminiActive ? '#10b981' : '#f59e0b' ?>;"></span>
        <span style="color:var(--text-muted);">Gemini AI:</span>
        <strong style="color:white;"><?= $geminiActive ? 'Active' : 'Standby' ?></strong>
      </a>

      <!-- YouTube Channel Status -->
      <a href="<?= url('youtube-studio.php') ?>" style="display:flex; align-items:center; gap:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:5px 10px; border-radius:20px; text-decoration:none; font-size:11px;">
        <span style="width:7px; height:7px; border-radius:50%; background:<?= $topbarYt ? '#10b981' : '#ef4444' ?>;"></span>
        <span style="color:var(--text-muted);">YouTube:</span>
        <strong style="color:white;"><?= $topbarYt ? htmlspecialchars(substr($topbarYt['channel_title'], 0, 14)) : 'Not Connected' ?></strong>
      </a>
    </div>

    <!-- Right-side User Profile Dropdown Menu -->
    <div class="user-menu-container" style="position:relative;">
      <button 
        type="button" 
        onclick="toggleUserMenu()" 
        id="user-menu-btn"
        style="display:flex; align-items:center; gap:10px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); padding:5px 12px 5px 6px; border-radius:24px; cursor:pointer; color:white;"
      >
        <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; color:white;">
          <?= strtoupper(substr($topbarUser['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div style="text-align:left; line-height:1.2;">
          <div style="font-size:12px; font-weight:700; color:white;"><?= htmlspecialchars($topbarUser['username'] ?? 'User') ?></div>
          <div style="font-size:10px; color:var(--text-dim);"><?= htmlspecialchars($topbarUser['plan_tier'] ?? 'Pro') ?> Tier</div>
        </div>
        <span style="font-size:10px; color:var(--text-dim); margin-left:4px;">▼</span>
      </button>

      <!-- Dropdown Card -->
      <div 
        id="user-menu-dropdown" 
        class="glass-card" 
        style="display:none; position:absolute; top:calc(100% + 8px); right:0; width:250px; padding:12px; z-index:9999; box-shadow:0 20px 40px rgba(0,0,0,0.6); border:1px solid var(--border-color); border-radius:12px; background:#0d111d;"
      >
        <div style="border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:10px; margin-bottom:10px;">
          <div style="font-size:13px; font-weight:700; color:white;"><?= htmlspecialchars($topbarUser['full_name'] ?? $topbarUser['username'] ?? 'User') ?></div>
          <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($topbarUser['email'] ?? '') ?></div>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
          <a href="<?= url('settings.php') ?>" class="user-menu-item" style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; font-size:13px; color:white; text-decoration:none;">
            <span>⚙️</span> Account Settings
          </a>
          <a href="<?= url('youtube-studio.php') ?>" class="user-menu-item" style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; font-size:13px; color:white; text-decoration:none;">
            <span>▶️</span> Connected YouTube Channel
          </a>
          <a href="<?= url('scheduler.php') ?>" class="user-menu-item" style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; font-size:13px; color:white; text-decoration:none;">
            <span>⏰</span> Upload Scheduler
          </a>
          <a href="<?= url('api-usage.php') ?>" class="user-menu-item" style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; font-size:13px; color:white; text-decoration:none;">
            <span>⚡</span> API Status & Quotas
          </a>
        </div>

        <div style="border-top:1px solid rgba(255,255,255,0.08); margin-top:10px; padding-top:8px;">
          <a href="<?= url('logout.php') ?>" style="display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; font-size:13px; color:#ef4444; text-decoration:none; font-weight:600;">
            <span>🚪</span> Sign Out
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
function toggleUserMenu() {
  const menu = document.getElementById('user-menu-dropdown');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
  const container = document.querySelector('.user-menu-container');
  if (container && !container.contains(e.target)) {
    const menu = document.getElementById('user-menu-dropdown');
    if (menu) menu.style.display = 'none';
  }
});
</script>
