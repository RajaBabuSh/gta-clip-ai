<?php
// scripts/make_backup.php
$baseDir = dirname(__DIR__);
$backupDir = $baseDir . '/backups/pre_superadmin_backup_' . date('Ymd_His');

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

function copyRecursive($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0777, true);
    while (false !== ($file = readdir($dir))) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                copyRecursive($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

$dirsToBackup = ['admin', 'app', 'views', 'api'];
foreach ($dirsToBackup as $d) {
    if (is_dir($baseDir . '/' . $d)) {
        copyRecursive($baseDir . '/' . $d, $backupDir . '/' . $d);
        echo "Backed up folder: $d\n";
    }
}

if (file_exists($baseDir . '/.env')) {
    copy($baseDir . '/.env', $backupDir . '/.env');
    echo "Backed up .env\n";
}

// Backup MySQL Database Dump as SQL
require_once $baseDir . '/app/config/database.php';
$pdo = Database::getConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$sqlDump = "-- Pre-SuperAdmin Database Backup " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n" . $createTable . ";\n\n";
    
    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $colNames = implode('`, `', $cols);
        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($pdo) {
                if ($v === null) return "NULL";
                return $pdo->quote((string)$v);
            }, array_values($row));
            $sqlDump .= "INSERT INTO `$table` (`$colNames`) VALUES (" . implode(', ', $vals) . ");\n";
        }
        $sqlDump .= "\n";
    }
}

file_put_contents($backupDir . '/database_backup.sql', $sqlDump);
echo "Backed up database to: $backupDir/database_backup.sql\n";
echo "SUCCESS: Complete Pre-SuperAdmin Backup created at: $backupDir\n";
