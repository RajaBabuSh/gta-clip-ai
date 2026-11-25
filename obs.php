<?php
// obs.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';

$user = AuthMiddleware::requireUser();

$activeNav = 'obs';
$pageTitle = 'OBS Studio Integration';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Integrations / </span>
        <span style="font-size:14px; font-weight:700; color:white;">OBS Studio & Streamlabs Sync</span>
      </div>
      <div class="topbar-actions">
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container" style="max-width: 900px; margin: 0 auto;">
      <div class="page-header">
        <div>
          <h1 class="page-title">OBS Studio & Live Stream Sync</h1>
          <p class="page-desc">Automate clip triggers from your stream deck, or show real-time clip alerts directly on your stream.</p>
        </div>
      </div>

      <!-- Browser Source Widget -->
      <div class="glass-card" style="padding: 28px; margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <h2 style="font-size: 18px; font-weight: 700;">OBS Browser Source Overlay</h2>
          <span class="badge badge-cyan">1080x1920 or 600x400</span>
        </div>
        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
          Add this URL as a Browser Source in OBS Studio or Streamlabs to show an animated alert whenever the AI captures a high-energy clip on stream.
        </p>
        <div style="display:flex; gap:12px;">
          <input type="text" readonly class="form-control" value="<?= url('api/widgets/obs-alert.php?token=' . md5($user['id'] . 'salt')) ?>" style="font-family:monospace; font-size:13px; color:#67e8f9;">
          <button class="btn btn-secondary" onclick="copyText('<?= url('api/widgets/obs-alert.php?token=' . md5($user['id'] . 'salt')) ?>')">
            Copy URL
          </button>
        </div>
      </div>

      <!-- Stream Deck Hotkey Webhook -->
      <div class="glass-card" style="padding: 28px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 12px;">Elgato Stream Deck / Hotkey Webhook</h2>
        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
          Trigger an immediate 45-second high-priority clip with a single button press on your Stream Deck or macro key while streaming.
        </p>
        <div style="background:rgba(0,0,0,0.5); padding:16px; border-radius:8px; font-family:monospace; font-size:12px; color:#a7f3d0; margin-bottom:16px;">
          POST <?= url('api/clips.php?action=hotkey_clip&user_id=' . urlencode($user['id'])) ?>
        </div>
        <button class="btn btn-primary btn-sm" onclick="showToast('Webhook endpoint copied to clipboard!', 'success'); copyText('<?= url('api/clips.php?action=hotkey_clip&user_id=' . urlencode($user['id'])) ?>');">
          Copy Webhook URL
        </button>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
