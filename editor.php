<?php
// editor.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Clip.php';

$user = AuthMiddleware::requireUser();

$clipId = $_GET['id'] ?? null;
$clip = null;
if ($clipId) {
    $clip = Clip::findById($clipId);
}
if (!$clip) {
    // Pick the most recent clip
    $clips = Clip::getByUser($user['id'], 1);
    if (!empty($clips)) {
        $clip = $clips[0];
        $clipId = $clip['id'];
    }
}

$activeNav = 'editor';
$pageTitle = 'Browser Video Editor';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Studio / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Interactive Video Editor</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('workspace.php') ?>" class="btn btn-secondary btn-sm">⚡ Watch &amp; Clip Studio</a>
        <a href="<?= url('my-clips.php') ?>" class="btn btn-secondary btn-sm">Back to Library</a>
        <?php if ($clip): ?>
          <a href="<?= url($clip['file_path']) ?>" download class="btn btn-primary btn-sm">⬇️ Download MP4</a>
        <?php endif; ?>
      </div>
    </header>

    <div class="page-container" style="max-width: 1200px; margin: 0 auto;">
      <?php if (!$clip): ?>
        <div class="glass-card" style="text-align:center; padding:64px 24px;">
          <div style="font-size:48px; margin-bottom:16px;">✂️</div>
          <h2 style="font-size:20px; font-weight:700; margin-bottom:8px;">No Clip Selected</h2>
          <p style="font-size:14px; color:var(--text-muted); margin-bottom:24px;">Please select a clip from your library to edit and re-trim.</p>
          <a href="<?= url('my-clips.php') ?>" class="btn btn-primary">Go to Clips Library</a>
        </div>
      <?php else: 
        $videoUrl = url($clip['file_path']);
        $duration = (float)($clip['duration'] ?? 30);
      ?>
        <div style="display:grid; grid-template-columns: 440px 1fr; gap: 32px; align-items: start;">
          <!-- Left Column: 9:16 Video Canvas with Interactive Overlays -->
          <div class="glass-card" style="padding: 20px; display: flex; flex-direction: column; align-items: center;">
            <div style="position: relative; width: 100%; max-width: 340px; aspect-ratio: 9/16; background: #000; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.8); border: 1px solid var(--border-color);">
              <video id="editor-video" src="<?= htmlspecialchars($videoUrl) ?>" playsinline controls style="width:100%; height:100%; object-fit:cover;"></video>
              
              <!-- Live Top Hook Overlay -->
              <div id="live-hook-preview" style="position: absolute; top: 18px; left: 14px; right: 14px; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); border: 2px solid #fde047; border-radius: 8px; padding: 8px 12px; text-align: center; color: #ffffff; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; pointer-events: none;">
                <?= htmlspecialchars($clip['title']) ?>
              </div>

              <!-- Live Bottom Subtitle Overlay -->
              <div id="live-sub-preview" style="position: absolute; bottom: 36px; left: 16px; right: 16px; text-align: center; font-weight: 900; font-size: 16px; color: #fde047; text-shadow: 0 2px 8px #000; pointer-events: none;">
                WAIT FOR THE END... 😱
              </div>
            </div>

            <!-- Video Scrubber Controls -->
            <div style="width: 100%; margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted);">
              <span>Current: <strong id="current-timecode" style="color:white;">00:00</strong></span>
              <span>Total: <strong style="color:white;"><?= format_duration($duration) ?></strong></span>
            </div>
          </div>

          <!-- Right Column: Editing Tools & Form -->
          <div class="glass-card" style="padding: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
              <div>
                <h2 style="font-size: 20px; font-weight: 800;">Clip Customizer</h2>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                  Original Source: <?= htmlspecialchars($clip['video_title'] ?: 'Stream Highlight') ?>
                </div>
              </div>
              <span class="badge badge-purple" style="font-size: 12px;">
                🔥 <?= round((float)($clip['virality_score'] ?? 0.85) * 100) ?>% Viral Score
              </span>
            </div>

            <form id="editor-form">
              <?= csrf_field() ?>
              <input type="hidden" name="clip_id" value="<?= htmlspecialchars($clip['id']) ?>">

              <!-- Title & Top Hook -->
              <div class="form-group">
                <label class="form-label">Top Hook Headline</label>
                <input type="text" id="hook-input" name="title" class="form-control" value="<?= htmlspecialchars($clip['title']) ?>" oninput="updateLiveOverlays()">
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Appears in the upper bar overlay of the vertical video.</div>
              </div>

              <!-- Re-trim Boundaries -->
              <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 13px; font-weight: 700; margin-bottom: 12px; display: flex; justify-content: space-between;">
                  <span>Precision Timestamp Trim (Seconds)</span>
                  <span style="color: var(--secondary);">PeakCut Context Guard: Active</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                  <div>
                    <label class="form-label" style="font-size: 12px;">Start Time (s)</label>
                    <input type="number" step="0.1" name="start_time" id="start-trim-input" class="form-control" value="<?= (float)($clip['start_time'] ?? 0) ?>">
                  </div>
                  <div>
                    <label class="form-label" style="font-size: 12px;">End Time (s)</label>
                    <input type="number" step="0.1" name="end_time" id="end-trim-input" class="form-control" value="<?= (float)($clip['end_time'] ?? ($clip['start_time'] + $duration)) ?>">
                  </div>
                </div>
              </div>

              <!-- Subtitle & Caption Text -->
              <div class="form-group">
                <label class="form-label">Bottom Viral Subtitle Text</label>
                <input type="text" id="sub-input" name="subtitle_text" class="form-control" value="WAIT FOR THE END... 😱" oninput="updateLiveOverlays()">
              </div>

              <!-- Subtitle Styling -->
              <div class="form-group">
                <label class="form-label">Animated Caption Preset</label>
                <select name="caption_preset" class="form-control">
                  <option value="mrbeast" selected>MrBeast High-Contrast Yellow/Cyan (Bouncy)</option>
                  <option value="hermonzi">Alex Hormonzi Clean One-Word Highlight</option>
                  <option value="cyber">Cyberpunk Electric Blue & Purple Neon</option>
                  <option value="minimal">Minimal White Caption with Box</option>
                </select>
              </div>

              <!-- Description & Hashtags -->
              <div class="form-group">
                <label class="form-label">Social Description & Hashtags</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($clip['description'] ?: $clip['title'] . ' #gaming #shorts #gta #viral') ?></textarea>
              </div>

              <!-- Action Buttons -->
              <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="previewCurrentTrim()" style="flex: 1;">
                  ▶ Preview Trim
                </button>
                <button type="submit" id="save-clip-btn" class="btn btn-primary" style="flex: 2;">
                  💾 Save & Update Clip
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
const video = document.getElementById('editor-video');
const currentTimecode = document.getElementById('current-timecode');
const hookInput = document.getElementById('hook-input');
const subInput = document.getElementById('sub-input');
const hookPreview = document.getElementById('live-hook-preview');
const subPreview = document.getElementById('live-sub-preview');

if (video) {
  video.addEventListener('timeupdate', () => {
    const mins = Math.floor(video.currentTime / 60);
    const secs = Math.floor(video.currentTime % 60);
    currentTimecode.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  });
}

function updateLiveOverlays() {
  if (hookInput && hookPreview) {
    hookPreview.textContent = hookInput.value || 'TITLE HOOK';
  }
  if (subInput && subPreview) {
    subPreview.textContent = subInput.value || '';
  }
}

function previewCurrentTrim() {
  if (!video) return;
  const start = parseFloat(document.getElementById('start-trim-input').value) || 0;
  video.currentTime = 0;
  video.play();
  showToast('Previewing trimmed segment', 'info');
}

// Save form
document.getElementById('editor-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const saveBtn = document.getElementById('save-clip-btn');
  saveBtn.textContent = 'Saving Changes...';
  saveBtn.disabled = true;

  try {
    const form = e.target;
    const clipId = form.clip_id.value;
    const title = form.title.value;
    const desc = form.description.value;

    const res = await fetch(`api/clips.php?action=update`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        clip_id: clipId,
        title: title,
        description: desc
      })
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Clip updated successfully!', 'success');
    } else {
      showToast(data.message || 'Failed to update clip', 'error');
    }
  } catch (err) {
    showToast('Network error while saving clip', 'error');
  } finally {
    saveBtn.textContent = '💾 Save & Update Clip';
    saveBtn.disabled = false;
  }
});
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
