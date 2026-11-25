<?php
// downloads.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Video.php';
require_once __DIR__ . '/app/models/Clip.php';

$user = AuthMiddleware::requireUser();
$videos = Video::getByUser($user['id'], 20);
$allClips = Clip::getByUser($user['id'], 100);

$activeNav = 'downloads';
$pageTitle = 'Download Center & Batch ZIPs';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Export & Batch Downloads</h1>
          <p class="page-desc">Download complete packages of 10, 20, 30, or 40 clips as compressed ZIP archives or grab individual MP4 files.</p>
        </div>
      </div>

      <!-- Batch ZIP Archives by Stream -->
      <div style="margin-bottom:36px;">
        <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Stream ZIP Packages</h2>

        <?php if (empty($videos)): ?>
          <div class="glass-card" style="text-align:center; padding:48px;">
            <p style="color:var(--text-muted);">No streams processed yet.</p>
          </div>
        <?php else: ?>
          <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(360px, 1fr)); gap:20px;">
            <?php foreach ($videos as $v): 
              $vClips = Clip::getByVideo($v['id']);
              if (empty($vClips)) continue;
              $totalSize = array_sum(array_column($vClips, 'file_size'));
            ?>
              <div class="glass-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; gap:14px; margin-bottom:16px;">
                  <img src="<?= htmlspecialchars($v['thumbnail_url'] ?: 'https://via.placeholder.com/120x68') ?>" style="width:100px; height:56px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color);">
                  <div style="flex-grow:1; overflow:hidden;">
                    <div style="font-size:14px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($v['title']) ?>">
                      <?= htmlspecialchars($v['title']) ?>
                    </div>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                      <?= count($vClips) ?> Clips Generated &bull; <?= format_bytes($totalSize) ?>
                    </div>
                  </div>
                </div>

                <div style="margin-top:auto; display:flex; gap:10px;">
                  <a href="<?= url('api/clips.php?action=zip&video_id=' . urlencode($v['id'])) ?>" class="btn btn-primary btn-sm" style="flex:1;">
                    📦 Download Complete ZIP (<?= count($vClips) ?> Clips)
                  </a>
                  <a href="<?= url('my-clips.php?video_id=' . urlencode($v['id'])) ?>" class="btn btn-secondary btn-sm">
                    View
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Individual MP4 Table -->
      <div>
        <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">All Available MP4 Files (<?= count($allClips) ?>)</h2>
        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Clip Title</th>
                  <th>Duration</th>
                  <th>Size</th>
                  <th>Viral Score</th>
                  <th>Generated</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($allClips)): ?>
                  <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">No clips ready for download.</td></tr>
                <?php else: ?>
                  <?php foreach (array_slice($allClips, 0, 15) as $c): 
                    $vUrl = url($c['file_path']);
                  ?>
                    <tr>
                      <td style="font-weight:600; max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($c['title']) ?>
                      </td>
                      <td><?= format_duration($c['duration'] ?? 30) ?></td>
                      <td><?= format_bytes($c['file_size'] ?? 0) ?></td>
                      <td>
                        <span class="badge badge-purple">
                          🔥 <?= round((float)($c['virality_score'] ?? 0.85) * 100) ?>%
                        </span>
                      </td>
                      <td style="color:var(--text-muted); font-size:13px;"><?= time_ago($c['created_at']) ?></td>
                      <td>
                        <a href="<?= htmlspecialchars($vUrl) ?>" download class="btn btn-primary btn-sm">
                          ⬇️ MP4
                        </a>
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

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
