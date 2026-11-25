<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$cols = $pdo->query("DESCRIBE processing_jobs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
