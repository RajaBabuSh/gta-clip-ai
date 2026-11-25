<?php
// views/partials/admin_sidebar.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../app/models/SystemSetting.php';

$activeNav = $activeNav ?? 'index';
$admin = get_auth_admin();
$role = $admin['role'] ?? 'viewer';
$siteName = SystemSetting::get('site_name', 'GTA CLIP AI');
?>
<aside class="app-sidebar" style="background: #0b0f1a; border-right: 1px solid rgba(255, 255, 255, 0.08); width: 260px; display: flex; flex-direction: column; height: 100vh; position: sticky; top: 0; overflow-y: auto;">
  <a href="<?= url('admin/index.php') ?>" class="brand-badge" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding: 18px 20px; display: flex; align-items: center; gap: 12px; text-decoration: none;">
    <div class="brand-icon" style="background: linear-gradient(135deg, #ef4444, #8b5cf6); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">🛡️</div>
    <div>
      <div class="brand-title" style="font-weight: 800; font-size: 15px; color: #ffffff; letter-spacing: -0.3px;"><?= htmlspecialchars(substr($siteName, 0, 16)) ?></div>
      <div class="brand-sub" style="color: #f87171; font-size: 11px; font-weight: 700; text-transform: uppercase;">Control Center</div>
    </div>
  </a>

  <nav class="sidebar-nav" style="padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
    <div class="nav-label" style="color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 6px 10px; letter-spacing: 0.5px;">Main Dashboard</div>
    
    <a href="<?= url('admin/index.php') ?>" class="nav-item <?= $activeNav === 'index' ? 'active' : '' ?>">
      <span class="nav-icon">📊</span> Overview Stats
    </a>

    <?php if (AdminMiddleware::hasPermission('users.manage', $admin)): ?>
      <a href="<?= url('admin/users.php') ?>" class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> User Management
      </a>
    <?php endif; ?>

    <?php if ($role === 'superadmin'): ?>
      <div class="nav-label" style="color: #f87171; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 10px 4px 10px; letter-spacing: 0.5px;">Super Admin Control</div>
      <a href="<?= url('admin/admins.php') ?>" class="nav-item <?= $activeNav === 'admins' ? 'active' : '' ?>">
        <span class="nav-icon">👑</span> Admins & Staff
      </a>
      <a href="<?= url('admin/roles.php') ?>" class="nav-item <?= $activeNav === 'roles' ? 'active' : '' ?>">
        <span class="nav-icon">🔑</span> Roles & Permissions
      </a>
    <?php endif; ?>

    <div class="nav-label" style="color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 10px 4px 10px; letter-spacing: 0.5px;">Clipping & Content</div>

    <?php if (AdminMiddleware::hasPermission('jobs.manage', $admin)): ?>
      <a href="<?= url('admin/jobs.php') ?>" class="nav-item <?= $activeNav === 'jobs' ? 'active' : '' ?>">
        <span class="nav-icon">⚡</span> AI Clipping Pipeline
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('clips.manage', $admin)): ?>
      <a href="<?= url('admin/clips.php') ?>" class="nav-item <?= $activeNav === 'clips' ? 'active' : '' ?>">
        <span class="nav-icon">🎬</span> Generated Clips
      </a>
    <?php endif; ?>

    <a href="<?= url('admin/projects.php') ?>" class="nav-item <?= $activeNav === 'projects' ? 'active' : '' ?>">
      <span class="nav-icon">📁</span> Creator Projects
    </a>

    <?php if (AdminMiddleware::hasPermission('youtube.manage', $admin)): ?>
      <a href="<?= url('admin/youtube.php') ?>" class="nav-item <?= $activeNav === 'youtube' ? 'active' : '' ?>">
        <span class="nav-icon">📺</span> YouTube Automation
      </a>
    <?php endif; ?>

    <div class="nav-label" style="color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 10px 4px 10px; letter-spacing: 0.5px;">Settings & Engine</div>

    <?php if (AdminMiddleware::hasPermission('ai.config', $admin)): ?>
      <a href="<?= url('admin/ai-settings.php') ?>" class="nav-item <?= $activeNav === 'ai-settings' ? 'active' : '' ?>">
        <span class="nav-icon">🧠</span> AI & Model Settings
      </a>
      <a href="<?= url('admin/providers.php') ?>" class="nav-item <?= $activeNav === 'providers' ? 'active' : '' ?>">
        <span class="nav-icon">🌐</span> Free Providers Hub
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('customization.manage', $admin)): ?>
      <a href="<?= url('admin/customization.php') ?>" class="nav-item <?= $activeNav === 'customization' ? 'active' : '' ?>">
        <span class="nav-icon">🎨</span> Website Customization
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('settings.manage', $admin)): ?>
      <a href="<?= url('admin/system-settings.php') ?>" class="nav-item <?= $activeNav === 'system-settings' ? 'active' : '' ?>">
        <span class="nav-icon">⚙️</span> System Settings
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('infra.manage', $admin)): ?>
      <a href="<?= url('admin/infrastructure.php') ?>" class="nav-item <?= $activeNav === 'infrastructure' ? 'active' : '' ?>">
        <span class="nav-icon">🖥️</span> Infrastructure Health
      </a>
      <a href="<?= url('admin/storage.php') ?>" class="nav-item <?= $activeNav === 'storage' ? 'active' : '' ?>">
        <span class="nav-icon">☁️</span> Storage & Cloudflare R2
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('finance.manage', $admin)): ?>
      <a href="<?= url('admin/costs.php') ?>" class="nav-item <?= $activeNav === 'costs' ? 'active' : '' ?>">
        <span class="nav-icon">💰</span> Financial & Costs
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('logs.view', $admin)): ?>
      <a href="<?= url('admin/logs.php') ?>" class="nav-item <?= $activeNav === 'logs' ? 'active' : '' ?>">
        <span class="nav-icon">📜</span> Security Audit Logs
      </a>
    <?php endif; ?>

    <?php if (AdminMiddleware::hasPermission('backups.manage', $admin)): ?>
      <a href="<?= url('admin/backups.php') ?>" class="nav-item <?= $activeNav === 'backups' ? 'active' : '' ?>">
        <span class="nav-icon">💾</span> Backup Management
      </a>
    <?php endif; ?>
  </nav>

  <div style="margin-top: auto; padding: 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0,0,0,0.2);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
      <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
        <span class="badge badge-<?= htmlspecialchars($role) ?>" style="font-size: 10px; font-weight: 800; text-transform: uppercase;">
          <?= htmlspecialchars($role) ?>
        </span>
        <span style="font-size: 12px; color: #cbd5e1; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($admin['email'] ?? '') ?>">
          <?= htmlspecialchars($admin['username'] ?? 'admin') ?>
        </span>
      </div>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
      <a href="<?= url('/') ?>" target="_blank" style="color: #94a3b8; text-decoration: none;">View Site ↗</a>
      <a href="<?= url('admin/logout.php') ?>" style="color: #f87171; text-decoration: none; font-weight: 600;">Sign Out</a>
    </div>
  </div>
</aside>
