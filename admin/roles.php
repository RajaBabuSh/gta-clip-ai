<?php
// admin/roles.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';

// Super Admin access only
$admin = AdminMiddleware::requireSuperAdmin();
$pdo = Database::getConnection();

// Handle update role permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $roleSlug = trim($_POST['role_slug'] ?? '');
    $selectedPerms = $_POST['permissions'] ?? [];

    if (!empty($roleSlug) && $roleSlug !== 'superadmin') {
        $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_slug = ?");
        $del->execute([$roleSlug]);

        $ins = $pdo->prepare("INSERT INTO role_permissions (role_slug, permission_slug) VALUES (?, ?)");
        foreach ($selectedPerms as $perm) {
            $ins->execute([$roleSlug, trim($perm)]);
        }

        Admin::logAction($admin['id'], 'roles.update_permissions', "Updated permission matrix for role '{$roleSlug}'");
        flash('success', "Permissions for role '{$roleSlug}' updated successfully.");
    }
    redirect('admin/roles.php');
}

$roles = Admin::getAllRoles();
$permissions = Admin::getAllPermissions();

// Build matrix: [role_slug => [perm_slug => true]]
$matrix = [];
$matrixStmt = $pdo->query("SELECT role_slug, permission_slug FROM role_permissions");
while ($row = $matrixStmt->fetch(PDO::FETCH_ASSOC)) {
    $matrix[$row['role_slug']][$row['permission_slug']] = true;
}

$activeNav = 'roles';
$pageTitle = 'Roles & Permissions Matrix';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Roles & Permissions</span>
      </div>
    
      <div class="topbar-actions">
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Role-Based Access Control (RBAC) Matrix</h1>
          <p class="page-desc">Define granular system capabilities for each operational tier across the GTA CLIP AI platform.</p>
        </div>
      </div>

      <!-- Roles Overview Cards -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:28px;">
        <?php foreach ($roles as $r): ?>
          <div class="glass-card" style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <span class="badge badge-<?= htmlspecialchars($r['slug']) ?>" style="font-size:12px; font-weight:700; text-transform:uppercase;">
                <?= htmlspecialchars($r['name']) ?>
              </span>
              <code style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($r['slug']) ?></code>
            </div>
            <p style="font-size:12px; color:var(--text-muted); line-height:1.5;">
              <?= htmlspecialchars($r['description'] ?? 'Standard system role.') ?>
            </p>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Permission Matrix Table -->
      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.08); background:rgba(0,0,0,0.2);">
          <h2 style="font-size:16px; font-weight:700;">Permission Allocation Matrix</h2>
          <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Check capabilities granted to each operational role. Super Admin always retains 100% platform authority.</p>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="min-width:260px;">Permission Capability</th>
                <th style="text-align:center;">Super Admin</th>
                <th style="text-align:center;">Admin</th>
                <th style="text-align:center;">Moderator</th>
                <th style="text-align:center;">Support</th>
                <th style="text-align:center;">Finance</th>
                <th style="text-align:center;">Viewer</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                $rolesList = ['admin', 'moderator', 'support', 'finance_admin', 'viewer'];
                foreach ($permissions as $perm): 
              ?>
                <tr>
                  <td>
                    <div style="font-weight:700; color:#fff; font-size:13px;"><?= htmlspecialchars($perm['name']) ?></div>
                    <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($perm['description'] ?? '') ?></div>
                    <code style="font-size:10px; color:var(--secondary);"><?= htmlspecialchars($perm['slug']) ?></code>
                  </td>
                  <td style="text-align:center; color:#6ee7b7; font-size:16px;" title="Super Admin always has full access">
                    ✓
                  </td>
                  <?php foreach ($rolesList as $roleKey): ?>
                    <td style="text-align:center;">
                      <?php $has = !empty($matrix[$roleKey][$perm['slug']]); ?>
                      <span style="font-size:16px; color: <?= $has ? '#6ee7b7' : 'rgba(255,255,255,0.2)' ?>;">
                        <?= $has ? '✓' : '—' ?>
                      </span>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
