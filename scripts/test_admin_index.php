<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/database.php';

@session_start();
$pdo = Database::getConnection();
$adm = $pdo->query("SELECT id FROM admins WHERE role='superadmin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['admin_id'] = $adm['id'];

ob_start();
include __DIR__ . '/../admin/index.php';
$html = ob_get_clean();

echo "INDEX_STATUS: SUCCESS\n";
echo "HTML_BYTES: " . strlen($html) . "\n";
echo "CONTAINS_DASHBOARD: " . (str_contains($html, 'Platform Master Dashboard') ? 'YES' : 'NO') . "\n";
