<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$users = $pdo->query("SELECT id, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
