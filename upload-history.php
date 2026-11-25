<?php
// upload-history.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/YouTubeUpload.php';

$user = AuthMiddleware::requireUser();
$uploads = YouTubeUpload::getByUser($user['id'], 50);

$activeNav = 'history';
$pageTitle = 'YouTube Upload History';
$topbarTitle = 'Upload History & Analytics';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="max-width: 1040px; margin: 0 auto; padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:26px; font-weight:800; color:white; display:flex; align-items:center; gap:10px;">
            📜 YouTube Upload History & Audit Log
          </h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Full history of published, scheduled, and failed YouTube Shorts uploads with direct video links and status indicators.
          </p>
        </div>
        <div>
          <a href="<?= url('youtube-studio.php') ?>" class="btn btn-primary btn-sm">▶️ YouTube Studio</a>
        </div>
      </div>

      <div class="glass-card" style="padding:24px;">
        <?php if (empty($uploads)): ?>
          <div style="text-align:center; padding:48px; color:var(--text-muted);">
            <div style="font-size:40px; margin-bottom:12px;">📭</div>
            <h3 style="font-size:18px; color:white; margin-bottom:6px;">No Upload Records Found</h3>
            <p style="font-size:13px; margin-bottom:20px;">Use the YouTube Studio or the Automated Scheduler to publish clips.</p>
            <a href="<?= url('youtube-studio.php') ?>" class="btn btn-primary btn-md">Go to YouTube Studio</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Clip & Thumbnail</th>
                  <th>Published Title</th>
                  <th>Privacy</th>
                  <th>Status</th>
                  <th>YouTube Shorts Link</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($uploads as $u): ?>
                  <tr>
                    <td style="width:140px;">
                      <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:48px; aspect-ratio:9/16; background:#000; border-radius:4px; overflow:hidden;">
                          <?php if (!empty($u['thumbnail_path'])): ?>
                            <img src="<?= htmlspecialchars(url($u['thumbnail_path'])) ?>" style="width:100%; height:100%; object-fit:cover;">
                          <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:14px;">🎬</div>
                          <?php endif; ?>
                        </div>
                        <span style="font-size:11px; color:var(--text-dim);"><?= round((float)($u['duration'] ?? 15), 1) ?>s</span>
                      </div>
                    </td>
                    <td>
                      <div style="font-size:13px; font-weight:700; color:white; margin-bottom:2px; max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($u['title']) ?>
                      </div>
                      <div style="font-size:11px; color:var(--text-muted);">
                        Virality: <?= round((float)($u['virality_score'] ?? 0.9) * 100) ?>%
                      </div>
                    </td>
                    <td>
                      <span class="badge badge-purple"><?= strtoupper($u['privacy_status']) ?></span>
                    </td>
                    <td>
                      <span class="badge <?= $u['upload_status'] === 'published' ? 'badge-green' : ($u['upload_status'] === 'failed' ? 'badge-red' : 'badge-cyan') ?>">
                        <?= strtoupper($u['upload_status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if (!empty($u['youtube_url'])): ?>
                        <a href="<?= htmlspecialchars($u['youtube_url']) ?>" target="_blank" style="color:#38bdf8; text-decoration:underline; font-size:12px; font-weight:700;">
                          <?= htmlspecialchars($u['youtube_video_id'] ?: 'Watch Short') ?> ↗
                        </a>
                      <?php else: ?>
                        <span style="color:var(--text-dim); font-size:12px;">-</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:var(--text-muted);">
                      <?= date('M d, Y H:i', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                      <?php if ($u['upload_status'] === 'failed'): ?>
                        <button onclick="retryUpload('<?= htmlspecialchars($u['id']) ?>')" class="btn btn-secondary btn-sm" style="font-size:11px; color:#38bdf8;">
                          🔄 Retry
                        </button>
                      <?php else: ?>
                        <span style="font-size:11px; color:#10b981;">✓ Synced</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
async function retryUpload(uploadId) {
  showToast('Retrying YouTube upload...', 'info');
  try {
    const res = await fetch(`api/clips.php?action=retry_upload&id=${encodeURIComponent(uploadId)}`, { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Upload succeeded!', 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(data.message || 'Retry failed', 'error');
    }
  } catch (e) {
    showToast('Error retrying upload: ' + e.message, 'error');
  }
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
