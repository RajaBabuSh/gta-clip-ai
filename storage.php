<?php
// storage.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Clip.php';

$user = AuthMiddleware::requireUser();

$usedBytes = Clip::getTotalStorageBytes($user['id']);
$maxBytes = 25 * 1024 * 1024 * 1024; // 25 GB default
$percent = min(100, round(($usedBytes / $maxBytes) * 100, 1));
$clips = Clip::getByUser($user['id'], 50);

$activeNav = 'storage';
$pageTitle = 'Cloud Storage';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Studio / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Cloud Storage</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('create-clips.php') ?>" class="btn btn-primary btn-sm">+ Create Clips</a>
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container" style="max-width: 1000px; margin: 0 auto;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Cloud Storage Meter</h1>
          <p class="page-desc">Manage your high-definition MP4 clips, master audio chunks, and stream caches.</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="showToast('Cache is optimized!', 'success')">
          🧹 Clean Temp Files
        </button>
      </div>

      <!-- Storage Meter Card -->
      <div class="glass-card" style="padding:28px; margin-bottom:28px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:12px;">
          <div>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:4px;">Allocated Cloud Storage</div>
            <div style="font-size:28px; font-weight:900;">
              <?= format_bytes($usedBytes) ?> <span style="font-size:16px; color:var(--text-muted); font-weight:500;">/ <?= format_bytes($maxBytes) ?></span>
            </div>
          </div>
          <span class="badge badge-purple" style="font-size:12px;"><?= $percent ?>% Used</span>
        </div>
        <div class="quota-progress" style="height:10px;">
          <div class="quota-progress-bar" style="width: <?= $percent ?>%;"></div>
        </div>
      </div>

      <!-- Files breakdown -->
      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div style="padding:20px 24px; border-bottom:1px solid var(--border-color); font-weight:700; font-size:16px;">
          Stored Clip Media Files (<?= count($clips) ?>)
        </div>
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>File Name / Title</th>
                <th>Format</th>
                <th>File Size</th>
                <th>Date Saved</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clips)): ?>
                <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No stored media files yet.</td></tr>
              <?php else: ?>
                <?php foreach (array_slice($clips, 0, 15) as $c): 
                  $vUrl = url($c['file_path']);
                ?>
                  <tr>
                    <td style="font-weight:600; max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars($c['title']) ?>.mp4
                    </td>
                    <td><span class="badge badge-cyan">MP4 (9:16)</span></td>
                    <td><?= format_bytes($c['file_size'] ?? 0) ?></td>
                    <td style="color:var(--text-muted); font-size:13px;"><?= time_ago($c['created_at']) ?></td>
                    <td>
                      <a href="<?= htmlspecialchars($vUrl) ?>" download class="btn btn-secondary btn-sm">Download</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
