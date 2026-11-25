<?php
// admin/youtube.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';
require_once __DIR__ . '/../app/services/YouTubeOAuthService.php';

$admin = AdminMiddleware::requirePermission('youtube.manage');
$pdo = Database::getConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        SystemSetting::set('youtube_default_privacy', trim($_POST['default_privacy'] ?? 'private'), 'youtube');
        SystemSetting::set('youtube_auto_publish_enabled', !empty($_POST['auto_publish_enabled']) ? '1' : '0', 'youtube');
        SystemSetting::set('youtube_max_daily_uploads', (string)max(1, (int)($_POST['max_daily_uploads'] ?? 10)), 'youtube');
        
        Admin::logAction($admin['id'], 'youtube.update_settings', 'Updated YouTube automation preferences');
        flash('success', 'YouTube automation settings saved.');
    } elseif ($action === 'update_oauth_credentials') {
        $clientId = trim($_POST['google_client_id'] ?? '');
        $clientSecret = trim($_POST['google_client_secret'] ?? '');
        SystemSetting::set('google_client_id', $clientId, 'youtube', 'Google OAuth 2.0 Client ID', 0);
        SystemSetting::set('google_client_secret', $clientSecret, 'youtube', 'Google OAuth 2.0 Client Secret', 0);
        Admin::logAction($admin['id'], 'youtube.update_oauth', 'Updated Google Cloud OAuth 2.0 API credentials');
        flash('success', 'Google Cloud OAuth 2.0 credentials saved successfully.');
    } elseif ($action === 'disconnect_channel') {
        $channelId = $_POST['channel_id'] ?? '';
        $stmt = $pdo->prepare("UPDATE youtube_accounts SET is_connected = 0 WHERE id = ?");
        $stmt->execute([$channelId]);
        Admin::logAction($admin['id'], 'youtube.disconnect_channel', "Disconnected YouTube channel {$channelId}");
        flash('success', 'Channel connection deactivated.');
    } elseif ($action === 'retry_upload') {
        $uploadId = $_POST['upload_id'] ?? '';
        $stmt = $pdo->prepare("UPDATE youtube_uploads SET upload_status = 'pending', error_message = NULL, retry_count = retry_count + 1 WHERE id = ?");
        $stmt->execute([$uploadId]);
        Admin::logAction($admin['id'], 'youtube.retry_upload', "Queued retry for upload {$uploadId}");
        flash('success', 'Upload queued for re-attempt.');
    }
    redirect('admin/youtube.php');
}

// Fetch connected accounts
$accountsStmt = $pdo->query("
    SELECT ya.*, u.username as owner_username, u.email as owner_email
    FROM youtube_accounts ya
    JOIN users u ON ya.user_id = u.id
    ORDER BY ya.connected_at DESC
");
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent uploads
$uploadsStmt = $pdo->query("
    SELECT yu.*, u.username as creator_username, ya.channel_title
    FROM youtube_uploads yu
    LEFT JOIN users u ON yu.user_id = u.id
    LEFT JOIN youtube_accounts ya ON yu.youtube_account_id = ya.id
    ORDER BY yu.created_at DESC
    LIMIT 30
");
$uploads = $uploadsStmt->fetchAll(PDO::FETCH_ASSOC);

$defaultPrivacy = SystemSetting::get('youtube_default_privacy', 'private');
$autoPublish = SystemSetting::get('youtube_auto_publish_enabled', '1');
$maxDaily = SystemSetting::get('youtube_max_daily_uploads', '10');

$activeNav = 'youtube';
$pageTitle = 'YouTube Channel Automation';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">YouTube Automation</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-red"><?= count($accounts) ?> Connected Channels</span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">YouTube Channel & Auto-Publishing Suite</h1>
          <p class="page-desc">Supervise connected creator channels, schedule YouTube Shorts uploads, and audit publishing logs.</p>
        </div>
      </div>

      <!-- Google Cloud OAuth 2.0 API Configuration -->
      <div class="glass-card" style="padding:24px; margin-bottom:28px; border:1px solid <?= YouTubeOAuthService::isConfigured() ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <div>
            <h2 style="font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="#ef4444"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
              Google Cloud OAuth 2.0 & YouTube Data API v3 Configuration
            </h2>
            <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">
              Configure your Google Cloud Project credentials to enable channel linking and video uploads.
            </p>
          </div>
          <span class="badge <?= YouTubeOAuthService::isConfigured() ? 'badge-green' : 'badge-red' ?>">
            <?= YouTubeOAuthService::isConfigured() ? '● OAuth Configured' : '○ Credentials Required' ?>
          </span>
        </div>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_oauth_credentials">

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div>
              <label class="form-label">Google Client ID</label>
              <input type="text" name="google_client_id" class="form-control" placeholder="xxxxxxxxxxxx-xxxxxxxxxxxx.apps.googleusercontent.com" value="<?= htmlspecialchars(YouTubeOAuthService::getClientId()) ?>" required>
            </div>
            <div>
              <label class="form-label">Google Client Secret</label>
              <input type="password" name="google_client_secret" class="form-control" placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx" value="<?= htmlspecialchars(YouTubeOAuthService::getClientSecret()) ?>" required>
            </div>
          </div>

          <div style="margin-bottom:16px; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:12px 14px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase;">Authorized Redirect URI (Copy into Google Cloud Console):</span>
                <div style="font-family:monospace; font-size:12.5px; color:#c4b5fd; margin-top:4px;" id="admin-yt-redirect-uri"><?= htmlspecialchars(YouTubeOAuthService::getRedirectUri()) ?></div>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('admin-yt-redirect-uri').innerText); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy URI', 2000);">
                Copy URI
              </button>
            </div>
            <div style="font-size:11px; color:#64748b; margin-top:6px;">
              Required Scopes: <code>https://www.googleapis.com/auth/youtube.upload</code>, <code>https://www.googleapis.com/auth/youtube.readonly</code>
            </div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="font-size:12px; color:var(--secondary); text-decoration:underline;">
              Google Cloud Console Credentials Manager ↗
            </a>
            <button type="submit" class="btn btn-primary" style="padding:8px 20px;">Save OAuth Credentials</button>
          </div>
        </form>
      </div>

      <!-- Settings Panel -->
      <div class="glass-card" style="padding:24px; margin-bottom:28px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:16px; color:#fff;">Global Publishing Defaults</h2>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_settings">

          <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:end;">
            <div>
              <label class="form-label">Default Privacy Status</label>
              <select name="default_privacy" class="form-control">
                <option value="private" <?= $defaultPrivacy === 'private' ? 'selected' : '' ?>>Private (Safe)</option>
                <option value="unlisted" <?= $defaultPrivacy === 'unlisted' ? 'selected' : '' ?>>Unlisted</option>
                <option value="public" <?= $defaultPrivacy === 'public' ? 'selected' : '' ?>>Public</option>
              </select>
            </div>

            <div>
              <label class="form-label">Max Daily Uploads / Channel</label>
              <input type="number" name="max_daily_uploads" class="form-control" value="<?= htmlspecialchars($maxDaily) ?>" min="1" max="50">
            </div>

            <div style="display:flex; gap:12px; align-items:center;">
              <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#fff; cursor:pointer;">
                <input type="checkbox" name="auto_publish_enabled" value="1" <?= $autoPublish === '1' ? 'checked' : '' ?>>
                <span>Allow Automated Publishing</span>
              </label>
              <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto; padding:8px 16px;">Save Settings</button>
            </div>
          </div>
        </form>
      </div>

      <!-- Connected Channels -->
      <div style="margin-bottom:28px;">
        <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Connected YouTube Channels</h2>
        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Channel</th>
                  <th>Creator Account</th>
                  <th>Subscribers</th>
                  <th>Connected Date</th>
                  <th>Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($accounts)): ?>
                  <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">No YouTube channels currently authorized.</td></tr>
                <?php else: ?>
                  <?php foreach ($accounts as $acc): ?>
                    <tr>
                      <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                          <?php if (!empty($acc['channel_thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($acc['channel_thumbnail_url']) ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                          <?php else: ?>
                            <div style="width:32px; height:32px; border-radius:50%; background:#ef4444; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff;">▶</div>
                          <?php endif; ?>
                          <div>
                            <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($acc['channel_title']) ?></div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($acc['channel_id']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div style="font-size:13px; color:#fff; font-weight:600;"><?= htmlspecialchars($acc['owner_username']) ?></div>
                        <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($acc['owner_email']) ?></div>
                      </td>
                      <td><strong><?= number_format((int)$acc['subscriber_count']) ?></strong></td>
                      <td style="color:var(--text-muted); font-size:12px;"><?= date('M j, Y', strtotime($acc['connected_at'])) ?></td>
                      <td>
                        <span class="badge <?= $acc['is_connected'] ? 'badge-green' : 'badge-red' ?>">
                          <?= $acc['is_connected'] ? 'ACTIVE' : 'DISCONNECTED' ?>
                        </span>
                      </td>
                      <td style="text-align:right;">
                        <?php if ($acc['is_connected']): ?>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Disconnect this YouTube channel?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="disconnect_channel">
                            <input type="hidden" name="channel_id" value="<?= htmlspecialchars($acc['id']) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color:#f87171;">Disconnect</button>
                          </form>
                        <?php else: ?>
                          <span style="font-size:12px; color:var(--text-muted);">Revoked</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Upload History -->
      <div>
        <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Recent YouTube Upload Activity</h2>
        <div class="glass-card" style="padding:0; overflow:hidden;">
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Video Title</th>
                  <th>Channel</th>
                  <th>Creator</th>
                  <th>Privacy</th>
                  <th>Status</th>
                  <th>Published Time</th>
                  <th style="text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($uploads)): ?>
                  <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">No YouTube uploads registered yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($uploads as $up): ?>
                    <tr>
                      <td style="font-weight:700; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($up['title']) ?>
                      </td>
                      <td><?= htmlspecialchars($up['channel_title'] ?? 'Direct Channel') ?></td>
                      <td><?= htmlspecialchars($up['creator_username'] ?? 'User') ?></td>
                      <td><span class="badge badge-secondary"><?= strtoupper($up['privacy_status']) ?></span></td>
                      <td>
                        <span class="badge <?= $up['upload_status'] === 'published' ? 'badge-green' : ($up['upload_status'] === 'failed' ? 'badge-red' : 'badge-cyan') ?>">
                          <?= strtoupper($up['upload_status']) ?>
                        </span>
                        <?php if (!empty($up['error_message'])): ?>
                          <div style="font-size:10px; color:#f87171; max-width:180px; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($up['error_message']) ?>">
                            <?= htmlspecialchars($up['error_message']) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td style="color:var(--text-muted); font-size:12px;">
                        <?= $up['published_at'] ? time_ago($up['published_at']) : time_ago($up['created_at']) ?>
                      </td>
                      <td style="text-align:right;">
                        <?php if ($up['upload_status'] === 'published' && !empty($up['youtube_url'])): ?>
                          <a href="<?= htmlspecialchars($up['youtube_url']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="color:#ef4444;">
                            Watch ↗
                          </a>
                        <?php elseif ($up['upload_status'] === 'failed'): ?>
                          <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="retry_upload">
                            <input type="hidden" name="upload_id" value="<?= htmlspecialchars($up['id']) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Retry</button>
                          </form>
                        <?php else: ?>
                          <span style="font-size:12px; color:var(--text-muted);">Queued</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
