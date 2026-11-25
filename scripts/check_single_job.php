<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT id, status, progress_percentage, current_step, checkpoint_chunk_index, moments_detected_count, clips_selected_count, clips_rendered_count, updated_at FROM processing_jobs WHERE id = ?');
$stmt->execute(['96535937-0fca-4221-9dba-859554acb7a0']);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
