<?php
// views/partials/admin_header.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/SystemSetting.php';

$admin = get_auth_admin();
$pageTitle = $pageTitle ?? 'Admin Portal';

$defaultAppName = defined('APP_NAME') ? APP_NAME : 'GTA CLIP AI ULTIMATE';
$siteName = SystemSetting::get('site_name', $defaultAppName);
$accentColor = SystemSetting::get('theme_accent_color', '#6366f1');
$bannerEnabled = (int)SystemSetting::get('announcement_banner_enabled', 0);
$bannerText = SystemSetting::get('announcement_banner_text', '');
$bannerType = SystemSetting::get('announcement_banner_type', 'info');
$faviconUrl = SystemSetting::get('site_favicon_url', '/favicon.ico');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | Control Center - <?= htmlspecialchars($siteName) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= time() ?>">
  <style>
    :root {
      --primary: <?= htmlspecialchars($accentColor) ?>;
      --primary-hover: <?= htmlspecialchars($accentColor) ?>dd;
    }
    .badge-superadmin { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
    .badge-admin { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.4); }
    .badge-moderator { background: rgba(245, 158, 11, 0.2); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.4); }
    .badge-support { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4); }
    .badge-finance { background: rgba(14, 165, 233, 0.2); color: #7dd3fc; border: 1px solid rgba(14, 165, 233, 0.4); }
    .badge-viewer { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.4); }
  </style>
</head>
<body style="background-color: #07090e; color: #f1f5f9; min-height: 100vh;">

<?php if ($bannerEnabled && !empty($bannerText)): ?>
  <div style="background: <?= $bannerType === 'warning' ? '#d97706' : ($bannerType === 'danger' ? '#dc2626' : ($bannerType === 'success' ? '#059669' : '#4f46e5')) ?>; color: #ffffff; padding: 8px 16px; text-align: center; font-size: 13px; font-weight: 600; display:flex; justify-content:center; align-items:center; gap:8px;">
    <span><?= htmlspecialchars($bannerText) ?></span>
  </div>
<?php endif; ?>

<?php if ($flash = get_flash()): ?>
  <div style="position:fixed; top:24px; right:24px; z-index:99999; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" class="toast toast-<?= $flash['type'] ?>">
    <?= htmlspecialchars($flash['message']) ?>
  </div>
<?php endif; ?>
