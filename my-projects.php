<?php
// my-projects.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/models/Project.php';

$user = AuthMiddleware::requireUser();

// Handle new project submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf()) {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Gaming');
        $desc = trim($_POST['description'] ?? '');
        if (!empty($title)) {
            Project::create($user['id'], $title, $desc, $category);
            flash('success', "Project '$title' created!");
            redirect('my-projects.php');
        }
    }
}

$projects = Project::getByUser($user['id']);

$activeNav = 'projects';
$pageTitle = 'My Projects';
require_once __DIR__ . '/views/partials/header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/views/partials/sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Workspace / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Projects</span>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('new-project-modal').style.display='flex'">
          + New Project
        </button>
        <div class="user-profile-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
          <span><?= htmlspecialchars($user['username']) ?></span>
        </div>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Projects Library</h1>
          <p class="page-desc">Organize clips by games, series, Twitch VODs, or YouTube live streams.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('new-project-modal').style.display='flex'">
          <span>📁</span> Create Project
        </button>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php if (empty($projects)): ?>
          <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:48px;">
            <div style="font-size:40px; margin-bottom:12px;">📁</div>
            <h3 style="font-size:18px; font-weight:700; margin-bottom:6px;">No Projects Created Yet</h3>
            <p style="font-size:14px; color:var(--text-muted); margin-bottom:20px;">Create folders to group your highlight clips and stream archives.</p>
            <button class="btn btn-primary" onclick="document.getElementById('new-project-modal').style.display='flex'">Create First Project</button>
          </div>
        <?php else: ?>
          <?php foreach ($projects as $proj): ?>
            <div class="glass-card" style="display:flex; flex-direction:column;">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <span class="badge badge-purple"><?= htmlspecialchars($proj['category']) ?></span>
                <span style="font-size:11px; color:var(--text-dim);"><?= time_ago($proj['updated_at']) ?></span>
              </div>
              <h3 style="font-size:17px; font-weight:700; margin-bottom:8px;"><?= htmlspecialchars($proj['title']) ?></h3>
              <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px; flex-grow:1; line-height:1.4;">
                <?= htmlspecialchars($proj['description'] ?: 'No description provided.') ?>
              </p>
              <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border-color); padding-top:14px; margin-bottom:16px; font-size:12px; color:var(--text-muted);">
                <span>📹 <strong><?= (int)$proj['total_videos'] ?></strong> Streams</span>
                <span>🎬 <strong><?= (int)$proj['total_clips'] ?></strong> Clips</span>
              </div>
              <div style="display:flex; gap:8px;">
                <a href="<?= url('my-clips.php?project_id=' . urlencode($proj['id'])) ?>" class="btn btn-secondary btn-sm" style="flex:1;">
                  Open Clips
                </a>
                <a href="<?= url('create-clips.php?project_id=' . urlencode($proj['id'])) ?>" class="btn btn-primary btn-sm" style="flex:1;">
                  + Add Stream
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Modal: New Project -->
<div id="new-project-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(6px); z-index:999; align-items:center; justify-content:center; padding:20px;">
  <div class="glass-card" style="width:100%; max-width:440px; padding:32px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="font-size:18px; font-weight:700;">Create New Project</h3>
      <button onclick="document.getElementById('new-project-modal').style.display='none'" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Project Title</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. GTA VI RP Highlights" required>
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-control">
          <option value="Gaming" selected>Gaming</option>
          <option value="Just Chatting">Just Chatting</option>
          <option value="Podcast">Podcast</option>
          <option value="IRL">IRL</option>
          <option value="Esports">Esports</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Description (Optional)</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Brief project summary..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Create Project</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
