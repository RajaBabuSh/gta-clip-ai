<?php
// admin/index.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/Job.php';
require_once __DIR__ . '/../app/models/CostService.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

$admin = AdminMiddleware::requireAdmin();

$totalUsers = User::countAll();
$totalVideos = Video::countAll();
$totalClips = Clip::countAll();
$activeJobsCount = Job::countActive();
$totalStorage = Clip::getTotalStorageBytes();
$costs = CostService::getMonthlyTotals();
$workerHealth = AiWorkerClient::checkHealth();

$recentJobs = Job::getAll(8);

$activeNav = 'index';
$pageTitle = 'Master Overview';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">System Master Overview</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('dashboard.php') ?>" target="_blank" class="btn btn-secondary btn-sm">Preview User Studio ↗</a>
        <span class="badge <?= $workerHealth['online'] ? 'badge-green' : 'badge-red' ?>">
          <?= $workerHealth['online'] ? '● AI GPU Online' : '○ AI GPU Offline' ?>
        </span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Platform Master Dashboard</h1>
          <p class="page-desc">Comprehensive administrative control of users, video jobs, GPU worker cluster and storage.</p>
        </div>
      </div>

      <!-- Admin Stat Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon purple">👥</div>
          <div>
            <div class="stat-val"><?= $totalUsers ?></div>
            <div class="stat-lbl">Registered Users</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">⚡</div>
          <div>
            <div class="stat-val"><?= $activeJobsCount ?></div>
            <div class="stat-lbl">Active Jobs In Queue</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">🎬</div>
          <div>
            <div class="stat-val"><?= $totalClips ?></div>
            <div class="stat-lbl">Total Clips Produced</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">☁️</div>
          <div>
            <div class="stat-val"><?= format_bytes($totalStorage) ?></div>
            <div class="stat-lbl">Cluster Storage Used</div>
          </div>
        </div>
      </div>

      <!-- Worker Status Banner -->
      <div class="glass-card" style="padding:20px; margin-bottom:28px; border-color: <?= $workerHealth['online'] ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div style="display:flex; align-items:center; gap:16px;">
            <div style="font-size:32px;"><?= $workerHealth['online'] ? '🟢' : '🔴' ?></div>
            <div>
              <div style="font-size:16px; font-weight:700;">
                AI Remote Worker: <code style="color:var(--secondary);"><?= htmlspecialchars($workerHealth['worker_url']) ?></code>
              </div>
              <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                Status: <?= $workerHealth['online'] ? '<span style="color:#6ee7b7; font-weight:600;">Operational (FastAPI + FFmpeg NVENC + Whisper)</span>' : '<span style="color:#f87171; font-weight:600;">Unreachable or Starting</span>' ?>
              </div>
            </div>
          </div>
          <a href="<?= url('admin/workers.php') ?>" class="btn btn-secondary btn-sm">Worker Node Manager →</a>
        </div>
      </div>

      <!-- Global Job Queue -->
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <h2 style="font-size:18px; font-weight:700;">Recent Platform Processing Jobs</h2>
          <a href="<?= url('admin/jobs.php') ?>" style="font-size:13px; color:var(--secondary); text-decoration:none;">View All Jobs →</a>
        </div>

        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Job ID</th>
                  <th>User</th>
                  <th>Source Stream</th>
                  <th>Progress</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentJobs)): ?>
                  <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">No platform jobs recorded.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentJobs as $j): ?>
                    <tr>
                      <td><code style="color:var(--secondary); font-size:12px;"><?= htmlspecialchars(substr($j['id'], 0, 8)) ?>...</code></td>
                      <td><?= htmlspecialchars($j['username'] ?? 'User') ?></td>
                      <td style="font-weight:600; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($j['video_title'] ?: 'Stream Processing') ?>
                      </td>
                      <td style="width:140px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                          <div class="quota-progress" style="flex:1; height:6px;">
                            <div class="quota-progress-bar" style="width: <?= (int)$j['progress'] ?>%;"></div>
                          </div>
                          <span style="font-size:11px; font-weight:700;"><?= (int)$j['progress'] ?>%</span>
                        </div>
                      </td>
                      <td>
                        <span class="badge <?= $j['status'] === 'completed' ? 'badge-green' : ($j['status'] === 'failed' ? 'badge-red' : 'badge-cyan') ?>">
                          <?= strtoupper($j['status']) ?>
                        </span>
                      </td>
                      <td style="color:var(--text-muted); font-size:12px;"><?= time_ago($j['created_at']) ?></td>
                      <td>
                        <a href="<?= url('admin/jobs.php?id=' . urlencode($j['id'])) ?>" class="btn btn-secondary btn-sm">Inspect</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
