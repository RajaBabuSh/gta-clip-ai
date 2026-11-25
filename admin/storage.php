<?php
// admin/storage.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/services/StorageManager.php';

$admin = AdminMiddleware::requirePermission('infra.manage');

$testResult = null;
$signedUrlTest = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    // 1. Purge Temp Files
    if ($action === 'purge_temp') {
        $olderThan = (int)($_POST['older_than_minutes'] ?? 0);
        $res = StorageManager::purgeTempFiles($olderThan);
        log_audit('storage_temp_purged', null, $admin['id'], 'system', 'storage', $res);
        flash('success', "Purged {$res['deleted_files']} temporary files, reclaiming {$res['freed_formatted']} of disk space.");
        redirect('admin/storage.php');
    }

    // 2. Save Storage Settings
    if ($action === 'save_storage_config') {
        $driver = $_POST['storage_driver'] ?? 'local';
        $r2AccountId = trim($_POST['r2_account_id'] ?? '');
        $r2Bucket = trim($_POST['r2_bucket_name'] ?? '');
        $r2AccessKey = trim($_POST['r2_access_key_id'] ?? '');
        $r2SecretKey = trim($_POST['r2_secret_access_key'] ?? '');
        $r2PublicDomain = trim($_POST['r2_public_domain'] ?? '');

        SystemSetting::set('storage_driver', $driver, 'storage', 'Active storage driver: local or cloudflare_r2');
        SystemSetting::set('r2_account_id', $r2AccountId, 'storage', 'Cloudflare Account ID');
        SystemSetting::set('r2_bucket_name', $r2Bucket, 'storage', 'Cloudflare R2 Bucket Name');
        SystemSetting::set('r2_access_key_id', $r2AccessKey, 'storage', 'Cloudflare R2 Access Key ID');
        if (!empty($r2SecretKey)) {
            SystemSetting::set('r2_secret_access_key', $r2SecretKey, 'storage', 'Cloudflare R2 Secret Access Key');
        }
        SystemSetting::set('r2_public_domain', $r2PublicDomain, 'storage', 'Cloudflare R2 Public Domain (Optional)');

        log_audit('storage_config_updated', null, $admin['id'], 'system', 'storage', ['driver' => $driver]);
        flash('success', 'Storage settings saved successfully.');
        redirect('admin/storage.php');
    }

    // 3. Test R2 Connection
    if ($action === 'test_r2') {
        $config = [
            'r2_account_id' => trim($_POST['r2_account_id'] ?? SystemSetting::get('r2_account_id', '')),
            'r2_bucket_name' => trim($_POST['r2_bucket_name'] ?? SystemSetting::get('r2_bucket_name', '')),
            'r2_access_key_id' => trim($_POST['r2_access_key_id'] ?? SystemSetting::get('r2_access_key_id', '')),
            'r2_secret_access_key' => trim($_POST['r2_secret_access_key'] ?? SystemSetting::get('r2_secret_access_key', ''))
        ];
        $testResult = StorageManager::testR2Connection($config);
    }

    // 4. Test Signed URL Generation
    if ($action === 'test_signed_url') {
        $samplePath = 'backend/storage/clips/sample_preview.mp4';
        $ttl = (int)($_POST['signed_ttl'] ?? 3600);
        $signedUrlTest = StorageManager::getSignedDownloadUrl($samplePath, $ttl, 'GTA_Viral_Clip_Sample.mp4');
    }
}

$stats = StorageManager::getStorageStats();
$driver = StorageManager::getDriver();
$r2AccountId = SystemSetting::get('r2_account_id', '');
$r2Bucket = SystemSetting::get('r2_bucket_name', '');
$r2AccessKey = SystemSetting::get('r2_access_key_id', '');
$r2SecretKey = SystemSetting::get('r2_secret_access_key', '');
$r2PublicDomain = SystemSetting::get('r2_public_domain', '');

$activeNav = 'storage';
$pageTitle = 'Storage & Cloudflare R2 Object Storage';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / Infrastructure / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Storage & Cloudflare R2</span>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-<?= $driver === 'cloudflare_r2' ? 'purple' : 'green' ?>">
          <?= $driver === 'cloudflare_r2' ? '☁️ Cloudflare R2' : '💾 Local Fast Disk' ?>
        </span>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container" style="max-width: 1100px; margin: 0 auto;">
      
      <?php if ($testResult): ?>
        <div class="alert <?= $testResult['success'] ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom: 20px;">
          <strong><?= $testResult['success'] ? '✅ Cloudflare R2 Connection Successful!' : '❌ Cloudflare R2 Connection Failed' ?></strong>
          <p style="margin: 6px 0 0; font-size: 13px;"><?= htmlspecialchars($testResult['message']) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($signedUrlTest): ?>
        <div class="glass-card" style="margin-bottom: 20px; border-left: 4px solid #8b5cf6;">
          <h4 style="font-size: 14px; font-weight: 700; color: #c4b5fd; margin-bottom: 6px;">🔐 Generated Signed Download URL (Sample)</h4>
          <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px;">This URL includes an HMAC cryptographic signature and expiration timestamp to protect direct video assets.</p>
          <div style="background: rgba(0,0,0,0.4); padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 12px; word-break: break-all; color: #a7f3d0;">
            <?= htmlspecialchars($signedUrlTest) ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Storage Metrics Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="glass-card" style="padding: 20px;">
          <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Final Rendered Clips</div>
          <div style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 8px 0 4px;"><?= format_bytes($stats['clips_bytes']) ?></div>
          <div style="font-size: 12px; color: #64748b;"><?= number_format($stats['clips_count']) ?> vertical MP4 clips</div>
        </div>

        <div class="glass-card" style="padding: 20px;">
          <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Source Video Ingestion</div>
          <div style="font-size: 24px; font-weight: 800; color: #38bdf8; margin: 8px 0 4px;"><?= format_bytes($stats['uploads_bytes']) ?></div>
          <div style="font-size: 12px; color: #64748b;"><?= number_format($stats['uploads_count']) ?> ingested source files</div>
        </div>

        <div class="glass-card" style="padding: 20px;">
          <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Temp Audio & Chunks</div>
          <div style="font-size: 24px; font-weight: 800; color: #f59e0b; margin: 8px 0 4px;"><?= format_bytes($stats['audio_bytes'] + $stats['temp_bytes']) ?></div>
          <div style="font-size: 12px; color: #64748b;"><?= number_format($stats['audio_count'] + $stats['temp_count']) ?> reclaimable cache files</div>
        </div>

        <div class="glass-card" style="padding: 20px;">
          <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Disk Free Space</div>
          <div style="font-size: 24px; font-weight: 800; color: #10b981; margin: 8px 0 4px;"><?= format_bytes($stats['disk_free_bytes']) ?></div>
          <div style="font-size: 12px; color: #64748b;">Total volume: <?= format_bytes($stats['disk_total_bytes']) ?></div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 24px; margin-bottom: 24px;">
        
        <!-- Storage Configuration Form -->
        <div class="glass-card" style="padding: 24px;">
          <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Storage Driver & Cloudflare R2 Integration</h2>
          <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
            Choose between local storage and Cloudflare R2 (10 GB Free-tier, zero egress bandwidth fees, S3-compatible).
          </p>

          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_storage_config">

            <div class="form-group" style="margin-bottom: 20px;">
              <label class="form-label" style="font-weight: 700;">Storage Driver</label>
              <div style="display: flex; gap: 16px; margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="radio" name="storage_driver" value="local" <?= $driver === 'local' ? 'checked' : '' ?>>
                  <span><strong>Local Private Storage</strong> (Default, zero setup, signed streaming)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="radio" name="storage_driver" value="cloudflare_r2" <?= $driver === 'cloudflare_r2' ? 'checked' : '' ?>>
                  <span><strong>Cloudflare R2 (S3)</strong> (10 GB Free Tier, $0 Egress)</span>
                </label>
              </div>
            </div>

            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 18px; margin-bottom: 20px;">
              <h3 style="font-size: 14px; font-weight: 700; color: #cbd5e1; margin-bottom: 12px;">Cloudflare R2 Credentials (Optional / Free Tier)</h3>
              
              <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12px;">Cloudflare Account ID</label>
                <input type="text" name="r2_account_id" class="form-control" value="<?= htmlspecialchars($r2AccountId) ?>" placeholder="e.g. 5f98a28c3b7a87e..." style="font-size: 13px;">
              </div>

              <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12px;">R2 Bucket Name</label>
                <input type="text" name="r2_bucket_name" class="form-control" value="<?= htmlspecialchars($r2Bucket) ?>" placeholder="e.g. gtaclip-production-clips" style="font-size: 13px;">
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group">
                  <label class="form-label" style="font-size: 12px;">R2 Access Key ID</label>
                  <input type="text" name="r2_access_key_id" class="form-control" value="<?= htmlspecialchars($r2AccessKey) ?>" placeholder="Access key" style="font-size: 13px;">
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size: 12px;">R2 Secret Access Key</label>
                  <input type="password" name="r2_secret_access_key" class="form-control" value="<?= !empty($r2SecretKey) ? '********' : '' ?>" placeholder="Leave blank to keep unchanged" style="font-size: 13px;">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" style="font-size: 12px;">Public Domain or R2.dev URL (Optional)</label>
                <input type="text" name="r2_public_domain" class="form-control" value="<?= htmlspecialchars($r2PublicDomain) ?>" placeholder="https://pub-xxxxxx.r2.dev or https://cdn.yourdomain.com" style="font-size: 13px;">
              </div>
            </div>

            <div style="display: flex; gap: 12px;">
              <button type="submit" class="btn btn-primary" style="flex: 1;">
                💾 Save Storage Configuration
              </button>
            </div>
          </form>

          <form method="POST" style="margin-top: 12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_r2">
            <button type="submit" class="btn btn-secondary" style="width: 100%;">
              ⚡ Test Cloudflare R2 Connection
            </button>
          </form>
        </div>

        <!-- Cleanup and Signed URLs Card -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          
          <!-- Temp Cleanup Card -->
          <div class="glass-card" style="padding: 24px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 8px;">🧹 Temporary Artifact Purge</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
              Purge temporary audio WAV slices, incomplete chunks, and subtitle files without affecting final clips.
            </p>

            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="purge_temp">
              
              <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12px;">Delete Artifacts Older Than</label>
                <select name="older_than_minutes" class="form-control" style="font-size: 13px;">
                  <option value="0">All temporary files (immediate flush)</option>
                  <option value="60">Older than 1 hour</option>
                  <option value="1440" selected>Older than 24 hours</option>
                  <option value="10080">Older than 7 days</option>
                </select>
              </div>

              <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Purge temporary processing chunks? Generated clips will be safely preserved.');">
                Reclaim Disk Space (Purge Temp)
              </button>
            </form>
          </div>

          <!-- Signed URL Testing -->
          <div class="glass-card" style="padding: 24px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 8px;">🔐 Signed URL Generation</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
              Generate time-limited signed links for private content distribution.
            </p>

            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_signed_url">
              
              <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12px;">URL Expiry TTL (Seconds)</label>
                <select name="signed_ttl" class="form-control" style="font-size: 13px;">
                  <option value="900">15 Minutes</option>
                  <option value="3600" selected>1 Hour</option>
                  <option value="86400">24 Hours</option>
                </select>
              </div>

              <button type="submit" class="btn btn-secondary" style="width: 100%;">
                Generate Sample Signed URL
              </button>
            </form>
          </div>

        </div>

      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
