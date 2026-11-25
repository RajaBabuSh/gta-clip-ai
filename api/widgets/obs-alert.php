<?php
// api/widgets/obs-alert.php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Clip.php';

$clips = Clip::getByUser($_GET['token'] ? 'default' : 'all', 1);
$latest = !empty($clips) ? $clips[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OBS Studio Alert Widget</title>
  <style>
    body {
      margin: 0;
      background: transparent;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .obs-banner {
      background: rgba(11, 14, 23, 0.95);
      border: 2px solid #8b5cf6;
      border-radius: 16px;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 0 30px rgba(139, 92, 246, 0.6);
      animation: popIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .icon {
      font-size: 32px;
      animation: pulse 1s infinite alternate;
    }
    .title {
      font-size: 16px;
      font-weight: 800;
      color: white;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .sub {
      font-size: 13px;
      color: #67e8f9;
      font-weight: 600;
    }
    @keyframes popIn {
      from { transform: scale(0.8) translateY(20px); opacity: 0; }
      to { transform: scale(1) translateY(0); opacity: 1; }
    }
    @keyframes pulse {
      from { transform: scale(1); }
      to { transform: scale(1.15); }
    }
  </style>
</head>
<body>
  <div class="obs-banner">
    <div class="icon">🔥</div>
    <div>
      <div class="title">VIRAL MOMENT CLIPPED!</div>
      <div class="sub"><?= htmlspecialchars($latest['title'] ?? 'AI Detected High Energy Reaction') ?></div>
    </div>
  </div>
</body>
</html>
