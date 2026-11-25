<?php
// dashboard.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Video.php';
require_once __DIR__ . '/app/models/Clip.php';
require_once __DIR__ . '/app/models/Project.php';
require_once __DIR__ . '/app/models/Job.php';
require_once __DIR__ . '/app/models/YouTubeUpload.php';
require_once __DIR__ . '/app/models/UploadScheduler.php';

$user = AuthMiddleware::requireUser();
$pdo = Database::getConnection();

$totalClips = Clip::countByUser($user['id']);
$totalVideos = Video::countByUser($user['id']);
$storageBytes = Clip::getTotalStorageBytes($user['id']);
$recentClips = Clip::getByUser($user['id'], 6);
$recentJobs = Job::getByUser($user['id'], 5);
$projects = Project::getByUser($user['id']);

// Count YouTube Uploads
$stmt = $pdo->prepare("SELECT COUNT(*) FROM youtube_uploads WHERE user_id = ? AND upload_status = 'published'");
$stmt->execute([$user['id']]);
$totalUploadedShorts = (int)$stmt->fetchColumn();

// Count Gemini API Calls
$stmt = $pdo->prepare("SELECT COUNT(*) FROM api_usage WHERE (user_id = ? OR user_id IS NULL) AND service = 'gemini'");
$stmt->execute([$user['id']]);
$totalGeminiCalls = (int)$stmt->fetchColumn();

// Awaiting Approval
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clips WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$clipsAwaitingApproval = (int)$stmt->fetchColumn();

$schedule = UploadScheduler::getSchedule($user['id']);
$nextSlot = UploadScheduler::getNextSlot($schedule);

$activeNav = 'dashboard';
$pageTitle = 'Creator Dashboard';
$topbarTitle = 'Creator Studio Dashboard';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:24px; font-weight:800; color:white;">
            Welcome back, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?> 👋
          </h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Here is your live AI clipping engine overview, automated publishing schedule, and workspace statistics.
          </p>
        </div>
        <div style="display:flex; gap:10px;">
          <a href="<?= url('workspace.php') ?>" class="btn btn-secondary btn-md">
            ⚡ Watch &amp; Clip Studio
          </a>
          <a href="<?= url('add-video.php') ?>" class="btn btn-secondary btn-md">
            ➕ Add Video
          </a>
          <a href="<?= url('create-clips.php') ?>" class="btn btn-primary btn-md">
            <span>✨</span> Create AI Clips
          </a>
        </div>
      </div>

      <!-- Stats Grid (8 Core Metrics) -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon purple">🎬</div>
          <div>
            <div class="stat-val"><?= $totalClips ?></div>
            <div class="stat-lbl">Clips Generated</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">📹</div>
          <div>
            <div class="stat-val"><?= $totalVideos ?></div>
            <div class="stat-lbl">Videos Analyzed</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">▶️</div>
          <div>
            <div class="stat-val"><?= $totalUploadedShorts ?></div>
            <div class="stat-lbl">Uploaded Shorts</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">⏰</div>
          <div>
            <div class="stat-val" style="font-size:15px; font-weight:800;"><?= $nextSlot['time'] ?></div>
            <div class="stat-lbl">Next Scheduled Slot</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">📁</div>
          <div>
            <div class="stat-val"><?= count($projects) ?></div>
            <div class="stat-lbl">Total Projects</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">⏳</div>
          <div>
            <div class="stat-val"><?= $clipsAwaitingApproval ?></div>
            <div class="stat-lbl">Awaiting Approval</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">⚡</div>
          <div>
            <div class="stat-val"><?= $totalGeminiCalls ?></div>
            <div class="stat-lbl">Gemini AI Invocations</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">☁️</div>
          <div>
            <div class="stat-val" style="font-size:16px; font-weight:800;"><?= format_bytes($storageBytes) ?></div>
            <div class="stat-lbl">Storage Used</div>
          </div>
        </div>
      </div>

      <!-- Recent Highlight Clips Section -->
      <div style="margin-bottom: 36px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
          <h2 style="font-size:18px; font-weight:800; color:white;">Recent AI Highlights</h2>
          <a href="<?= url('my-clips.php') ?>" style="font-size:13px; color:var(--secondary); text-decoration:none; font-weight:700;">
            View All Clips (<?= $totalClips ?>) →
          </a>
        </div>

        <?php if (empty($recentClips)): ?>
          <div class="glass-card" style="text-align:center; padding:48px 24px;">
            <div style="font-size:40px; margin-bottom:12px;">🎬</div>
            <h3 style="font-size:18px; font-weight:700; color:white; margin-bottom:6px;">No Clips Generated Yet</h3>
            <p style="font-size:14px; color:var(--text-muted); margin-bottom:20px;">
              Paste an authorized YouTube stream or upload raw gameplay to generate vertical shorts automatically.
            </p>
            <a href="<?= url('add-video.php') ?>" class="btn btn-primary btn-md">Add First Video</a>
          </div>
        <?php else: ?>
          <div class="clips-grid">
            <?php foreach ($recentClips as $clip): 
              $videoUrl = $clip['video_url'];
              $thumbUrl = $clip['thumbnail_url'];
            ?>
              <div class="clip-card">
                <div class="clip-preview-wrap">
                  <div class="clip-overlay-score">
                    🔥 <span><?= round((float)($clip['virality_score'] ?? 0.85) * ($clip['virality_score'] > 1 ? 1 : 100)) ?>% Viral</span>
                  </div>
                  <video 
                    src="<?= htmlspecialchars($videoUrl) ?>" 
                    poster="<?= htmlspecialchars($thumbUrl ?: '') ?>"
                    preload="metadata" 
                    controls 
                    playsinline 
                    class="clip-video"
                  ></video>
                  <div class="clip-duration-badge"><?= format_duration($clip['duration'] ?? 30) ?></div>
                </div>
                <div class="clip-content">
                  <div class="clip-title" title="<?= htmlspecialchars($clip['title']) ?>">
                    <?= htmlspecialchars($clip['title']) ?>
                  </div>
                  <div class="clip-meta">
                    <span><?= htmlspecialchars(substr($clip['video_title'] ?: 'Stream Highlight', 0, 20)) ?></span>
                    <span><?= time_ago($clip['created_at']) ?></span>
                  </div>
                  <div class="clip-actions" style="display:flex; gap:6px;">
                    <a href="<?= url('editor.php?id=' . urlencode($clip['id'])) ?>" class="btn btn-secondary btn-sm" style="flex:1;">
                      ✂️ Edit
                    </a>
                    <a href="<?= htmlspecialchars($videoUrl) ?>" download class="btn btn-secondary btn-sm" style="flex:1;">
                      ⬇️ MP4
                    </a>
                    <a href="<?= url('youtube-studio.php') ?>" class="btn btn-danger btn-sm" title="Publish to YouTube">
                      ▶️
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent Jobs Table -->
      <div class="glass-card" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <h2 style="font-size:18px; font-weight:800; color:white;">Recent AI Clipping Jobs</h2>
          <a href="<?= url('live-monitor.php') ?>" class="btn btn-secondary btn-sm">Live Monitor →</a>
        </div>

        <div id="recent-jobs-wrapper">
          <?php if (empty($recentJobs)): ?>
            <div id="empty-jobs-notice" style="text-align:center; padding:32px; color:var(--text-muted);">
              No processing jobs dispatched yet.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Source Video</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Target / Rendered</th>
                    <th>Dispatched</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="jobs-table-body">
                  <?php foreach ($recentJobs as $jb): 
                    $st = strtoupper($jb['status']);
                    $badgeClass = ($st === 'COMPLETED') ? 'badge-green' : (($st === 'PARTIALLY_COMPLETED') ? 'badge-cyan' : (($st === 'FAILED') ? 'badge-red' : 'badge-purple'));
                  ?>
                    <tr id="job-row-<?= htmlspecialchars($jb['id']) ?>">
                      <td>
                        <strong><?= htmlspecialchars(substr($jb['video_title'] ?: 'Stream Clipping', 0, 34)) ?>...</strong>
                        <div style="font-size:11px; color:var(--text-dim);"><?= htmlspecialchars($jb['id']) ?></div>
                      </td>
                      <td>
                        <span class="badge <?= $badgeClass ?>"><?= $st === 'PARTIALLY_COMPLETED' ? 'PARTIAL' : $st ?></span>
                      </td>
                      <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                          <div class="quota-progress" style="width:70px; height:6px;">
                            <div class="quota-progress-bar" style="width:<?= (int)$jb['progress'] ?>%;"></div>
                          </div>
                          <span style="font-size:12px; font-weight:700; color:white;"><?= (int)$jb['progress'] ?>%</span>
                        </div>
                      </td>
                      <td style="font-size:12px; color:var(--text-muted);">
                        <?= (int)($jb['clips_rendered_count'] ?? 0) ?> / <?= (int)($jb['clips_count'] ?: 10) ?> Clips
                      </td>
                      <td style="font-size:12px; color:var(--text-muted);">
                        <?= time_ago($jb['created_at']) ?>
                      </td>
                      <td>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                          <a href="<?= url('live-monitor.php?job_id=' . urlencode($jb['id'])) ?>" class="btn btn-secondary btn-sm" style="font-size:11px; white-space:nowrap;">
                            View Results →
                          </a>
                          <button type="button" class="btn btn-sm btn-delete-job" 
                                  data-job-id="<?= htmlspecialchars($jb['id']) ?>" 
                                  data-video-title="<?= htmlspecialchars($jb['video_title'] ?: 'AI Clipping Job') ?>"
                                  style="font-size:11px; padding:6px 10px; background:rgba(239, 68, 68, 0.12); color:#f87171; border:1px solid rgba(239, 68, 68, 0.3); border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-weight:600; white-space:nowrap; transition:all 0.15s ease;"
                                  onmouseover="this.style.background='rgba(239, 68, 68, 0.22)'; this.style.borderColor='#ef4444';"
                                  onmouseout="this.style.background='rgba(239, 68, 68, 0.12)'; this.style.borderColor='rgba(239, 68, 68, 0.3)';"
                                  title="Delete this clipping job and all associated clips">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteJobModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.78); backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px;">
  <div style="background:#13151b; border:1px solid rgba(239,68,68,0.35); border-radius:14px; max-width:480px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.9); overflow:hidden;">
    
    <!-- Modal Header -->
    <div style="padding:20px 24px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:14px;">
      <div style="width:40px; height:40px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); display:flex; align-items:center; justify-content:center; color:#ef4444; flex-shrink:0;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
      <div>
        <h3 style="margin:0; font-size:17px; font-weight:800; color:#fff;">Delete Clipping Job</h3>
        <p style="margin:2px 0 0; font-size:12px; color:var(--text-dim);">Permanent Removal Confirmation</p>
      </div>
    </div>

    <!-- Modal Body -->
    <div style="padding:22px 24px;">
      <p style="margin:0 0 16px; font-size:14px; color:#e2e8f0; line-height:1.5; font-weight:600;">
        Are you sure you want to delete this clipping job? This action cannot be undone.
      </p>

      <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:8px; padding:12px 14px; margin-bottom:16px;">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-dim); margin-bottom:4px;">Target Video / Job</div>
        <div id="modalTargetTitle" style="font-size:13px; font-weight:700; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Stream Clipping</div>
        <div id="modalTargetJobId" style="font-size:11px; color:#94a3b8; font-family:monospace; margin-top:2px;">uuid</div>
      </div>

      <div style="background:rgba(239,68,68,0.08); border-left:3px solid #ef4444; border-radius:4px; padding:10px 12px; font-size:12px; color:#fca5a5; line-height:1.4;">
        <strong>Storage Cleanup:</strong> Associated generated clips, thumbnails, and render artifacts will be permanently purged from storage. The original source video is kept safe.
      </div>
    </div>

    <!-- Modal Footer Actions -->
    <div style="padding:16px 24px; background:rgba(0,0,0,0.25); border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:flex-end; gap:10px;">
      <button type="button" id="modalCancelBtn" class="btn btn-secondary" style="font-size:13px; padding:8px 16px;">
        Cancel
      </button>
      <button type="button" id="modalConfirmDeleteBtn" class="btn" style="background:#ef4444; color:#fff; font-size:13px; padding:8px 18px; font-weight:700; border:none; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
        <span id="deleteSpinner" style="display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.8s linear infinite;"></span>
        <svg id="deleteIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        <span id="deleteBtnText">Delete Job Permanently</span>
      </button>
    </div>
  </div>
</div>

<!-- Floating Toast Container -->
<div id="toastNotification" style="display:none; position:fixed; bottom:24px; right:24px; z-index:999999; padding:14px 20px; border-radius:8px; font-size:13px; font-weight:600; box-shadow:0 10px 25px rgba(0,0,0,0.5); align-items:center; gap:10px; transition:opacity 0.3s ease;">
  <span id="toastIcon"></span>
  <span id="toastMsg"></span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('deleteJobModal');
  const modalTargetTitle = document.getElementById('modalTargetTitle');
  const modalTargetJobId = document.getElementById('modalTargetJobId');
  const cancelBtn = document.getElementById('modalCancelBtn');
  const confirmBtn = document.getElementById('modalConfirmDeleteBtn');
  const deleteSpinner = document.getElementById('deleteSpinner');
  const deleteIcon = document.getElementById('deleteIcon');
  const deleteBtnText = document.getElementById('deleteBtnText');
  const toast = document.getElementById('toastNotification');
  const toastMsg = document.getElementById('toastMsg');
  const toastIcon = document.getElementById('toastIcon');

  let activeJobId = null;
  const csrfToken = '<?= csrf_token() ?>';

  function showToast(message, isSuccess = true) {
    if (!toast) return;
    toastMsg.textContent = message;
    if (isSuccess) {
      toast.style.background = '#065f46';
      toast.style.border = '1px solid #10b981';
      toast.style.color = '#ecfdf5';
      toastIcon.innerHTML = '✓';
    } else {
      toast.style.background = '#7f1d1d';
      toast.style.border = '1px solid #ef4444';
      toast.style.color = '#fef2f2';
      toastIcon.innerHTML = '⚠';
    }
    toast.style.display = 'flex';
    toast.style.opacity = '1';
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => { toast.style.display = 'none'; }, 300);
    }, 4000);
  }

  function openModal(jobId, videoTitle) {
    activeJobId = jobId;
    modalTargetTitle.textContent = videoTitle || 'AI Clipping Job';
    modalTargetJobId.textContent = 'ID: ' + jobId;
    deleteSpinner.style.display = 'none';
    deleteIcon.style.display = 'inline-block';
    deleteBtnText.textContent = 'Delete Job Permanently';
    confirmBtn.disabled = false;
    cancelBtn.disabled = false;
    modal.style.display = 'flex';
  }

  function closeModal() {
    modal.style.display = 'none';
    activeJobId = null;
  }

  // Bind all Delete buttons
  document.querySelectorAll('.btn-delete-job').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const jobId = this.getAttribute('data-job-id');
      const title = this.getAttribute('data-video-title');
      openModal(jobId, title);
    });
  });

  // Cancel button
  cancelBtn.addEventListener('click', closeModal);

  // Close on backdrop click
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeModal();
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      closeModal();
    }
  });

  // Confirm Delete
  confirmBtn.addEventListener('click', async function() {
    if (!activeJobId) return;

    // Loading state
    deleteSpinner.style.display = 'inline-block';
    deleteIcon.style.display = 'none';
    deleteBtnText.textContent = 'Deleting...';
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;

    try {
      const response = await fetch('<?= url("api/jobs.php?action=delete") ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          job_id: activeJobId,
          csrf_token: csrfToken
        })
      });

      const data = await response.json();

      if (response.ok && data.success) {
        const deletedId = activeJobId;
        closeModal();
        showToast('Clipping job and associated files successfully deleted.', true);

        // Animate row removal from table
        const row = document.getElementById('job-row-' + deletedId);
        if (row) {
          row.style.transition = 'all 0.3s ease';
          row.style.opacity = '0';
          row.style.transform = 'translateX(20px)';
          setTimeout(() => {
            row.remove();
            // Check if any rows left
            const tbody = document.getElementById('jobs-table-body');
            if (tbody && tbody.children.length === 0) {
              const wrapper = document.getElementById('recent-jobs-wrapper');
              if (wrapper) {
                wrapper.innerHTML = '<div id="empty-jobs-notice" style="text-align:center; padding:32px; color:var(--text-muted);">No processing jobs dispatched yet.</div>';
              }
            }
          }, 300);
        }
      } else {
        throw new Error(data.error || 'Failed to delete clipping job.');
      }
    } catch (err) {
      deleteSpinner.style.display = 'none';
      deleteIcon.style.display = 'inline-block';
      deleteBtnText.textContent = 'Delete Job Permanently';
      confirmBtn.disabled = false;
      cancelBtn.disabled = false;
      showToast(err.message || 'An error occurred while deleting the job.', false);
    }
  });
});
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
