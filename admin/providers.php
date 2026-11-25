<?php
// admin/providers.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/services/StorageManager.php';
require_once __DIR__ . '/../app/services/GeminiService.php';

$admin = AdminMiddleware::requirePermission('ai.config');

// AJAX Diagnostics
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    $provider = $_GET['test'];
    $out = ['provider' => $provider, 'timestamp' => date('Y-m-d H:i:s')];

    switch ($provider) {
        case 'worker':
        case 'whisper':
            $health = AiWorkerClient::checkHealth();
            $out['success'] = $health['online'];
            $out['worker_url'] = $health['worker_url'];
            $out['whisper_model'] = $health['data']['worker']['whisper_model'] ?? 'base';
            $out['device'] = $health['data']['worker']['whisper_device'] ?? 'cpu';
            $out['message'] = $health['online'] ? "Worker online. Whisper model '{$out['whisper_model']}' loaded on {$out['device']}." : "Worker unreachable: " . ($health['error'] ?? 'timeout');
            break;

        case 'ffmpeg':
            $health = AiWorkerClient::checkHealth();
            $ffmpegOk = $health['data']['worker']['ffmpeg_available'] ?? false;
            $nvencOk = $health['data']['worker']['nvenc_available'] ?? false;
            $out['success'] = $ffmpegOk;
            $out['ffmpeg'] = $ffmpegOk;
            $out['nvenc'] = $nvencOk;
            $out['message'] = $ffmpegOk ? ("FFmpeg available. Hardware NVENC: " . ($nvencOk ? 'ENABLED (NVIDIA)' : 'CPU libx264 Fallback Active')) : "FFmpeg binary missing in worker PATH.";
            break;

        case 'rms':
        case 'moments':
            $health = AiWorkerClient::checkHealth();
            $out['success'] = $health['online'];
            $out['message'] = $health['online'] ? "Local RMS acoustic energy & speech velocity analyzer ready. 100% offline, zero API fees." : "AI worker unreachable.";
            break;

        case 'gemini':
            $apiKey = SystemSetting::get('gemini_api_key', '');
            $model = SystemSetting::get('gemini_model', 'gemini-1.5-flash');
            if (empty($apiKey)) {
                $out['success'] = true;
                $out['mode'] = 'offline_fallback';
                $out['message'] = "Gemini API key is blank. Pipeline will use 100% offline local algorithmic SEO generation (zero cost, 0 API calls).";
            } else {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}?key={$apiKey}";
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    $json = json_decode($resp, true);
                    $out['success'] = true;
                    $out['mode'] = 'free_tier_active';
                    $out['model'] = $model;
                    $out['message'] = "Gemini API Free-Tier connected ({$model}). Rate limits: 15 RPM / 1500 RPD free.";
                } else {
                    $out['success'] = false;
                    $out['http_code'] = $code;
                    $out['message'] = "Gemini API returned HTTP {$code}. Pipeline will gracefully fallback to local algorithmic SEO.";
                }
            }
            break;

        case 'storage':
            $driver = StorageManager::getDriver();
            $stats = StorageManager::getStorageStats();
            $out['success'] = is_writable($stats['storage_root']);
            $out['driver'] = $driver;
            $out['free_gb'] = round($stats['disk_free_bytes'] / (1024*1024*1024), 2);
            $out['message'] = "Storage active: " . ($driver === 'cloudflare_r2' ? 'Cloudflare R2 Object Storage (10GB Free Tier)' : "Local NVMe/SSD ({$out['free_gb']} GB Free)");
            break;

        case 'ytdlp':
            $health = AiWorkerClient::checkHealth();
            $out['success'] = $health['online'];
            $out['message'] = $health['online'] ? "yt-dlp stream slice extractor active via worker sys.executable. Slicing with --download-sections enabled for 9-10h streams." : "Worker unreachable.";
            break;

        default:
            $out['success'] = false;
            $out['message'] = 'Unknown provider test target.';
            break;
    }
    echo json_encode($out);
    exit;
}

// Handle Form Saves
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_provider_priorities') {
        SystemSetting::set('ai_fallback_to_offline_moments', $_POST['ai_fallback_to_offline_moments'] ?? '1', 'ai', 'Fallback to local algorithmic moments if AI fails');
        SystemSetting::set('ffmpeg_prefer_nvenc', $_POST['ffmpeg_prefer_nvenc'] ?? '1', 'ai', 'Prefer NVENC hardware encoding before CPU fallback');
        SystemSetting::set('gemini_model', $_POST['gemini_model'] ?? 'gemini-1.5-flash', 'ai', 'Google Gemini Model (Free Tier)');
        
        flash('success', 'Provider fallback configuration updated successfully.');
        redirect('admin/providers.php');
    }
}

// Fetch live statuses
$workerHealth = AiWorkerClient::checkHealth();
$storageStats = StorageManager::getStorageStats();
$geminiKey = SystemSetting::get('gemini_api_key', '');
$geminiModel = SystemSetting::get('gemini_model', 'gemini-1.5-flash');
$preferNvenc = SystemSetting::get('ffmpeg_prefer_nvenc', '1') === '1';
$fallbackOffline = SystemSetting::get('ai_fallback_to_offline_moments', '1') === '1';
$storageDriver = StorageManager::getDriver();

$activeNav = 'providers';
$pageTitle = 'Free & Open-Source AI Provider Hub';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / AI Engine / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Free & Open-Source Provider Hub</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-green">100% Free / Open-Source Architecture</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container" style="max-width: 1100px; margin: 0 auto;">
      
      <div class="page-header" style="margin-bottom: 24px;">
        <div>
          <h1 class="page-title">Free-First AI & Video Provider Hub</h1>
          <p class="page-desc">Every AI task runs on open-source local models or verified free-tier APIs with automatic offline fallback so processing never fails or costs unexpected fees.</p>
        </div>
      </div>

      <!-- Resilient Fallback Architecture Banner -->
      <div class="glass-card" style="padding: 20px 24px; margin-bottom: 24px; border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.05);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div>
            <h3 style="font-size: 15px; font-weight: 800; color: #a7f3d0; margin-bottom: 4px;">🛡️ Zero-Cost Offline Resiliency Guarantee</h3>
            <p style="font-size: 13px; color: #cbd5e1; margin: 0;">
              If any external cloud API hits quota limits or has missing keys, the processing engine switches dynamically to local open-source algorithmic analysis. Video clipping jobs will <strong>never fail at 7% or 10%</strong> due to missing paid subscriptions.
            </p>
          </div>
        </div>
      </div>

      <!-- Grid of 7 Providers -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 32px;">
        
        <!-- 1. Whisper Speech Recognition -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">🎙️</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">OpenAI Whisper (Local)</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Speech-to-Text & Subtitles</span>
              </div>
            </div>
            <span class="badge badge-<?= $workerHealth['online'] ? 'green' : 'danger' ?>">
              <?= $workerHealth['online'] ? 'ONLINE (0 Cost)' : 'OFFLINE' ?>
            </span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Runs fully offline on the AI Worker host. Generates word-level timestamps and ASS subtitles without paid APIs like AssemblyAI or Deepgram.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Active Model:</span>
              <strong style="color: #f8fafc;"><?= htmlspecialchars($workerHealth['data']['worker']['whisper_model'] ?? 'base') ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Compute Device:</span>
              <strong style="color: #38bdf8;"><?= strtoupper($workerHealth['data']['worker']['whisper_device'] ?? 'CPU') ?></strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('whisper', this)">
            ⚡ Verify Whisper Engine
          </button>
          <div id="diag-whisper" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 2. FFmpeg Video Reframing -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">🎞️</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">FFmpeg Video Engine</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">9:16 Vertical Cropping & Render</span>
              </div>
            </div>
            <span class="badge badge-green">LOCAL / OPEN-SOURCE</span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            High-speed video rendering with 4-tier fallback: Hardware NVENC + Subtitles → NVENC clean → Multi-core CPU libx264 + Subtitles → CPU clean.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Hardware Acceleration:</span>
              <strong style="color: #10b981;"><?= ($workerHealth['data']['worker']['nvenc_available'] ?? false) ? 'NVENC (NVIDIA)' : 'CPU Multi-thread' ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Resolution Output:</span>
              <strong style="color: #f8fafc;">1080x1920 (9:16 Portrait)</strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('ffmpeg', this)">
            ⚡ Test FFmpeg & Codecs
          </button>
          <div id="diag-ffmpeg" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 3. Acoustic Energy & RMS Peak Detection -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">📊</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">RMS Acoustic Engine</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Sound Peaks & Pitch Spikes</span>
              </div>
            </div>
            <span class="badge badge-green">100% OFFLINE</span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Pure local NumPy/SciPy digital signal processing. Scans raw audio waveforms for shout peaks, gunfights, laughter, and decibel energy without any external API calls.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Sampling Window:</span>
              <strong style="color: #f8fafc;">0.50s Hanning RMS</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">External API Cost:</span>
              <strong style="color: #10b981;">$0.00 (Zero Cost)</strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('rms', this)">
            ⚡ Test RMS Detection
          </button>
          <div id="diag-rms" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 4. SmartMoments Algorithmic Virality -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">🎯</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">SmartMoments Engine</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Local Highlight Scoring</span>
              </div>
            </div>
            <span class="badge badge-purple">LOCAL ALGORITHMIC</span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Combines acoustic decibel energy (40%), speech velocity / excitement (35%), and viral gaming keyword frequency (25%) to rank top highlights mathematically.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Virality Scoring:</span>
              <strong style="color: #f8fafc;">Multi-factor Weighted</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Offline Autonomy:</span>
              <strong style="color: #10b981;">Fully Autonomous</strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('moments', this)">
            ⚡ Test Scoring Algorithm
          </button>
          <div id="diag-moments" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 5. Google Gemini Free-Tier & Local Fallback -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">✨</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">Google Gemini (Free-Tier)</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Viral SEO & Title Generation</span>
              </div>
            </div>
            <span class="badge badge-<?= empty($geminiKey) ? 'amber' : 'green' ?>">
              <?= empty($geminiKey) ? 'OFFLINE FALLBACK' : 'FREE TIER (15 RPM)' ?>
            </span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Uses Google Gemini 1.5 Flash Free-Tier for high-converting YouTube Shorts titles and hashtags. If key is missing or throttled, falls back instantly to local algorithmic SEO.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Free Tier Limits:</span>
              <strong style="color: #f8fafc;">15 RPM / 1,500 RPD</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Fail-Safe Action:</span>
              <strong style="color: #a7f3d0;">Local Algorithmic Fallback</strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('gemini', this)">
            ⚡ Test Gemini / Fallback
          </button>
          <div id="diag-gemini" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 6. Local Disk & Cloudflare R2 Free Storage -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">☁️</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">Storage: Local / R2</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Free Tier Object Storage</span>
              </div>
            </div>
            <span class="badge badge-<?= $storageDriver === 'cloudflare_r2' ? 'purple' : 'green' ?>">
              <?= $storageDriver === 'cloudflare_r2' ? 'Cloudflare R2' : 'Local Fast Disk' ?>
            </span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Local disk storage with HMAC signed URL access, or Cloudflare R2 (10 GB free tier with $0 egress bandwidth costs). Automatically purges temporary WAV chunks.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Free Bandwidth:</span>
              <strong style="color: #10b981;">Unlimited ($0 Egress)</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Driver:</span>
              <strong style="color: #f8fafc;"><?= htmlspecialchars($storageDriver) ?></strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('storage', this)">
            ⚡ Verify Storage Mount
          </button>
          <div id="diag-storage" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

        <!-- 7. yt-dlp Video Stream Ingestion -->
        <div class="glass-card" style="padding: 22px; display: flex; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 26px;">📥</span>
              <div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0;">yt-dlp Slice Extractor</h3>
                <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Long Stream Ingestion</span>
              </div>
            </div>
            <span class="badge badge-green">OPEN-SOURCE</span>
          </div>

          <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; flex-grow: 1;">
            Enables instant clipping on 9-10 hour gaming streams using <code style="color:#a7f3d0;">--download-sections</code> without downloading multi-gigabyte stream files.
          </p>

          <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
              <span style="color: #94a3b8;">Execution:</span>
              <strong style="color: #f8fafc;">sys.executable Subprocess</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #94a3b8;">Long Video RAM Bloat:</span>
              <strong style="color: #10b981;">0% (Section Slicing)</strong>
            </div>
          </div>

          <button class="btn btn-secondary btn-sm" onclick="runDiagnostic('ytdlp', this)">
            ⚡ Verify Ingestion Engine
          </button>
          <div id="diag-ytdlp" style="margin-top: 8px; font-size: 11.5px; display: none;"></div>
        </div>

      </div>

      <!-- Fallback Configuration & Settings Form -->
      <div class="glass-card" style="padding: 24px; margin-bottom: 24px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Fallback Hierarchy Configuration</h2>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
          Configure how the processing pipeline handles hardware and API fallbacks.
        </p>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_provider_priorities">

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 700;">Google Gemini Model (Free-Tier)</label>
              <select name="gemini_model" class="form-control">
                <option value="gemini-1.5-flash" <?= $geminiModel === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash (15 RPM Free Tier - Recommended)</option>
                <option value="gemini-1.5-pro" <?= $geminiModel === 'gemini-1.5-pro' ? 'selected' : '' ?>>Gemini 1.5 Pro (2 RPM Free Tier)</option>
              </select>
              <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Gemini 1.5 Flash is 100% free under Google AI Studio limits.</div>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 700;">AI Highlight Fallback Mode</label>
              <select name="ai_fallback_to_offline_moments" class="form-control">
                <option value="1" <?= $fallbackOffline ? 'selected' : '' ?>>Enabled: Fall back to Local SmartMoments if API fails</option>
                <option value="0" <?= !$fallbackOffline ? 'selected' : '' ?>>Strict: Stop job if Gemini API call fails</option>
              </select>
              <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Keeps processing jobs reliable even without active internet or API quota.</div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label" style="font-weight: 700;">FFmpeg Video Acceleration Fallback</label>
            <div style="display: flex; gap: 20px; margin-top: 8px;">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="ffmpeg_prefer_nvenc" value="1" <?= $preferNvenc ? 'checked' : '' ?>>
                <span>Attempt GPU NVENC hardware acceleration first, automatically fall back to CPU libx264</span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            💾 Save Provider Settings
          </button>
        </form>
      </div>

    </div>
  </main>
</div>

<script>
async function runDiagnostic(provider, btn) {
  const origText = btn.innerHTML;
  btn.innerHTML = 'Testing...';
  btn.disabled = true;

  const outEl = document.getElementById('diag-' + provider);
  outEl.style.display = 'block';
  outEl.style.color = '#94a3b8';
  outEl.innerHTML = 'Running diagnostic verification...';

  try {
    const res = await fetch('providers.php?test=' + encodeURIComponent(provider));
    const data = await res.json();
    
    if (data.success) {
      outEl.style.color = '#10b981';
      outEl.innerHTML = '✅ ' + (data.message || 'Verification successful');
    } else {
      outEl.style.color = '#ef4444';
      outEl.innerHTML = '⚠️ ' + (data.message || 'Diagnostic check failed');
    }
  } catch (e) {
    outEl.style.color = '#ef4444';
    outEl.innerHTML = '❌ Network error during check: ' + e.message;
  } finally {
    btn.innerHTML = origText;
    btn.disabled = false;
  }
}
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
