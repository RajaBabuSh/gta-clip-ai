<?php
// admin/clips.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/services/StorageManager.php';

$admin = AdminMiddleware::requirePermission('clips.manage');

$pdo = Database::getConnection();
$clips = $pdo->query("
    SELECT c.*, v.title AS video_title, u.username
    FROM clips c
    LEFT JOIN videos v ON c.video_id = v.id
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC LIMIT 100
")->fetchAll();

$activeNav = 'clips';
$pageTitle = 'Clips Moderation';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Clips Moderation</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-purple">Total: <?= count($clips) ?> Clips</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">All Generated Video Clips</h1>
          <p class="page-desc">Inspect generated AI shorts, review virality ratings and check source provenance.</p>
        </div>
      </div>

      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Owner</th>
                <th>Source Stream</th>
                <th>Duration</th>
                <th>Size</th>
                <th>Viral Score</th>
                <th>Date</th>
                <th>Preview</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clips as $c): 
                $vUrl = url($c['file_path']);
              ?>
                <tr>
                  <td style="font-weight:600; max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($c['title']) ?>
                  </td>
                  <td><?= htmlspecialchars($c['username'] ?? 'User') ?></td>
                  <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--text-muted);">
                    <?= htmlspecialchars($c['video_title'] ?: 'Stream Highlight') ?>
                  </td>
                  <td><?= format_duration($c['duration'] ?? 30) ?></td>
                  <td><?= format_bytes($c['file_size'] ?? 0) ?></td>
                  <td>
                    <span class="badge badge-purple">
                      🔥 <?= round((float)($c['virality_score'] ?? 0.85) * 100) ?>%
                    </span>
                  </td>
                  <td style="color:var(--text-muted); font-size:12px;"><?= time_ago($c['created_at']) ?></td>
                  <td style="white-space: nowrap;">
                    <a href="<?= htmlspecialchars($vUrl) ?>" target="_blank" class="btn btn-secondary btn-sm">
                      ▶ Open
                    </a>
                    <a href="<?= htmlspecialchars(StorageManager::getSignedDownloadUrl($c['file_path'], 3600, $c['title'])) ?>" class="btn btn-primary btn-sm" title="Signed Download">
                      ⬇️ MP4
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
