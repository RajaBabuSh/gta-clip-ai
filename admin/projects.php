<?php
// admin/projects.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';

$admin = AdminMiddleware::requireAdmin();

$pdo = Database::getConnection();
$projects = $pdo->query("
    SELECT p.*, u.username, u.email
    FROM projects p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.updated_at DESC
")->fetchAll();

$activeNav = 'projects';
$pageTitle = 'All Projects';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Global Projects</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-purple">Total: <?= count($projects) ?> Projects</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Creator Workspaces & Projects</h1>
          <p class="page-desc">Inspect all project folders created across all registered user accounts.</p>
        </div>
      </div>

      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Project Title</th>
                <th>Owner</th>
                <th>Category</th>
                <th>Streams</th>
                <th>Clips</th>
                <th>Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($projects)): ?>
                <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">No projects found.</td></tr>
              <?php else: ?>
                <?php foreach ($projects as $p): ?>
                  <tr>
                    <td style="font-weight:700;"><?= htmlspecialchars($p['title']) ?></td>
                    <td><?= htmlspecialchars($p['username'] ?? 'User') ?></td>
                    <td><span class="badge badge-cyan"><?= htmlspecialchars($p['category']) ?></span></td>
                    <td><?= (int)$p['total_videos'] ?></td>
                    <td><?= (int)$p['total_clips'] ?></td>
                    <td style="color:var(--text-muted); font-size:12px;"><?= time_ago($p['updated_at']) ?></td>
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
