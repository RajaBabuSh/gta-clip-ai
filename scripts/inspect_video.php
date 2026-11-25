<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = '68b543a6-1b1d-4962-a6d1-d2d5dd74b1be'");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
