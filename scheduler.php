<?php
// scheduler.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/UploadScheduler.php';
require_once __DIR__ . '/app/models/YouTubeAccount.php';
require_once __DIR__ . '/app/models/Clip.php';

$user = AuthMiddleware::requireUser();
$ytAccount = YouTubeAccount::getConnectedAccount($user['id']);
$schedule = UploadScheduler::getSchedule($user['id']);
$nextSlot = UploadScheduler::getNextSlot($schedule);

$clips = Clip::getByUser($user['id'], 20);

$activeNav = 'scheduler';
$pageTitle = 'Automated Upload Scheduler';
$topbarTitle = 'Automated Upload Scheduler';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/views/partials/topbar.php'; ?>

    <div class="page-container" style="max-width: 1040px; margin: 0 auto; padding: 24px;">
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title" style="font-size:26px; font-weight:800; color:white; display:flex; align-items:center; gap:10px;">
            ⏰ Twice-Daily Automated Upload Scheduler
          </h1>
          <p class="page-desc" style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Configure peak publishing windows for YouTube Shorts (Default 12:00 PM & 7:00 PM IST) with manual review or automatic publishing.
          </p>
        </div>
        <div style="display:flex; gap:10px;">
          <button onclick="handleTriggerUploadNow()" class="btn btn-danger btn-sm">
            ⚡ Upload Now (Run Next Slot)
          </button>
          <a href="<?= url('upload-history.php') ?>" class="btn btn-secondary btn-sm">
            📜 Upload History →
          </a>
        </div>
      </div>

      <!-- Schedule Status Banner -->
      <div class="glass-card" style="padding:24px; margin-bottom:28px; border-left:4px solid <?= $schedule['is_paused'] ? '#f59e0b' : '#10b981' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
          <div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="badge <?= $schedule['is_paused'] ? 'badge-yellow' : 'badge-green' ?>">
                <?= $schedule['is_paused'] ? '⏸ SCHEDULER PAUSED' : 'ACTIVE SCHEDULE' ?>
              </span>
              <span class="badge badge-purple">
                <?= $schedule['publishing_mode'] === 'auto_publishing' ? 'MODE B: AUTO-PUBLISHING' : 'MODE A: MANUAL APPROVAL' ?>
              </span>
            </div>
            <div style="font-size:15px; font-weight:700; color:white; margin-top:8px;">
              Next Scheduled Trigger: <span style="color:#38bdf8;"><?= $nextSlot['time'] ?></span> (Slot <?= $nextSlot['slot'] ?>)
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
              Timezone: <strong><?= htmlspecialchars($schedule['timezone']) ?></strong> • 
              Target: <strong><?= $schedule['daily_quantity'] ?> Shorts/Day</strong> • 
              Channel: <strong><?= $ytAccount ? htmlspecialchars($ytAccount['channel_title']) : 'Not Connected' ?></strong>
            </div>
          </div>

          <div style="display:flex; gap:10px;">
            <button onclick="handleTogglePause()" class="btn <?= $schedule['is_paused'] ? 'btn-success' : 'btn-secondary' ?> btn-sm">
              <?= $schedule['is_paused'] ? '▶ Resume Scheduler' : '⏸ Pause Scheduler' ?>
            </button>
            <button onclick="handleSkipNext()" class="btn btn-secondary btn-sm">
              ⏭ Skip Next Upload
            </button>
          </div>
        </div>
      </div>

      <!-- Configuration Form -->
      <div style="display:grid; grid-template-columns: 1.3fr 1fr; gap:24px; margin-bottom:28px;">
        <div class="glass-card" style="padding:28px;">
          <h2 style="font-size:18px; font-weight:800; color:white; margin-bottom:16px;">
            ⚙️ Publishing Schedule & Timing
          </h2>

          <form id="scheduler-form" onsubmit="handleSaveSchedule(event)">
            <!-- Publishing Mode Selection -->
            <div class="form-group" style="margin-bottom:20px;">
              <label style="font-size:13px; font-weight:700; color:white; margin-bottom:8px; display:block;">
                Publishing Operating Mode:
              </label>
              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <label style="background:rgba(255,255,255,0.03); border:1px solid <?= $schedule['publishing_mode'] === 'manual_approval' ? 'var(--primary)' : 'rgba(255,255,255,0.1)' ?>; border-radius:10px; padding:14px; cursor:pointer;">
                  <input type="radio" name="publishing_mode" value="manual_approval" <?= $schedule['publishing_mode'] === 'manual_approval' ? 'checked' : '' ?> style="margin-right:6px;">
                  <strong style="color:white; display:block; margin-bottom:4px;">Mode A: Manual Approval</strong>
                  <span style="font-size:11px; color:var(--text-muted);">AI creates clips. You review and click approve before publishing.</span>
                </label>
                <label style="background:rgba(255,255,255,0.03); border:1px solid <?= $schedule['publishing_mode'] === 'auto_publishing' ? 'var(--primary)' : 'rgba(255,255,255,0.1)' ?>; border-radius:10px; padding:14px; cursor:pointer;">
                  <input type="radio" name="publishing_mode" value="auto_publishing" <?= $schedule['publishing_mode'] === 'auto_publishing' ? 'checked' : '' ?> style="margin-right:6px;">
                  <strong style="color:white; display:block; margin-bottom:4px;">Mode B: Auto-Publishing</strong>
                  <span style="font-size:11px; color:var(--text-muted);">System automatically publishes top approved clips at the scheduled times.</span>
                </label>
              </div>
            </div>

            <!-- Upload Slots -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
              <div class="form-group">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                  First Upload Slot (Default 12:00 PM IST)
                </label>
                <input type="time" name="slot_1_time" class="form-control" value="<?= htmlspecialchars($schedule['slot_1_time']) ?>" required style="width:100%;">
              </div>
              <div class="form-group">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                  Second Upload Slot (Default 7:00 PM IST)
                </label>
                <input type="time" name="slot_2_time" class="form-control" value="<?= htmlspecialchars($schedule['slot_2_time']) ?>" required style="width:100%;">
              </div>
            </div>

            <!-- Timezone & Daily Quantity -->
            <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:16px; margin-bottom:18px;">
              <div class="form-group">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                  Timezone
                </label>
                <select name="timezone" class="form-control" style="width:100%;">
                  <option value="Asia/Kolkata" <?= $schedule['timezone'] === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST, UTC+5:30)</option>
                  <option value="America/New_York" <?= $schedule['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                  <option value="Europe/London" <?= $schedule['timezone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT/BST)</option>
                  <option value="UTC" <?= $schedule['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                </select>
              </div>
              <div class="form-group">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                  Daily Quantity
                </label>
                <select name="daily_quantity" class="form-control" style="width:100%;">
                  <option value="1" <?= $schedule['daily_quantity'] == 1 ? 'selected' : '' ?>>1 Short/day</option>
                  <option value="2" <?= $schedule['daily_quantity'] == 2 ? 'selected' : '' ?>>2 Shorts/day (Recommended)</option>
                  <option value="3" <?= $schedule['daily_quantity'] == 3 ? 'selected' : '' ?>>3 Shorts/day</option>
                  <option value="4" <?= $schedule['daily_quantity'] == 4 ? 'selected' : '' ?>>4 Shorts/day</option>
                </select>
              </div>
            </div>

            <!-- Privacy & Title Template -->
            <div class="form-group" style="margin-bottom:18px;">
              <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                Default Upload Privacy:
              </label>
              <select name="preferred_privacy" class="form-control" style="width:100%;">
                <option value="private" <?= $schedule['preferred_privacy'] === 'private' ? 'selected' : '' ?>>Private (Recommended for verification)</option>
                <option value="unlisted" <?= $schedule['preferred_privacy'] === 'unlisted' ? 'selected' : '' ?>>Unlisted</option>
                <option value="public" <?= $schedule['preferred_privacy'] === 'public' ? 'selected' : '' ?>>Public (Direct Feed Push)</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:24px;">
              <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px; display:block;">
                Title Template:
              </label>
              <input type="text" name="title_template" class="form-control" value="<?= htmlspecialchars($schedule['title_template'] ?? '{title} #Shorts #GTA5') ?>" style="width:100%;">
              <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">Available tags: <code>{title}</code>, <code>{virality}</code>, <code>{game}</code></div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
              💾 Save Schedule Settings
            </button>
          </form>
        </div>

        <!-- Right Side: Scheduled Queue & Rules -->
        <div style="display:flex; flex-direction:column; gap:20px;">
          <div class="glass-card" style="padding:24px;">
            <h3 style="font-size:16px; font-weight:800; color:white; margin-bottom:12px;">🛡️ Safety & Quota Rules</h3>
            <ul style="font-size:13px; color:var(--text-muted); line-height:1.7; padding-left:18px; margin:0;">
              <li><strong>Duplicate Detection:</strong> System tracks video hashes and never uploads the same clip twice.</li>
              <li><strong>Status Integrity:</strong> Never publishes clips marked as rejected by the creator.</li>
              <li><strong>Strict Authorization:</strong> Auto-publishing only runs if Mode B is explicitly chosen by the user.</li>
              <li><strong>Daily API Quota:</strong> Monitors YouTube Data API 10,000 unit limit and pauses if quota is low.</li>
            </ul>
          </div>

          <div class="glass-card" style="padding:24px;">
            <h3 style="font-size:16px; font-weight:800; color:white; margin-bottom:12px;">🎬 Top Queue for Publishing</h3>
            <?php if (empty($clips)): ?>
              <p style="font-size:13px; color:var(--text-muted);">No clips in library. Ingest a stream to fill the queue.</p>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach (array_slice($clips, 0, 4) as $qc): ?>
                  <div style="display:flex; align-items:center; gap:10px; background:rgba(255,255,255,0.02); padding:8px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:16px;">🔥</div>
                    <div style="flex:1; overflow:hidden;">
                      <div style="font-size:12px; font-weight:700; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($qc['title']) ?>
                      </div>
                      <div style="font-size:10px; color:var(--text-dim);">
                        Score: <?= round((float)$qc['virality_score'] * ($qc['virality_score'] > 1 ? 1 : 100)) ?>% • <?= round((float)$qc['duration'], 1) ?>s
                      </div>
                    </div>
                    <span class="badge badge-green" style="font-size:10px;">Ready</span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
async function handleSaveSchedule(e) {
  e.preventDefault();
  const form = document.getElementById('scheduler-form');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  try {
    const res = await fetch('api/scheduler.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const resData = await res.json();
    if (res.ok && resData.success) {
      showToast('Schedule settings saved successfully!', 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(resData.message || 'Failed to save schedule', 'error');
    }
  } catch (err) {
    showToast('Failed to save schedule: ' + err.message, 'error');
  }
}

async function handleTogglePause() {
  try {
    const res = await fetch('api/scheduler.php?action=toggle_pause', { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'info');
      setTimeout(() => location.reload(), 1000);
    }
  } catch (e) {
    showToast('Error toggling pause: ' + e.message, 'error');
  }
}

async function handleSkipNext() {
  if (!confirm('Skip next scheduled publishing slot?')) return;
  try {
    const res = await fetch('api/scheduler.php?action=skip_next', { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Next scheduled slot skipped.', 'info');
      setTimeout(() => location.reload(), 1000);
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  }
}

async function handleTriggerUploadNow() {
  if (!confirm('Publish the top approved clip from your queue immediately?')) return;
  try {
    const res = await fetch('api/scheduler.php?action=upload_now', { method: 'POST' });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('Successfully published: ' + data.youtube_url, 'success');
      setTimeout(() => location.reload(), 2000);
    } else {
      showToast(data.message || 'Upload failed.', 'error');
    }
  } catch (e) {
    showToast('Error triggering upload: ' + e.message, 'error');
  }
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
