<?php
// admin/users.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Admin.php';

$admin = AdminMiddleware::requirePermission('users.manage');

$search = trim($_GET['q'] ?? '');
$statusFilter = !empty($_GET['status']) ? trim($_GET['status']) : null;
$tierFilter = !empty($_GET['tier']) ? trim($_GET['tier']) : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $user = User::findById($userId);

    if (!$user) {
        flash('danger', 'User account not found.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle_status') {
        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        User::updateStatus($userId, $newStatus);
        Admin::logAction($admin['id'], 'user.toggle_status', "User {$user['username']} changed to {$newStatus}", $userId);
        flash('success', "User '{$user['username']}' status set to {$newStatus}.");
    } elseif ($action === 'edit_user') {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'plan_tier' => trim($_POST['plan_tier'] ?? 'free'),
            'monthly_clip_quota' => (int)($_POST['monthly_clip_quota'] ?? 50),
            'status' => trim($_POST['status'] ?? 'active')
        ];
        User::update($userId, $data);
        Admin::logAction($admin['id'], 'user.update', "Updated profile/quota for {$user['username']}", $userId);
        flash('success', "Account for '{$user['username']}' updated successfully.");
    } elseif ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            flash('danger', 'Password must be at least 6 characters.');
        } else {
            User::resetPassword($userId, $newPassword);
            Admin::logAction($admin['id'], 'user.reset_password', "Password reset for {$user['username']}", $userId);
            flash('success', "Password for user '{$user['username']}' has been updated.");
        }
    } elseif ($action === 'delete_user') {
        $cascade = !empty($_POST['cascade_purge']);
        User::delete($userId, $cascade);
        Admin::logAction($admin['id'], 'user.delete', "Deleted user {$user['username']} (Cascade: " . ($cascade ? 'YES' : 'NO') . ")", $userId);
        flash('success', "User '{$user['username']}' was deleted.");
    }
    redirect('admin/users.php');
}

$totalUsers = User::countFiltered($search, $statusFilter, $tierFilter);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$users = User::search($search, $statusFilter, $tierFilter, $perPage, $offset);

$activeNav = 'users';
$pageTitle = 'User Management';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">User Directory</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-purple"><?= $totalUsers ?> Registered Users</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Creator User Directory</h1>
          <p class="page-desc">Manage accounts, change membership tiers, adjust quotas, reset credentials, or suspend access.</p>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="glass-card" style="padding:16px; margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
          <input type="text" name="q" class="form-control" placeholder="Search by username, email or name..." value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:240px;">
          
          <select name="status" class="form-control" style="width:140px;">
            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          </select>

          <select name="tier" class="form-control" style="width:140px;">
            <option value="">All Tiers</option>
            <option value="free" <?= $tierFilter === 'free' ? 'selected' : '' ?>>Free</option>
            <option value="pro" <?= $tierFilter === 'pro' ? 'selected' : '' ?>>Pro</option>
            <option value="enterprise" <?= $tierFilter === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
          </select>

          <button type="submit" class="btn btn-primary" style="padding:8px 18px;">Filter</button>
          <?php if (!empty($search) || !empty($statusFilter) || !empty($tierFilter)): ?>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Users Table -->
      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Creator Account</th>
                <th>Email</th>
                <th>Plan Tier</th>
                <th>Usage Quota</th>
                <th>Status</th>
                <th>Joined</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center; padding:36px; color:var(--text-muted);">No users match the specified criteria.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td>
                      <div style="display:flex; align-items:center; gap:10px;">
                        <div class="user-avatar" style="width:32px; height:32px; font-size:13px; font-weight:700; background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff;">
                          <?= strtoupper(substr($u['username'], 0, 1)) ?>
                        </div>
                        <div>
                          <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($u['username']) ?></div>
                          <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($u['full_name'] ?? '') ?></div>
                        </div>
                      </div>
                    </td>
                    <td><code style="color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></code></td>
                    <td>
                      <span class="badge <?= $u['plan_tier'] === 'pro' ? 'badge-purple' : ($u['plan_tier'] === 'enterprise' ? 'badge-green' : 'badge-secondary') ?>">
                        <?= strtoupper($u['plan_tier']) ?>
                      </span>
                    </td>
                    <td>
                      <div style="font-size:12px; font-weight:700; margin-bottom:3px;">
                        <?= (int)$u['clips_generated_this_month'] ?> / <?= (int)$u['monthly_clip_quota'] ?> clips
                      </div>
                      <div class="quota-progress" style="width:100px; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                        <?php 
                          $pct = min(100, (int)($u['monthly_clip_quota'] > 0 ? ($u['clips_generated_this_month'] / $u['monthly_clip_quota']) * 100 : 0));
                        ?>
                        <div style="width:<?= $pct ?>%; height:100%; background:<?= $pct > 90 ? '#ef4444' : '#6366f1' ?>;"></div>
                      </div>
                    </td>
                    <td>
                      <span class="badge <?= $u['status'] === 'active' ? 'badge-green' : 'badge-red' ?>">
                        <?= strtoupper($u['status']) ?>
                      </span>
                    </td>
                    <td style="color:var(--text-muted); font-size:12px;"><?= time_ago($u['created_at']) ?></td>
                    <td style="text-align:right;">
                      <div style="display:flex; justify-content:flex-end; gap:6px;">
                        <!-- Quick Suspend / Activate -->
                        <form method="POST" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                          <button type="submit" class="btn btn-secondary btn-sm" title="<?= $u['status'] === 'active' ? 'Suspend User' : 'Activate User' ?>">
                            <?= $u['status'] === 'active' ? '⏸️ Suspend' : '▶️ Activate' ?>
                          </button>
                        </form>

                        <!-- Edit Button -->
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                          ✏️ Edit
                        </button>

                        <!-- Reset Password Button -->
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openResetModal('<?= htmlspecialchars($u['id']) ?>', '<?= htmlspecialchars($u['username']) ?>')">
                          🔑 Reset
                        </button>

                        <!-- Delete Button -->
                        <button type="button" class="btn btn-secondary btn-sm" style="color:#f87171;" onclick="openDeleteModal('<?= htmlspecialchars($u['id']) ?>', '<?= htmlspecialchars($u['username']) ?>')">
                          🗑️
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:12px; color:var(--text-muted);">
              Page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalUsers ?> users)
            </div>
            <div style="display:flex; gap:6px;">
              <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode((string)$statusFilter) ?>&tier=<?= urlencode((string)$tierFilter) ?>" class="btn btn-secondary btn-sm">← Prev</a>
              <?php endif; ?>
              <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode((string)$statusFilter) ?>&tier=<?= urlencode((string)$tierFilter) ?>" class="btn btn-secondary btn-sm">Next →</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
  <div class="glass-card" style="width:100%; max-width:480px; padding:28px; border-radius:16px;">
    <h3 style="font-size:18px; font-weight:700; margin-bottom:16px;">Edit User Account</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="editUserId">

      <div class="form-group mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" id="editFullName" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" id="editEmail" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Plan Tier</label>
        <select name="plan_tier" id="editPlanTier" class="form-control">
          <option value="free">Free Tier</option>
          <option value="pro">Pro Tier</option>
          <option value="enterprise">Enterprise Tier</option>
        </select>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Monthly Clip Quota</label>
        <input type="number" name="monthly_clip_quota" id="editQuota" class="form-control" min="1" max="10000" required>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Account Status</label>
        <select name="status" id="editStatus" class="form-control">
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
  <div class="glass-card" style="width:100%; max-width:420px; padding:28px; border-radius:16px;">
    <h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Reset Password</h3>
    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Set a new secure password for <strong id="resetUsername" style="color:#fff;"></strong>.</p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetUserId">

      <div class="form-group mb-4">
        <label class="form-label">New Password (min 6 chars)</label>
        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="••••••••">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
  <div class="glass-card" style="width:100%; max-width:440px; padding:28px; border-radius:16px; border-color:rgba(239,68,68,0.4);">
    <h3 style="font-size:18px; font-weight:700; color:#ef4444; margin-bottom:12px;">Delete Creator Account</h3>
    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
      Are you sure you want to delete user <strong id="deleteUsername" style="color:#fff;"></strong>?
    </p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="deleteUserId">

      <div style="margin-bottom:20px; padding:12px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:8px;">
        <label style="display:flex; align-items:flex-start; gap:8px; font-size:12px; color:#fca5a5; cursor:pointer;">
          <input type="checkbox" name="cascade_purge" value="1" style="margin-top:2px;">
          <span><strong>Cascade Purge:</strong> Also delete all user projects, original videos, generated clips, and storage files permanently.</span>
        </label>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#ef4444; border-color:#ef4444;">Permanently Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(user) {
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editFullName').value = user.full_name || '';
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editPlanTier').value = user.plan_tier || 'free';
  document.getElementById('editQuota').value = user.monthly_clip_quota || 50;
  document.getElementById('editStatus').value = user.status || 'active';
  document.getElementById('editUserModal').style.display = 'flex';
}
function closeEditModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

function openResetModal(userId, username) {
  document.getElementById('resetUserId').value = userId;
  document.getElementById('resetUsername').textContent = username;
  document.getElementById('resetPasswordModal').style.display = 'flex';
}
function closeResetModal() {
  document.getElementById('resetPasswordModal').style.display = 'none';
}

function openDeleteModal(userId, username) {
  document.getElementById('deleteUserId').value = userId;
  document.getElementById('deleteUsername').textContent = username;
  document.getElementById('deleteUserModal').style.display = 'flex';
}
function closeDeleteModal() {
  document.getElementById('deleteUserModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
