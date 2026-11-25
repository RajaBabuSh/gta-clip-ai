<?php
// views/partials/sidebar.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/functions.php';

$activeNav = $activeNav ?? 'dashboard';
$user = get_auth_user();
$quotaUsed = $user['clips_generated_this_month'] ?? 0;
$quotaTotal = $user['monthly_clip_quota'] ?? 50;
$quotaPercent = min(100, round(($quotaUsed / max(1, $quotaTotal)) * 100));
?>
<aside class="app-sidebar">
  <a href="<?= url('dashboard.php') ?>" class="brand-badge">
    <div class="brand-icon">⚡</div>
    <div>
      <div class="brand-title">EiziClip</div>
      <div class="brand-sub">Clip while you watch.</div>
    </div>
  </a>

  <nav class="sidebar-nav">
    <div class="nav-label">Core Studio</div>
    <a href="<?= url('dashboard.php') ?>" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="<?= url('add-video.php') ?>" class="nav-item <?= $activeNav === 'add-video' ? 'active' : '' ?>">
      <span class="nav-icon">➕</span> Add Video
    </a>
    <a href="<?= url('create-clips.php') ?>" class="nav-item <?= $activeNav === 'create' ? 'active' : '' ?>">
      <span class="nav-icon">✨</span> Create Clips
    </a>
    <a href="<?= url('live-monitor.php') ?>" class="nav-item <?= $activeNav === 'monitor' ? 'active' : '' ?>">
      <span class="nav-icon">📡</span> Live Monitor
    </a>

    <div class="nav-label">Workspace</div>
    <a href="<?= url('workspace.php') ?>" class="nav-item <?= $activeNav === 'workspace' ? 'active' : '' ?>">
      <span class="nav-icon">⚡</span> Watch &amp; Clip Studio
    </a>
    <a href="<?= url('my-projects.php') ?>" class="nav-item <?= $activeNav === 'projects' ? 'active' : '' ?>">
      <span class="nav-icon">📁</span> My Projects
    </a>
    <a href="<?= url('my-clips.php') ?>" class="nav-item <?= $activeNav === 'clips' ? 'active' : '' ?>">
      <span class="nav-icon">🎬</span> My Clips
    </a>
    <a href="<?= url('editor.php') ?>" class="nav-item <?= $activeNav === 'editor' ? 'active' : '' ?>">
      <span class="nav-icon">✂️</span> Video Formatter
    </a>

    <div class="nav-label">YouTube Automation</div>
    <a href="<?= url('youtube-studio.php') ?>" class="nav-item <?= $activeNav === 'youtube' ? 'active' : '' ?>">
      <span class="nav-icon">▶️</span> YouTube Studio
    </a>
    <a href="<?= url('scheduler.php') ?>" class="nav-item <?= $activeNav === 'scheduler' ? 'active' : '' ?>">
      <span class="nav-icon">⏰</span> Upload Scheduler
    </a>
    <a href="<?= url('upload-history.php') ?>" class="nav-item <?= $activeNav === 'history' ? 'active' : '' ?>">
      <span class="nav-icon">📜</span> Upload History
    </a>

    <div class="nav-label">Data & Configuration</div>
    <a href="<?= url('downloads.php') ?>" class="nav-item <?= $activeNav === 'downloads' ? 'active' : '' ?>">
      <span class="nav-icon">📦</span> Downloads
    </a>
    <a href="<?= url('api-usage.php') ?>" class="nav-item <?= $activeNav === 'api-usage' ? 'active' : '' ?>">
      <span class="nav-icon">⚡</span> API Usage & Costs
    </a>
    <a href="<?= url('settings.php') ?>" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
  </nav>

  <div class="sidebar-quota-box">
    <div class="quota-header">
      <span style="color:var(--text-muted);">Monthly Quota</span>
      <span style="font-weight:700; color:#c4b5fd;"><?= $quotaUsed ?> / <?= $quotaTotal ?></span>
    </div>
    <div class="quota-progress">
      <div class="quota-progress-bar" style="width: <?= $quotaPercent ?>%;"></div>
    </div>
    <div style="margin-top: 10px; display:flex; justify-content:space-between; align-items:center;">
      <span class="badge badge-purple" style="font-size:10px;"><?= strtoupper($user['plan_tier'] ?? 'PRO') ?> TIER</span>
      <a href="<?= url('settings.php') ?>" style="font-size:11px; color:var(--secondary); text-decoration:none;">Upgrade</a>
    </div>
  </div>
</aside>
