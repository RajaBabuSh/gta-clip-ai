<?php
// admin/analytics.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/User.php';

$admin = AdminMiddleware::requireAdmin();

$totalUsers = User::countAll();
$totalClips = Clip::countAll();
$totalVideos = Video::countAll();

$activeNav = 'analytics';
$pageTitle = 'Platform Analytics';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Platform Growth & Health</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-purple"><?= $totalClips ?> Total Generated</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container" style="max-width: 1000px; margin: 0 auto;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Growth & Platform Telemetry</h1>
          <p class="page-desc">System analytics on user retention, processing load, and AI inference performance.</p>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon purple">👥</div>
          <div>
            <div class="stat-val"><?= $totalUsers ?></div>
            <div class="stat-lbl">Creators Registered</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">📹</div>
          <div>
            <div class="stat-val"><?= $totalVideos ?></div>
            <div class="stat-lbl">Source Streams Analyzed</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">⚡</div>
          <div>
            <div class="stat-val"><?= $totalClips ?></div>
            <div class="stat-lbl">Vertical Shorts Exported</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">⏱️</div>
          <div>
            <div class="stat-val">99.9%</div>
            <div class="stat-lbl">Worker Availability</div>
          </div>
        </div>
      </div>

      <!-- Ingestion Sources -->
      <div class="glass-card" style="padding: 24px; margin-top: 24px;">
        <h2 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Source Stream Ingestion Breakdown</h2>
        <div style="display:flex; flex-direction:column; gap:16px;">
          <div>
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
              <span>YouTube Live Streams & VODs</span>
              <strong style="color:#c4b5fd;">75%</strong>
            </div>
            <div class="quota-progress" style="height:8px;">
              <div class="quota-progress-bar" style="width:75%; background:linear-gradient(90deg, #ef4444, #8b5cf6);"></div>
            </div>
          </div>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
              <span>Direct File Uploads (MP4 / MKV)</span>
              <strong style="color:#67e8f9;">20%</strong>
            </div>
            <div class="quota-progress" style="height:8px;">
              <div class="quota-progress-bar" style="width:20%; background:linear-gradient(90deg, #06b6d4, #22d3ee);"></div>
            </div>
          </div>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
              <span>Twitch & Kick Streams</span>
              <strong style="color:#6ee7b7;">5%</strong>
            </div>
            <div class="quota-progress" style="height:8px;">
              <div class="quota-progress-bar" style="width:5%; background:linear-gradient(90deg, #10b981, #34d399);"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
