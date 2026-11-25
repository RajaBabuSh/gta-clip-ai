<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$clips = $pdo->query('SELECT id, job_id, file_path, thumbnail_path FROM clips LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($clips);
