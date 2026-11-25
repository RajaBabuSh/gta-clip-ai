<?php
// workspace.php - EiziClip-Inspired Watch & Clip Studio & Stream Wall
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Video.php';
require_once __DIR__ . '/app/models/Clip.php';

$user = AuthMiddleware::requireUser();

// Get active video
$videoId = $_GET['video_id'] ?? null;
$video = null;

if ($videoId) {
    $video = Video::findById($videoId);
}

// If no video specified, fetch the most recent video
if (!$video) {
    $recentVideos = Video::getByUser($user['id'], 1);
    if (!empty($recentVideos)) {
        $video = $recentVideos[0];
        $videoId = $video['id'];
    }
}

// Fetch all user videos for the switcher
$allVideos = Video::getByUser($user['id'], 50);

// Fetch detected moments for active video
$moments = [];
$clips = [];
if ($video) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM detected_moments WHERE video_id = ? ORDER BY start_time ASC");
    $stmt->execute([$video['id']]);
    $moments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch clips for active video
    $clips = Clip::getByVideo($video['id']);
}

$activeNav = 'workspace';
$pageTitle = 'Watch & Clip Studio';
require_once __DIR__ . '/views/partials/header.php';
?>

<link rel="stylesheet" href="<?= asset('css/workspace.css') ?>?v=<?= time() ?>">

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <div class="workspace-container">
      
      <!-- Top Studio Toolbar -->
      <div class="studio-topbar">
        <div class="studio-selector-group">
          <span style="font-size:18px;">⚡</span>
          <div>
            <div style="font-size:14px; font-weight:800; color:#fff;">EiziClip Studio</div>
            <div style="font-size:11px; color:var(--text-muted);">Watch &amp; Clip Engine</div>
          </div>

          <!-- Video Selector Dropdown -->
          <div style="margin-left: 16px;">
            <select id="video-selector" class="form-control" style="font-size:13px; padding:6px 12px; background:rgba(0,0,0,0.5); color:#fff; border-radius:8px;" onchange="switchVideo(this.value)">
              <?php if (empty($allVideos)): ?>
                <option value="">No videos uploaded yet</option>
              <?php else: ?>
                <?php foreach ($allVideos as $v): ?>
                  <option value="<?= htmlspecialchars($v['id']) ?>" <?= ($video && $video['id'] === $v['id']) ? 'selected' : '' ?>>
                    📹 <?= htmlspecialchars(substr($v['title'], 0, 42)) ?> (<?= format_duration((float)($v['duration_seconds'] ?? 0)) ?>)
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <!-- Mode Toggle Pills: Watch & Clip Studio vs Live Stream Wall -->
        <div class="studio-mode-pills">
          <button id="pill-mode-studio" class="mode-pill active" onclick="setStudioMode('studio')">
            🎬 Watch &amp; Clip Studio
          </button>
          <button id="pill-mode-wall" class="mode-pill" onclick="setStudioMode('wall')">
            🧱 Live Stream Wall
          </button>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
          <a href="<?= url('add-video.php') ?>" class="btn btn-secondary btn-sm">+ Import Video/Stream</a>
          <button type="button" class="btn btn-primary btn-sm" onclick="triggerBatchExport()">
            📦 Batch Export All (ZIP)
          </button>
        </div>
      </div>

      <?php if (!$video): ?>
        <!-- Empty State -->
        <div class="glass-card" style="text-align:center; padding:80px 24px; margin-top:20px;">
          <div style="font-size:56px; margin-bottom:16px;">📹</div>
          <h2 style="font-size:22px; font-weight:800; margin-bottom:8px;">No Video Loaded</h2>
          <p style="font-size:14px; color:var(--text-muted); max-width:480px; margin:0 auto 24px;">
            Import a Twitch, YouTube or Kick stream, or upload a local MP4 to start clipping while you watch.
          </p>
          <a href="<?= url('add-video.php') ?>" class="btn btn-primary">➕ Import Video or Stream</a>
        </div>
      <?php else: 
        $videoUrl = url($video['file_path']);
        $totalDur = (float)($video['duration_seconds'] ?? 60.0);
      ?>

        <!-- MODE 1: WATCH & CLIP STUDIO -->
        <div id="view-mode-studio" class="studio-main-grid">
          
          <!-- Left Column: Video Stage, Player Scrubber, Interactive Timeline, AI Copilot -->
          <div style="display:flex; flex-direction:column; gap:16px;">
            
            <!-- Video Stage Card -->
            <div class="stage-card">
              
              <!-- Video Viewport -->
              <div class="player-stage-viewport">
                <div id="preview-wrapper" class="video-preview-wrapper aspect-16-9 mode-blurred-bg">
                  <!-- Blurred Background Underlay (EiziClip signature look) -->
                  <video id="bg-video" class="blurred-bg-underlay" src="<?= htmlspecialchars($videoUrl) ?>" muted playsinline></video>
                  
                  <!-- Main Video Player -->
                  <video id="main-player" class="main-video-element" src="<?= htmlspecialchars($videoUrl) ?>" playsinline></video>

                  <!-- Live Overlays -->
                  <div id="hook-overlay" class="overlay-hook-badge" style="display:none;">TOP HOOK HEADLINE</div>
                  <div id="sub-overlay" class="overlay-subtitle-caption" style="display:none;"></div>
                  
                  <!-- PIP Facecam Inset -->
                  <div id="pip-inset" class="pip-facecam-inset">
                    <video id="pip-video" src="<?= htmlspecialchars($videoUrl) ?>" muted playsinline style="width:100%; height:100%; object-fit:cover;"></video>
                  </div>
                </div>
              </div>

              <!-- Scrubber & Action Bar -->
              <div class="player-controls-bar">
                
                <!-- Timecode Display -->
                <div class="timecode-display">
                  <span id="tc-current">00:00:00.000</span>
                  <span class="muted">/</span>
                  <span id="tc-total" class="muted"><?= sprintf("%02d:%02d:%02d.%03d", floor($totalDur/3600), floor(($totalDur%3600)/60), floor($totalDur%60), floor(($totalDur - floor($totalDur))*1000)) ?></span>
                </div>

                <!-- Playback & Frame Buttons -->
                <div class="playback-buttons">
                  <button type="button" class="ctrl-btn" onclick="stepTime(-1.0)" title="Back 1s">⏮️ -1s</button>
                  <button type="button" class="ctrl-btn" onclick="stepFrame(-1)" title="Back 1 Frame (0.033s)">⏪ -1f</button>
                  <button type="button" id="btn-toggle-play" class="ctrl-btn btn-play" onclick="togglePlay()">▶ Play</button>
                  <button type="button" class="ctrl-btn" onclick="stepFrame(1)" title="Forward 1 Frame (0.033s)">+1f ⏩</button>
                  <button type="button" class="ctrl-btn" onclick="stepTime(1.0)" title="Forward 1s">+1s ⏭️</button>
                </div>

                <!-- Watch & Clip In/Out Markers -->
                <div style="display:flex; align-items:center; gap:8px;">
                  <button type="button" class="ctrl-btn" onclick="markInPoint()" title="Shortcut: 'I'">
                    [ Mark In (I)
                  </button>
                  <button type="button" class="ctrl-btn" onclick="markOutPoint()" title="Shortcut: 'O'">
                    Mark Out (O) ]
                  </button>
                  <button type="button" class="ctrl-btn btn-quick-clip" onclick="quickClip(30)" title="Instant 30s Rolling Buffer Capture">
                    ⚡ Clip (30s)
                  </button>
                </div>

                <!-- Playback Speed -->
                <div class="speed-pills">
                  <button type="button" class="speed-btn" onclick="setSpeed(0.5)">0.5x</button>
                  <button type="button" class="speed-btn active" onclick="setSpeed(1.0)">1x</button>
                  <button type="button" class="speed-btn" onclick="setSpeed(1.25)">1.25x</button>
                  <button type="button" class="speed-btn" onclick="setSpeed(1.5)">1.5x</button>
                  <button type="button" class="speed-btn" onclick="setSpeed(2.0)">2x</button>
                </div>
              </div>
            </div>

            <!-- INTERACTIVE MULTI-TRACK TIMELINE -->
            <div class="timeline-section">
              <div class="timeline-toolbar">
                <div class="timeline-tools-left">
                  <button type="button" class="ctrl-btn" onclick="createClipFromSelection()">
                    ✂️ Create Clip From Range
                  </button>
                  <button type="button" class="ctrl-btn" onclick="splitAtPlayhead()">
                    ✂️ Split
                  </button>
                  <button type="button" class="ctrl-btn" onclick="clearRangeSelection()">
                    🗑️ Clear Range
                  </button>
                  <button type="button" class="ctrl-btn" onclick="triggerAiSuggestions()">
                    ✨ Auto-Detect Highlights
                  </button>
                </div>

                <!-- Timeline Zoom Controls -->
                <div class="timeline-tools-right">
                  <span>Zoom:</span>
                  <button type="button" class="speed-btn active" onclick="setTimelineZoom(1.0)">1x</button>
                  <button type="button" class="speed-btn" onclick="setTimelineZoom(2.0)">2x</button>
                  <button type="button" class="speed-btn" onclick="setTimelineZoom(4.0)">4x</button>
                  <button type="button" class="speed-btn" onclick="setTimelineZoom(8.0)">8x</button>
                  <span style="margin-left:12px; color:var(--text-dim);">Keys: Space (Play), I (In), O (Out), C (Clip)</span>
                </div>
              </div>

              <!-- Timeline Viewport Container -->
              <div id="timeline-container" class="timeline-viewport-container">
                <div id="timeline-canvas-wrapper" class="timeline-track-canvas-wrapper" onclick="handleTimelineClick(event)">
                  
                  <!-- Track 1: Time Ruler -->
                  <div id="timeline-ruler" class="timeline-ruler"></div>

                  <!-- Track 2: Video Thumbnail Strip -->
                  <div id="timeline-video-track" class="timeline-track-video"></div>

                  <!-- Track 3: Audio Waveform Track -->
                  <div class="timeline-track-audio">
                    <canvas id="waveform-canvas" class="waveform-canvas"></canvas>
                  </div>

                  <!-- Track 4: AI Highlights Track -->
                  <div id="timeline-moments-track" class="timeline-track-moments"></div>

                  <!-- In/Out Selection Range Box -->
                  <div id="timeline-selection" class="timeline-selection-range">
                    <span id="range-tag-in" class="range-handle-in">IN 00:00</span>
                    <span id="range-tag-out" class="range-handle-out">OUT 00:00</span>
                  </div>

                  <!-- Draggable Playhead Scrubber -->
                  <div id="timeline-playhead" class="timeline-playhead" style="left: 0px;">
                    <div id="playhead-handle" class="timeline-playhead-handle" onmousedown="startPlayheadDrag(event)"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- FORMATTING & ASPECT RATIO SUITE -->
            <div class="glass-card" style="padding:18px 22px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <div style="font-size:14px; font-weight:800; color:#fff; display:flex; align-items:center; gap:8px;">
                  <span>📐</span> Format &amp; Aspect Ratio Suite (EiziClip Preview)
                </div>
                <span class="badge badge-purple" style="font-size:11px;">NVENC Hardware Active</span>
              </div>

              <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                <!-- Aspect Ratio Picker -->
                <div>
                  <label class="form-label" style="font-size:12px;">Aspect Ratio</label>
                  <div style="display:flex; gap:6px;">
                    <button type="button" class="ctrl-btn" id="btn-aspect-16-9" onclick="setAspectRatio('16:9')">16:9 Wide</button>
                    <button type="button" class="ctrl-btn" id="btn-aspect-9-16" style="border-color:var(--primary); color:#c4b5fd;" onclick="setAspectRatio('9:16')">9:16 Shorts</button>
                    <button type="button" class="ctrl-btn" id="btn-aspect-1-1" onclick="setAspectRatio('1:1')">1:1 Square</button>
                    <button type="button" class="ctrl-btn" id="btn-aspect-4-5" onclick="setAspectRatio('4:5')">4:5 Social</button>
                  </div>
                </div>

                <!-- Layout Mode -->
                <div>
                  <label class="form-label" style="font-size:12px;">Background Framing</label>
                  <select id="select-layout-mode" class="form-control" style="font-size:12px;" onchange="setLayoutMode(this.value)">
                    <option value="blurred_background" selected>Blurred Background (EiziClip Look)</option>
                    <option value="fit_center">Fit Center (Letterbox)</option>
                    <option value="crop_fill">Crop Fill (Action Zoom)</option>
                    <option value="pip_facecam">Picture-in-Picture Facecam</option>
                  </select>
                </div>

                <!-- Caption / Subtitle Style -->
                <div>
                  <label class="form-label" style="font-size:12px;">Animated Subtitle Preset</label>
                  <select id="select-caption-style" class="form-control" style="font-size:12px;" onchange="setCaptionStyle(this.value)">
                    <option value="mrbeast" selected>MrBeast High-Contrast Yellow/Cyan</option>
                    <option value="hormozi">Alex Hormozi Word Highlight</option>
                    <option value="cyber">Cyberpunk Neon</option>
                    <option value="minimal">Minimal White Box</option>
                  </select>
                </div>

                <!-- Top Hook Input -->
                <div>
                  <label class="form-label" style="font-size:12px;">Top Hook Headline</label>
                  <input type="text" id="input-hook-text" class="form-control" style="font-size:12px;" placeholder="WAIT FOR THE END... 😱" oninput="updateHookOverlay(this.value)">
                </div>
              </div>
            </div>

            <!-- AI AGENT COPILOT PANEL -->
            <div class="copilot-card">
              <div class="copilot-header">
                <div style="font-size:14px; font-weight:800; color:#fff; display:flex; align-items:center; gap:8px;">
                  <span>🤖</span> AI Editing Assistant Copilot
                </div>
                <span style="font-size:11px; color:var(--text-muted);">Natural Language Command Engine</span>
              </div>

              <!-- Quick Command Chips -->
              <div class="copilot-chips">
                <button type="button" class="copilot-chip" onclick="sendAiPrompt('Find the best moments in this stream')">🔥 Find Best Moments</button>
                <button type="button" class="copilot-chip" onclick="sendAiPrompt('Make 9:16 vertical with blurred background')">📐 Make 9:16 Vertical</button>
                <button type="button" class="copilot-chip" onclick="sendAiPrompt('Quick clip the last 30 seconds')">⚡ Quick Clip (30s)</button>
                <button type="button" class="copilot-chip" onclick="sendAiPrompt('Apply MrBeast animated subtitles')">✨ MrBeast Subtitles</button>
                <button type="button" class="copilot-chip" onclick="sendAiPrompt('Export all clips as ZIP')">📦 Export All (ZIP)</button>
              </div>

              <!-- Copilot Input Form -->
              <form id="copilot-form" onsubmit="handleCopilotSubmit(event)" class="copilot-input-group">
                <input type="text" id="copilot-input" class="copilot-input" placeholder="Ask AI: 'Find funny fails', 'Make it vertical', 'Clip last 45s'...">
                <button type="submit" id="copilot-btn" class="btn btn-primary btn-sm">Ask Copilot</button>
              </form>

              <!-- Assistant Response Feed -->
              <div id="copilot-feed" style="margin-top:10px; font-size:12px; color:#cbd5e1; display:none; background:rgba(0,0,0,0.3); padding:10px; border-radius:8px;"></div>
            </div>

          </div>

          <!-- Right Column: Live Clips Drawer & Session Library -->
          <aside class="clips-drawer">
            <div class="drawer-header">
              <div class="drawer-title">
                <span>🎬</span> Session Clips
                <span id="clips-count-badge" class="drawer-count-badge"><?= count($clips) ?> Clips</span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="refreshClipsList()" title="Refresh List">🔄</button>
            </div>

            <!-- Quick Action: Batch Export ZIP -->
            <button type="button" class="btn btn-primary" style="width:100%; font-size:13px; font-weight:700;" onclick="triggerBatchExport()">
              📦 Export All Clips (ZIP)
            </button>

            <!-- Clip Items List -->
            <div id="clips-list-container" class="clip-cards-list">
              <?php if (empty($clips)): ?>
                <div id="empty-clips-msg" style="text-align:center; padding:32px 10px; color:var(--text-muted); font-size:13px;">
                  <div style="font-size:32px; margin-bottom:8px;">✂️</div>
                  No clips created yet.<br>
                  Click <strong>[ Mark In ]</strong> &amp; <strong>[ Mark Out ]</strong> or press <strong>⚡ Clip (30s)</strong> while watching!
                </div>
              <?php else: ?>
                <?php foreach ($clips as $c): 
                  $cDur = (float)($c['duration'] ?? 30.0);
                  $cScore = round((float)($c['virality_score'] ?? 0.85) * 100);
                  $cUrl = url($c['file_path']);
                ?>
                  <div class="clip-card-item" id="clip-card-<?= htmlspecialchars($c['id']) ?>">
                    <div class="clip-item-top">
                      <div class="clip-thumbnail-box">
                        <video src="<?= htmlspecialchars($cUrl) ?>" muted></video>
                      </div>
                      <div class="clip-item-meta">
                        <input type="text" class="clip-item-title-input" value="<?= htmlspecialchars($c['title']) ?>" onchange="updateClipTitle('<?= htmlspecialchars($c['id']) ?>', this.value)">
                        <div class="clip-item-submeta">
                          <span class="badge-time">⏱️ <?= round((float)$c['start_time'], 1) ?>s - <?= round((float)$c['end_time'], 1) ?>s</span>
                          <span class="badge-time"><?= round($cDur, 1) ?>s</span>
                          <span style="color:#f59e0b; font-weight:700;">🔥 <?= $cScore ?>%</span>
                        </div>
                      </div>
                    </div>

                    <div class="clip-actions-row">
                      <button type="button" class="clip-action-btn" onclick="previewClipSegment(<?= (float)$c['start_time'] ?>, <?= (float)$c['end_time'] ?>)">▶ Preview</button>
                      <a href="<?= htmlspecialchars($cUrl) ?>" download class="clip-action-btn">⬇️ MP4</a>
                      <button type="button" class="clip-action-btn" onclick="exportFormat('<?= htmlspecialchars($c['id']) ?>', 'mp3')">🎵 MP3</button>
                      <button type="button" class="clip-action-btn" onclick="deleteClip('<?= htmlspecialchars($c['id']) ?>')">🗑️</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </aside>

        </div>

        <!-- MODE 2: LIVE STREAM WALL -->
        <div id="view-mode-wall" class="stream-wall-grid" style="display:none;">
          
          <!-- Tile 1: Primary Stream / Current Video -->
          <div class="stream-tile-card">
            <div class="tile-header">
              <span>📹 Stream 1 (Focus Source)</span>
              <span class="badge badge-purple" style="font-size:10px;">LIVE FEED</span>
            </div>
            <div class="tile-player-box">
              <video id="wall-player-1" src="<?= htmlspecialchars($videoUrl) ?>" playsinline controls></video>
            </div>
            <div class="tile-footer">
              <button type="button" class="ctrl-btn btn-quick-clip" onclick="quickClip(30)">⚡ Clip Last 30s</button>
              <button type="button" class="ctrl-btn" onclick="setStudioMode('studio')">Focus Studio</button>
            </div>
          </div>

          <!-- Tile 2: YouTube / Twitch Live Embed Stream -->
          <div class="stream-tile-card">
            <div class="tile-header">
              <span>🔴 YouTube Gaming Feed</span>
              <span class="badge badge-danger" style="font-size:10px;">STREAM WALL</span>
            </div>
            <div class="tile-player-box">
              <iframe src="https://www.youtube.com/embed/irNhWMG560k?autoplay=0&mute=1&enablejsapi=1" allowfullscreen></iframe>
            </div>
            <div class="tile-footer">
              <button type="button" class="ctrl-btn btn-quick-clip" onclick="quickClip(30)">⚡ Clip Last 30s</button>
              <span style="font-size:11px; color:var(--text-muted);">Wall plays while clipping</span>
            </div>
          </div>

          <!-- Tile 3: Secondary Stream (Twitch/Kick style) -->
          <div class="stream-tile-card">
            <div class="tile-header">
              <span>🎮 Stream 3 (Los Santos Gameplay)</span>
              <span class="badge badge-info" style="font-size:10px;">STANDBY</span>
            </div>
            <div class="tile-player-box">
              <video src="<?= htmlspecialchars($videoUrl) ?>" muted loop playsinline controls></video>
            </div>
            <div class="tile-footer">
              <button type="button" class="ctrl-btn btn-quick-clip" onclick="quickClip(30)">⚡ Clip Last 30s</button>
              <span style="font-size:11px; color:var(--text-muted);">No pause needed</span>
            </div>
          </div>

        </div>

      <?php endif; ?>

    </div>
  </main>
</div>

<!-- Studio Client JS Engine -->
<script>
const VIDEO_ID = <?= json_encode($video['id'] ?? '') ?>;
const VIDEO_DURATION = <?= (float)($video['duration_seconds'] ?? 60.0) ?>;
const INITIAL_MOMENTS = <?= json_encode($moments ?? []) ?>;

let currentMarkIn = null;
let currentMarkOut = null;
let activeAspectRatio = '16:9';
let activeLayoutMode = 'blurred_background';
let timelineZoom = 1.0;
let isDraggingPlayhead = false;

const mainPlayer = document.getElementById('main-player');
const bgVideo = document.getElementById('bg-video');
const pipVideo = document.getElementById('pip-video');
const tcCurrent = document.getElementById('tc-current');
const btnPlay = document.getElementById('btn-toggle-play');
const previewWrapper = document.getElementById('preview-wrapper');
const timelinePlayhead = document.getElementById('timeline-playhead');
const timelineSelection = document.getElementById('timeline-selection');
const rangeTagIn = document.getElementById('range-tag-in');
const rangeTagOut = document.getElementById('range-tag-out');
const hookOverlay = document.getElementById('hook-overlay');
const subOverlay = document.getElementById('sub-overlay');

// Sync bgVideo and pipVideo with mainPlayer
if (mainPlayer) {
  mainPlayer.addEventListener('play', () => {
    btnPlay.textContent = '⏸️ Pause';
    if (bgVideo) bgVideo.play();
    if (pipVideo) pipVideo.play();
  });

  mainPlayer.addEventListener('pause', () => {
    btnPlay.textContent = '▶ Play';
    if (bgVideo) bgVideo.pause();
    if (pipVideo) pipVideo.pause();
  });

  mainPlayer.addEventListener('timeupdate', () => {
    updateTimecodeDisplay(mainPlayer.currentTime);
    updatePlayheadPosition(mainPlayer.currentTime);
    if (bgVideo && Math.abs(bgVideo.currentTime - mainPlayer.currentTime) > 0.3) {
      bgVideo.currentTime = mainPlayer.currentTime;
    }
  });

  mainPlayer.addEventListener('loadedmetadata', () => {
    drawWaveform();
    drawRuler();
    renderMomentsTrack();
  });
}

function updateTimecodeDisplay(seconds) {
  if (!tcCurrent) return;
  const hrs = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  const millis = Math.floor((seconds - Math.floor(seconds)) * 1000);
  tcCurrent.textContent = `${String(hrs).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}.${String(millis).padStart(3,'0')}`;
}

function updatePlayheadPosition(seconds) {
  if (!timelinePlayhead || !mainPlayer) return;
  const wrapper = document.getElementById('timeline-canvas-wrapper');
  if (!wrapper) return;
  const pct = Math.min(1.0, Math.max(0.0, seconds / VIDEO_DURATION));
  const posPx = pct * wrapper.clientWidth;
  timelinePlayhead.style.left = `${posPx}px`;
}

function togglePlay() {
  if (!mainPlayer) return;
  if (mainPlayer.paused) {
    mainPlayer.play();
  } else {
    mainPlayer.pause();
  }
}

function stepFrame(frames = 1) {
  if (!mainPlayer) return;
  mainPlayer.pause();
  // 30 FPS => 1/30s = 0.0333s
  mainPlayer.currentTime = Math.max(0, Math.min(VIDEO_DURATION, mainPlayer.currentTime + (frames * 0.0333)));
}

function stepTime(deltaSeconds) {
  if (!mainPlayer) return;
  mainPlayer.currentTime = Math.max(0, Math.min(VIDEO_DURATION, mainPlayer.currentTime + deltaSeconds));
}

function setSpeed(rate) {
  if (!mainPlayer) return;
  mainPlayer.playbackRate = rate;
  document.querySelectorAll('.speed-btn').forEach(btn => {
    if (btn.textContent === `${rate}x`) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  showToast(`Playback speed: ${rate}x`, 'info');
}

// Watch & Clip: Mark In / Out Points
function markInPoint() {
  if (!mainPlayer) return;
  currentMarkIn = mainPlayer.currentTime;
  if (currentMarkOut !== null && currentMarkOut <= currentMarkIn) {
    currentMarkOut = Math.min(VIDEO_DURATION, currentMarkIn + 30.0);
  }
  updateRangeSelectionUI();
  showToast(`Mark In set to ${currentMarkIn.toFixed(2)}s`, 'success');
}

function markOutPoint() {
  if (!mainPlayer) return;
  currentMarkOut = mainPlayer.currentTime;
  if (currentMarkIn === null) {
    currentMarkIn = Math.max(0, currentMarkOut - 30.0);
  } else if (currentMarkOut <= currentMarkIn) {
    currentMarkIn = Math.max(0, currentMarkOut - 5.0);
  }
  updateRangeSelectionUI();
  showToast(`Mark Out set to ${currentMarkOut.toFixed(2)}s`, 'success');
}

function clearRangeSelection() {
  currentMarkIn = null;
  currentMarkOut = null;
  if (timelineSelection) timelineSelection.classList.remove('active');
  showToast('Range selection cleared', 'info');
}

function updateRangeSelectionUI() {
  if (!timelineSelection || currentMarkIn === null || currentMarkOut === null) return;
  const wrapper = document.getElementById('timeline-canvas-wrapper');
  if (!wrapper) return;

  const totalW = wrapper.clientWidth;
  const leftPx = (currentMarkIn / VIDEO_DURATION) * totalW;
  const rightPx = (currentMarkOut / VIDEO_DURATION) * totalW;
  const widthPx = Math.max(4, rightPx - leftPx);

  timelineSelection.style.left = `${leftPx}px`;
  timelineSelection.style.width = `${widthPx}px`;
  timelineSelection.classList.add('active');

  if (rangeTagIn) rangeTagIn.textContent = `IN ${currentMarkIn.toFixed(1)}s`;
  if (rangeTagOut) rangeTagOut.textContent = `OUT ${currentMarkOut.toFixed(1)}s`;
}

// Instant Quick Clip (Rolling Buffer Capture - EiziClip Core Capability)
async function quickClip(seconds = 30) {
  if (!mainPlayer) return;
  const curTime = mainPlayer.currentTime;
  showToast(`⚡ Clipping last ${seconds}s while stream continues...`, 'info');

  try {
    const res = await fetch('api/clips.php?action=quick_clip', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        video_id: VIDEO_ID,
        current_time: curTime,
        duration: seconds,
        aspect_ratio: activeAspectRatio,
        layout_mode: activeLayoutMode
      })
    });
    const data = await res.json();
    if (res.ok && data.success && data.clip) {
      showToast('🎉 Quick clip captured and added to session!', 'success');
      prependClipToUI(data.clip);
    } else {
      showToast(data.message || 'Quick clip rendering failed', 'error');
    }
  } catch (err) {
    showToast('Network error while capturing quick clip', 'error');
  }
}

// Create Clip from Active Selection
async function createClipFromSelection() {
  if (currentMarkIn === null || currentMarkOut === null) {
    showToast('Please set [ Mark In ] and [ Mark Out ] points first.', 'warning');
    return;
  }
  const hookText = document.getElementById('input-hook-text')?.value || 'Stream Highlight';
  showToast('Rendering custom clip from range selection...', 'info');

  try {
    const res = await fetch('api/clips.php?action=create_custom', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        video_id: VIDEO_ID,
        start_time: currentMarkIn,
        end_time: currentMarkOut,
        title: hookText,
        aspect_ratio: activeAspectRatio,
        layout_mode: activeLayoutMode,
        burn_subtitles: false
      })
    });
    const data = await res.json();
    if (res.ok && data.success && data.clip) {
      showToast('🎉 Custom clip created and added to library!', 'success');
      prependClipToUI(data.clip);
      clearRangeSelection();
    } else {
      showToast(data.message || 'Failed to render clip', 'error');
    }
  } catch (err) {
    showToast('Network error during clip creation', 'error');
  }
}

function splitAtPlayhead() {
  if (!mainPlayer) return;
  const cur = mainPlayer.currentTime;
  currentMarkIn = Math.max(0, cur - 15);
  currentMarkOut = Math.min(VIDEO_DURATION, cur + 15);
  updateRangeSelectionUI();
  showToast(`Split anchor created at ${cur.toFixed(2)}s (±15s window)`, 'info');
}

// UI Aspect Ratio & Layout Mode Switcher
function setAspectRatio(ratio) {
  activeAspectRatio = ratio;
  const preview = document.getElementById('preview-wrapper');
  if (!preview) return;

  preview.className = `video-preview-wrapper aspect-${ratio.replace(':', '-')} mode-${activeLayoutMode.replace('_', '-')}`;
  showToast(`Aspect ratio set to ${ratio}`, 'info');

  ['16-9', '9-16', '1-1', '4-5'].forEach(r => {
    const btn = document.getElementById(`btn-aspect-${r}`);
    if (btn) {
      if (r === ratio.replace(':', '-')) {
        btn.style.borderColor = 'var(--primary)';
        btn.style.color = '#c4b5fd';
      } else {
        btn.style.borderColor = '';
        btn.style.color = '';
      }
    }
  });
}

function setLayoutMode(mode) {
  activeLayoutMode = mode;
  setAspectRatio(activeAspectRatio);
}

function setCaptionStyle(style) {
  showToast(`Caption style: ${style.toUpperCase()}`, 'info');
}

function updateHookOverlay(val) {
  if (!hookOverlay) return;
  if (val.trim()) {
    hookOverlay.textContent = val.toUpperCase();
    hookOverlay.style.display = 'block';
  } else {
    hookOverlay.style.display = 'none';
  }
}

// Timeline Zooming & Rendering
function setTimelineZoom(zoom) {
  timelineZoom = zoom;
  const wrapper = document.getElementById('timeline-canvas-wrapper');
  if (!wrapper) return;
  wrapper.style.width = `${zoom * 100}%`;
  drawWaveform();
  drawRuler();
  renderMomentsTrack();
  updateRangeSelectionUI();
  if (mainPlayer) updatePlayheadPosition(mainPlayer.currentTime);
}

function drawRuler() {
  const ruler = document.getElementById('timeline-ruler');
  if (!ruler) return;
  ruler.innerHTML = '';
  const totalSecs = VIDEO_DURATION;
  const stepSecs = totalSecs > 300 ? 30 : (totalSecs > 60 ? 10 : 5);

  for (let s = 0; s <= totalSecs; s += stepSecs) {
    const mark = document.createElement('div');
    mark.style.position = 'absolute';
    mark.style.left = `${(s / totalSecs) * 100}%`;
    mark.style.top = '4px';
    mark.style.fontSize = '9px';
    mark.style.color = 'var(--text-dim)';
    mark.style.fontFamily = 'monospace';
    const m = Math.floor(s / 60);
    const sc = Math.floor(s % 60);
    mark.textContent = `${m}:${String(sc).padStart(2,'0')}`;
    ruler.appendChild(mark);
  }
}

function drawWaveform() {
  const canvas = document.getElementById('waveform-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.clientWidth || 800;
  const h = canvas.clientHeight || 60;
  canvas.width = w;
  canvas.height = h;

  ctx.clearRect(0, 0, w, h);
  ctx.fillStyle = '#1e293b';
  ctx.fillRect(0, 0, w, h);

  // Generate smooth audio peak visualization
  const bars = Math.floor(w / 3);
  ctx.fillStyle = '#6366f1';
  for (let i = 0; i < bars; i++) {
    const progress = i / bars;
    const sin1 = Math.sin(progress * 40);
    const sin2 = Math.cos(progress * 15);
    const amp = Math.abs(sin1 * 0.6 + sin2 * 0.4);
    const barHeight = Math.max(4, amp * (h - 10));
    const x = i * 3;
    const y = (h - barHeight) / 2;
    ctx.fillRect(x, y, 2, barHeight);
  }
}

function renderMomentsTrack() {
  const track = document.getElementById('timeline-moments-track');
  if (!track || !INITIAL_MOMENTS.length) return;
  track.innerHTML = '';

  INITIAL_MOMENTS.forEach((m, idx) => {
    const s = parseFloat(m.start_time);
    const e = parseFloat(m.end_time);
    const dur = Math.max(2, e - s);
    const leftPct = (s / VIDEO_DURATION) * 100;
    const widthPct = (dur / VIDEO_DURATION) * 100;

    const chip = document.createElement('div');
    chip.className = 'moment-chip-marker';
    chip.style.left = `${leftPct}%`;
    chip.style.width = `${Math.max(widthPct, 2)}%`;
    chip.textContent = `🔥 Moment ${idx + 1}`;
    chip.title = `Moment ${idx + 1} (${s.toFixed(1)}s - ${e.toFixed(1)}s) | Score: ${Math.round((m.virality_score || 0.85)*100)}%`;
    chip.onclick = (e) => {
      e.stopPropagation();
      previewClipSegment(s, e);
    };
    track.appendChild(chip);
  });
}

function handleTimelineClick(e) {
  if (isDraggingPlayhead) return;
  const wrapper = document.getElementById('timeline-canvas-wrapper');
  if (!wrapper || !mainPlayer) return;
  const rect = wrapper.getBoundingClientRect();
  const clickX = e.clientX - rect.left;
  const pct = Math.max(0, Math.min(1.0, clickX / rect.width));
  mainPlayer.currentTime = pct * VIDEO_DURATION;
}

function startPlayheadDrag(e) {
  isDraggingPlayhead = true;
  const onMove = (moveEvent) => {
    if (!isDraggingPlayhead) return;
    const wrapper = document.getElementById('timeline-canvas-wrapper');
    if (!wrapper || !mainPlayer) return;
    const rect = wrapper.getBoundingClientRect();
    const x = moveEvent.clientX - rect.left;
    const pct = Math.max(0, Math.min(1.0, x / rect.width));
    mainPlayer.currentTime = pct * VIDEO_DURATION;
  };
  const onUp = () => {
    isDraggingPlayhead = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

// Preview segment in main player
function previewClipSegment(start, end) {
  if (!mainPlayer) return;
  currentMarkIn = start;
  currentMarkOut = end;
  updateRangeSelectionUI();
  mainPlayer.currentTime = start;
  mainPlayer.play();
  showToast(`Previewing segment (${start.toFixed(1)}s - ${end.toFixed(1)}s)`, 'info');
}

// Prepend newly rendered clip card to drawer list
function prependClipToUI(clip) {
  const container = document.getElementById('clips-list-container');
  const emptyMsg = document.getElementById('empty-clips-msg');
  if (emptyMsg) emptyMsg.remove();

  const cId = clip.id;
  const card = document.createElement('div');
  card.className = 'clip-card-item';
  card.id = `clip-card-${cId}`;
  card.innerHTML = `
    <div class="clip-item-top">
      <div class="clip-thumbnail-box">
        <video src="${clip.download_url || clip.file_path}" muted></video>
      </div>
      <div class="clip-item-meta">
        <input type="text" class="clip-item-title-input" value="${clip.title}" onchange="updateClipTitle('${cId}', this.value)">
        <div class="clip-item-submeta">
          <span class="badge-time">⏱️ ${parseFloat(clip.start_time).toFixed(1)}s - ${parseFloat(clip.end_time).toFixed(1)}s</span>
          <span class="badge-time">${parseFloat(clip.duration).toFixed(1)}s</span>
          <span style="color:#f59e0b; font-weight:700;">🔥 ${Math.round((clip.virality_score || 0.85)*100)}%</span>
        </div>
      </div>
    </div>
    <div class="clip-actions-row">
      <button type="button" class="clip-action-btn" onclick="previewClipSegment(${clip.start_time}, ${clip.end_time})">▶ Preview</button>
      <a href="${clip.download_url || clip.file_path}" download class="clip-action-btn">⬇️ MP4</a>
      <button type="button" class="clip-action-btn" onclick="exportFormat('${cId}', 'mp3')">🎵 MP3</button>
      <button type="button" class="clip-action-btn" onclick="deleteClip('${cId}')">🗑️</button>
    </div>
  `;
  container.prepend(card);

  // Update badge count
  const badge = document.getElementById('clips-count-badge');
  if (badge) {
    const curCount = container.querySelectorAll('.clip-card-item').length;
    badge.textContent = `${curCount} Clips`;
  }
}

// Multi-format exporter (MP4, MP3, SRT)
async function exportFormat(clipId, fmt) {
  showToast(`Exporting ${fmt.toUpperCase()}...`, 'info');
  try {
    const res = await fetch('api/clips.php?action=export_format', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ clip_id: clipId, format: fmt })
    });
    const data = await res.json();
    if (res.ok && data.success && data.download_url) {
      window.open(data.download_url, '_blank');
      showToast(`Export completed! Download started.`, 'success');
    } else {
      showToast(data.message || 'Export failed', 'error');
    }
  } catch (err) {
    showToast('Network error during export', 'error');
  }
}

// Update Clip Title
async function updateClipTitle(clipId, newTitle) {
  try {
    await fetch('api/clips.php?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ clip_id: clipId, title: newTitle })
    });
    showToast('Clip title saved', 'success');
  } catch (err) {
    showToast('Failed to save title', 'error');
  }
}

// Delete Clip
async function deleteClip(clipId) {
  if (!confirm('Are you sure you want to delete this clip?')) return;
  try {
    const res = await fetch(`api/clips.php?action=delete&id=${encodeURIComponent(clipId)}`, { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      document.getElementById(`clip-card-${clipId}`)?.remove();
      showToast('Clip deleted', 'info');
      const badge = document.getElementById('clips-count-badge');
      if (badge) {
        const curCount = document.querySelectorAll('.clip-card-item').length;
        badge.textContent = `${curCount} Clips`;
      }
    } else {
      showToast(data.message || 'Could not delete clip', 'error');
    }
  } catch (err) {
    showToast('Network error while deleting', 'error');
  }
}

// Batch ZIP Download
function triggerBatchExport() {
  showToast('Preparing batch ZIP download...', 'info');
  window.location.href = `api/clips.php?action=download_zip&video_id=${encodeURIComponent(VIDEO_ID)}`;
}

// AI Copilot Integration
async function handleCopilotSubmit(e) {
  e.preventDefault();
  const input = document.getElementById('copilot-input');
  const prompt = input.value.trim();
  if (!prompt) return;
  input.value = '';
  await sendAiPrompt(prompt);
}

async function sendAiPrompt(prompt) {
  const feed = document.getElementById('copilot-feed');
  const btn = document.getElementById('copilot-btn');
  if (feed) {
    feed.style.display = 'block';
    feed.innerHTML = `<em>🤖 AI Assistant is processing: "${prompt}"...</em>`;
  }
  if (btn) btn.disabled = true;

  try {
    const curTime = mainPlayer ? mainPlayer.currentTime : 0;
    const res = await fetch('api/ai_assistant.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        prompt: prompt,
        video_id: VIDEO_ID,
        current_time: curTime
      })
    });
    const data = await res.json();
    if (res.ok && data.success) {
      if (feed) feed.innerHTML = `<strong>🤖 Copilot:</strong> ${data.message.replace(/\n/g, '<br>')}`;
      executeCopilotAction(data.action, data.data);
    } else {
      if (feed) feed.innerHTML = `<span style="color:#ef4444;">❌ Error: ${data.message || 'Copilot failed to respond'}</span>`;
    }
  } catch (err) {
    if (feed) feed.innerHTML = `<span style="color:#ef4444;">❌ Network error contacting AI Copilot.</span>`;
  } finally {
    if (btn) btn.disabled = false;
  }
}

function executeCopilotAction(action, data) {
  if (!action) return;
  if (action === 'set_format') {
    if (data.aspect_ratio) setAspectRatio(data.aspect_ratio);
    if (data.layout_mode) setLayoutMode(data.layout_mode);
  } else if (action === 'trigger_quick_clip') {
    quickClip(data.duration || 30);
  } else if (action === 'set_captions') {
    setCaptionStyle(data.preset || 'mrbeast');
  } else if (action === 'batch_export') {
    triggerBatchExport();
  } else if (action === 'apply_moments' && data.moments) {
    showToast(`Loaded ${data.moments.length} highlight moments into timeline!`, 'success');
  }
}

function triggerAiSuggestions() {
  sendAiPrompt('Find best moments in this video');
}

// Switch between Studio and Live Stream Wall
function setStudioMode(mode) {
  const viewStudio = document.getElementById('view-mode-studio');
  const viewWall = document.getElementById('view-mode-wall');
  const pillStudio = document.getElementById('pill-mode-studio');
  const pillWall = document.getElementById('pill-mode-wall');

  if (mode === 'wall') {
    if (viewStudio) viewStudio.style.display = 'none';
    if (viewWall) viewWall.style.display = 'grid';
    if (pillWall) pillWall.classList.add('active');
    if (pillStudio) pillStudio.classList.remove('active');
    showToast('Switched to Live Stream Wall (Multi-Stream Grid)', 'info');
  } else {
    if (viewStudio) viewStudio.style.display = 'grid';
    if (viewWall) viewWall.style.display = 'none';
    if (pillStudio) pillStudio.classList.add('active');
    if (pillWall) pillWall.classList.remove('active');
    showToast('Switched to Watch & Clip Studio', 'info');
  }
}

function switchVideo(newId) {
  if (!newId) return;
  window.location.href = `workspace.php?video_id=${encodeURIComponent(newId)}`;
}

// Global Keyboard Shortcuts (Space, I, O, C, Left, Right)
window.addEventListener('keydown', (e) => {
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
  if (e.code === 'Space') {
    e.preventDefault();
    togglePlay();
  } else if (e.code === 'KeyI') {
    e.preventDefault();
    markInPoint();
  } else if (e.code === 'KeyO') {
    e.preventDefault();
    markOutPoint();
  } else if (e.code === 'KeyC') {
    e.preventDefault();
    quickClip(30);
  } else if (e.code === 'ArrowLeft') {
    e.preventDefault();
    stepTime(-5.0);
  } else if (e.code === 'ArrowRight') {
    e.preventDefault();
    stepTime(5.0);
  }
});
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
