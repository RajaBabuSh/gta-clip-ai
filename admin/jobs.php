<?php
// admin/jobs.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Job.php';

$admin = AdminMiddleware::requirePermission('jobs.manage');

// Handle job cancellation and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $jobId = $_POST['job_id'] ?? '';
    $action = $_POST['action'] ?? 'cancel';

    if (!empty($jobId)) {
        if ($action === 'delete') {
            $res = Job::delete($jobId, null, true);
            if (!empty($res['success'])) {
                flash('success', "Job $jobId and associated files permanently deleted.");
            } else {
                flash('danger', "Failed to delete job: " . ($res['error'] ?? 'Unknown error'));
            }
        } else {
            Job::cancel($jobId);
            log_audit('job_cancelled_by_admin', null, $admin['id'], 'job', $jobId);
            flash('success', "Job $jobId cancelled.");
        }
    }
    redirect('admin/jobs.php');
}

$statusFilter = $_GET['status'] ?? null;
$jobs = Job::getAll(100, $statusFilter);

$activeNav = 'jobs';
$pageTitle = 'Global Job Queue';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Global Job Queue</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('admin/jobs.php') ?>" class="btn btn-secondary btn-sm <?= empty($statusFilter) ? 'active' : '' ?>">All</a>
        <a href="<?= url('admin/jobs.php?status=processing') ?>" class="btn btn-secondary btn-sm <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="<?= url('admin/jobs.php?status=failed') ?>" class="btn btn-secondary btn-sm <?= $statusFilter === 'failed' ? 'active' : '' ?>">Failed</a>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">AI Processing Job Queue</h1>
          <p class="page-desc">Monitor live execution steps, error states, and terminate hung tasks.</p>
        </div>
      </div>

      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Creator</th>
                <th>Stream Title</th>
                <th>Current Step</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($jobs)): ?>
                <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">No jobs found matching criteria.</td></tr>
              <?php else: ?>
                <?php foreach ($jobs as $j): ?>
                  <tr>
                    <td><code style="color:var(--secondary);"><?= htmlspecialchars(substr($j['id'], 0, 10)) ?>...</code></td>
                    <td><?= htmlspecialchars($j['username'] ?? 'User') ?></td>
                    <td style="font-weight:600; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars($j['video_title'] ?: 'Stream Processing') ?>
                    </td>
                    <td style="font-size:12px; color:var(--text-muted); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars($j['current_step'] ?: 'Ready') ?>
                    </td>
                    <td style="width:130px;">
                      <div style="display:flex; align-items:center; gap:8px;">
                        <div class="quota-progress" style="flex:1; height:6px;">
                          <div class="quota-progress-bar" style="width: <?= (int)$j['progress'] ?>%;"></div>
                        </div>
                        <span style="font-size:11px;"><?= (int)$j['progress'] ?>%</span>
                      </div>
                    </td>
                    <td>
                      <span class="badge <?= $j['status'] === 'completed' ? 'badge-green' : ($j['status'] === 'failed' ? 'badge-red' : 'badge-cyan') ?>">
                        <?= strtoupper($j['status']) ?>
                      </span>
                    </td>
                    <td>
                      <div style="display:flex; align-items:center; gap:6px;">
                        <?php if (in_array($j['status'], ['pending', 'processing'])): ?>
                          <form method="POST" onsubmit="return confirm('Cancel this running job?');" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="job_id" value="<?= htmlspecialchars($j['id']) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color:#f87171; font-size:11px; padding:4px 8px;">Kill Job</button>
                          </form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this clipping job? This action cannot be undone.');" style="margin:0;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="job_id" value="<?= htmlspecialchars($j['id']) ?>">
                          <button type="submit" class="btn btn-sm" style="font-size:11px; padding:4px 8px; background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,0.3); border-radius:6px; cursor:pointer;" title="Permanently delete job and clips">
                            Delete
                          </button>
                        </form>
                      </div>
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
