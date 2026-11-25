<?php
// admin/admins.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';

// Super Admin access only
$currentAdmin = AdminMiddleware::requireSuperAdmin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'admin');
        $selectedPerms = $_POST['permissions'] ?? [];

        if (empty($username) || empty($email) || empty($password)) {
            flash('danger', 'Username, email and password are required.');
        } elseif (strlen($password) < 8) {
            flash('danger', 'Password must be at least 8 characters.');
        } elseif (Admin::findByEmailOrUsername($email) || Admin::findByEmailOrUsername($username)) {
            flash('danger', 'An administrator with this email or username already exists.');
        } else {
            $newAdminId = Admin::create([
                'username' => $username,
                'email' => $email,
                'full_name' => $fullName,
                'password' => $password,
                'role' => $role,
                'status' => 'active',
                'permissions' => $selectedPerms
            ]);
            Admin::logAction($currentAdmin['id'], 'admin.create', "Created admin {$username} with role {$role}");
            flash('success', "Administrator '{$username}' created successfully.");
        }
    } elseif ($action === 'edit_admin') {
        $targetId = $_POST['admin_id'] ?? '';
        $targetAdmin = Admin::findById($targetId);

        if (!$targetAdmin) {
            flash('danger', 'Administrator record not found.');
        } else {
            $updateData = [
                'full_name' => trim($_POST['full_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'status' => trim($_POST['status'] ?? 'active'),
                'role' => trim($_POST['role'] ?? $targetAdmin['role'])
            ];

            // Prevent self-demotion if last superadmin
            if ($targetAdmin['id'] === $currentAdmin['id'] && $updateData['role'] !== 'superadmin' && Admin::countSuperAdmins() <= 1) {
                flash('danger', 'You cannot remove your own Super Administrator status because you are the only Super Admin.');
            } else {
                if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
                    $updateData['password'] = $_POST['new_password'];
                }
                if (isset($_POST['permissions'])) {
                    $updateData['permissions'] = $_POST['permissions'];
                }

                Admin::update($targetId, $updateData);
                Admin::logAction($currentAdmin['id'], 'admin.update', "Updated admin {$targetAdmin['username']}");
                flash('success', "Administrator '{$targetAdmin['username']}' updated successfully.");
            }
        }
    } elseif ($action === 'delete_admin') {
        $targetId = $_POST['admin_id'] ?? '';
        if ($targetId === $currentAdmin['id']) {
            flash('danger', 'You cannot delete your own active administrator account.');
        } else {
            try {
                $targetAdmin = Admin::findById($targetId);
                Admin::delete($targetId);
                Admin::logAction($currentAdmin['id'], 'admin.delete', "Deleted admin {$targetAdmin['username']}");
                flash('success', "Administrator account was removed.");
            } catch (Exception $e) {
                flash('danger', $e->getMessage());
            }
        }
    }
    redirect('admin/admins.php');
}

$admins = Admin::getAll();
$allRoles = Admin::getAllRoles();
$allPermissions = Admin::getAllPermissions();

$activeNav = 'admins';
$pageTitle = 'Administrator Staff Management';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Administrator Staff Directory</span>
      </div>
      <div class="topbar-actions">
        <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Add Administrator</button>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Administrator & Staff Management</h1>
          <p class="page-desc">Grant role-based access, configure granular permissions, manage system operators, and track logins.</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
          👑 New Administrator Account
        </button>
      </div>

      <!-- Admins Table -->
      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Admin Profile</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Active</th>
                <th>Created</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $adm): ?>
                <tr>
                  <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                      <div class="user-avatar" style="width:34px; height:34px; font-size:14px; font-weight:700; background:linear-gradient(135deg,#ef4444,#dc2626); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff;">
                        <?= strtoupper(substr($adm['username'], 0, 1)) ?>
                      </div>
                      <div>
                        <div style="font-weight:700; color:#fff;">
                          <?= htmlspecialchars($adm['username']) ?>
                          <?php if ($adm['id'] === $currentAdmin['id']): ?>
                            <span style="font-size:10px; color:#a5b4fc; margin-left:4px;">(You)</span>
                          <?php endif; ?>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($adm['full_name'] ?? 'Staff Member') ?></div>
                      </div>
                    </div>
                  </td>
                  <td><code style="color:var(--text-muted);"><?= htmlspecialchars($adm['email']) ?></code></td>
                  <td>
                    <span class="badge badge-<?= htmlspecialchars($adm['role']) ?>" style="font-size:11px; font-weight:700; text-transform:uppercase;">
                      <?= htmlspecialchars(str_replace('_', ' ', $adm['role'])) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $adm['status'] === 'active' ? 'badge-green' : 'badge-red' ?>">
                      <?= strtoupper($adm['status']) ?>
                    </span>
                  </td>
                  <td style="color:var(--text-muted); font-size:12px;">
                    <?= $adm['last_login_at'] ? time_ago($adm['last_login_at']) : 'Never' ?>
                  </td>
                  <td style="color:var(--text-muted); font-size:12px;"><?= date('M j, Y', strtotime($adm['created_at'])) ?></td>
                  <td style="text-align:right;">
                    <div style="display:flex; justify-content:flex-end; gap:6px;">
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openEditAdminModal(<?= htmlspecialchars(json_encode($adm)) ?>)">
                        ✏️ Edit
                      </button>
                      <?php if ($adm['id'] !== $currentAdmin['id']): ?>
                        <button type="button" class="btn btn-secondary btn-sm" style="color:#f87171;" onclick="openDeleteAdminModal('<?= htmlspecialchars($adm['id']) ?>', '<?= htmlspecialchars($adm['username']) ?>')">
                          🗑️
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Create Admin Modal -->
<div id="createAdminModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; overflow-y:auto; padding:20px;">
  <div class="glass-card" style="width:100%; max-width:560px; padding:32px; border-radius:18px;">
    <h3 style="font-size:20px; font-weight:700; margin-bottom:16px;">Create Administrator Account</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_admin">

      <div class="form-group mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" placeholder="DevOps Engineer" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="mb-3">
        <div>
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" placeholder="john_ops" required>
        </div>
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="john@gtaclip.ai" required>
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Role Designation</label>
        <select name="role" class="form-control" required>
          <?php foreach ($allRoles as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['name']) ?> - <?= htmlspecialchars($r['description']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Password (min 8 chars)</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••••••" required minlength="8">
      </div>

      <div class="form-group mb-4">
        <label class="form-label" style="margin-bottom:8px;">Granular Permission Overrides (Optional)</label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; max-height:160px; overflow-y:auto; background:rgba(0,0,0,0.25); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.08);">
          <?php foreach ($allPermissions as $perm): ?>
            <label style="font-size:12px; display:flex; align-items:center; gap:6px; cursor:pointer;">
              <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm['slug']) ?>">
              <span><?= htmlspecialchars($perm['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Administrator</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Admin Modal -->
<div id="editAdminModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; overflow-y:auto; padding:20px;">
  <div class="glass-card" style="width:100%; max-width:560px; padding:32px; border-radius:18px;">
    <h3 style="font-size:20px; font-weight:700; margin-bottom:16px;">Modify Administrator</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_admin">
      <input type="hidden" name="admin_id" id="editAdminId">

      <div class="form-group mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" id="editAdminFullName" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="editAdminEmail" class="form-control" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="mb-3">
        <div>
          <label class="form-label">Assigned Role</label>
          <select name="role" id="editAdminRole" class="form-control" required>
            <?php foreach ($allRoles as $r): ?>
              <option value="<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Account Status</label>
          <select name="status" id="editAdminStatus" class="form-control" required>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Change Password (Leave blank to keep existing)</label>
        <input type="password" name="new_password" class="form-control" placeholder="••••••••••••" minlength="8">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditAdminModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Admin Record</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Admin Modal -->
<div id="deleteAdminModal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center;">
  <div class="glass-card" style="width:100%; max-width:440px; padding:28px; border-radius:16px; border-color:rgba(239,68,68,0.4);">
    <h3 style="font-size:18px; font-weight:700; color:#ef4444; margin-bottom:12px;">Delete Administrator</h3>
    <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
      Are you sure you want to revoke administrative access for <strong id="deleteAdminUsername" style="color:#fff;"></strong>?
    </p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_admin">
      <input type="hidden" name="admin_id" id="deleteAdminId">

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteAdminModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#ef4444; border-color:#ef4444;">Revoke & Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal() {
  document.getElementById('createAdminModal').style.display = 'flex';
}
function closeCreateModal() {
  document.getElementById('createAdminModal').style.display = 'none';
}

function openEditAdminModal(adm) {
  document.getElementById('editAdminId').value = adm.id;
  document.getElementById('editAdminFullName').value = adm.full_name || '';
  document.getElementById('editAdminEmail').value = adm.email || '';
  document.getElementById('editAdminRole').value = adm.role || 'admin';
  document.getElementById('editAdminStatus').value = adm.status || 'active';
  document.getElementById('editAdminModal').style.display = 'flex';
}
function closeEditAdminModal() {
  document.getElementById('editAdminModal').style.display = 'none';
}

function openDeleteAdminModal(adminId, username) {
  document.getElementById('deleteAdminId').value = adminId;
  document.getElementById('deleteAdminUsername').textContent = username;
  document.getElementById('deleteAdminModal').style.display = 'flex';
}
function closeDeleteAdminModal() {
  document.getElementById('deleteAdminModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
