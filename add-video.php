<?php
// add-video.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Project.php';
require_once __DIR__ . '/app/models/Video.php';

$user = AuthMiddleware::requireUser();
$projects = Project::getByUser($user['id']);
$recentVideos = Video::getByUser($user['id'], 6);

$activeNav = 'add-video';
$pageTitle = 'Add New Source Video';
$topbarTitle = 'Add New Source Video';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="max-width: 960px; margin: 0 auto; padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:26px; font-weight:800; color:white;">Add New Gaming Stream or Video</h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Input your authorized stream URL or upload a high-bitrate video file to prepare for AI peak highlight extraction.
          </p>
        </div>
        <div>
          <a href="<?= url('workspace.php') ?>" class="btn btn-primary btn-sm">⚡ Watch &amp; Clip Studio</a>
        </div>
      </div>

      <!-- Source Selection Tabs -->
      <div class="glass-card" style="padding:28px; margin-bottom:24px; border:1px solid var(--border-color);">
        <div style="display:flex; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:24px; gap:8px;">
          <button type="button" class="tab-btn active" id="tab-btn-url" onclick="switchSourceTab('url')">
            🔗 Authorized Stream URL (YouTube / Twitch / Kick)
          </button>
          <button type="button" class="tab-btn" id="tab-btn-upload" onclick="switchSourceTab('upload')">
            📁 Direct File Upload (MP4 / MOV / WebM / MKV)
          </button>
        </div>

        <!-- TAB 1: Stream URL Verification -->
        <div id="source-tab-url">
          <form id="url-verify-form" onsubmit="handleVerifyUrl(event)">
            <div class="form-group" style="margin-bottom:16px;">
              <label style="font-size:13px; font-weight:700; color:white; margin-bottom:6px; display:block;">
                Paste Video or Stream URL:
              </label>
              <div style="display:flex; gap:10px;">
                <input 
                  type="url" 
                  id="source-url-input" 
                  class="form-control" 
                  placeholder="https://www.youtube.com/watch?v=irNhWMG560k" 
                  required 
                  style="flex:1; padding:12px 16px; font-size:14px;"
                >
                <button type="submit" id="verify-url-btn" class="btn btn-primary btn-md" style="min-width:140px;">
                  Verify Media
                </button>
              </div>
              <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
                Supports YouTube Live streams, VODs, completed broadcasts, Twitch & authorized creator streams.
              </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
              <label style="font-size:13px; font-weight:700; color:white; margin-bottom:6px; display:block;">
                Assign to Project (Optional):
              </label>
              <select id="project-select-url" class="form-control" style="width:100%; padding:10px 14px;">
                <option value="">No Project (General Ingestion)</option>
                <?php foreach ($projects as $prj): ?>
                  <option value="<?= htmlspecialchars((string)$prj['id']) ?>">
                    📁 <?= htmlspecialchars((string)($prj['title'] ?? $prj['name'] ?? 'Project')) ?><?= !empty($prj['category']) ? ' (' . htmlspecialchars((string)$prj['category']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>

          <!-- Verification Result Box -->
          <div id="url-result-box" style="display:none; margin-top:20px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:20px;">
            <div style="display:flex; gap:20px; flex-direction:column; sm:flex-direction:row;">
              <div style="width:240px; aspect-ratio:16/9; background:#000; border-radius:8px; overflow:hidden; flex-shrink:0;">
                <img id="verified-thumb" src="" alt="Thumbnail" style="width:100%; height:100%; object-fit:cover;">
              </div>
              <div style="flex:1; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <span class="badge badge-green" id="verified-platform">YOUTUBE</span>
                    <span class="badge badge-purple" id="verified-status">AUTHORIZED</span>
                    <span style="font-size:11px; color:var(--text-muted);" id="verified-id"></span>
                  </div>
                  <h3 id="verified-title" style="font-size:16px; font-weight:700; color:white; margin-bottom:6px; line-height:1.4;"></h3>
                  <div style="font-size:13px; color:var(--text-muted); display:flex; gap:16px;">
                    <span>Channel: <strong id="verified-channel" style="color:white;"></strong></span>
                    <span>Duration: <strong id="verified-duration" style="color:#c4b5fd;"></strong></span>
                  </div>
                </div>

                <div style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap;">
                  <button type="button" id="confirm-and-proceed-btn" class="btn btn-success btn-md" onclick="proceedToClipping()">
                    ✨ Ingest & Generate AI Clips →
                  </button>
                  <button type="button" id="open-studio-btn" class="btn btn-secondary btn-md" onclick="proceedToStudio()">
                    ⚡ Open in Watch &amp; Clip Studio →
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: Direct File Upload -->
        <div id="source-tab-upload" style="display:none;">
          <form id="file-upload-form" onsubmit="handleFileUpload(event)">
            <div 
              id="drop-zone" 
              style="border:2px dashed rgba(255,255,255,0.2); border-radius:12px; padding:48px 24px; text-align:center; background:rgba(0,0,0,0.2); cursor:pointer; margin-bottom:20px;"
              onclick="document.getElementById('file-input').click()"
            >
              <div style="font-size:44px; margin-bottom:12px;">📤</div>
              <div style="font-size:16px; font-weight:700; color:white; margin-bottom:4px;">
                Drag & Drop Video File Here, or Click to Browse
              </div>
              <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
                Supports MP4, MOV, WebM, and MKV files up to 500 MB.
              </div>
              <input 
                type="file" 
                id="file-input" 
                accept="video/mp4,video/quicktime,video/webm,video/x-matroska" 
                style="display:none;" 
                onchange="handleFileSelected(this)"
              >
              <div id="selected-file-name" style="font-size:13px; font-weight:700; color:var(--secondary);"></div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
              <label style="font-size:13px; font-weight:700; color:white; margin-bottom:6px; display:block;">
                Video Title:
              </label>
              <input type="text" id="upload-title-input" class="form-control" placeholder="e.g. GTA 5 Online Stream Gameplay" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
              <label style="font-size:13px; font-weight:700; color:white; margin-bottom:6px; display:block;">
                Assign to Project:
              </label>
              <select id="project-select-upload" class="form-control" style="width:100%; padding:10px 14px;">
                <option value="">No Project</option>
                <?php foreach ($projects as $prj): ?>
                  <option value="<?= htmlspecialchars((string)$prj['id']) ?>">
                    📁 <?= htmlspecialchars((string)($prj['title'] ?? $prj['name'] ?? 'Project')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Upload Progress Bar -->
            <div id="upload-progress-container" style="display:none; margin-bottom:20px;">
              <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:6px;">
                <span id="upload-progress-label">Uploading media...</span>
                <span id="upload-progress-pct" style="font-weight:700; color:var(--secondary);">0%</span>
              </div>
              <div class="quota-progress" style="height:8px;">
                <div id="upload-progress-bar" class="quota-progress-bar" style="width:0%;"></div>
              </div>
            </div>

            <button type="submit" id="start-upload-btn" class="btn btn-primary btn-lg" style="width:100%;" disabled>
              Upload & Ingest Video
            </button>
          </form>
        </div>
      </div>

      <!-- Recent Videos Table -->
      <?php if (!empty($recentVideos)): ?>
        <div class="glass-card" style="padding:24px;">
          <h2 style="font-size:16px; font-weight:700; color:white; margin-bottom:16px;">Previously Ingested Videos</h2>
          <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px;">
            <?php foreach ($recentVideos as $rv): ?>
              <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; overflow:hidden;">
                <div style="aspect-ratio:16/9; background:#000;">
                  <?php if (!empty($rv['thumbnail_url'])): ?>
                    <img src="<?= htmlspecialchars($rv['thumbnail_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                  <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:24px;">🎬</div>
                  <?php endif; ?>
                </div>
                <div style="padding:12px;">
                  <div style="font-size:13px; font-weight:700; color:white; margin-bottom:4px; line-height:1.3;" title="<?= htmlspecialchars($rv['title']) ?>">
                    <?= htmlspecialchars(substr($rv['title'], 0, 36)) ?>...
                  </div>
                  <div style="font-size:11px; color:var(--text-muted); margin-bottom:10px;">
                    Duration: <?= gmdate("H:i:s", (int)$rv['duration_seconds']) ?>
                  </div>
                  <a href="<?= url('create-clips.php?video_id=' . urlencode($rv['id'])) ?>" class="btn btn-primary btn-sm" style="width:100%; text-align:center;">
                    Extract Clips →
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<style>
.tab-btn {
  background: none;
  border: none;
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 700;
  color: var(--text-muted);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s ease;
}
.tab-btn.active {
  color: white;
  border-bottom-color: var(--primary);
}
</style>

<script>
let verifiedVideoData = null;

function switchSourceTab(tab) {
  document.getElementById('tab-btn-url').classList.toggle('active', tab === 'url');
  document.getElementById('tab-btn-upload').classList.toggle('active', tab === 'upload');
  document.getElementById('source-tab-url').style.display = (tab === 'url') ? 'block' : 'none';
  document.getElementById('source-tab-upload').style.display = (tab === 'upload') ? 'block' : 'none';
}

async function handleVerifyUrl(e) {
  e.preventDefault();
  const url = document.getElementById('source-url-input').value.trim();
  const btn = document.getElementById('verify-url-btn');
  btn.disabled = true;
  btn.textContent = 'Verifying...';

  try {
    const res = await fetch('<?= url("api/videos.php?action=verify") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: url })
    });
    const data = await res.json().catch(() => ({ success: false, message: 'Invalid response from server' }));
    const videoObj = data.video || data.data;
    if (res.ok && data.success && videoObj) {
      verifiedVideoData = videoObj;
      displayVerifiedData(videoObj);
      showToast(data.message || 'Media stream verified successfully!', 'success');
    } else {
      showToast(data.message || 'Verification failed. Ensure URL is an authorized video.', 'error');
    }
  } catch (err) {
    console.error('Verification error:', err);
    showToast('Failed to verify stream: ' + (err.message || 'Server error'), 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Verify Media';
  }
}

function displayVerifiedData(v) {
  if (!v) return;
  document.getElementById('verified-thumb').src = v.thumbnail_url || 'assets/img/default-video.jpg';
  document.getElementById('verified-title').textContent = v.title || 'Untitled Stream';
  document.getElementById('verified-channel').textContent = v.channel_title || v.uploader || 'Creator';
  document.getElementById('verified-id').textContent = 'ID: ' + (v.youtube_id || v.id || '');
  
  const sec = parseInt(v.duration_seconds || v.duration || 0);
  const hours = Math.floor(sec / 3600);
  const minutes = Math.floor((sec % 3600) / 60);
  const seconds = sec % 60;
  document.getElementById('verified-duration').textContent = `${hours}h ${minutes}m ${seconds}s`;

  document.getElementById('url-result-box').style.display = 'block';
}

async function proceedToClipping() {
  if (!verifiedVideoData) return;
  const btn = document.getElementById('confirm-and-proceed-btn');
  btn.disabled = true;
  btn.textContent = 'Registering in Database...';

  try {
    const projectId = document.getElementById('project-select-url').value;
    const res = await fetch('<?= url("api/videos.php?action=confirm") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        video: verifiedVideoData,
        project_id: projectId
      })
    });
    const data = await res.json().catch(() => ({}));
    const vidId = data.video_id || (verifiedVideoData && verifiedVideoData.id);
    if (res.ok && data.success && vidId) {
      window.location.href = `<?= url("create-clips.php") ?>?video_id=${encodeURIComponent(vidId)}`;
    } else if (vidId) {
      window.location.href = `<?= url("create-clips.php") ?>?video_id=${encodeURIComponent(vidId)}`;
    } else {
      showToast(data.message || 'Failed to register video record', 'error');
      btn.disabled = false;
      btn.textContent = '✨ Ingest & Generate AI Clips →';
    }
  } catch (err) {
    if (verifiedVideoData && verifiedVideoData.id) {
      window.location.href = `<?= url("create-clips.php") ?>?video_id=${encodeURIComponent(verifiedVideoData.id)}`;
    } else {
      showToast('Error registering video: ' + err.message, 'error');
      btn.disabled = false;
      btn.textContent = '✨ Ingest & Generate AI Clips →';
    }
  }
}

function proceedToStudio() {
  if (!verifiedVideoData) return;
  const vidId = verifiedVideoData.id;
  if (vidId) {
    window.location.href = `<?= url("workspace.php") ?>?video_id=${encodeURIComponent(vidId)}`;
  }
}

function handleFileSelected(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    document.getElementById('selected-file-name').textContent = `Selected: ${file.name} (${(file.size / (1024*1024)).toFixed(1)} MB)`;
    document.getElementById('upload-title-input').value = file.name.replace(/\.[^/.]+$/, "");
    document.getElementById('start-upload-btn').disabled = false;
  }
}

async function handleFileUpload(e) {
  e.preventDefault();
  const fileInput = document.getElementById('file-input');
  if (!fileInput.files || !fileInput.files[0]) return;

  const file = fileInput.files[0];
  const title = document.getElementById('upload-title-input').value.trim();
  const projectId = document.getElementById('project-select-upload').value;

  const formData = new FormData();
  formData.append('video_file', file);
  formData.append('title', title);
  formData.append('project_id', projectId);

  const progContainer = document.getElementById('upload-progress-container');
  const progBar = document.getElementById('upload-progress-bar');
  const progPct = document.getElementById('upload-progress-pct');
  const submitBtn = document.getElementById('start-upload-btn');

  progContainer.style.display = 'block';
  submitBtn.disabled = true;

  const xhr = new XMLHttpRequest();
  xhr.open('POST', '<?= url("api/videos.php?action=upload") ?>', true);

  xhr.upload.onprogress = (evt) => {
    if (evt.lengthComputable) {
      const pct = Math.round((evt.loaded / evt.total) * 100);
      progBar.style.width = pct + '%';
      progPct.textContent = pct + '%';
    }
  };

  xhr.onload = () => {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        const data = JSON.parse(xhr.responseText);
        if (data.success) {
          showToast('Video uploaded and registered successfully!', 'success');
          setTimeout(() => {
            window.location.href = `<?= url("create-clips.php") ?>?video_id=${encodeURIComponent(data.video_id)}`;
          }, 1000);
        } else {
          showToast(data.message || 'Upload processing failed.', 'error');
          submitBtn.disabled = false;
        }
      } catch (e) {
        showToast('Invalid server response.', 'error');
        submitBtn.disabled = false;
      }
    } else {
      showToast('HTTP upload error: ' + xhr.status, 'error');
      submitBtn.disabled = false;
    }
  };

  xhr.onerror = () => {
    showToast('Network error during video upload.', 'error');
    submitBtn.disabled = false;
  };

  xhr.send(formData);
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
