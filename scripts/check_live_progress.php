<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT id, status, progress_percentage, current_stage, current_step, clips_rendered_count, worker_heartbeat_at FROM processing_jobs WHERE id = 'e01d5f87-e54e-4da2-99fa-ada16e311a42'");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
