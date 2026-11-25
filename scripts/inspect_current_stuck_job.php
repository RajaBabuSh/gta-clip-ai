<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();

$stmt = $pdo->query("SELECT id, status, progress_percentage, current_step, error_message FROM processing_jobs WHERE progress_percentage = 10");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobs as $j) {
    echo "{$j['id']} | Status: {$j['status']} | Error: {$j['error_message']}\n";
}
