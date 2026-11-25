<?php
// views/partials/header.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/SystemSetting.php';

$currentUser = get_auth_user();
$adminUser = get_auth_admin();

$defaultAppName = defined('APP_NAME') ? APP_NAME : 'GTA CLIP AI ULTIMATE';
$siteName = SystemSetting::get('site_name', $defaultAppName);
$pageTitle = $pageTitle ?? $siteName;
$accentColor = SystemSetting::get('theme_accent_color', '#6366f1');
$faviconUrl = SystemSetting::get('site_favicon_url', '/favicon.ico');
$bannerEnabled = (int)SystemSetting::get('announcement_banner_enabled', 0);
$bannerText = SystemSetting::get('announcement_banner_text', '');
$bannerType = SystemSetting::get('announcement_banner_type', 'info');

$maintenanceMode = (int)SystemSetting::get('maintenance_mode', 0);
$maintenanceMsg = SystemSetting::get('maintenance_message', 'GTA CLIP AI ULTIMATE is currently undergoing scheduled maintenance.');

// If maintenance mode is enabled and user is not an admin, show maintenance notice
if ($maintenanceMode && !$adminUser && basename($_SERVER['PHP_SELF']) !== 'login.php' && !str_contains($_SERVER['PHP_SELF'], '/admin/')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><title>Maintenance Mode</title><style>body{background:#07090e;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;} .box{background:#111827;padding:40px;border-radius:16px;border:1px solid #374151;max-width:480px;}</style></head><body><div class="box"><div style="font-size:48px;margin-bottom:16px;">🛠️</div><h2>Scheduled Platform Maintenance</h2><p style="color:#9ca3af;margin-top:12px;">' . htmlspecialchars($maintenanceMsg) . '</p></div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= str_contains($pageTitle, $siteName) ? htmlspecialchars($pageTitle) : htmlspecialchars($pageTitle . ' | ' . $siteName) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= time() ?>">
  <style>
    :root {
      --primary: <?= htmlspecialchars($accentColor) ?>;
      --primary-hover: <?= htmlspecialchars($accentColor) ?>dd;
    }
  </style>
  <script>
    window.APP_URL = "<?= APP_URL ?>";
    window.BASE_PATH = "<?= defined('BASE_PATH') ? BASE_PATH : '' ?>";
  </script>
</head>
<body>

<?php if ($bannerEnabled && !empty($bannerText)): ?>
  <div style="background: <?= $bannerType === 'warning' ? '#d97706' : ($bannerType === 'danger' ? '#dc2626' : ($bannerType === 'success' ? '#059669' : '#4f46e5')) ?>; color: #ffffff; padding: 8px 16px; text-align: center; font-size: 13px; font-weight: 600; display:flex; justify-content:center; align-items:center; gap:8px;">
    <span><?= htmlspecialchars($bannerText) ?></span>
  </div>
<?php endif; ?>

<?php if ($flash = get_flash()): ?>
  <div style="position:fixed; top:20px; right:20px; z-index:9999;" class="toast toast-<?= $flash['type'] ?>">
    <?= htmlspecialchars($flash['message']) ?>
  </div>
<?php endif; ?>
