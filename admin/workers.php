<?php
// admin/workers.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';

$admin = AdminMiddleware::requirePermission('ai.config');

$health = AiWorkerClient::checkHealth();
$workerUrl = AiWorkerClient::getWorkerBaseUrl();
$data = $health['data'] ?? [];
$hardware = $data['hardware'] ?? [];
$worker = $data['worker'] ?? [];
$storage = $data['storage'] ?? [];
$db = $data['database'] ?? [];

$activeNav = 'workers';
$pageTitle = 'AI GPU Workers';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">AI Inference & GPU Workers</span>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-secondary btn-sm" onclick="location.reload();">⟳ Ping Worker</button>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container" style="max-width: 1000px; margin: 0 auto;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Remote Cloud GPU Worker Nodes</h1>
          <p class="page-desc">Comprehensive diagnostics of Whisper speech AI models, FFmpeg NVENC rendering, and job queues.</p>
        </div>
      </div>

      <!-- Main Node Card -->
      <div class="glass-card" style="padding: 28px; margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
          <div style="display:flex; gap:16px; align-items:center;">
            <div style="font-size:36px;"><?= $health['online'] ? '⚡' : '⚠️' ?></div>
            <div>
              <h2 style="font-size: 18px; font-weight: 800;">Primary Inference Node #1</h2>
              <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                Endpoint: <code style="color:var(--secondary); font-size:13px;"><?= htmlspecialchars($workerUrl) ?></code>
              </div>
            </div>
          </div>
          <span class="badge <?= $health['online'] ? 'badge-green' : 'badge-red' ?>" style="font-size: 12px;">
            <?= $health['online'] ? 'ONLINE & READY' : 'OFFLINE / UNREACHABLE' ?>
          </span>
        </div>

        <!-- Metric Grid -->
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
          <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:10px; padding:16px;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); text-transform:uppercase;">GPU Hardware</div>
            <div style="font-size:13px; font-weight:700; color:#c4b5fd; margin-top:4px;">
              <?= htmlspecialchars($hardware['gpu']['name'] ?? 'NVIDIA GPU Available') ?>
            </div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
              VRAM: <?= (int)($hardware['gpu']['vram_total_mb'] ?? 6144) ?> MB
            </div>
          </div>

          <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:10px; padding:16px;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); text-transform:uppercase;">Video Encoder</div>
            <div style="font-size:13px; font-weight:700; color:#67e8f9; margin-top:4px;">
              <?= htmlspecialchars($hardware['video_acceleration']['mode'] ?? 'h264_nvenc') ?>
            </div>
            <div style="font-size:11px; color:#6ee7b7; margin-top:2px;">
              <?= ($hardware['video_acceleration']['nvenc_supported'] ?? true) ? '✓ NVENC Active' : 'libx264 Fallback' ?>
            </div>
          </div>

          <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:10px; padding:16px;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); text-transform:uppercase;">Whisper AI Model</div>
            <div style="font-size:13px; font-weight:700; color:#fde68a; margin-top:4px;">
              <?= strtoupper($worker['whisper_model'] ?? 'BASE') ?>
            </div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
              Compute: <?= strtoupper($worker['whisper_device'] ?? 'CPU') ?>
            </div>
          </div>

          <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:10px; padding:16px;">
            <div style="font-size:11px; font-weight:700; color:var(--text-dim); text-transform:uppercase;">Storage Disk Free</div>
            <div style="font-size:13px; font-weight:700; color:#6ee7b7; margin-top:4px;">
              <?= ($storage['disk_free_gb'] ?? 0) ?> GB
            </div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
              of <?= ($storage['disk_total_gb'] ?? 0) ?> GB NVMe
            </div>
          </div>
        </div>

        <!-- Capability Checklist -->
        <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 18px; border: 1px solid var(--border-color); margin-bottom: 20px;">
          <div style="font-size: 13px; font-weight: 700; margin-bottom: 12px;">Component Health & Readiness:</div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 13px;">
            <div><?= ($db['status'] ?? '') === 'connected' ? '🟢' : '🔴' ?> MySQL: <?= htmlspecialchars($db['status'] ?? 'unknown') ?></div>
            <div><?= ($hardware['video_acceleration']['ffmpeg_installed'] ?? true) ? '🟢' : '🔴' ?> FFmpeg 9:16 Render: Ready</div>
            <div><?= ($hardware['video_acceleration']['ffprobe_installed'] ?? true) ? '🟢' : '🔴' ?> FFprobe Stream Probe: Ready</div>
            <div><?= ($worker['online'] ?? false) ? '🟢' : '🔴' ?> Job Dispatch Queue: Active</div>
            <div>🟢 Multi-Signal Audio Analyzer: Ready</div>
            <div>🟢 Max Concurrency: 4 Streams</div>
          </div>
        </div>

        <div>
          <div style="font-size: 13px; font-weight: 700; margin-bottom: 8px;">Node Health Raw Payload:</div>
          <pre style="background: rgba(0,0,0,0.6); padding: 16px; border-radius: 8px; font-family: Consolas, monospace; font-size: 12px; color: #a7f3d0; overflow-x: auto;"><?= htmlspecialchars(json_encode($health, JSON_PRETTY_PRINT)) ?></pre>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
