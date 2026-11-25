<?php
// admin/ai-settings.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/services/GeminiService.php';

$admin = AdminMiddleware::requirePermission('ai.config');

$testResult = null;

// Handle Test Connection AJAX or Form
if (isset($_GET['test_worker'])) {
    header('Content-Type: application/json');
    $start = microtime(true);
    $health = AiWorkerClient::checkHealth();
    $latencyMs = round((microtime(true) - $start) * 1000, 2);
    echo json_encode([
        'online' => $health['online'],
        'status_code' => $health['status_code'] ?? 0,
        'latency_ms' => $latencyMs,
        'worker_url' => $health['worker_url'] ?? '',
        'data' => $health['data'] ?? null,
        'error' => $health['error'] ?? null
    ]);
    exit;
}

if (isset($_GET['test_gemini'])) {
    header('Content-Type: application/json');
    $apiKey = SystemSetting::get('gemini_api_key', '');
    $model = SystemSetting::get('gemini_model', 'gemini-1.5-flash');
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Gemini API key is not configured.']);
        exit;
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}?key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($code === 200) {
        $json = json_decode($resp, true);
        echo json_encode([
            'success' => true,
            'model' => $model,
            'display_name' => $json['displayName'] ?? $model,
            'input_token_limit' => $json['inputTokenLimit'] ?? 'N/A'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status_code' => $code,
            'error' => $curlErr ?: "Gemini API returned HTTP {$code}: {$resp}"
        ]);
    }
    exit;
}

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $settings = [
        'gemini_model' => trim($_POST['gemini_model'] ?? 'gemini-1.5-flash'),
        'whisper_model' => trim($_POST['whisper_model'] ?? 'base'),
        'cloud_worker_url' => trim($_POST['cloud_worker_url'] ?? 'http://127.0.0.1:8000'),
        'ai_worker_endpoint' => trim($_POST['cloud_worker_url'] ?? 'http://127.0.0.1:8000'),
        'ai_worker_auth_token' => trim($_POST['ai_worker_auth_token'] ?? ''),
        'redis_url' => trim($_POST['redis_url'] ?? 'redis://127.0.0.1:6379/0'),
        'default_clip_quantity' => (string)max(1, (int)($_POST['default_clip_quantity'] ?? 10)),
        'max_clip_quantity' => (string)max(1, (int)($_POST['max_clip_quantity'] ?? 40)),
        'max_concurrent_jobs' => (string)max(1, (int)($_POST['max_concurrent_jobs'] ?? 3)),
        'job_timeout_seconds' => (string)max(60, (int)($_POST['job_timeout_seconds'] ?? 1800)),
    ];

    // Only update Gemini API Key if user typed something new (to avoid clearing masked values)
    if (!empty($_POST['gemini_api_key']) && !str_starts_with($_POST['gemini_api_key'], '••••')) {
        $settings['gemini_api_key'] = trim($_POST['gemini_api_key']);
    }

    foreach ($settings as $k => $v) {
        SystemSetting::set($k, $v, 'ai');
    }

    Admin::logAction($admin['id'], 'ai_settings.update', 'Updated AI pipeline & worker configuration');
    flash('success', 'AI and API settings updated successfully.');
    redirect('admin/ai-settings.php');
}

$activeWorkerUrl = SystemSetting::get('cloud_worker_url', 'http://127.0.0.1:8000');
$geminiApiKey = SystemSetting::get('gemini_api_key', '');
$maskedGeminiKey = !empty($geminiApiKey) ? '••••••••••••••••' . substr($geminiApiKey, -4) : '';
$geminiModel = SystemSetting::get('gemini_model', 'gemini-1.5-flash');
$whisperModel = SystemSetting::get('whisper_model', 'base');
$workerToken = SystemSetting::get('ai_worker_auth_token', 'worker-secret-token-key-2026');
$redisUrl = SystemSetting::get('redis_url', 'redis://127.0.0.1:6379/0');
$defaultClips = SystemSetting::get('default_clip_quantity', '10');
$maxClips = SystemSetting::get('max_clip_quantity', '40');
$maxConcurrent = SystemSetting::get('max_concurrent_jobs', '3');
$timeoutSeconds = SystemSetting::get('job_timeout_seconds', '1800');

$activeNav = 'ai-settings';
$pageTitle = 'AI & Worker Configuration';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">AI & API Configuration</span>
      </div>
    
      <div class="topbar-actions">
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">AI Engine & Model Settings</h1>
          <p class="page-desc">Configure FastAPI GPU worker endpoints, Whisper speech recognition, Google Gemini models, and pipeline limits.</p>
        </div>
      </div>

      <!-- Live Connectivity Test Cards -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">
        <!-- Worker Card -->
        <div class="glass-card" style="padding:20px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div style="font-weight:700; font-size:15px; color:#fff;">🖥️ Python FastAPI GPU Worker</div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnTestWorker" onclick="testWorker()">
              ⚡ Test Worker Ping
            </button>
          </div>
          <p style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">
            Target: <code><?= htmlspecialchars($activeWorkerUrl) ?>/api/v1/health</code>
          </p>
          <div id="workerTestStatus" style="font-size:13px; color:var(--text-muted); padding:10px; background:rgba(0,0,0,0.25); border-radius:8px; border:1px solid rgba(255,255,255,0.06);">
            Click "Test Worker Ping" to check live availability.
          </div>
        </div>

        <!-- Gemini Card -->
        <div class="glass-card" style="padding:20px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div style="font-weight:700; font-size:15px; color:#fff;">✨ Google Gemini AI</div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnTestGemini" onclick="testGemini()">
              ⚡ Test Gemini API
            </button>
          </div>
          <p style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">
            Model: <code><?= htmlspecialchars($geminiModel) ?></code>
          </p>
          <div id="geminiTestStatus" style="font-size:13px; color:var(--text-muted); padding:10px; background:rgba(0,0,0,0.25); border-radius:8px; border:1px solid rgba(255,255,255,0.06);">
            Click "Test Gemini API" to verify Google AI key.
          </div>
        </div>
      </div>

      <!-- Settings Form -->
      <form method="POST">
        <?= csrf_field() ?>

        <div class="glass-card" style="padding:28px; margin-bottom:24px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:20px; color:#fff; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:10px;">
            GPU Worker & Processing Architecture
          </h2>

          <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px;" class="mb-4">
            <div>
              <label class="form-label">FastAPI Worker Endpoint URL</label>
              <input type="url" name="cloud_worker_url" class="form-control" value="<?= htmlspecialchars($activeWorkerUrl) ?>" required placeholder="http://127.0.0.1:8000">
              <span style="font-size:11px; color:var(--text-muted);">Remote Python worker handling FFmpeg NVENC and AI clipping.</span>
            </div>
            <div>
              <label class="form-label">Worker Bearer Secret Token</label>
              <input type="text" name="ai_worker_auth_token" class="form-control" value="<?= htmlspecialchars($workerToken) ?>" placeholder="worker-secret-token">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;" class="mb-3">
            <div>
              <label class="form-label">Whisper STT Model</label>
              <select name="whisper_model" class="form-control">
                <option value="tiny" <?= $whisperModel === 'tiny' ? 'selected' : '' ?>>tiny (Fastest, ~1GB VRAM)</option>
                <option value="base" <?= $whisperModel === 'base' ? 'selected' : '' ?>>base (Balanced, Standard ~2GB VRAM)</option>
                <option value="small" <?= $whisperModel === 'small' ? 'selected' : '' ?>>small (Higher Accuracy, ~4GB VRAM)</option>
                <option value="medium" <?= $whisperModel === 'medium' ? 'selected' : '' ?>>medium (High Accuracy, ~8GB VRAM)</option>
              </select>
            </div>
            <div>
              <label class="form-label">Max Concurrent Jobs</label>
              <input type="number" name="max_concurrent_jobs" class="form-control" value="<?= htmlspecialchars($maxConcurrent) ?>" min="1" max="10">
            </div>
            <div>
              <label class="form-label">Job Timeout (Seconds)</label>
              <input type="number" name="job_timeout_seconds" class="form-control" value="<?= htmlspecialchars($timeoutSeconds) ?>" min="300" max="7200">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
            <div>
              <label class="form-label">Default Target Clips</label>
              <input type="number" name="default_clip_quantity" class="form-control" value="<?= htmlspecialchars($defaultClips) ?>" min="1" max="50">
            </div>
            <div>
              <label class="form-label">Max Allowed Clips / Job</label>
              <input type="number" name="max_clip_quantity" class="form-control" value="<?= htmlspecialchars($maxClips) ?>" min="5" max="100">
            </div>
            <div>
              <label class="form-label">Redis Queue URL</label>
              <input type="text" name="redis_url" class="form-control" value="<?= htmlspecialchars($redisUrl) ?>" placeholder="redis://127.0.0.1:6379/0">
            </div>
          </div>
        </div>

        <div class="glass-card" style="padding:28px; margin-bottom:28px;">
          <h2 style="font-size:16px; font-weight:700; margin-bottom:20px; color:#fff; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:10px;">
            Google Gemini API Intelligence
          </h2>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
              <label class="form-label">Gemini Model Designation</label>
              <select name="gemini_model" class="form-control">
                <option value="gemini-1.5-flash" <?= $geminiModel === 'gemini-1.5-flash' ? 'selected' : '' ?>>gemini-1.5-flash (Fast & Efficient)</option>
                <option value="gemini-1.5-pro" <?= $geminiModel === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Complex Reasoning)</option>
                <option value="gemini-2.0-flash" <?= $geminiModel === 'gemini-2.0-flash' ? 'selected' : '' ?>>gemini-2.0-flash (Latest Generation)</option>
              </select>
              <span style="font-size:11px; color:var(--text-muted);">Used for AI viral peak scoring, title generation, and hook optimization.</span>
            </div>
            <div>
              <label class="form-label">Gemini API Key</label>
              <input type="password" name="gemini_api_key" class="form-control" placeholder="<?= $maskedGeminiKey ? htmlspecialchars($maskedGeminiKey) : 'Enter Google Gemini API Key' ?>">
              <span style="font-size:11px; color:var(--text-muted);"><?= $maskedGeminiKey ? 'Key is configured. Enter new value only to update.' : 'No key detected. Add key to enable Gemini features.' ?></span>
            </div>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" class="btn btn-primary" style="padding:12px 28px; font-weight:700;">
            💾 Save AI & API Configuration
          </button>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
async function testWorker() {
  const btn = document.getElementById('btnTestWorker');
  const box = document.getElementById('workerTestStatus');
  btn.disabled = true;
  box.innerHTML = '<span style="color:#a5b4fc;">Pinging FastAPI worker node...</span>';

  try {
    const res = await fetch('?test_worker=1');
    const data = await res.json();
    if (data.online) {
      box.innerHTML = `<span style="color:#6ee7b7; font-weight:700;">✓ ONLINE (HTTP ${data.status_code})</span> - Latency: <strong>${data.latency_ms} ms</strong><br><span style="font-size:11px; color:var(--text-muted);">Worker: ${data.worker_url}</span>`;
    } else {
      box.innerHTML = `<span style="color:#f87171; font-weight:700;">✗ OFFLINE (${data.error || 'Connection Failed'})</span>`;
    }
  } catch (err) {
    box.innerHTML = `<span style="color:#f87171; font-weight:700;">✗ Error testing worker: ${err.message}</span>`;
  } finally {
    btn.disabled = false;
  }
}

async function testGemini() {
  const btn = document.getElementById('btnTestGemini');
  const box = document.getElementById('geminiTestStatus');
  btn.disabled = true;
  box.innerHTML = '<span style="color:#a5b4fc;">Contacting Google Gemini API...</span>';

  try {
    const res = await fetch('?test_gemini=1');
    const data = await res.json();
    if (data.success) {
      box.innerHTML = `<span style="color:#6ee7b7; font-weight:700;">✓ CONNECTED: ${data.display_name}</span><br><span style="font-size:11px; color:var(--text-muted);">Input Token Limit: ${data.input_token_limit}</span>`;
    } else {
      box.innerHTML = `<span style="color:#f87171; font-weight:700;">✗ FAILED: ${data.error || 'Unauthorized'}</span>`;
    }
  } catch (err) {
    box.innerHTML = `<span style="color:#f87171; font-weight:700;">✗ Error contacting Gemini: ${err.message}</span>`;
  } finally {
    btn.disabled = false;
  }
}
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
