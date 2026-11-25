<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$tables = ['api_settings', 'system_settings', 'worker_status', 'users'];
foreach ($tables as $t) {
    echo "=== $t ===" . PHP_EOL;
    $cols = $pdo->query("DESCRIBE $t")->fetchAll();
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " (" . $c['Type'] . ")" . PHP_EOL;
    }
}
