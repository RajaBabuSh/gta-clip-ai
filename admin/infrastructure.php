<?php
// admin/infrastructure.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';

$admin = AdminMiddleware::requirePermission('infra.manage');
$pdo = Database::getConnection();

// 1. Database Info
$dbSize = 0;
$tableCount = 0;
$dbVersion = '';
try {
    $dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn() ?: 'Unknown';
    $sizeStmt = $pdo->query("
        SELECT table_schema,
               ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
               COUNT(table_name) as total_tables
        FROM information_schema.tables
        WHERE table_schema = 'gtaclipai'
        GROUP BY table_schema
    ");
    $dbInfo = $sizeStmt->fetch(PDO::FETCH_ASSOC);
    $dbSize = (float)($dbInfo['size_mb'] ?? 0);
    $tableCount = (int)($dbInfo['total_tables'] ?? 0);
} catch (Exception $e) {
    // continue
}

// 2. Worker Status
$workerHealth = AiWorkerClient::checkHealth();

// 3. Disk Space
$diskTotal = @disk_total_space(__DIR__);
$diskFree = @disk_free_space(__DIR__);
$diskUsed = ($diskTotal !== false && $diskFree !== false) ? ($diskTotal - $diskFree) : 0;
$diskPct = ($diskTotal > 0) ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

// 4. Redis Check
$redisStatus = ['connected' => false, 'message' => 'Not installed or extension missing'];
if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $redisUrl = parse_url(SystemSetting::get('redis_url', 'redis://127.0.0.1:6379/0'));
        $host = $redisUrl['host'] ?? '127.0.0.1';
        $port = (int)($redisUrl['port'] ?? 6379);
        $con = @$redis->connect($host, $port, 1.0);
        if ($con && $redis->ping()) {
            $redisStatus = ['connected' => true, 'message' => "Connected to {$host}:{$port}"];
        } else {
            $redisStatus = ['connected' => false, 'message' => "Unreachable on {$host}:{$port}"];
        }
    } catch (Exception $e) {
        $redisStatus = ['connected' => false, 'message' => $e->getMessage()];
    }
}

// 5. FFmpeg Check
$ffmpegStatus = ['installed' => false, 'version' => 'Not detected in PATH'];
$ffmpegOutput = @shell_exec('ffmpeg -version 2>&1');
if ($ffmpegOutput && str_contains($ffmpegOutput, 'ffmpeg version')) {
    $firstLine = explode("\n", $ffmpegOutput)[0];
    $ffmpegStatus = ['installed' => true, 'version' => $firstLine];
}

$activeNav = 'infrastructure';
$pageTitle = 'Infrastructure & Hardware Health';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Infrastructure & Hardware Monitor</span>
      </div>
      <div class="topbar-actions">
        <span class="badge <?= $workerHealth['online'] ? 'badge-green' : 'badge-red' ?>">
          <?= $workerHealth['online'] ? '● AI GPU Online' : '○ AI GPU Offline' ?>
        </span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Hardware, Microservices & Storage Cluster</h1>
          <p class="page-desc">Real-time health telemetry across the Core PHP engine, MySQL 8, Python AI GPU nodes, Redis, and disk storage.</p>
        </div>
      </div>

      <!-- Quick Metrics -->
      <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon purple">🐘</div>
          <div>
            <div class="stat-val"><?= phpversion() ?></div>
            <div class="stat-lbl">PHP Engine Version</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon cyan">🐬</div>
          <div>
            <div class="stat-val"><?= $dbSize ?> MB</div>
            <div class="stat-lbl">MySQL Database (<?= $tableCount ?> tables)</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">🖥️</div>
          <div>
            <div class="stat-val"><?= $workerHealth['online'] ? 'Online (200 OK)' : 'Offline' ?></div>
            <div class="stat-lbl">FastAPI AI Worker</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon amber">💾</div>
          <div>
            <div class="stat-val"><?= $diskPct ?>%</div>
            <div class="stat-lbl">Disk Volume Used</div>
          </div>
        </div>
      </div>

      <!-- Detail Cards Grid -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:28px;">
        <!-- AI Worker Node Details -->
        <div class="glass-card" style="padding:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff; display:flex; align-items:center; gap:8px;">
            <span>🚀</span> Remote AI GPU Worker Node
          </h2>
          <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Worker URL:</span>
              <code><?= htmlspecialchars($workerHealth['worker_url']) ?></code>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Connection Status:</span>
              <span style="color:<?= $workerHealth['online'] ? '#6ee7b7' : '#f87171' ?>; font-weight:700;">
                <?= $workerHealth['online'] ? '● OPERATIONAL' : '○ DISCONNECTED' ?>
              </span>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Hardware Acceleration:</span>
              <span style="color:#a5b4fc; font-weight:600;">NVIDIA NVENC / CUDA</span>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Whisper Speech Engine:</span>
              <span style="color:#6ee7b7; font-weight:600;">OpenAI Whisper (Python)</span>
            </div>
          </div>
        </div>

        <!-- Database & Engine Details -->
        <div class="glass-card" style="padding:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff; display:flex; align-items:center; gap:8px;">
            <span>🗄️</span> MySQL Database & Server Environment
          </h2>
          <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">MySQL Version:</span>
              <code><?= htmlspecialchars($dbVersion) ?></code>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Active Tables:</span>
              <span><?= $tableCount ?> tables</span>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">PHP Memory Limit:</span>
              <code><?= ini_get('memory_limit') ?></code>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">Max Execution Time:</span>
              <code><?= ini_get('max_execution_time') ?>s</code>
            </div>
          </div>
        </div>
      </div>

      <!-- Storage & Transcoder Row -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
        <!-- Disk Usage -->
        <div class="glass-card" style="padding:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff; display:flex; align-items:center; gap:8px;">
            <span>💽</span> Local Storage Partition
          </h2>
          <?php if ($diskTotal !== false): ?>
            <div style="margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted); margin-bottom:6px;">
                <span><?= format_bytes($diskUsed) ?> Used</span>
                <span><?= format_bytes($diskTotal) ?> Total (<?= format_bytes($diskFree) ?> Free)</span>
              </div>
              <div class="quota-progress" style="height:10px; border-radius:5px;">
                <div class="quota-progress-bar" style="width:<?= $diskPct ?>%; background:<?= $diskPct > 85 ? '#ef4444' : '#6366f1' ?>;"></div>
              </div>
            </div>
          <?php endif; ?>
          <div style="font-size:12px; color:var(--text-muted); margin-top:10px;">
            Location: <code><?= htmlspecialchars(__DIR__) ?></code>
          </div>
        </div>

        <!-- FFmpeg & Transcoding -->
        <div class="glass-card" style="padding:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff; display:flex; align-items:center; gap:8px;">
            <span>🎞️</span> Video Transcoder & FFmpeg
          </h2>
          <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
              <span style="color:var(--text-muted);">FFmpeg Status:</span>
              <span style="color:<?= $ffmpegStatus['installed'] ? '#6ee7b7' : '#f87171' ?>; font-weight:700;">
                <?= $ffmpegStatus['installed'] ? '● AVAILABLE' : '○ MISSING IN PATH' ?>
              </span>
            </div>
            <div style="font-size:11px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis;">
              <?= htmlspecialchars($ffmpegStatus['version']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
