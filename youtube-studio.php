<?php
// youtube-studio.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/YouTubeAccount.php';
require_once __DIR__ . '/app/models/YouTubeUpload.php';
require_once __DIR__ . '/app/models/Clip.php';
require_once __DIR__ . '/app/services/YouTubeOAuthService.php';

$user = AuthMiddleware::requireUser();
$ytAccount = YouTubeAccount::getConnectedAccount($user['id']);
$recentUploads = YouTubeUpload::getByUser($user['id'], 15);
$userClips = Clip::getByUser($user['id'], 50);

$isOAuthConfigured = YouTubeOAuthService::isConfigured();
$oauthRedirectUri = YouTubeOAuthService::getRedirectUri();

$activeNav = 'youtube';
$pageTitle = 'YouTube Studio & Channel Automation';
$topbarTitle = 'YouTube Studio & Publishing';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="max-width: 1100px; margin: 0 auto; padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:26px; font-weight:800; color:white; display:flex; align-items:center; gap:10px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#ef4444"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            YouTube Studio & Automation
          </h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Connect your authorized YouTube channel via official Google OAuth 2.0 to publish AI Shorts directly to your feed.
          </p>
        </div>
        <div>
          <a href="<?= url('scheduler.php') ?>" class="btn btn-secondary btn-sm">⏰ Automated Scheduler →</a>
        </div>
      </div>

      <!-- SECTION 0: OAuth Setup Banner (if not configured yet) -->
      <?php if (!$isOAuthConfigured && !$ytAccount): ?>
        <div class="glass-card" style="padding:20px; margin-bottom:24px; border:1px solid rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.08);">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
            <div>
              <div style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700; color:#fbbf24;">
                <span>⚠️</span> Google Cloud OAuth 2.0 Credentials Required
              </div>
              <p style="font-size:13px; color:#fde68a; margin-top:4px; line-height:1.4;">
                To connect your YouTube channel, enter your Google Cloud OAuth Client ID and Secret in Settings.
              </p>
              <div style="margin-top:10px; font-size:12px; color:#cbd5e1;">
                Authorized Redirect URI: <code style="background:rgba(0,0,0,0.4); padding:3px 8px; border-radius:4px; color:#c4b5fd;" id="yt-redirect-code"><?= htmlspecialchars($oauthRedirectUri) ?></code>
                <button type="button" class="btn btn-xs btn-secondary" style="margin-left:6px;" onclick="navigator.clipboard.writeText(document.getElementById('yt-redirect-code').innerText); showToast('Redirect URI copied to clipboard!', 'success');">Copy URI</button>
              </div>
            </div>
            <div>
              <a href="<?= url('admin/youtube.php') ?>" class="btn btn-warning btn-sm" style="font-weight:700;">
                ⚙️ Configure Google Credentials
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- SECTION 1: Connected Channel Status -->
      <div class="glass-card" style="padding:28px; margin-bottom:28px; border:1px solid <?= $ytAccount ? 'rgba(16,185,129,0.3)' : 'var(--border-color)' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
          <div style="display:flex; align-items:center; gap:16px;">
            <?php if ($ytAccount): ?>
              <?php if (!empty($ytAccount['channel_thumbnail_url'])): ?>
                <img src="<?= htmlspecialchars($ytAccount['channel_thumbnail_url']) ?>" alt="Channel Avatar" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.2); box-shadow:0 0 15px rgba(239,68,68,0.3);">
              <?php else: ?>
                <div style="width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg, #ef4444, #b91c1c); display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; color:white; border:2px solid rgba(255,255,255,0.2);">
                  <?= strtoupper(substr($ytAccount['channel_title'] ?? 'Y', 0, 1)) ?>
                </div>
              <?php endif; ?>
              <div>
                <div style="display:flex; align-items:center; gap:8px;">
                  <h2 style="font-size:20px; font-weight:800; color:white; margin:0;"><?= htmlspecialchars($ytAccount['channel_title']) ?></h2>
                  <span class="badge badge-green">Connected (YouTube Data API v3)</span>
                </div>
                <div style="font-size:12px; color:var(--text-muted); margin-top:5px;">
                  Channel ID: <a href="https://www.youtube.com/channel/<?= htmlspecialchars($ytAccount['channel_id']) ?>" target="_blank" style="color:#38bdf8; text-decoration:underline;"><code><?= htmlspecialchars($ytAccount['channel_id']) ?></code> ↗</a> • 
                  Subscribers: <strong><?= number_format((int)$ytAccount['subscriber_count']) ?></strong> • 
                  Scope: <code>youtube.upload</code>
                </div>
              </div>
            <?php else: ?>
              <div style="width:64px; height:64px; border-radius:50%; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; font-size:24px; color:var(--text-dim); border:2px dashed rgba(255,255,255,0.2);">
                📡
              </div>
              <div>
                <h2 style="font-size:18px; font-weight:800; color:white; margin:0;">No YouTube Channel Linked</h2>
                <div style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                  Authorize your YouTube account securely using official Google OAuth 2.0 to enable one-click publishing.
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($ytAccount): ?>
              <a href="<?= url('api/youtube.php?action=authorize') ?>" class="btn btn-secondary btn-sm" title="Refresh Google OAuth connection">
                ⟳ Reconnect
              </a>
              <button onclick="handleDisconnectChannel()" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:rgba(239,68,68,0.4);">
                Disconnect Channel
              </button>
            <?php else: ?>
              <a href="<?= url('api/youtube.php?action=authorize') ?>" class="btn btn-danger btn-md" style="display:flex; align-items:center; gap:8px; text-decoration:none; font-weight:700; box-shadow:0 0 20px rgba(239,68,68,0.35);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                Connect YouTube Channel (Google OAuth)
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SECTION 2: One-Click YouTube Shorts Publisher -->
      <div class="glass-card" style="padding:28px; margin-bottom:28px;">
        <h2 style="font-size:18px; font-weight:800; color:white; margin-bottom:6px;">
          🚀 One-Click YouTube Shorts Publisher
        </h2>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
          Select any generated vertical clip from your library, review the metadata, and publish securely using YouTube Resumable Upload.
        </p>

        <?php if (empty($userClips)): ?>
          <div style="text-align:center; padding:32px; background:rgba(0,0,0,0.2); border-radius:12px; color:var(--text-muted);">
            No clips generated yet. <a href="<?= url('create-clips.php') ?>" style="color:var(--secondary); font-weight:700;">Create AI Clips</a> first to publish them here.
          </div>
        <?php else: ?>
          <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:24px;">
            <!-- Left: Select Clip & Preview -->
            <div>
              <div class="form-group" style="margin-bottom:16px;">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                  Select Generated Clip:
                </label>
                <select id="publisher-clip-select" class="form-control" style="width:100%;" onchange="loadClipForPublishing(this.value)">
                  <?php foreach ($userClips as $idx => $uc): ?>
                    <option value="<?= htmlspecialchars($uc['id']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                      <?= htmlspecialchars($uc['title']) ?> (<?= round((float)$uc['duration'], 1) ?>s)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Video Player Preview Container -->
              <div style="width:100%; max-width:280px; margin:0 auto; aspect-ratio:9/16; background:#000; border-radius:12px; overflow:hidden; border:1px solid rgba(255,255,255,0.1);">
                <video 
                  id="publisher-video-preview" 
                  src="<?= htmlspecialchars($userClips[0]['video_url'] ?? '') ?>" 
                  controls 
                  playsinline 
                  style="width:100%; height:100%; object-fit:cover;"
                ></video>
              </div>
            </div>

            <!-- Right: AI Metadata & Publishing Options -->
            <div>
              <form id="publisher-form" onsubmit="handleDirectPublish(event)">
                <div class="form-group" style="margin-bottom:14px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-muted);">Shorts Title (Under 100 chars)</label>
                    <button type="button" onclick="regenerateSeo()" class="btn btn-secondary btn-sm" style="font-size:11px; padding:2px 8px;">
                      ✨ Regenerate with Gemini
                    </button>
                  </div>
                  <input type="text" id="pub-title-input" class="form-control" style="width:100%;" value="<?= htmlspecialchars($userClips[0]['title'] ?? '') ?> #Shorts #GTA5" required maxlength="100">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                  <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">Description</label>
                  <textarea id="pub-desc-input" rows="4" class="form-control" style="width:100%;"><?= htmlspecialchars($userClips[0]['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                  <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">Tags & Hashtags</label>
                  <input type="text" id="pub-tags-input" class="form-control" style="width:100%;" value="#GTAV, #GTA5, #GamingShorts, #Shorts, #Reels">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                  <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px; display:block;">
                    Publishing Privacy (Default: Private)
                  </label>
                  <select id="pub-privacy-select" class="form-control" style="width:100%;">
                    <option value="private" selected>Private (Default & Recommended - only you can view)</option>
                    <option value="unlisted">Unlisted (Anyone with link can view)</option>
                    <option value="public">Public (Immediate Feed & Shorts Distribution)</option>
                  </select>
                </div>

                <!-- Live Upload Progress Bar -->
                <div id="upload-progress-container" style="display:none; margin-bottom:18px; background:rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:14px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; margin-bottom:6px;">
                    <span id="upload-stage-label" style="color:#c4b5fd; font-weight:700;">Initiating Resumable Upload...</span>
                    <span id="upload-percent-label" style="font-weight:800; color:white;">0%</span>
                  </div>
                  <div style="height:8px; background:rgba(255,255,255,0.1); border-radius:4px; overflow:hidden;">
                    <div id="upload-progress-fill" style="width:0%; height:100%; background:linear-gradient(90deg, #ef4444, #8b5cf6); transition:width 0.3s ease;"></div>
                  </div>
                </div>

                <div id="pub-status-msg" style="display:none; padding:14px; border-radius:10px; margin-bottom:16px; font-size:13px;"></div>

                <div style="display:flex; gap:12px;">
                  <button type="submit" id="pub-submit-btn" class="btn btn-danger btn-lg" style="flex:1; font-weight:700;" <?= $ytAccount ? '' : 'disabled' ?>>
                    🚀 Publish to YouTube Shorts
                  </button>
                  <a id="pub-download-btn" href="<?= htmlspecialchars($userClips[0]['video_url'] ?? '') ?>" download class="btn btn-secondary btn-lg">
                    ⬇ Download MP4
                  </a>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- SECTION 3: Recent Upload History -->
      <div class="glass-card" style="padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <h2 style="font-size:18px; font-weight:800; color:white; margin:0;">Recent YouTube Uploads</h2>
          <a href="<?= url('upload-history.php') ?>" class="btn btn-secondary btn-sm">Full History →</a>
        </div>

        <?php if (empty($recentUploads)): ?>
          <div id="no-uploads-placeholder" style="text-align:center; padding:32px; color:var(--text-muted);">
            No videos uploaded yet. Use the publisher above or enable the automated scheduler.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table" id="uploads-table">
              <thead>
                <tr>
                  <th>Clip Title</th>
                  <th>Privacy</th>
                  <th>Status</th>
                  <th>YouTube Video ID / Link</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody id="uploads-tbody">
                <?php foreach ($recentUploads as $up): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($up['title']) ?></strong>
                    </td>
                    <td>
                      <span class="badge badge-purple"><?= strtoupper($up['privacy_status']) ?></span>
                    </td>
                    <td>
                      <span class="badge <?= $up['upload_status'] === 'published' ? 'badge-green' : ($up['upload_status'] === 'failed' ? 'badge-red' : 'badge-cyan') ?>">
                        <?= strtoupper($up['upload_status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if (!empty($up['youtube_url'])): ?>
                        <a href="<?= htmlspecialchars($up['youtube_url']) ?>" target="_blank" style="color:#38bdf8; text-decoration:underline; font-weight:600;">
                          <code><?= htmlspecialchars($up['youtube_video_id'] ?: 'Watch Short') ?></code> ↗
                        </a>
                      <?php else: ?>
                        <span style="color:var(--text-dim);">-</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, H:i', strtotime($up['created_at'])) ?></td>
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
async function handleDisconnectChannel() {
  if (!confirm("Are you sure you want to disconnect your YouTube channel? Security tokens will be revoked and removed.")) return;
  try {
    const res = await fetch('api/youtube.php?action=disconnect', { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Channel disconnected.', 'info');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(data.message || 'Failed to disconnect channel.', 'error');
    }
  } catch (e) {
    showToast('Failed to disconnect channel: ' + e.message, 'error');
  }
}

async function loadClipForPublishing(clipId) {
  try {
    const res = await fetch(`api/clips.php?action=get&id=${encodeURIComponent(clipId)}`);
    const data = await res.json();
    if (res.ok && data.success && data.clip) {
      const c = data.clip;
      document.getElementById('publisher-video-preview').src = c.video_url;
      document.getElementById('pub-title-input').value = (c.seo && c.seo.youtube_title) ? c.seo.youtube_title : (c.title + ' #Shorts #GTA5');
      document.getElementById('pub-desc-input').value = (c.seo && c.seo.description) ? c.seo.description : (c.description || 'GTA 5 gameplay highlight.');
      document.getElementById('pub-tags-input').value = (c.seo && c.seo.hashtags) ? c.seo.hashtags.join(', ') : '#GTAV, #GTA5, #Shorts';
      document.getElementById('pub-download-btn').href = c.video_url;
    }
  } catch (e) {
    console.error(e);
  }
}

async function regenerateSeo() {
  const clipId = document.getElementById('publisher-clip-select').value;
  showToast('Generating viral SEO tags with Gemini AI...', 'info');
  try {
    const res = await fetch('api/clips.php?action=seo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ clip_id: clipId })
    });
    const data = await res.json();
    if (res.ok && data.success && data.seo) {
      document.getElementById('pub-title-input').value = data.seo.youtube_title;
      document.getElementById('pub-desc-input').value = data.seo.description;
      document.getElementById('pub-tags-input').value = data.seo.hashtags.join(', ');
      showToast('Gemini SEO tags updated!', 'success');
    }
  } catch (e) {
    showToast('Failed to generate SEO: ' + e.message, 'error');
  }
}

async function handleDirectPublish(e) {
  e.preventDefault();
  const clipId = document.getElementById('publisher-clip-select').value;
  const title = document.getElementById('pub-title-input').value.trim();
  const desc = document.getElementById('pub-desc-input').value.trim();
  const privacy = document.getElementById('pub-privacy-select').value;
  const tags = document.getElementById('pub-tags-input').value.trim();

  const statusMsg = document.getElementById('pub-status-msg');
  const btn = document.getElementById('pub-submit-btn');
  const progressContainer = document.getElementById('upload-progress-container');
  const progressFill = document.getElementById('upload-progress-fill');
  const progressPercent = document.getElementById('upload-percent-label');
  const stageLabel = document.getElementById('upload-stage-label');

  statusMsg.style.display = 'none';
  progressContainer.style.display = 'block';
  btn.disabled = true;

  // Progressive upload visualizer
  let progress = 10;
  progressFill.style.width = '10%';
  progressPercent.textContent = '10%';
  stageLabel.textContent = 'Contacting YouTube Data API v3... Initiating Resumable Upload Session';

  const progressInterval = setInterval(() => {
    if (progress < 85) {
      progress += Math.floor(Math.random() * 12) + 5;
      if (progress > 85) progress = 85;
      progressFill.style.width = progress + '%';
      progressPercent.textContent = progress + '%';
      stageLabel.textContent = `Streaming video payload to YouTube (${progress}%)...`;
    }
  }, 400);

  try {
    const res = await fetch('api/youtube.php?action=upload', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        clip_id: clipId,
        title: title,
        description: desc,
        privacy_status: privacy,
        tags: tags
      })
    });

    clearInterval(progressInterval);
    const data = await res.json();

    if (res.ok && data.success) {
      progressFill.style.width = '100%';
      progressPercent.textContent = '100%';
      stageLabel.textContent = 'Upload Verified & Published to YouTube!';

      statusMsg.style.display = 'block';
      statusMsg.style.background = 'rgba(16, 185, 129, 0.15)';
      statusMsg.style.border = '1px solid #10b981';
      statusMsg.style.color = '#6ee7b7';
      statusMsg.innerHTML = `
        <div style="font-weight:800; font-size:14px; margin-bottom:4px;">✅ Published to YouTube Shorts!</div>
        <div style="margin-bottom:4px;">YouTube Video ID: <code style="color:#ffffff; font-weight:700;">${data.youtube_video_id}</code></div>
        <div>Watch Live: <a href="${data.youtube_url}" target="_blank" style="color:#38bdf8; font-weight:700; text-decoration:underline;">${data.youtube_url} ↗</a></div>
      `;
      showToast('Clip successfully published to YouTube!', 'success');
      btn.disabled = false;
    } else {
      progressContainer.style.display = 'none';
      statusMsg.style.display = 'block';
      statusMsg.style.background = 'rgba(239, 68, 68, 0.15)';
      statusMsg.style.border = '1px solid #ef4444';
      statusMsg.style.color = '#fca5a5';
      statusMsg.textContent = 'Upload failed: ' + (data.message || 'API error');
      btn.disabled = false;
    }
  } catch (err) {
    clearInterval(progressInterval);
    progressContainer.style.display = 'none';
    statusMsg.style.display = 'block';
    statusMsg.style.background = 'rgba(239, 68, 68, 0.15)';
    statusMsg.style.border = '1px solid #ef4444';
    statusMsg.style.color = '#fca5a5';
    statusMsg.textContent = 'Connection error: ' + err.message;
    btn.disabled = false;
  }
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
