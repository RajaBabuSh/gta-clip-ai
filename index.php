<?php
// index.php - EiziClip Official Homepage
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';

$currentUser = get_auth_user();
$pageTitle = 'EiziClip | Clip while you watch.';
require_once __DIR__ . '/views/partials/header.php';
?>

<style>
/* EiziClip Signature Aesthetic */
:root {
  --eizi-bg: #050506;
  --eizi-card: rgba(18, 18, 22, 0.7);
  --eizi-card-hover: rgba(26, 26, 32, 0.9);
  --eizi-border: rgba(255, 255, 255, 0.08);
  --eizi-accent: #8b5cf6;
  --eizi-cyan: #06b6d4;
  --eizi-yellow: #fde047;
  --eizi-green: #10b981;
}

body {
  background-color: var(--eizi-bg);
  color: #f4f4f5;
  font-family: -apple-system, BlinkMacSystemFont, "Google Sans", "Segoe UI", Roboto, sans-serif;
  overflow-x: hidden;
}

/* Nav */
.eizi-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 40px;
  border-bottom: 1px solid var(--eizi-border);
  background: rgba(5, 5, 6, 0.85);
  backdrop-filter: blur(16px);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.eizi-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #fff;
}

.eizi-logo-badge {
  background: linear-gradient(135deg, #8b5cf6, #06b6d4);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 900;
  color: #fff;
}

.eizi-logo-text {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.eizi-logo-tag {
  font-size: 10px;
  background: rgba(139, 92, 246, 0.2);
  color: #c4b5fd;
  border: 1px solid rgba(139, 92, 246, 0.3);
  padding: 2px 6px;
  border-radius: 10px;
  margin-left: 6px;
  font-weight: 700;
}

.eizi-nav-links {
  display: flex;
  align-items: center;
  gap: 24px;
}

.eizi-nav-link {
  color: #a1a1aa;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.15s ease;
}

.eizi-nav-link:hover {
  color: #fff;
}

/* Hero Section */
.hero-wrapper {
  padding: 80px 24px 60px;
  max-width: 1240px;
  margin: 0 auto;
  text-align: center;
}

.hero-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  font-size: 12px;
  font-weight: 600;
  color: #d4d4d8;
  margin-bottom: 24px;
}

.hero-pill-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 10px #10b981;
}

.hero-heading {
  font-size: 64px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -2px;
  margin-bottom: 24px;
  color: #fff;
}

.hero-heading span.gradient-span {
  background: linear-gradient(135deg, #c4b5fd 0%, #22d3ee 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-subhead {
  font-size: 18px;
  color: #a1a1aa;
  max-width: 720px;
  margin: 0 auto 36px;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 40px;
}

.btn-eizi-primary {
  background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
  color: #fff;
  padding: 12px 28px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 15px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
}

.btn-eizi-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 26px rgba(139, 92, 246, 0.6);
  color: #fff;
}

.btn-eizi-secondary {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #fff;
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 15px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.btn-eizi-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.hero-trust-row {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
  font-size: 13px;
  color: #71717a;
  margin-bottom: 56px;
}

.hero-trust-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

/* ================================================= */
/* INTERACTIVE STREAM WALL SHOWCASE (EiziClip Look)  */
/* ================================================= */
.showcase-window {
  background: #09090b;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8), 0 0 40px rgba(139, 92, 246, 0.15);
  overflow: hidden;
  text-align: left;
  margin-bottom: 80px;
}

.window-header {
  padding: 12px 18px;
  background: #111115;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.window-dots {
  display: flex;
  gap: 6px;
}

.window-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.dot-red { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green { background: #10b981; }

.window-title {
  font-size: 12px;
  font-weight: 700;
  color: #a1a1aa;
}

/* Stream Wall Grid Inside Desktop App */
.showcase-wall-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding: 16px;
  background: #050506;
}

@media (max-width: 860px) {
  .showcase-wall-grid {
    grid-template-columns: 1fr;
  }
}

.showcase-stream-tile {
  background: #0d0d11;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.tile-topbar {
  padding: 8px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.tile-video-box {
  position: relative;
  aspect-ratio: 16 / 9;
  background: #000;
  overflow: hidden;
}

.tile-video-box video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tile-bottom-bar {
  padding: 8px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #111115;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.btn-clip-pulse {
  background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
  border: none;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s ease;
}

.btn-clip-pulse:hover {
  transform: scale(1.04);
}

/* Format Preview Drawer underneath showcase */
.showcase-format-drawer {
  padding: 16px;
  background: #09090d;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.format-drawer-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Feature Cards Grid */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 24px;
  max-width: 1240px;
  margin: 0 auto 100px;
  padding: 0 24px;
}

.eizi-card {
  background: var(--eizi-card);
  border: 1px solid var(--eizi-border);
  border-radius: 16px;
  padding: 32px 28px;
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
}

.eizi-card:hover {
  background: var(--eizi-card-hover);
  border-color: rgba(139, 92, 246, 0.3);
  transform: translateY(-3px);
}

.eizi-card-icon {
  font-size: 32px;
  margin-bottom: 16px;
  display: inline-block;
}

.eizi-card-title {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 10px;
}

.eizi-card-desc {
  font-size: 14px;
  color: #a1a1aa;
  line-height: 1.6;
}

/* Pricing Section */
.pricing-section {
  padding: 80px 24px;
  max-width: 1100px;
  margin: 0 auto 80px;
  text-align: center;
}

.pricing-cards-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  margin-top: 48px;
  text-align: left;
}

@media (max-width: 768px) {
  .pricing-cards-grid {
    grid-template-columns: 1fr;
  }
}

.pricing-card {
  background: rgba(18, 18, 22, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 40px 32px;
  display: flex;
  flex-direction: column;
  position: relative;
}

.pricing-card.premium-card {
  border-color: rgba(139, 92, 246, 0.6);
  box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
}

.pricing-tier-tag {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  color: #c4b5fd;
  margin-bottom: 12px;
}

.pricing-price {
  font-size: 44px;
  font-weight: 900;
  color: #fff;
  margin-bottom: 8px;
}

.pricing-price span {
  font-size: 14px;
  font-weight: 500;
  color: #71717a;
}

.pricing-desc {
  font-size: 14px;
  color: #a1a1aa;
  margin-bottom: 28px;
  line-height: 1.5;
}

.pricing-features-list {
  list-style: none;
  padding: 0;
  margin: 0 0 36px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex-grow: 1;
}

.pricing-feature-item {
  font-size: 14px;
  color: #d4d4d8;
  display: flex;
  align-items: center;
  gap: 10px;
}

.pricing-feature-item svg {
  color: #10b981;
  flex-shrink: 0;
}

/* FAQ */
.faq-section {
  max-width: 860px;
  margin: 0 auto 100px;
  padding: 0 24px;
}

.faq-item {
  border-bottom: 1px solid var(--eizi-border);
  padding: 20px 0;
}

.faq-question {
  font-size: 17px;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.faq-answer {
  font-size: 14px;
  color: #a1a1aa;
  margin-top: 10px;
  line-height: 1.6;
}

/* Footer */
.eizi-footer {
  border-top: 1px solid var(--eizi-border);
  padding: 48px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  max-width: 1240px;
  margin: 0 auto;
  font-size: 13px;
  color: #71717a;
}
</style>

<!-- Navbar -->
<nav class="eizi-nav">
  <a href="<?= url('/') ?>" class="eizi-logo">
    <div class="eizi-logo-badge">⚡</div>
    <div class="eizi-logo-text">EiziClip</div>
    <span class="eizi-logo-tag">Clip while you watch.</span>
  </a>

  <div class="eizi-nav-links">
    <a href="#features" class="eizi-nav-link">Features</a>
    <a href="<?= url('workspace.php?mode=wall') ?>" class="eizi-nav-link">Stream Wall</a>
    <a href="#formatting" class="eizi-nav-link">Blurred Background</a>
    <a href="#pricing" class="eizi-nav-link">Pricing</a>
    <a href="#faq" class="eizi-nav-link">FAQ</a>

    <?php if ($currentUser): ?>
      <a href="<?= url('workspace.php') ?>" class="btn-eizi-primary" style="padding:8px 18px; font-size:13px;">
        ⚡ Launch Studio →
      </a>
    <?php else: ?>
      <a href="<?= url('login.php') ?>" class="eizi-nav-link" style="color:#fff; font-weight:600;">Sign In</a>
      <a href="<?= url('workspace.php') ?>" class="btn-eizi-primary" style="padding:8px 18px; font-size:13px;">
        Launch Studio Free →
      </a>
    <?php endif; ?>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero-wrapper">
  
  <div class="hero-pill-badge">
    <span class="hero-pill-dot"></span>
    <span>Every stream. One wall. Clip any tile while the rest keep playing.</span>
  </div>

  <h1 class="hero-heading">
    Clip while you watch.<br>
    <span class="gradient-span">Every stream. One wall.</span>
  </h1>

  <p class="hero-subhead">
    Twitch, YouTube and Kick play side by side. Muted, together, and never hidden behind a tab.
    Press Clip on any stream in the wall — the last 30 seconds are cut while every other stream keeps playing.
  </p>

  <div class="hero-actions">
    <a href="<?= url('workspace.php') ?>" class="btn-eizi-primary">
      <span>⚡</span> Open Watch &amp; Clip Studio
    </a>
    <a href="<?= url('workspace.php?mode=wall') ?>" class="btn-eizi-secondary">
      <span>🧱</span> Launch Live Stream Wall
    </a>
  </div>

  <div class="hero-trust-row">
    <div class="hero-trust-item">
      <span>🛡️</span>
      <span>Clips stay on your machine</span>
    </div>
    <div class="hero-trust-item">
      <span>⚡</span>
      <span>Zero upload delay</span>
    </div>
    <div class="hero-trust-item">
      <span>🚀</span>
      <span>NVIDIA NVENC GPU Accelerated</span>
    </div>
    <div class="hero-trust-item">
      <span>✅</span>
      <span>Free tier works without account</span>
    </div>
  </div>

  <!-- INTERACTIVE STREAM WALL SHOWCASE (Desktop Mockup) -->
  <div class="showcase-window">
    <div class="window-header">
      <div class="window-dots">
        <span class="window-dot dot-red"></span>
        <span class="window-dot dot-yellow"></span>
        <span class="window-dot dot-green"></span>
      </div>
      <div class="window-title">EiziClip Desktop Studio · Live Stream Wall</div>
      <div style="font-size:11px; color:#10b981; font-weight:700;">● 2 STREAMS PLAYING</div>
    </div>

    <!-- 2 Live Stream Tiles Side-by-Side -->
    <div class="showcase-wall-grid">
      
      <!-- Tile 1: Los Santos Gameplay -->
      <div class="showcase-stream-tile">
        <div class="tile-topbar">
          <span style="font-weight:700; color:#fff;">📹 Gameplay Stream · CarryMinati GTA VI</span>
          <span style="color:#9146ff; font-weight:700;">TWITCH LIVE</span>
        </div>
        <div class="tile-video-box">
          <video src="<?= url('storage/uploads/e8582014-1478-4ee6-928f-a60f18042897.mp4') ?>" autoplay loop muted playsinline></video>
        </div>
        <div class="tile-bottom-bar">
          <button type="button" class="btn-clip-pulse" onclick="simulateDemoClip('CarryMinati GTA VI Highlight')">
            ⚡ Clip Last 30s
          </button>
          <span style="font-size:11px; color:#71717a;">Buffer: 00:30.000</span>
        </div>
      </div>

      <!-- Tile 2: YouTube / Secondary Stream -->
      <div class="showcase-stream-tile">
        <div class="tile-topbar">
          <span style="font-weight:700; color:#fff;">🔴 Streamer Facecam / YouTube Live</span>
          <span style="color:#ef4444; font-weight:700;">YOUTUBE LIVE</span>
        </div>
        <div class="tile-video-box">
          <iframe src="https://www.youtube.com/embed/irNhWMG560k?autoplay=0&mute=1" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
        </div>
        <div class="tile-bottom-bar">
          <button type="button" class="btn-clip-pulse" onclick="simulateDemoClip('YouTube Streamer Reaction')">
            ⚡ Clip Last 30s
          </button>
          <span style="font-size:11px; color:#71717a;">Plays while clipping</span>
        </div>
      </div>

    </div>

    <!-- Real-Time Format Preview Drawer -->
    <div id="formatting" class="showcase-format-drawer">
      <div class="format-drawer-left">
        <span style="font-size:22px;">📐</span>
        <div>
          <div style="font-size:13px; font-weight:800; color:#fff;">Format the clip. Keep the wall live.</div>
          <div style="font-size:11px; color:#71717a;">Choose vertical 9:16 or widescreen before the clip lands. EiziClip adds the blurred background automatically.</div>
        </div>
      </div>

      <div style="display:flex; align-items:center; gap:8px;">
        <span class="eizi-logo-tag">Blurred Background Active</span>
        <a href="<?= url('workspace.php') ?>" class="btn-eizi-primary" style="padding:6px 14px; font-size:12px;">
          Try in Studio →
        </a>
      </div>
    </div>
  </div>

</section>

<!-- Features Grid -->
<section id="features" style="max-width:1240px; margin:0 auto 100px; padding:0 24px;">
  
  <div style="text-align:center; margin-bottom:56px;">
    <div class="hero-pill-badge">EIZICLIP CAPABILITIES</div>
    <h2 style="font-size:40px; font-weight:800; letter-spacing:-1px; color:#fff; margin-bottom:12px;">
      Watching and clipping are the exact same screen.
    </h2>
    <p style="font-size:16px; color:#a1a1aa; max-width:680px; margin:0 auto;">
      The stream keeps playing while EiziClip watches chat, analyzes audio peaks, cuts the clip and gets your export ready.
    </p>
  </div>

  <div class="features-grid">
    
    <!-- 1 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">🧱</div>
      <h3 class="eizi-card-title">Watch several at once</h3>
      <p class="eizi-card-desc">
        Twitch, YouTube and Kick play side by side on one wall. Muted, together, and never hidden behind a tab. Monitor all your favorite creators simultaneously.
      </p>
    </div>

    <!-- 2 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">⚡</div>
      <h3 class="eizi-card-title">Clip without leaving</h3>
      <p class="eizi-card-desc">
        Press Clip on any stream in the wall. The last 30 seconds are cut instantly while every other stream keeps playing. You keep watching. It keeps clipping.
      </p>
    </div>

    <!-- 3 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">💬</div>
      <h3 class="eizi-card-title">Auto clips &amp; chat bursts</h3>
      <p class="eizi-card-desc">
        Chat spikes, keywords and laughing bursts can make the cut for you. Faster-Whisper and acoustic energy detection automatically fill your library while you watch.
      </p>
    </div>

    <!-- 4 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">🎭</div>
      <h3 class="eizi-card-title">Blurred background formatting</h3>
      <p class="eizi-card-desc">
        Choose vertical 9:16 or widescreen before the clip lands. EiziClip scales and blurs the background feed, places the center gameplay sharp, and positions facecam overlays.
      </p>
    </div>

    <!-- 5 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">💻</div>
      <h3 class="eizi-card-title">Clips stay on your machine</h3>
      <p class="eizi-card-desc">
        Clips are cut locally and never uploaded to third-party servers. Hardware accelerated via NVIDIA NVENC with zero cloud render queues or monthly egress fees.
      </p>
    </div>

    <!-- 6 -->
    <div class="eizi-card">
      <div class="eizi-card-icon">📦</div>
      <h3 class="eizi-card-title">Multi-format export</h3>
      <p class="eizi-card-desc">
        Download high-definition MP4 videos, extract MP3 audio files, export SRT/VTT subtitles, or bundle all session clips into a single batch ZIP archive in one click.
      </p>
    </div>

  </div>
</section>

<!-- Pricing Section (Directly from eiziclip.in) -->
<section id="pricing" class="pricing-section">
  
  <div class="hero-pill-badge">NO SURPRISE CHARGES</div>
  <h2 style="font-size:40px; font-weight:800; color:#fff; letter-spacing:-1px; margin-bottom:12px;">
    The free tier is a real wall with a daily limit.
  </h2>
  <p style="font-size:16px; color:#a1a1aa; max-width:620px; margin:0 auto;">
    Premium removes the limit and formats every export. Renew whenever you choose.
  </p>

  <div class="pricing-cards-grid">
    
    <!-- Free Tier -->
    <div class="pricing-card">
      <div class="pricing-tier-tag">Free Tier</div>
      <div class="pricing-price">₹0 <span>/ forever</span></div>
      <p class="pricing-desc">The free tier works without an account. Real stream wall with local clipping.</p>

      <ul class="pricing-features-list">
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Up to 2 streams playing on the wall</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Manual 30-second clip button</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>10 clips per day limit</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Clips stay locally on your machine</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>No credit card required</span>
        </li>
      </ul>

      <a href="<?= url('workspace.php') ?>" class="btn-eizi-secondary" style="width:100%; justify-content:center;">
        Start Free (No Account Needed)
      </a>
    </div>

    <!-- Creator Premium Tier -->
    <div class="pricing-card premium-card">
      <div class="pricing-tier-tag" style="color:#fde047;">★ Creator Premium</div>
      <div class="pricing-price">₹499 <span>/ month</span></div>
      <p class="pricing-desc">Upgrading unlocks unlimited clipping, auto chat burst cuts, and export formatting.</p>

      <ul class="pricing-features-list">
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <strong>Unlimited streams on the live wall</strong>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <strong>Unlimited clips per day</strong>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Auto clips: Chat spikes &amp; RMS audio bursts</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Blurred background vertical 9:16 reframer</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Animated karaoke subtitles (MrBeast / Hormozi)</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Multi-format export (MP4, MP3, SRT) + Batch ZIP</span>
        </li>
        <li class="pricing-feature-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Manual renewal — zero surprise charges</span>
        </li>
      </ul>

      <a href="<?= $currentUser ? url('settings.php') : url('register.php') ?>" class="btn-eizi-primary" style="width:100%; justify-content:center;">
        Upgrade to Premium →
      </a>
    </div>

  </div>
</section>

<!-- FAQ Section -->
<section id="faq" class="faq-section">
  
  <div style="text-align:center; margin-bottom:48px;">
    <div class="hero-pill-badge">FREQUENTLY ASKED QUESTIONS</div>
    <h2 style="font-size:36px; font-weight:800; color:#fff;">Everything you need to know</h2>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFaq(this)">
      <span>How does clipping while watching work?</span>
      <span style="font-size:20px;">+</span>
    </div>
    <div class="faq-answer">
      EiziClip maintains a rolling video buffer in memory for every stream on your wall. When you click "Clip Last 30s" or press the 'C' key, EiziClip instantly cuts and saves that segment without interrupting playback or pausing the stream.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFaq(this)">
      <span>Do my video files or clips get uploaded to an external cloud?</span>
      <span style="font-size:20px;">+</span>
    </div>
    <div class="faq-answer">
      No! All clipping, FFmpeg transcoding, and Whisper speech transcription run locally on your machine. Your clips stay safely stored on your local disk.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFaq(this)">
      <span>Which streaming platforms are supported?</span>
      <span style="font-size:20px;">+</span>
    </div>
    <div class="faq-answer">
      EiziClip natively supports streams from Twitch, YouTube, Kick, and direct local video files (MP4, MKV, MOV).
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFaq(this)">
      <span>How does the blurred background feature work?</span>
      <span style="font-size:20px;">+</span>
    </div>
    <div class="faq-answer">
      When converting 16:9 widescreen gameplay into a 9:16 vertical TikTok/Short, EiziClip scales and applies a boxblur filter to the background video, centering the sharp gameplay feed in the foreground with zero black bars.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFaq(this)">
      <span>Are subscription renewals automatic or manual?</span>
      <span style="font-size:20px;">+</span>
    </div>
    <div class="faq-answer">
      Renewals are manual. There are no recurring auto-charges or surprise debits. You renew whenever you choose, and active access runs until the end of your paid period.
    </div>
  </div>

</section>

<!-- Footer -->
<footer class="eizi-footer">
  <div style="display:flex; align-items:center; gap:8px;">
    <div class="eizi-logo-badge" style="width:24px; height:24px; font-size:12px;">⚡</div>
    <strong style="color:#fff;">EiziClip</strong>
    <span>· Clips stay on your machine.</span>
  </div>

  <div style="display:flex; gap:20px;">
    <a href="<?= url('workspace.php') ?>" style="color:#a1a1aa; text-decoration:none;">Studio</a>
    <a href="<?= url('workspace.php?mode=wall') ?>" style="color:#a1a1aa; text-decoration:none;">Stream Wall</a>
    <a href="#pricing" style="color:#a1a1aa; text-decoration:none;">Pricing</a>
    <a href="<?= url('admin/login.php') ?>" style="color:#71717a; text-decoration:none;">Admin Portal</a>
  </div>
</footer>

<script>
function toggleFaq(el) {
  const ans = el.nextElementSibling;
  const icon = el.querySelector('span:last-child');
  if (ans.style.display === 'none' || !ans.style.display) {
    ans.style.display = 'block';
    icon.textContent = '−';
  } else {
    ans.style.display = 'none';
    icon.textContent = '+';
  }
}

function simulateDemoClip(title) {
  showToast(`⚡ Clipping 30s buffer: "${title}"...`, 'info');
  setTimeout(() => {
    showToast(`🎉 Clip saved locally to your library!`, 'success');
  }, 1200);
}
</script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
