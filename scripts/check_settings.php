<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings');
echo json_encode($stmt->fetchAll(PDO::FETCH_KEY_PAIR), JSON_PRETTY_PRINT) . "\n";
