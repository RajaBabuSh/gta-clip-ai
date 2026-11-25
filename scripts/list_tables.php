<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($tables, JSON_PRETTY_PRINT);
