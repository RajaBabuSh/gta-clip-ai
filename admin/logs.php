<?php
// admin/logs.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';

$admin = AdminMiddleware::requirePermission('logs.view');

$filterAction = !empty($_GET['action']) ? trim($_GET['action']) : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$logs = Admin::getAuditLogs($perPage, $offset, $filterAction);

$pdo = Database::getConnection();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$activeNav = 'logs';
$pageTitle = 'Security & Audit Logs';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Security Audit Trail</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('admin/logs.php') ?>" class="btn btn-secondary btn-sm">⟳ Refresh Logs</a>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Security & Administrative Audit Logs</h1>
          <p class="page-desc">Comprehensive audit history recording logins, role adjustments, configuration modifications, and jobs.</p>
        </div>
      </div>

      <!-- Action Filter Bar -->
      <div class="glass-card" style="padding:16px; margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:12px; align-items:center;">
          <select name="action" class="form-control" style="width:280px;">
            <option value="">All Administrative Actions</option>
            <?php foreach ($actions as $act): ?>
              <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                <?= htmlspecialchars($act) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary" style="padding:8px 16px;">Filter Action</button>
          <?php if (!empty($filterAction)): ?>
            <a href="<?= url('admin/logs.php') ?>" class="btn btn-secondary" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Log Table -->
      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Actor</th>
                <th>Action Category</th>
                <th>IP Address</th>
                <th>Details & Payload</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align:center; padding:36px; color:var(--text-muted);">No matching audit events recorded.</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td style="color:var(--text-muted); font-size:12px; font-family:monospace; white-space:nowrap;">
                      <?= htmlspecialchars($l['created_at']) ?>
                    </td>
                    <td>
                      <?php if (!empty($l['admin_username'])): ?>
                        <span class="badge badge-red" style="font-size:11px;">Admin: <?= htmlspecialchars($l['admin_username']) ?></span>
                      <?php elseif (!empty($l['user_email'])): ?>
                        <span class="badge badge-purple" style="font-size:11px;">User: <?= htmlspecialchars($l['user_email']) ?></span>
                      <?php else: ?>
                        <span style="color:var(--text-dim); font-size:12px;">System Automation</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <code style="font-weight:700; color:#a5b4fc; font-size:12px;">
                        <?= htmlspecialchars($l['action']) ?>
                      </code>
                    </td>
                    <td style="font-family:monospace; font-size:12px; color:var(--text-muted);">
                      <?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?>
                    </td>
                    <td style="font-size:12px; color:#cbd5e1; max-width:320px; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars($l['details'] ?? '') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
