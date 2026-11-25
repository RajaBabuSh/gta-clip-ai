<?php
// live-monitor.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Job.php';
require_once __DIR__ . '/app/models/Clip.php';
require_once __DIR__ . '/app/models/YouTubeAccount.php';

$user = AuthMiddleware::requireUser();
$ytAccount = YouTubeAccount::getConnectedAccount($user['id']);

$jobId = $_GET['job_id'] ?? null;
$job = null;
if ($jobId) {
    $job = Job::findById($jobId);
} else {
    // Pick the most recent job
    $recentJobs = Job::getByUser($user['id'], 1);
    if (!empty($recentJobs)) {
        $job = $recentJobs[0];
        $jobId = $job['id'];
    }
}

$clips = $jobId ? Clip::getByJob($jobId) : [];
$renderedCount = count($clips);
$targetCount = (int)($job['clips_total_target'] ?? 10);
$isDone = in_array(strtolower($job['status'] ?? ''), ['completed', 'partially_completed']) || $renderedCount > 0;

$activeNav = 'monitor';
$pageTitle = 'Live Stream AI Monitor & Results';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Studio / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Pipeline Monitor & Results</span>
      </div>
      <div class="topbar-actions" style="display:flex; align-items:center; gap:12px;">
        <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary btn-sm">📊 Dashboard</a>
        <a href="<?= url('my-clips.php') ?>" class="btn btn-secondary btn-sm">🎬 My Clips</a>
        <a href="<?= url('create-clips.php') ?>" class="btn btn-primary btn-sm">+ New Ingestion</a>
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container" style="max-width: 1100px; margin: 0 auto;">
      <?php if (!$job): ?>
        <div class="glass-card" style="text-align:center; padding:60px 24px;">
          <div style="font-size:48px; margin-bottom:16px;">📡</div>
          <h2 style="font-size:22px; font-weight:800; margin-bottom:8px;">No Stream Processing Active</h2>
          <p style="font-size:14px; color:var(--text-muted); margin-bottom:24px;">Submit an authorized YouTube stream or gaming video to start automated peak extraction.</p>
          <a href="<?= url('create-clips.php') ?>" class="btn btn-primary btn-lg">Start New Clipping Job</a>
        </div>
      <?php else: 
        $rawStatus = strtoupper($job['status']);
        $badgeClass = ($rawStatus === 'COMPLETED') ? 'badge-green' : (($rawStatus === 'PARTIALLY_COMPLETED') ? 'badge-cyan' : (($rawStatus === 'FAILED') ? 'badge-red' : 'badge-purple'));
      ?>
        <div class="page-header" style="margin-bottom:20px;">
          <div>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
              <h1 class="page-title" style="font-size:24px;">AI Video Clipping Pipeline Monitor</h1>
              <span id="job-status-badge" class="badge <?= $badgeClass ?>">
                <?= $rawStatus === 'PARTIALLY_COMPLETED' ? 'PARTIALLY COMPLETED' : $rawStatus ?>
              </span>
            </div>
            <p class="page-desc">
              Source: <strong style="color:white;"><?= htmlspecialchars($job['video_title'] ?: 'Authorized Gaming Stream') ?></strong>
              (Job ID: <code style="color:var(--secondary);"><?= htmlspecialchars($job['id']) ?></code>)
            </p>
          </div>
          <div style="display:flex; gap:10px;">
            <?php if (!empty($clips)): ?>
              <a href="<?= url('api/clips.php?action=download_zip&job_id=' . urlencode($job['id'])) ?>" class="btn btn-success btn-sm" id="download-zip-btn">
                📦 Download All ZIP (<?= count($clips) ?> Clips)
              </a>
            <?php endif; ?>
            <button id="cancel-job-btn" class="btn btn-secondary btn-sm" onclick="cancelCurrentJob('<?= htmlspecialchars($job['id']) ?>')" <?= in_array($job['status'], ['completed', 'partially_completed', 'failed']) ? 'disabled style="display:none;"' : '' ?>>
              ⏹ Cancel Job
            </button>
            <button id="retry-job-btn" class="btn btn-secondary btn-sm" onclick="retryCurrentJob('<?= htmlspecialchars($job['id']) ?>')" <?= in_array($job['status'], ['failed', 'partially_completed']) ? '' : 'style="display:none;"' ?>>
              🔄 Resume / Retry Job
            </button>
            <a href="<?= url('my-clips.php?video_id=' . urlencode($job['video_id'])) ?>" id="view-clips-btn" class="btn btn-primary btn-sm" <?= $isDone ? '' : 'style="display:none;"' ?>>
              🎬 Open in Library →
            </a>
          </div>
        </div>

        <!-- Error / Alert Banner -->
        <div id="pipeline-error-card" class="glass-card" style="padding:18px 24px; margin-bottom:24px; border-left:4px solid #ef4444; background:rgba(239,68,68,0.08); display:<?= (!empty($job['error_message']) || $rawStatus === 'FAILED') ? 'flex' : 'none' ?>; align-items:flex-start; justify-content:space-between; gap:16px;">
          <div style="display:flex; align-items:flex-start; gap:14px;">
            <div style="font-size:24px; color:#ef4444; line-height:1;">⚠️</div>
            <div>
              <div style="font-size:14px; font-weight:800; color:#fca5a5; margin-bottom:4px;">Pipeline Alert</div>
              <div id="pipeline-error-text" style="font-size:13px; color:#fee2e2; line-height:1.4;">
                <?= htmlspecialchars($job['error_message'] ?: 'Processing was interrupted. You can resume from the last saved checkpoint.') ?>
              </div>
            </div>
          </div>
          <button class="btn btn-sm" onclick="retryCurrentJob('<?= htmlspecialchars($job['id']) ?>')" style="background:rgba(239,68,68,0.2); color:#fca5a5; border:1px solid rgba(239,68,68,0.4); font-weight:700; white-space:nowrap; padding:6px 14px; border-radius:6px; cursor:pointer;">
            🔄 Retry from Checkpoint
          </button>
        </div>

        <!-- Overall Progress Box -->
        <div class="glass-card" style="padding:24px; margin-bottom:24px; border-left: 4px solid var(--primary);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div>
              <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-dim); margin-bottom:4px;">Current Task</div>
              <div style="font-size:15px; font-weight:700;" id="pipeline-current-step">
                <?= htmlspecialchars($job['current_step'] ?: 'Initializing AI workers...') ?>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:24px; font-weight:900; color:#c4b5fd;" id="pipeline-progress-num">
                <?= (int)$job['progress'] ?>%
              </div>
              <div style="font-size:11px; color:var(--text-dim);" id="pipeline-stage-badge">
                Stage: <strong><?= htmlspecialchars($job['current_stage'] ?? 'Processing') ?></strong>
              </div>
            </div>
          </div>

          <div class="quota-progress" style="height:10px; margin-bottom:14px;">
            <div id="pipeline-progress-bar" class="quota-progress-bar" style="width: <?= (int)$job['progress'] ?>%;"></div>
          </div>

          <!-- Live Pipeline Status Grid -->
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); font-size:12px;">
            <div>
              <span style="color:var(--text-muted);">Worker Heartbeat:</span><br>
              <span id="pipeline-heartbeat-status" style="font-weight:700; color:#10b981;">
                🟢 Active
              </span>
            </div>
            <div>
              <span style="color:var(--text-muted);">Candidates Detected:</span><br>
              <strong id="pipeline-detected-summary" style="color:#a78bfa;"><?= (int)($job['moments_detected_count'] ?? 0) ?> candidates</strong>
            </div>
            <div>
              <span style="color:var(--text-muted);">Clips Accepted:</span><br>
              <strong id="pipeline-selected-summary" style="color:#38bdf8;"><?= (int)($job['clips_selected_count'] ?? 0) ?> / <?= $targetCount ?> target</strong>
            </div>
            <div>
              <span style="color:var(--text-muted);">Clips Rendered:</span><br>
              <strong id="pipeline-rendered-summary" style="color:#34d399;"><?= $renderedCount ?> / <?= $targetCount ?> clips</strong>
            </div>
            <div>
              <span style="color:var(--text-muted);">Checkpoint Saved:</span><br>
              <strong id="pipeline-checkpoint-status" style="color:#fbbf24;">
                Chunk <?= (int)($job['checkpoint_chunk_index'] ?? 0) ?>
              </strong>
            </div>
          </div>
        </div>

        <!-- Visual Multi-Signal Pipeline Stages -->
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:28px;">
          <div class="glass-card phase-step-card" id="step-chunking" style="padding:16px; border-left:4px solid var(--primary); transition:all 0.3s ease;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">PHASE 01</div>
            <div style="font-weight:700; font-size:14px;">Chunk Ingestion</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">16kHz audio slicing</div>
          </div>
          <div class="glass-card phase-step-card" id="step-whisper" style="padding:16px; border-left:4px solid var(--secondary); transition:all 0.3s ease;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">PHASE 02</div>
            <div style="font-weight:700; font-size:14px;">Whisper Speech AI</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Timestamped dialogue</div>
          </div>
          <div class="glass-card phase-step-card" id="step-energy" style="padding:16px; border-left:4px solid var(--accent-amber); transition:all 0.3s ease;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">PHASE 03</div>
            <div style="font-weight:700; font-size:14px;">RMS Energy Peaks</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Excitement & reactions</div>
          </div>
          <div class="glass-card phase-step-card" id="step-render" style="padding:16px; border-left:4px solid var(--accent-green); transition:all 0.3s ease;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">PHASE 04</div>
            <div style="font-weight:700; font-size:14px;">GPU 9:16 Render</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">FFmpeg NVENC output</div>
          </div>
        </div>

        <!-- GENERATED RESULTS SECTION (Always rendered when clips exist) -->
        <div id="results-gallery-container" style="<?= $isDone ? '' : 'display:none;' ?> margin-bottom:32px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <div>
              <h2 style="font-size:20px; font-weight:800; color:white; display:flex; align-items:center; gap:10px;">
                <span>🎬 Generated Viral Clips Gallery</span>
                <span class="badge badge-green" id="results-count-badge"><?= count($clips) ?> Clips Ready</span>
              </h2>
              <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                Accurately extracted peak moments rendered in 1080x1920 9:16 vertical format with word-level captions and NVENC acceleration.
              </p>
            </div>
            <div style="display:flex; gap:10px;">
              <a href="<?= url('api/clips.php?action=download_zip&job_id=' . urlencode($job['id'])) ?>" class="btn btn-success btn-sm">
                📦 Download All ZIP
              </a>
              <a href="<?= url('my-clips.php') ?>" class="btn btn-secondary btn-sm">
                🎬 Open My Clips
              </a>
              <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary btn-sm">
                📊 Back to Dashboard
              </a>
            </div>
          </div>

          <?php if (empty($clips)): ?>
            <div class="glass-card" style="text-align:center; padding:40px; color:var(--text-muted);">
              ⏳ Processing is finalizing. Generated clips will appear here momentarily...
            </div>
          <?php else: ?>
            <div class="clips-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
              <?php foreach ($clips as $clip): 
                $videoUrl = $clip['video_url'];
                $thumbUrl = $clip['thumbnail_url'];
                $score = round((float)($clip['virality_score'] ?? 0.85) * ( $clip['virality_score'] > 1 ? 1 : 100));
              ?>
                <div class="glass-card clip-card" style="display:flex; flex-direction:column; overflow:hidden; border:1px solid rgba(255,255,255,0.1); background:#0c101d;">
                  <!-- 9:16 Vertical Player Preview -->
                  <div style="position:relative; width:100%; aspect-ratio:9/16; background:#000; overflow:hidden; border-radius:12px 12px 0 0;">
                    <video 
                      poster="<?= htmlspecialchars($thumbUrl ?: '') ?>"
                      controls 
                      preload="metadata" 
                      playsinline 
                      style="width:100%; height:100%; object-fit:cover; display:block;"
                    >
                      <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/mp4">
                      <?php if (!empty($clip['stream_url']) && $clip['stream_url'] !== $videoUrl): ?>
                        <source src="<?= htmlspecialchars($clip['stream_url']) ?>" type="video/mp4">
                      <?php endif; ?>
                    </video>
                    
                    <div style="position:absolute; top:10px; left:10px; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); padding:4px 8px; border-radius:6px; font-size:11px; font-weight:800; color:#fbbf24; border:1px solid rgba(251,191,36,0.3);">
                      🔥 <?= $score ?>% Viral Score
                    </div>

                    <div style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); padding:4px 8px; border-radius:6px; font-size:11px; font-weight:700; color:white;">
                      ⏱ <?= round((float)$clip['duration'], 1) ?>s
                    </div>
                  </div>

                  <!-- Metadata & Actions -->
                  <div style="padding:16px; display:flex; flex-direction:column; flex:1; justify-content:space-between;">
                    <div>
                      <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--text-dim); margin-bottom:6px;">
                        <span>Source: <?= gmdate("i:s", (int)$clip['start_time']) ?> - <?= gmdate("i:s", (int)$clip['end_time']) ?></span>
                        <span><?= format_bytes((int)($clip['file_size'] ?? $clip['file_size_bytes'] ?? 0)) ?></span>
                      </div>

                      <h3 style="font-size:14px; font-weight:700; color:white; margin-bottom:8px; line-height:1.4;" title="<?= htmlspecialchars($clip['title']) ?>">
                        <?= htmlspecialchars($clip['title']) ?>
                      </h3>

                      <?php if (!empty($clip['hashtags'])): 
                        $tags = is_string($clip['hashtags']) ? json_decode($clip['hashtags'], true) : $clip['hashtags'];
                        if (is_array($tags)):
                      ?>
                        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:12px;">
                          <?php foreach (array_slice($tags, 0, 3) as $tg): ?>
                            <span style="font-size:10px; color:var(--secondary); background:rgba(6,182,212,0.1); padding:2px 6px; border-radius:4px;">
                              #<?= htmlspecialchars(ltrim($tg, '#')) ?>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; endif; ?>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:12px;">
                      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
                        <a href="<?= htmlspecialchars($videoUrl) ?>" download="<?= htmlspecialchars(basename($clip['file_path'])) ?>" class="btn btn-secondary btn-sm" style="text-align:center;">
                          ⬇ Download MP4
                        </a>
                        <a href="<?= url('editor.php?id=' . urlencode($clip['id'])) ?>" class="btn btn-secondary btn-sm" style="text-align:center;">
                          ✂ Edit Clip
                        </a>
                      </div>

                      <button 
                        type="button" 
                        class="btn btn-primary btn-sm" 
                        style="width:100%; display:flex; align-items:center; justify-content:center; gap:6px;"
                        onclick="openYouTubeUploadModal('<?= htmlspecialchars($clip['id']) ?>', '<?= htmlspecialchars(addslashes($clip['title'])) ?>', '<?= htmlspecialchars(addslashes($clip['description'] ?? '')) ?>')"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        Upload to YouTube
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Live Terminal Log Stream -->
        <div class="glass-card" style="padding:20px; background:#070a12; margin-top:20px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid var(--border-color); padding-bottom:8px;">
            <div style="font-size:13px; font-weight:700; color:var(--text-muted); display:flex; align-items:center; gap:8px;">
              <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981; animation:pulse 1.5s infinite;"></span>
              Worker Console Output
            </div>
            <span style="font-size:11px; color:var(--text-dim);">Auto-refreshing</span>
          </div>
          <pre id="worker-log-console" style="font-family:Consolas, monospace; font-size:12px; line-height:1.6; color:#a7f3d0; background:rgba(0,0,0,0.5); padding:16px; border-radius:8px; max-height:220px; overflow-y:auto; white-space:pre-wrap;">
[<?= date('H:i:s') ?>] Monitoring Job <?= htmlspecialchars($job['id']) ?> (Status: <?= htmlspecialchars($job['status']) ?>)
[<?= date('H:i:s') ?>] Target: <?= $targetCount ?> clips in 9:16 vertical format.
[<?= date('H:i:s') ?>] Rendered clips available: <?= $renderedCount ?>
          </pre>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Modal: One-Click YouTube Upload -->
<div id="yt-upload-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px;">
  <div class="glass-card" style="width:100%; max-width:540px; padding:28px; background:#0d111d; border:1px solid var(--border-color); border-radius:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="font-size:18px; font-weight:800; color:white; display:flex; align-items:center; gap:8px;">
        <span style="color:#ef4444;">▶</span> Upload to YouTube Shorts
      </h3>
      <button onclick="closeYouTubeUploadModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
    </div>

    <?php if (!$ytAccount): ?>
      <div style="text-align:center; padding:24px 0;">
        <div style="font-size:40px; margin-bottom:12px;">📡</div>
        <h4 style="font-size:16px; color:white; margin-bottom:6px;">No YouTube Channel Connected</h4>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
          Connect your authorized YouTube channel once via Google OAuth 2.0 to publish Shorts directly.
        </p>
        <a href="<?= url('youtube-studio.php') ?>" class="btn btn-primary btn-md">Connect YouTube Channel</a>
      </div>
    <?php else: ?>
      <div style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.03); padding:10px 14px; border-radius:8px; margin-bottom:16px;">
        <div style="width:36px; height:36px; border-radius:50%; background:#ef4444; display:flex; align-items:center; justify-content:center; font-weight:800;">
          <?= strtoupper(substr($ytAccount['channel_title'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:13px; font-weight:700; color:white;"><?= htmlspecialchars($ytAccount['channel_title']) ?></div>
          <div style="font-size:11px; color:#10b981;">✓ Connected via Google OAuth 2.0</div>
        </div>
      </div>

      <form id="yt-upload-form" onsubmit="submitYouTubeUpload(event)">
        <input type="hidden" id="modal-clip-id" name="clip_id">
        <div class="form-group" style="margin-bottom:14px;">
          <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">Shorts Title</label>
          <input type="text" id="modal-clip-title" name="title" class="form-control" required style="width:100%;">
        </div>

        <div class="form-group" style="margin-bottom:14px;">
          <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">Description & Hashtags</label>
          <textarea id="modal-clip-desc" name="description" rows="3" class="form-control" style="width:100%;"></textarea>
        </div>

        <div class="form-group" style="margin-bottom:20px;">
          <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">Publishing Privacy</label>
          <select id="modal-clip-privacy" name="privacy_status" class="form-control" style="width:100%;">
            <option value="private" selected>Private (Safest - only you can view)</option>
            <option value="unlisted">Unlisted (Anyone with link can view)</option>
            <option value="public">Public (Published immediately to subscribers)</option>
          </select>
        </div>

        <div id="modal-upload-status" style="display:none; margin-bottom:16px; font-size:13px; color:var(--secondary); text-align:center;">
          Uploading to YouTube Data API... Please wait.
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeYouTubeUploadModal()" class="btn btn-secondary btn-sm">Cancel</button>
          <button type="submit" id="modal-upload-btn" class="btn btn-danger btn-sm">Publish to YouTube Shorts</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
function openYouTubeUploadModal(clipId, title, desc) {
  document.getElementById('modal-clip-id').value = clipId;
  document.getElementById('modal-clip-title').value = title;
  document.getElementById('modal-clip-desc').value = desc || (title + "\n\nWatch until the end! Best moments from the latest GTA gameplay. Drop a follow for more viral gaming clips daily! 🎮🔥\n\n#GTAV #GTA5 #GamingShorts #Shorts #Reels");
  document.getElementById('yt-upload-modal').style.display = 'flex';
}

function closeYouTubeUploadModal() {
  document.getElementById('yt-upload-modal').style.display = 'none';
}

async function submitYouTubeUpload(e) {
  e.preventDefault();
  const form = document.getElementById('yt-upload-form');
  const clipId = document.getElementById('modal-clip-id').value;
  const title = document.getElementById('modal-clip-title').value;
  const description = document.getElementById('modal-clip-desc').value;
  const privacy = document.getElementById('modal-clip-privacy').value;

  const statusBox = document.getElementById('modal-upload-status');
  const submitBtn = document.getElementById('modal-upload-btn');

  statusBox.style.display = 'block';
  statusBox.textContent = '🚀 Contacting YouTube Data API v3...';
  submitBtn.disabled = true;

  try {
    const res = await fetch('<?= url("api/clips.php?action=upload_youtube") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        clip_id: clipId,
        title: title,
        description: description,
        privacy_status: privacy
      })
    });
    const data = await res.json();
    if (res.ok && data.success) {
      statusBox.innerHTML = `✅ Published! <a href="${data.youtube_url}" target="_blank" style="color:#38bdf8; text-decoration:underline;">Watch on YouTube (${data.youtube_video_id})</a>`;
      showToast('Successfully published to YouTube Shorts!', 'success');
      setTimeout(() => {
        closeYouTubeUploadModal();
        location.reload();
      }, 2500);
    } else {
      statusBox.textContent = '❌ Upload failed: ' + (data.message || 'Unknown error');
      submitBtn.disabled = false;
    }
  } catch (err) {
    statusBox.textContent = '❌ Connection error: ' + err.message;
    submitBtn.disabled = false;
  }
}

<?php if ($job && !in_array($job['status'], ['completed', 'partially_completed', 'failed'])): ?>
const activeJobId = <?= json_encode($job['id']) ?>;
const consoleBox = document.getElementById('worker-log-console');

function appendLog(msg) {
  const time = new Date().toLocaleTimeString();
  consoleBox.textContent += `\n[${time}] ${msg}`;
  consoleBox.scrollTop = consoleBox.scrollHeight;
}

pollJobProgress(
  activeJobId,
  (progress, step, data) => {
    document.getElementById('pipeline-progress-bar').style.width = `${progress}%`;
    document.getElementById('pipeline-progress-num').textContent = `${progress}%`;
    document.getElementById('pipeline-current-step').textContent = step;

    // Stage Badge & Text
    const stageName = data.current_stage || 'Processing';
    const stageElem = document.getElementById('pipeline-current-stage');
    if (stageElem) stageElem.textContent = stageName;
    const stageBadge = document.getElementById('pipeline-stage-badge');
    if (stageBadge) stageBadge.innerHTML = `Stage: <strong>${stageName}</strong>`;

    // Heartbeat Status
    const hbStatus = document.getElementById('pipeline-heartbeat-status');
    if (hbStatus) {
      if (data.is_stalled) {
        hbStatus.innerHTML = '<span style="color:#ef4444;">🔴 Stalled (>120s)</span>';
      } else if (data.heartbeat_seconds_ago !== null && data.heartbeat_seconds_ago !== undefined) {
        hbStatus.innerHTML = `<span style="color:#10b981;">🟢 Active (${data.heartbeat_seconds_ago}s ago)</span>`;
      } else {
        hbStatus.innerHTML = '<span style="color:#10b981;">🟢 Active</span>';
      }
    }

    // Checkpoint Status
    const cpElem = document.getElementById('pipeline-checkpoint-status');
    if (cpElem && data.checkpoint_chunk_index !== undefined) {
      cpElem.textContent = `Chunk ${data.checkpoint_chunk_index}`;
    }

    // Phase Card Visual Highlighting
    document.querySelectorAll('.phase-step-card').forEach(c => {
      c.style.background = '';
      c.style.boxShadow = '';
    });
    const stageLower = stageName.toLowerCase();
    if (stageLower.includes('chunk') || stageLower.includes('slicing') || stageLower.includes('audio')) {
      const c = document.getElementById('step-chunking');
      if (c) { c.style.background = 'rgba(99,102,241,0.18)'; c.style.boxShadow = '0 0 15px rgba(99,102,241,0.3)'; }
    } else if (stageLower.includes('whisper') || stageLower.includes('transcription')) {
      const c = document.getElementById('step-whisper');
      if (c) { c.style.background = 'rgba(6,182,212,0.18)'; c.style.boxShadow = '0 0 15px rgba(6,182,212,0.3)'; }
    } else if (stageLower.includes('peak') || stageLower.includes('energy') || stageLower.includes('moment')) {
      const c = document.getElementById('step-energy');
      if (c) { c.style.background = 'rgba(245,158,11,0.18)'; c.style.boxShadow = '0 0 15px rgba(245,158,11,0.3)'; }
    } else if (stageLower.includes('render') || stageLower.includes('nvenc')) {
      const c = document.getElementById('step-render');
      if (c) { c.style.background = 'rgba(16,185,129,0.18)'; c.style.boxShadow = '0 0 15px rgba(16,185,129,0.3)'; }
    }

    appendLog(`[${stageName}] ${progress}% - ${step}`);
    if (data && data.clips_rendered_count !== undefined) {
      document.getElementById('pipeline-rendered-summary').textContent = `${data.clips_rendered_count} / ${data.clips_total_target || 10} clips`;
    }
    if (data && data.moments_detected_count !== undefined) {
      const el = document.getElementById('pipeline-detected-summary');
      if (el) el.textContent = `${data.moments_detected_count} candidates`;
    }
    if (data && data.clips_selected_count !== undefined) {
      const el = document.getElementById('pipeline-selected-summary');
      if (el) el.textContent = `${data.clips_selected_count} / ${data.clips_total_target || 10} target`;
    }
    if (data && data.checkpoint_chunk_index !== undefined) {
      const el = document.getElementById('pipeline-checkpoint-status');
      if (el) el.textContent = `Chunk ${data.checkpoint_chunk_index}`;
    }
  },
  (data) => {
    document.getElementById('pipeline-progress-bar').style.width = `${data.progress || 100}%`;
    document.getElementById('pipeline-progress-num').textContent = `${data.progress || 100}%`;
    document.getElementById('pipeline-current-step').textContent = data.current_step || 'Analysis complete!';
    
    const badge = document.getElementById('job-status-badge');
    const isPartial = data.status === 'partially_completed';
    badge.className = isPartial ? 'badge badge-cyan' : 'badge badge-green';
    badge.textContent = isPartial ? 'PARTIALLY COMPLETED' : 'COMPLETED';

    document.getElementById('cancel-job-btn').style.display = 'none';
    const retryBtn = document.getElementById('retry-job-btn');
    if (retryBtn) retryBtn.style.display = 'none';
    document.getElementById('view-clips-btn').style.display = 'inline-flex';
    appendLog(`✓ Processing finished! Status: ${data.status.toUpperCase()} (${data.clips_rendered_count || 0} clips generated)`);
    showToast('AI Clipping completed! Updating results gallery...', 'success');
    setTimeout(() => location.reload(), 1200);
  },
  (errMsg, data) => {
    const badge = document.getElementById('job-status-badge');
    badge.className = 'badge badge-red';
    badge.textContent = 'FAILED';

    const errorCard = document.getElementById('pipeline-error-card');
    const errorText = document.getElementById('pipeline-error-text');
    if (errorCard && errorText) {
      errorText.textContent = errMsg || 'Processing failed. You can resume from the last saved checkpoint.';
      errorCard.style.display = 'flex';
    }

    const retryBtn = document.getElementById('retry-job-btn');
    if (retryBtn) retryBtn.style.display = 'inline-flex';

    appendLog(`✕ Job error: ${errMsg}`);
    showToast(errMsg, 'error');
  }
);
<?php endif; ?>

async function cancelCurrentJob(id) {
  if (!confirm('Are you sure you want to stop this processing job?')) return;
  try {
    const res = await fetch(`<?= url("api/jobs.php") ?>?action=cancel&id=${encodeURIComponent(id)}`, { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Job cancelled', 'info');
      setTimeout(() => location.reload(), 1000);
    }
  } catch (e) {
    showToast('Failed to cancel job', 'error');
  }
}

async function retryCurrentJob(id) {
  try {
    const res = await fetch(`<?= url("api/clips.php") ?>?action=retry&job_id=${encodeURIComponent(id)}`, { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Job resumed from last checkpoint!', 'success');
      setTimeout(() => location.reload(), 1000);
    }
  } catch (e) {
    showToast('Failed to retry job', 'error');
  }
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
