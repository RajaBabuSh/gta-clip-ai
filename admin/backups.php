<?php
// admin/backups.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';
require_once __DIR__ . '/../app/models/Admin.php';

$admin = AdminMiddleware::requirePermission('backups.manage');
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

// Handle Download
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backupDir . '/' . $filename;
    if (file_exists($filepath) && (str_ends_with($filename, '.sql') || str_ends_with($filename, '.zip'))) {
        Admin::logAction($admin['id'], 'backup.download', "Downloaded backup file {$filename}");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    flash('danger', 'Requested backup file not found.');
    redirect('admin/backups.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_db_backup') {
        $timestamp = date('Ymd_His');
        $filename = "gtaclipai_db_backup_{$timestamp}.sql";
        $filepath = $backupDir . '/' . $filename;

        // Use mysqldump if available, or PHP PDO dump
        $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (file_exists($mysqldump)) {
            $cmd = "\"{$mysqldump}\" -u root gtaclipai > \"{$filepath}\"";
            shell_exec($cmd);
        }

        // Verify if file created and non-empty; if not, do manual PDO dump
        if (!file_exists($filepath) || filesize($filepath) < 100) {
            $pdo = Database::getConnection();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $handle = fopen($filepath, 'w');
            fwrite($handle, "-- GTA CLIP AI ULTIMATE Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            foreach ($tables as $t) {
                $create = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
                fwrite($handle, "DROP TABLE IF EXISTS `{$t}`;\n" . $create['Create Table'] . ";\n\n");
                $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $cols = array_map(fn($c) => "`{$c}`", array_keys($r));
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? "NULL" : $pdo->quote((string)$v);
                    }, array_values($r));
                    fwrite($handle, "INSERT INTO `{$t}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
        }

        Admin::logAction($admin['id'], 'backup.create', "Generated database backup {$filename}");
        flash('success', "Database backup created successfully ({$filename}).");
    } elseif ($action === 'delete_backup') {
        $filename = basename($_POST['filename'] ?? '');
        $filepath = $backupDir . '/' . $filename;
        if (file_exists($filepath) && !is_dir($filepath)) {
            @unlink($filepath);
            Admin::logAction($admin['id'], 'backup.delete', "Deleted backup file {$filename}");
            flash('success', "Backup file {$filename} removed.");
        }
    }
    redirect('admin/backups.php');
}

// Scan backups directory
$files = [];
if (is_dir($backupDir)) {
    $scanned = scandir($backupDir);
    foreach ($scanned as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $backupDir . '/' . $item;
        $files[] = [
            'name' => $item,
            'is_dir' => is_dir($path),
            'size' => is_dir($path) ? 0 : filesize($path),
            'mtime' => filemtime($path)
        ];
    }
}
usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$activeNav = 'backups';
$pageTitle = 'Backup Management';
require_once __DIR__ . '/../views/partials/admin_header.php';
?>

<div class="app-layout">
  <?php require_once __DIR__ . '/../views/partials/admin_sidebar.php'; ?>

  <main class="app-main">
    <header class="app-topbar">
      <div>
        <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Admin / </span>
        <span style="font-size:14px; font-weight:700; color:white;">Database & Storage Backups</span>
      </div>
      <div class="topbar-actions">
        <form method="POST" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_db_backup">
          <button type="submit" class="btn btn-primary btn-sm">⚡ Create Instant DB Backup</button>
        </form>
      
        <?php require __DIR__ . '/../views/partials/admin_account_menu.php'; ?>
      </div>
    </header>

    <div class="page-container">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
          <h1 class="page-title">Backup & Disaster Recovery</h1>
          <p class="page-desc">Generate point-in-time SQL database dumps and download system archives before upgrades.</p>
        </div>
      </div>

      <div class="glass-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Backup File / Archive</th>
                <th>Type</th>
                <th>Size</th>
                <th>Created At</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($files)): ?>
                <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No backup archives found.</td></tr>
              <?php else: ?>
                <?php foreach ($files as $f): ?>
                  <tr>
                    <td>
                      <div style="display:flex; align-items:center; gap:8px;">
                        <span><?= $f['is_dir'] ? '📁' : '💾' ?></span>
                        <strong style="color:#fff; font-size:13px;"><?= htmlspecialchars($f['name']) ?></strong>
                      </div>
                    </td>
                    <td>
                      <span class="badge <?= $f['is_dir'] ? 'badge-secondary' : 'badge-purple' ?>">
                        <?= $f['is_dir'] ? 'DIRECTORY SNAPSHOT' : 'SQL DUMP' ?>
                      </span>
                    </td>
                    <td style="color:var(--text-muted); font-size:12px;">
                      <?= $f['is_dir'] ? '—' : format_bytes($f['size']) ?>
                    </td>
                    <td style="color:var(--text-muted); font-size:12px;"><?= date('M j, Y H:i:s', $f['mtime']) ?></td>
                    <td style="text-align:right;">
                      <div style="display:flex; justify-content:flex-end; gap:6px;">
                        <?php if (!$f['is_dir']): ?>
                          <a href="?download=<?= urlencode($f['name']) ?>" class="btn btn-secondary btn-sm" style="color:#6ee7b7;">
                            ⬇️ Download
                          </a>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_backup">
                            <input type="hidden" name="filename" value="<?= htmlspecialchars($f['name']) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color:#f87171;">🗑️</button>
                          </form>
                        <?php else: ?>
                          <span style="font-size:11px; color:var(--text-muted); padding:4px 8px;">Preserved</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../views/partials/admin_footer.php'; ?>
