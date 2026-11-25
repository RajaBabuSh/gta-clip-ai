<?php
// admin/customization.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';

$admin = AdminMiddleware::requirePermission('customization.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $fields = [
        'site_name' => trim($_POST['site_name'] ?? 'GTA CLIP AI ULTIMATE'),
        'site_tagline' => trim($_POST['site_tagline'] ?? ''),
        'site_logo_url' => trim($_POST['site_logo_url'] ?? ''),
        'site_favicon_url' => trim($_POST['site_favicon_url'] ?? ''),
        'theme_accent_color' => trim($_POST['theme_accent_color'] ?? '#6366f1'),
        'theme_bg_color' => trim($_POST['theme_bg_color'] ?? '#0a0d14'),
        'announcement_banner_enabled' => !empty($_POST['announcement_banner_enabled']) ? '1' : '0',
        'announcement_banner_text' => trim($_POST['announcement_banner_text'] ?? ''),
        'announcement_banner_type' => trim($_POST['announcement_banner_type'] ?? 'info'),
        'homepage_headline' => trim($_POST['homepage_headline'] ?? ''),
        'homepage_subheadline' => trim($_POST['homepage_subheadline'] ?? ''),
        'footer_copyright_text' => trim($_POST['footer_copyright_text'] ?? '')
    ];

    foreach ($fields as $key => $val) {
        SystemSetting::set($key, $val, 'branding', null, 1);
    }

    Admin::logAction($admin['id'], 'branding.update', 'Updated website visual branding and appearance');
    flash('success', 'Website branding and customization updated successfully.');
    redirect('admin/customization.php');
}

$siteName = SystemSetting::get('site_name', 'GTA CLIP AI ULTIMATE');
$siteTagline = SystemSetting::get('site_tagline', 'AI Stream Highlights & Shorts Generator');
$logoUrl = SystemSetting::get('site_logo_url', '/assets/images/logo.png');
$faviconUrl = SystemSetting::get('site_favicon_url', '/favicon.ico');
$accentColor = SystemSetting::get('theme_accent_color', '#6366f1');
$bgColor = SystemSetting::get('theme_bg_color', '#0a0d14');
$bannerEnabled = SystemSetting::get('announcement_banner_enabled', '0');
$bannerText = SystemSetting::get('announcement_banner_text', '');
$bannerType = SystemSetting::get('announcement_banner_type', 'info');
$heroHeadline = SystemSetting::get('homepage_headline', 'Turn 4-Hour Gaming Streams Into Viral Clips in Seconds');
$heroSubheadline = SystemSetting::get('homepage_subheadline', 'Automatic speech recognition, high-energy peak detection, portrait 9:16 re-framing, and YouTube Shorts automation.');
$footerText = SystemSetting::get('footer_copyright_text', '© 2026 GTA CLIP AI ULTIMATE. All rights reserved.');

$activeNav = 'customization';
$pageTitle = 'Website Customization & Branding';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Website Customization</span>
      </div>
      <div class="topbar-actions">
        <a href="<?= url('/') ?>" target="_blank" class="btn btn-secondary btn-sm">Preview Public Site ↗</a>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Website Branding & Visuals</h1>
          <p class="page-desc">Modify platform names, logos, color themes, hero copy, and top announcement banners in real time.</p>
        </div>
      </div>

      <form method="POST">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">
          <!-- Left Column: Settings -->
          <div>
            <!-- Identity Card -->
            <div class="glass-card" style="padding:24px; margin-bottom:24px;">
              <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Platform Identity</h2>

              <div class="form-group mb-3">
                <label class="form-label">Platform Name</label>
                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($siteName) ?>" required>
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Platform Tagline</label>
                <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($siteTagline) ?>">
              </div>

              <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                  <label class="form-label">Logo Path / URL</label>
                  <input type="text" name="site_logo_url" class="form-control" value="<?= htmlspecialchars($logoUrl) ?>">
                </div>
                <div>
                  <label class="form-label">Favicon Path / URL</label>
                  <input type="text" name="site_favicon_url" class="form-control" value="<?= htmlspecialchars($faviconUrl) ?>">
                </div>
              </div>
            </div>

            <!-- Appearance & Colors Card -->
            <div class="glass-card" style="padding:24px; margin-bottom:24px;">
              <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Theme Colors</h2>

              <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                  <label class="form-label">Brand Accent Color</label>
                  <div style="display:flex; gap:8px; align-items:center;">
                    <input type="color" name="theme_accent_color" id="accentPicker" value="<?= htmlspecialchars($accentColor) ?>" style="width:40px; height:40px; padding:0; border:none; border-radius:6px; cursor:pointer;" oninput="document.getElementById('accentText').value = this.value">
                    <input type="text" id="accentText" class="form-control" value="<?= htmlspecialchars($accentColor) ?>" oninput="document.getElementById('accentPicker').value = this.value">
                  </div>
                </div>

                <div>
                  <label class="form-label">Background Dark Hex</label>
                  <input type="text" name="theme_bg_color" class="form-control" value="<?= htmlspecialchars($bgColor) ?>">
                </div>
              </div>
            </div>

            <!-- Announcement Banner Card -->
            <div class="glass-card" style="padding:24px; margin-bottom:24px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="font-size:16px; font-weight:700; color:#fff;">Top Announcement Banner</h2>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#fff; cursor:pointer;">
                  <input type="checkbox" name="announcement_banner_enabled" value="1" <?= $bannerEnabled === '1' ? 'checked' : '' ?>>
                  <span>Enable Banner</span>
                </label>
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Banner Text</label>
                <input type="text" name="announcement_banner_text" class="form-control" value="<?= htmlspecialchars($bannerText) ?>" placeholder="🚀 Welcome to GTA CLIP AI ULTIMATE!">
              </div>

              <div class="form-group">
                <label class="form-label">Banner Accent Style</label>
                <select name="announcement_banner_type" class="form-control">
                  <option value="info" <?= $bannerType === 'info' ? 'selected' : '' ?>>Info (Indigo/Blue)</option>
                  <option value="success" <?= $bannerType === 'success' ? 'selected' : '' ?>>Success (Green)</option>
                  <option value="warning" <?= $bannerType === 'warning' ? 'selected' : '' ?>>Warning (Amber)</option>
                  <option value="danger" <?= $bannerType === 'danger' ? 'selected' : '' ?>>Critical / Alert (Red)</option>
                </select>
              </div>
            </div>

            <!-- Homepage Copy Card -->
            <div class="glass-card" style="padding:24px; margin-bottom:24px;">
              <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Hero Content & Footer</h2>

              <div class="form-group mb-3">
                <label class="form-label">Hero Title Headline</label>
                <input type="text" name="homepage_headline" class="form-control" value="<?= htmlspecialchars($heroHeadline) ?>">
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Hero Subheadline</label>
                <textarea name="homepage_subheadline" class="form-control" rows="3"><?= htmlspecialchars($heroSubheadline) ?></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Footer Copyright Notice</label>
                <input type="text" name="footer_copyright_text" class="form-control" value="<?= htmlspecialchars($footerText) ?>">
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:12px 32px; font-weight:700;">
              💾 Apply Website Customization
            </button>
          </div>

          <!-- Right Column: Live Visual Preview Card -->
          <div class="glass-card" style="padding:24px; position:sticky; top:24px;">
            <h3 style="font-size:14px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:16px;">Live Component Preview</h3>

            <div style="background:#0a0d14; border:1px solid rgba(255,255,255,0.1); border-radius:12px; overflow:hidden;">
              <!-- Banner Preview -->
              <div style="background:<?= $accentColor ?>; color:#fff; padding:6px 10px; font-size:11px; text-align:center; font-weight:600;">
                <?= htmlspecialchars($bannerText ?: 'Announcement Banner Text') ?>
              </div>

              <!-- Mini Navbar -->
              <div style="padding:12px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06);">
                <span style="font-size:13px; font-weight:800; color:#fff;"><?= htmlspecialchars(substr($siteName, 0, 16)) ?></span>
                <span style="background:<?= $accentColor ?>; color:#fff; font-size:10px; padding:3px 8px; border-radius:4px; font-weight:700;">Studio</span>
              </div>

              <!-- Mini Hero -->
              <div style="padding:24px 16px; text-align:center;">
                <div style="font-size:15px; font-weight:800; color:#fff; line-height:1.3; margin-bottom:8px;">
                  <?= htmlspecialchars(substr($heroHeadline, 0, 48)) ?>...
                </div>
                <div style="font-size:11px; color:#94a3b8; line-height:1.4;">
                  <?= htmlspecialchars(substr($heroSubheadline, 0, 90)) ?>...
                </div>
                <div style="margin-top:14px;">
                  <button type="button" class="btn btn-primary btn-sm" style="background:<?= $accentColor ?>; border-color:<?= $accentColor ?>; padding:4px 12px; font-size:11px;">
                    Start Free
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
