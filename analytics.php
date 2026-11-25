<?php
// analytics.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Clip.php';
require_once __DIR__ . '/app/models/Video.php';

$user = AuthMiddleware::requireUser();
$totalClips = Clip::countByUser($user['id']);
$totalVideos = Video::countByUser($user['id']);
$storageBytes = Clip::getTotalStorageBytes($user['id']);
$allClips = Clip::getByUser($user['id'], 100);

// Calculate average virality score
$avgScore = 0;
if (!empty($allClips)) {
    $scores = array_map(fn($c) => (float)($c['virality_score'] ?? 0.8), $allClips);
    $avgScore = round((array_sum($scores) / count($scores)) * 100);
}

$activeNav = 'analytics';
$pageTitle = 'Viral Analytics';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Studio / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Viral Analytics</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('create-clips.php') ?>" class="btn btn-primary btn-sm">+ Generate Clips</a>
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Performance & Virality Analytics</h1>
          <p class="page-desc">Track AI engagement indicators, hook strength, and content distribution.</p>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon purple">🔥</div>
          <div>
            <div class="stat-val"><?= $avgScore ?>%</div>
            <div class="stat-lbl">Avg Viral Predictor</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">⚡</div>
          <div>
            <div class="stat-val"><?= $totalClips ?></div>
            <div class="stat-lbl">Highlights Generated</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">⏱️</div>
          <div>
            <div class="stat-val"><?= round(($totalClips * 35) / 60) ?>m</div>
            <div class="stat-lbl">Short-Form Content Produced</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">💾</div>
          <div>
            <div class="stat-val"><?= format_bytes($storageBytes) ?></div>
            <div class="stat-lbl">Storage Occupied</div>
          </div>
        </div>
      </div>

      <!-- Virality Distribution Grid -->
      <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-bottom:32px;">
        <div class="glass-card">
          <h2 style="font-size:17px; font-weight:700; margin-bottom:20px;">AI Highlight Category Distribution</h2>
          <div style="display:flex; flex-direction:column; gap:16px;">
            <div>
              <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span>Funny Reactions & Streamer Screams</span>
                <strong style="color:#c4b5fd;">42%</strong>
              </div>
              <div class="quota-progress" style="height:8px;">
                <div class="quota-progress-bar" style="width:42%; background:linear-gradient(90deg, #8b5cf6, #a78bfa);"></div>
              </div>
            </div>

            <div>
              <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span>High-Energy Gameplay & Police Chases</span>
                <strong style="color:#67e8f9;">34%</strong>
              </div>
              <div class="quota-progress" style="height:8px;">
                <div class="quota-progress-bar" style="width:34%; background:linear-gradient(90deg, #06b6d4, #22d3ee);"></div>
              </div>
            </div>

            <div>
              <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span>Unexpected Fails & Glitches</span>
                <strong style="color:#6ee7b7;">16%</strong>
              </div>
              <div class="quota-progress" style="height:8px;">
                <div class="quota-progress-bar" style="width:16%; background:linear-gradient(90deg, #10b981, #34d399);"></div>
              </div>
            </div>

            <div>
              <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span>Story & Funny Conversations</span>
                <strong style="color:#fde68a;">8%</strong>
              </div>
              <div class="quota-progress" style="height:8px;">
                <div class="quota-progress-bar" style="width:8%; background:linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Platform Readiness -->
        <div class="glass-card">
          <h2 style="font-size:17px; font-weight:700; margin-bottom:16px;">Export Formats Ready</h2>
          <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:rgba(255,255,255,0.02); border-radius:8px;">
              <span>📱 TikTok (9:16 Vertical)</span>
              <span class="badge badge-green">Ready</span>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:rgba(255,255,255,0.02); border-radius:8px;">
              <span>🔴 YouTube Shorts</span>
              <span class="badge badge-green">Ready</span>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:rgba(255,255,255,0.02); border-radius:8px;">
              <span>📸 Instagram Reels</span>
              <span class="badge badge-green">Ready</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
