<?php
// create-clips.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Project.php';

$user = AuthMiddleware::requireUser();
$projects = Project::getByUser($user['id']);

$prefillVideoId = $_GET['video_id'] ?? null;
$prefillVideo = null;
if ($prefillVideoId) {
    require_once __DIR__ . '/app/models/Video.php';
    $prefillVideo = Video::findById($prefillVideoId);
}

$activeNav = 'create';
$pageTitle = 'Create AI Video Clips';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Studio / </span>
        <span style="font-size:14px; font-weight:700; color:white;">AI Peak Ingestion</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('my-clips.php') ?>" class="btn btn-secondary btn-sm">Library</a>
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container" style="max-width: 900px; margin: 0 auto;">
      <div class="page-header" style="text-align: center; display: block; margin-bottom: 32px;">
        <div class="badge badge-purple" style="margin-bottom: 12px;">PEAKCUT SMART ANALYSIS ENGINE</div>
        <h1 class="page-title" style="font-size: 32px;">Extract Viral Shorts from Your Streams</h1>
        <p class="page-desc" style="font-size: 15px; max-width: 600px; margin: 8px auto 0 auto;">
          Our AI scans audio RMS energy, transcribes dialogue, detects funny reactions, and cuts up to 40 viral 9:16 vertical clips.
        </p>
        <div style="margin-top:14px;">
          <a href="<?= url('workspace.php') ?>" class="btn btn-secondary btn-sm">⚡ Or Clip While You Watch (Live Studio) →</a>
        </div>
      </div>

      <!-- Main Ingestion Card -->
      <div class="glass-card" style="padding: 32px;">
        <form id="create-clips-form">
          <?= csrf_field() ?>

          <!-- Source Tabs -->
          <div style="display:flex; gap:12px; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:16px;">
            <button type="button" id="tab-url-btn" class="btn btn-primary btn-sm" onclick="switchSourceTab('url')">
              🔗 Stream URL (YouTube / Twitch / Kick)
            </button>
            <button type="button" id="tab-file-btn" class="btn btn-secondary btn-sm" onclick="switchSourceTab('file')">
              📁 Direct MP4 Upload
            </button>
          </div>

          <!-- URL Input Section -->
          <div id="source-url-section">
            <div class="form-group">
              <label class="form-label">Stream or VOD URL</label>
              <div style="display:flex; gap:12px;">
                <input type="url" id="source-url" name="url" class="form-control" placeholder="https://www.youtube.com/watch?v=irNhWMG560k" value="<?= htmlspecialchars($prefillVideo['youtube_url'] ?? 'https://www.youtube.com/watch?v=irNhWMG560k') ?>">
                <button type="button" id="verify-btn" class="btn btn-secondary" onclick="verifyStreamUrl()" style="white-space:nowrap;">
                  Verify Media
                </button>
              </div>
              <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
                Supports full YouTube live streams, Twitch VODs, and Kick recordings.
              </div>
            </div>
          </div>

          <!-- File Upload Section -->
          <div id="source-file-section" style="display:none;">
            <div class="form-group">
              <label class="form-label">Upload Local MP4 Video File</label>
              <div id="dropzone" style="border:2px dashed var(--border-color); border-radius:var(--radius-md); padding:36px; text-align:center; background:rgba(255,255,255,0.02); cursor:pointer;">
                <div style="font-size:36px; margin-bottom:8px;">📁</div>
                <div style="font-weight:700; margin-bottom:4px;">Drag & Drop video file here, or click to browse</div>
                <div style="font-size:12px; color:var(--text-muted);">Max file size: 500 MB (MP4, MOV, MKV)</div>
                <input type="file" id="source-file" name="file" accept="video/mp4,video/quicktime,video/x-matroska" style="display:none;" onchange="handleFileSelected(this)">
              </div>
              <div id="file-selected-name" style="margin-top:10px; font-size:13px; color:var(--secondary); font-weight:600;"></div>
            </div>
          </div>

          <!-- Video Verification Preview Card -->
          <div id="media-preview-card" style="<?= $prefillVideo ? 'display:block;' : 'display:none;' ?> background:rgba(0,0,0,0.4); border:1px solid rgba(139,92,246,0.3); border-radius:12px; padding:16px; margin-bottom:24px;">
            <div style="display:flex; gap:16px; align-items:center;">
              <img id="preview-thumb" src="<?= htmlspecialchars($prefillVideo['thumbnail_url'] ?? '') ?>" alt="Thumbnail" style="width:120px; height:68px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color);">
              <div style="flex-grow:1;">
                <div id="preview-title" style="font-weight:700; font-size:14px; margin-bottom:4px;"><?= htmlspecialchars($prefillVideo['title'] ?? 'Video Title') ?></div>
                <div style="font-size:12px; color:var(--text-muted); display:flex; gap:16px;">
                  <span id="preview-channel"><?= htmlspecialchars($prefillVideo['channel_title'] ?? 'Channel') ?></span>
                  <span id="preview-duration"><?= $prefillVideo ? gmdate("H:i:s", (int)($prefillVideo['duration_seconds'] ?? 0)) : '00:00' ?></span>
                  <span class="badge badge-green" style="font-size:10px;">Authorized Access Verified</span>
                </div>
              </div>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
            <!-- Target Clips Quantity -->
            <div class="form-group">
              <label class="form-label">Target Clips Quantity</label>
              <select name="target_clips" id="target-clips" class="form-control">
                <option value="10" selected>10 Viral Clips (Recommended)</option>
                <option value="20">20 Viral Clips</option>
                <option value="30">30 Viral Clips</option>
                <option value="40">40 Viral Clips (Extensive Highlight Reel)</option>
              </select>
            </div>

            <!-- Aspect Ratio -->
            <div class="form-group">
              <label class="form-label">Export Aspect Ratio</label>
              <select name="aspect_ratio" class="form-control">
                <option value="9:16" selected>9:16 Vertical (TikTok / Shorts / Reels)</option>
                <option value="1:1">1:1 Square (Instagram Feed)</option>
                <option value="16:9">16:9 Landscape (YouTube Regular)</option>
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
            <!-- AI Highlight Mode -->
            <div class="form-group">
              <label class="form-label">Peak Detection Focus</label>
              <select name="style" class="form-control">
                <option value="auto" selected>Multi-Signal Full Stream (Balanced)</option>
                <option value="funny">Streamer Screams & Funny Reactions</option>
                <option value="gameplay">High-Octane Action & Police Chases</option>
                <option value="dialogue">Story Missions & Conversations</option>
              </select>
            </div>

            <!-- Workspace Project -->
            <div class="form-group">
              <label class="form-label">Assign to Project</label>
              <select name="project_id" class="form-control">
                <?php foreach ($projects as $proj): ?>
                  <option value="<?= htmlspecialchars($proj['id']) ?>"><?= htmlspecialchars($proj['title']) ?></option>
                <?php endforeach; ?>
                <option value="new">+ Create New Project...</option>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:28px;">
            <label class="form-label">Dynamic Subtitle Style</label>
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
              <label style="border:1px solid var(--border-color); border-radius:8px; padding:12px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.02);">
                <input type="radio" name="subtitle_style" value="bouncy" checked style="margin-bottom:6px;">
                <div style="font-weight:700; font-size:13px; color:#fde047;">BOUNCY VIRAL</div>
                <div style="font-size:11px; color:var(--text-muted);">Yellow & Cyan Highlight</div>
              </label>
              <label style="border:1px solid var(--border-color); border-radius:8px; padding:12px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.02);">
                <input type="radio" name="subtitle_style" value="minimal" style="margin-bottom:6px;">
                <div style="font-weight:700; font-size:13px; color:#ffffff;">MINIMAL WHITE</div>
                <div style="font-size:11px; color:var(--text-muted);">Clean Word-by-Word</div>
              </label>
              <label style="border:1px solid var(--border-color); border-radius:8px; padding:12px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.02);">
                <input type="radio" name="subtitle_style" value="none" style="margin-bottom:6px;">
                <div style="font-weight:700; font-size:13px; color:var(--text-muted);">NO SUBTITLES</div>
                <div style="font-size:11px; color:var(--text-muted);">Clean Gameplay Only</div>
              </label>
            </div>
          </div>

          <input type="hidden" id="verified-video-id" name="video_id" value="<?= htmlspecialchars($prefillVideo['id'] ?? '') ?>">

          <button type="submit" id="submit-job-btn" class="btn btn-primary btn-lg" style="width:100%; padding:16px;">
            ⚡ Start Peak Analysis & Generate Clips
          </button>
        </form>
      </div>
    </div>
  </main>
</div>

<script>
let currentMode = 'url';

function switchSourceTab(mode) {
  currentMode = mode;
  document.getElementById('source-url-section').style.display = mode === 'url' ? 'block' : 'none';
  document.getElementById('source-file-section').style.display = mode === 'file' ? 'block' : 'none';
  
  document.getElementById('tab-url-btn').className = mode === 'url' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm';
  document.getElementById('tab-file-btn').className = mode === 'file' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm';
}

// Drag and drop trigger
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('source-file');
dropzone.addEventListener('click', () => fileInput.click());

dropzone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropzone.style.borderColor = 'var(--primary)';
});
dropzone.addEventListener('dragleave', () => {
  dropzone.style.borderColor = 'var(--border-color)';
});
dropzone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropzone.style.borderColor = 'var(--border-color)';
  if (e.dataTransfer.files.length > 0) {
    fileInput.files = e.dataTransfer.files;
    handleFileSelected(fileInput);
  }
});

function handleFileSelected(input) {
  if (input.files && input.files[0]) {
    document.getElementById('file-selected-name').textContent = `Selected: ${input.files[0].name} (${(input.files[0].size / (1024*1024)).toFixed(1)} MB)`;
    showToast(`File selected: ${input.files[0].name}`, 'info');
  }
}

async function verifyStreamUrl() {
  const url = document.getElementById('source-url').value.trim();
  if (!url) {
    showToast('Please enter a stream URL', 'warning');
    return;
  }
  
  const btn = document.getElementById('verify-btn');
  btn.textContent = 'Verifying...';
  btn.disabled = true;

  try {
    const res = await fetch('<?= url("api/videos.php") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'verify', url: url })
    });
    const data = await res.json().catch(() => ({ success: false, message: 'Invalid response from server' }));
    const vid = data.video || data.data;
    if (res.ok && data.success && vid) {
      document.getElementById('media-preview-card').style.display = 'block';
      document.getElementById('preview-thumb').src = vid.thumbnail_url || 'assets/img/default-video.jpg';
      document.getElementById('preview-title').textContent = vid.title || 'Untitled Stream';
      document.getElementById('preview-channel').textContent = vid.channel_title || vid.uploader || 'Creator';
      const dur = parseInt(vid.duration_seconds || vid.duration || 0);
      document.getElementById('preview-duration').textContent = `${Math.floor(dur / 60)}m ${Math.floor(dur % 60)}s`;
      document.getElementById('verified-video-id').value = vid.id;
      showToast('Media stream verified successfully!', 'success');
    } else {
      showToast(data.message || 'Verification failed. Only authorized public streams are eligible.', 'error');
    }
  } catch (err) {
    showToast('Network error during verification', 'error');
  } finally {
    btn.textContent = 'Verify Media';
    btn.disabled = false;
  }
}

// Form Submission
document.getElementById('create-clips-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const submitBtn = document.getElementById('submit-job-btn');
  submitBtn.textContent = 'Initializing AI Workers...';
  submitBtn.disabled = true;

  try {
    let videoId = document.getElementById('verified-video-id').value;

    // If file mode, upload first
    if (currentMode === 'file') {
      const file = fileInput.files[0];
      if (!file) {
        showToast('Please choose a video file to upload', 'warning');
        submitBtn.textContent = '⚡ Start Peak Analysis & Generate Clips';
        submitBtn.disabled = false;
        return;
      }

      const formData = new FormData();
      formData.append('file', file);
      submitBtn.textContent = 'Uploading Video File...';

      const uploadRes = await fetch('<?= url("api/videos.php?action=upload") ?>', {
        method: 'POST',
        body: formData
      });
      const uploadData = await uploadRes.json().catch(() => ({ success: false, message: 'Invalid upload response' }));
      if (!uploadRes.ok || !uploadData.success) {
        throw new Error(uploadData.message || 'File upload failed');
      }
      videoId = uploadData.video_id;
    }

    if (!videoId) {
      // Auto-verify URL if not verified yet
      const url = document.getElementById('source-url').value.trim();
      const verifyRes = await fetch('<?= url("api/videos.php") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'verify', url: url })
      });
      const vData = await verifyRes.json().catch(() => ({ success: false, message: 'Invalid verify response' }));
      if (!verifyRes.ok || !vData.success) {
        throw new Error(vData.message || 'Failed to verify source stream');
      }
      videoId = vData.video.id;
    }

    const targetClips = parseInt(document.getElementById('target-clips').value) || 10;
    const style = document.querySelector('select[name="style"]').value;
    const aspectRatio = document.querySelector('select[name="aspect_ratio"]').value;

    submitBtn.textContent = 'Dispatching to Cloud AI GPU...';

    const dispatchRes = await fetch('<?= url("api/clips.php?action=generate") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        video_id: videoId,
        target_clips: targetClips,
        style: style,
        aspect_ratio: aspectRatio
      })
    });

    const dispatchData = await dispatchRes.json().catch(() => ({ success: false, message: 'Invalid dispatch response' }));
    if (!dispatchRes.ok || !dispatchData.success) {
      throw new Error(dispatchData.message || 'Failed to dispatch clipping job');
    }

    showToast('Job queued! Forwarding to Live AI Monitor...', 'success');
    window.location.href = `<?= url("live-monitor.php") ?>?job_id=${encodeURIComponent(dispatchData.job_id)}`;

  } catch (err) {
    showToast(err.message, 'error');
    submitBtn.textContent = '⚡ Start Peak Analysis & Generate Clips';
    submitBtn.disabled = false;
  }
});
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
