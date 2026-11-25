<?php
// pricing.php - EiziClip Pricing & Plans
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';

$currentUser = get_auth_user();
$pageTitle = 'Pricing · EiziClip';
require_once __DIR__ . '/views/partials/header.php';
?>

<style>
body {
  background-color: #050506;
  color: #f4f4f5;
  font-family: -apple-system, BlinkMacSystemFont, "Google Sans", "Segoe UI", Roboto, sans-serif;
}

.pricing-page-wrapper {
  max-width: 1100px;
  margin: 0 auto;
  padding: 60px 24px 100px;
}

.table-comparison {
  width: 100%;
  border-collapse: collapse;
  margin-top: 60px;
  background: rgba(18, 18, 22, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  overflow: hidden;
}

.table-comparison th, .table-comparison td {
  padding: 16px 24px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 14px;
}

.table-comparison th {
  background: rgba(255, 255, 255, 0.03);
  font-weight: 700;
  color: #fff;
}

.table-comparison td:nth-child(2), .table-comparison td:nth-child(3),
.table-comparison th:nth-child(2), .table-comparison th:nth-child(3) {
  text-align: center;
}
</style>

<!-- Navbar -->
<nav style="display:flex; justify-content:space-between; align-items:center; padding:16px 40px; border-bottom:1px solid rgba(255,255,255,0.08); background:rgba(5,5,6,0.85); backdrop-filter:blur(16px); position:sticky; top:0; z-index:1000;">
  <a href="<?= url('/') ?>" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:#fff;">
    <div style="background:linear-gradient(135deg, #8b5cf6, #06b6d4); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:900; color:#fff;">⚡</div>
    <span style="font-size:20px; font-weight:800;">EiziClip</span>
  </a>

  <div style="display:flex; align-items:center; gap:20px;">
    <a href="<?= url('/#features') ?>" style="color:#a1a1aa; text-decoration:none; font-size:14px;">Features</a>
    <a href="<?= url('workspace.php') ?>" style="color:#a1a1aa; text-decoration:none; font-size:14px;">Studio</a>
    <a href="<?= url('workspace.php?mode=wall') ?>" style="color:#a1a1aa; text-decoration:none; font-size:14px;">Stream Wall</a>
    <?php if ($currentUser): ?>
      <a href="<?= url('dashboard.php') ?>" class="btn btn-primary btn-sm">Dashboard →</a>
    <?php else: ?>
      <a href="<?= url('login.php') ?>" style="color:#fff; text-decoration:none; font-size:14px; font-weight:600;">Sign In</a>
    <?php endif; ?>
  </div>
</nav>

<div class="pricing-page-wrapper">
  
  <div style="text-align:center; margin-bottom:56px;">
    <div style="display:inline-block; padding:6px 16px; border-radius:20px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); font-size:12px; font-weight:700; color:#c4b5fd; margin-bottom:16px;">
      PRICING · EIZICLIP
    </div>
    <h1 style="font-size:48px; font-weight:900; letter-spacing:-1.5px; margin-bottom:16px; color:#fff;">
      Simple, manual pricing.<br>
      <span style="background:linear-gradient(135deg, #c4b5fd 0%, #22d3ee 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">No surprise recurring charges.</span>
    </h1>
    <p style="font-size:17px; color:#a1a1aa; max-width:640px; margin:0 auto; line-height:1.6;">
      The free tier is a real wall with a daily limit. Premium removes the limit and formats every export. Renew whenever you choose.
    </p>
  </div>

  <!-- Cards Grid -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:32px; margin-bottom:60px;">
    
    <!-- Free Tier Card -->
    <div style="background:rgba(18,18,22,0.85); border:1px solid rgba(255,255,255,0.08); border-radius:20px; padding:40px 32px; display:flex; flex-direction:column;">
      <div style="font-size:12px; font-weight:800; text-transform:uppercase; color:#a1a1aa; margin-bottom:8px;">Free Tier</div>
      <div style="font-size:44px; font-weight:900; color:#fff; margin-bottom:6px;">₹0 <span style="font-size:14px; color:#71717a; font-weight:400;">/ forever</span></div>
      <p style="font-size:14px; color:#a1a1aa; margin-bottom:28px;">No account needed. Real stream wall with local clipping.</p>

      <ul style="list-style:none; padding:0; margin:0 0 32px; display:flex; flex-direction:column; gap:12px; font-size:14px; color:#d4d4d8; flex-grow:1;">
        <li>✓ 2 streams on the wall</li>
        <li>✓ Manual 30-second clip button</li>
        <li>✓ 10 clips per day</li>
        <li>✓ Widescreen and vertical preview</li>
        <li>✓ Clips stay on your machine</li>
      </ul>

      <a href="<?= url('workspace.php') ?>" class="btn btn-secondary" style="width:100%; text-align:center;">
        Start Free
      </a>
    </div>

    <!-- Premium Tier Card -->
    <div style="background:rgba(18,18,22,0.85); border:1px solid rgba(139,92,246,0.6); box-shadow:0 0 40px rgba(139,92,246,0.25); border-radius:20px; padding:40px 32px; display:flex; flex-direction:column; position:relative;">
      <div style="position:absolute; top:-12px; right:24px; background:#8b5cf6; color:#fff; font-size:11px; font-weight:800; padding:4px 12px; border-radius:12px;">RECOMMENDED</div>
      <div style="font-size:12px; font-weight:800; text-transform:uppercase; color:#fde047; margin-bottom:8px;">★ Creator Premium</div>
      <div style="font-size:44px; font-weight:900; color:#fff; margin-bottom:6px;">₹499 <span style="font-size:14px; color:#71717a; font-weight:400;">/ month</span></div>
      <p style="font-size:14px; color:#a1a1aa; margin-bottom:28px;">Full access. No limits, auto clips, and blurred background formatting.</p>

      <ul style="list-style:none; padding:0; margin:0 0 32px; display:flex; flex-direction:column; gap:12px; font-size:14px; color:#d4d4d8; flex-grow:1;">
        <li>✓ <strong>Unlimited streams on the wall</strong></li>
        <li>✓ <strong>Unlimited clips per day</strong></li>
        <li>✓ Auto clips: Chat bursts &amp; RMS audio spikes</li>
        <li>✓ Blurred background 9:16 vertical exports</li>
        <li>✓ Animated karaoke subtitles (MrBeast &amp; Hormozi)</li>
        <li>✓ Multi-format: MP4, MP3 audio, SRT subtitles</li>
        <li>✓ Batch ZIP downloads</li>
      </ul>

      <a href="<?= $currentUser ? url('settings.php') : url('register.php') ?>" class="btn btn-primary" style="width:100%; text-align:center; font-weight:700;">
        Upgrade to Premium →
      </a>
    </div>

  </div>

  <!-- Feature Comparison Table -->
  <h2 style="font-size:24px; font-weight:800; color:#fff; margin-top:60px; margin-bottom:16px;">Plan Comparison</h2>
  <table class="table-comparison">
    <thead>
      <tr>
        <th>Feature</th>
        <th>Free</th>
        <th>Premium</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Simultaneous Streams on Wall</td>
        <td>2</td>
        <td><strong style="color:#c4b5fd;">Unlimited</strong></td>
      </tr>
      <tr>
        <td>Daily Clip Limit</td>
        <td>10 / day</td>
        <td><strong style="color:#c4b5fd;">Unlimited</strong></td>
      </tr>
      <tr>
        <td>Watch &amp; Clip Instant 30s Buffer</td>
        <td>✓ Included</td>
        <td>✓ Included</td>
      </tr>
      <tr>
        <td>Auto-Clips (Chat Bursts &amp; Audio Peaks)</td>
        <td>—</td>
        <td>✓ Whisper AI &amp; RMS DSP</td>
      </tr>
      <tr>
        <td>Blurred Background Framing</td>
        <td>—</td>
        <td>✓ Included (9:16 Vertical)</td>
      </tr>
      <tr>
        <td>Animated Word-Level Subtitles</td>
        <td>Basic</td>
        <td>✓ MrBeast, Hormozi, Neon</td>
      </tr>
      <tr>
        <td>Audio Extraction (MP3)</td>
        <td>Standard</td>
        <td>✓ 192k High Bitrate</td>
      </tr>
      <tr>
        <td>Subtitles Export (SRT / VTT)</td>
        <td>✓ Included</td>
        <td>✓ Word-Level Timestamps</td>
      </tr>
      <tr>
        <td>Batch ZIP Archive Export</td>
        <td>—</td>
        <td>✓ One-Click ZIP</td>
      </tr>
      <tr>
        <td>Local Storage &amp; Privacy</td>
        <td>✓ On your machine</td>
        <td>✓ On your machine</td>
      </tr>
      <tr>
        <td>Billing Type</td>
        <td>Free Forever</td>
        <td>Manual Renewal (No Auto-Debits)</td>
      </tr>
    </tbody>
  </table>

</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
