<?php
// admin/costs.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/CostService.php';

$admin = AdminMiddleware::requirePermission('finance.manage');

$streamHours = (float)($_GET['hours'] ?? 2.0);
$clipCount = (int)($_GET['clips'] ?? 10);
$gpuType = $_GET['gpu'] ?? 'rtx4090';

$estimate = CostService::estimateJobCost($streamHours * 3600, $clipCount, $gpuType);
$monthlyTotals = CostService::getMonthlyTotals();
$recentCostRecords = CostService::getRecentRecords(15);
$userCostBreakdown = CostService::getUserCostBreakdown(8);

$activeNav = 'costs';
$pageTitle = 'Financial & Cloud Cost Control';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Financial & Cloud GPU Costs</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-green">Monthly Cloud Spend: $<?= number_format((float)$monthlyTotals['total_cost_usd'], 2) ?></span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Financial Control & GPU Cost Analytics</h1>
          <p class="page-desc">Track real-time compute expenditures across worker instances, estimate job profitability, and audit user costs.</p>
        </div>
      </div>

      <!-- Financial KPI Cards -->
      <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon purple">💰</div>
          <div>
            <div class="stat-val">$<?= number_format((float)$monthlyTotals['total_cost_usd'], 2) ?></div>
            <div class="stat-lbl">30-Day Total Compute Spend</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon cyan">⏱️</div>
          <div>
            <div class="stat-val"><?= round((float)$monthlyTotals['total_gpu_seconds'] / 60, 1) ?> min</div>
            <div class="stat-lbl">Total GPU Time (30 Days)</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">⚡</div>
          <div>
            <div class="stat-val"><?= (int)$monthlyTotals['total_jobs'] ?></div>
            <div class="stat-lbl">Processed Jobs Recorded</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon amber">📈</div>
          <div>
            <div class="stat-val"><?= $estimate['gross_margin_percent'] ?>%</div>
            <div class="stat-lbl">Standard Gross Margin</div>
          </div>
        </div>
      </div>

      <!-- Cost Simulator Section -->
      <div class="glass-card" style="padding:24px; margin-bottom:28px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Interactive Cloud GPU Cost & Pricing Simulator</h2>
        <form method="GET" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)) 120px; gap:16px; align-items:flex-end;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Stream Duration (Hours)</label>
            <input type="number" step="0.5" name="hours" class="form-control" value="<?= $streamHours ?>" min="0.5" max="24">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Clips to Extract</label>
            <select name="clips" class="form-control">
              <option value="10" <?= $clipCount === 10 ? 'selected' : '' ?>>10 Viral Shorts</option>
              <option value="20" <?= $clipCount === 20 ? 'selected' : '' ?>>20 Viral Shorts</option>
              <option value="30" <?= $clipCount === 30 ? 'selected' : '' ?>>30 Viral Shorts</option>
              <option value="40" <?= $clipCount === 40 ? 'selected' : '' ?>>40 Viral Shorts</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Cloud GPU Instance</label>
            <select name="gpu" class="form-control">
              <option value="rtx3050" <?= $gpuType === 'rtx3050' ? 'selected' : '' ?>>RTX 3050 ($0.20/hr)</option>
              <option value="rtx4090" <?= $gpuType === 'rtx4090' ? 'selected' : '' ?>>NVIDIA RTX 4090 ($0.45/hr)</option>
              <option value="a10g" <?= $gpuType === 'a10g' ? 'selected' : '' ?>>AWS EC2 G5 / A10G ($1.00/hr)</option>
              <option value="a100_80gb" <?= $gpuType === 'a100_80gb' ? 'selected' : '' ?>>NVIDIA A100 80GB ($2.20/hr)</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="height:44px;">
            Simulate
          </button>
        </form>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
          <div style="background:rgba(0,0,0,0.25); padding:16px; border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:12px; color:var(--text-muted); margin-bottom:4px;">Compute Time & Resource Cost</div>
            <div style="font-size:18px; font-weight:800; color:#f87171;">$<?= number_format($estimate['total_cost_usd'], 4) ?></div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
              Compute: <?= round($estimate['estimated_compute_seconds'] / 60, 1) ?> mins | Storage: $<?= number_format($estimate['storage_cost_usd'], 4) ?>
            </div>
          </div>

          <div style="background:rgba(0,0,0,0.25); padding:16px; border-radius:10px; border:1px solid rgba(16,185,129,0.2);">
            <div style="font-size:12px; color:#6ee7b7; margin-bottom:4px;">Suggested Retail Price (<?= $estimate['gross_margin_percent'] ?>% Gross Margin)</div>
            <div style="font-size:18px; font-weight:800; color:#6ee7b7;">$<?= number_format($estimate['suggested_retail_price_usd'], 2) ?></div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
              Net Estimated Margin: $<?= number_format($estimate['suggested_retail_price_usd'] - $estimate['total_cost_usd'], 2) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Real Cost Breakdown Tables -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
        <!-- Top Cost Users -->
        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.08);">
            <h3 style="font-size:15px; font-weight:700; color:#fff;">Top Compute Users</h3>
          </div>
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Creator</th>
                  <th>Jobs</th>
                  <th>GPU Time</th>
                  <th>Cost (USD)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($userCostBreakdown)): ?>
                  <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No user compute recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($userCostBreakdown as $uc): ?>
                    <tr>
                      <td style="font-weight:600; color:#fff;"><?= htmlspecialchars($uc['email']) ?></td>
                      <td><?= (int)$uc['job_count'] ?></td>
                      <td style="color:var(--text-muted);"><?= round((float)$uc['total_gpu_seconds'] / 60, 1) ?>m</td>
                      <td style="color:#f87171; font-weight:700;">$<?= number_format((float)$uc['total_cost_usd'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Job Cost Log -->
        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.08);">
            <h3 style="font-size:15px; font-weight:700; color:#fff;">Recent Compute Invocations</h3>
          </div>
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Job ID</th>
                  <th>Worker</th>
                  <th>Compute Sec</th>
                  <th>Cost</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentCostRecords)): ?>
                  <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No recent cost events logged.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentCostRecords as $rc): ?>
                    <tr>
                      <td><code style="font-size:11px; color:var(--secondary);"><?= htmlspecialchars(substr($rc['job_id'] ?? $rc['id'], 0, 8)) ?>...</code></td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($rc['worker_type'] ?? 'GPU') ?></span></td>
                      <td style="color:var(--text-muted);"><?= (float)($rc['gpu_seconds'] ?? $rc['compute_duration_seconds']) ?>s</td>
                      <td style="color:#f87171; font-weight:700;">$<?= number_format((float)($rc['actual_cost_usd'] ?? $rc['total_cost_usd']), 4) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
