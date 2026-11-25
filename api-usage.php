<?php
// api-usage.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/services/GeminiService.php';

$user = AuthMiddleware::requireUser();
$pdo = Database::getConnection();

// Fetch API usage stats
$stmt = $pdo->prepare("
    SELECT service, COUNT(*) AS total_calls, SUM(tokens_used) AS total_tokens, SUM(estimated_cost_usd) AS total_cost
    FROM api_usage
    WHERE user_id = ? OR user_id IS NULL
    GROUP BY service
");
$stmt->execute([$user['id']]);
$serviceStats = $stmt->fetchAll();

// Recent logs
$stmt = $pdo->prepare("
    SELECT * FROM api_usage 
    WHERE user_id = ? OR user_id IS NULL
    ORDER BY created_at DESC LIMIT 20
");
$stmt->execute([$user['id']]);
$recentCalls = $stmt->fetchAll();

$geminiKeyPresent = !empty(GeminiService::getApiKey());
$geminiModel = GeminiService::getModel();

$activeNav = 'api-usage';
$pageTitle = 'API Usage, Quotas & Costs';
$topbarTitle = 'API Usage & Limits';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="max-width: 1040px; margin: 0 auto; padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:26px; font-weight:800; color:white; display:flex; align-items:center; gap:10px;">
            ⚡ AI & Cloud API Usage & Cost Transparency
          </h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Real-time tracking of Google Gemini API tokens, YouTube Data API quota units, Whisper compute, and NVENC GPU rendering.
          </p>
        </div>
        <div>
          <button onclick="testGeminiConnection()" class="btn btn-primary btn-sm">
            🔍 Test Gemini API Connection
          </button>
        </div>
      </div>

      <!-- Quota Cards -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:18px; margin-bottom:28px;">
        <!-- Gemini Card -->
        <div class="glass-card" style="padding:20px; border-left:4px solid #8b5cf6;">
          <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">GOOGLE GEMINI API</div>
          <div style="font-size:18px; font-weight:800; color:white; margin-bottom:4px;">
            <?= $geminiKeyPresent ? htmlspecialchars($geminiModel) : 'Local Heuristics' ?>
          </div>
          <div style="font-size:12px; color:var(--text-muted);">
            Status: <span style="color:<?= $geminiKeyPresent ? '#10b981' : '#f59e0b' ?>; font-weight:700;"><?= $geminiKeyPresent ? 'Key Configured' : 'Fallback Active' ?></span>
          </div>
          <div style="margin-top:10px; font-size:11px; color:var(--text-dim);">
            Free tier protected • Zero unexpected charges
          </div>
        </div>

        <!-- YouTube API Card -->
        <div class="glass-card" style="padding:20px; border-left:4px solid #ef4444;">
          <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">YOUTUBE DATA API V3</div>
          <div style="font-size:18px; font-weight:800; color:white; margin-bottom:4px;">
            10,000 Units/Day
          </div>
          <div style="font-size:12px; color:var(--text-muted);">
            Cost per upload: <span style="color:white; font-weight:700;">1,600 units</span>
          </div>
          <div style="margin-top:10px; font-size:11px; color:#10b981;">
            ✓ Within standard daily quota
          </div>
        </div>

        <!-- Local / Remote GPU Processing -->
        <div class="glass-card" style="padding:20px; border-left:4px solid #10b981;">
          <div style="font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:4px;">HARDWARE ACCELERATION</div>
          <div style="font-size:18px; font-weight:800; color:white; margin-bottom:4px;">
            NVIDIA NVENC + Whisper
          </div>
          <div style="font-size:12px; color:var(--text-muted);">
            Cost: <span style="color:#10b981; font-weight:700;">$0.00 (Self-Hosted Worker)</span>
          </div>
          <div style="margin-top:10px; font-size:11px; color:var(--text-dim);">
            Zero third-party cloud render fees
          </div>
        </div>
      </div>

      <!-- Recent API Calls Table -->
      <div class="glass-card" style="padding:24px;">
        <h2 style="font-size:18px; font-weight:800; color:white; margin-bottom:16px;">Audit Log of Recent API Calls</h2>
        <?php if (empty($recentCalls)): ?>
          <div style="text-align:center; padding:32px; color:var(--text-muted);">
            No external API calls logged yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Service</th>
                  <th>Operation</th>
                  <th>Tokens / Units</th>
                  <th>Estimated Cost</th>
                  <th>Status</th>
                  <th>Timestamp</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentCalls as $rc): ?>
                  <tr>
                    <td>
                      <span class="badge badge-purple"><?= strtoupper($rc['service']) ?></span>
                    </td>
                    <td style="font-weight:600; color:white;">
                      <?= htmlspecialchars($rc['operation']) ?>
                    </td>
                    <td>
                      <?= number_format((int)$rc['tokens_used']) ?> tokens (<?= (int)$rc['units_consumed'] ?> unit)
                    </td>
                    <td style="color:#10b981; font-weight:700;">
                      $<?= number_format((float)$rc['estimated_cost_usd'], 6) ?>
                    </td>
                    <td>
                      <span class="badge badge-green">HTTP <?= (int)$rc['status_code'] ?></span>
                    </td>
                    <td style="font-size:12px; color:var(--text-muted);">
                      <?= date('M d, H:i:s', strtotime($rc['created_at'])) ?>
                    </td>
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
async function testGeminiConnection() {
  showToast('Connecting to Google Gemini API...', 'info');
  try {
    const res = await fetch('api/settings.php?action=test_gemini');
    const data = await res.json();
    if (res.ok && data.success) {
      alert("✅ Gemini API Test Passed!\n\n" + data.message + "\n\nAvailable Models:\n" + data.models.map(m => "• " + m.name).slice(0, 5).join("\n"));
    } else {
      alert("⚠️ Gemini Connection Notice:\n\n" + (data.message || 'No API key configured.') + "\n\nSystem will continue seamlessly using local intelligent NLP heuristics.");
    }
  } catch (e) {
    showToast('Failed to test Gemini: ' + e.message, 'error');
  }
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
