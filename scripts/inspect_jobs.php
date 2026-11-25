<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

try {
    $pdo = Database::getConnection();

    echo "--- RECENT PROCESSING JOBS ---" . PHP_EOL;
    $stmt = $pdo->query("SELECT * FROM processing_jobs ORDER BY created_at DESC LIMIT 5");
    $jobs = $stmt->fetchAll();
    echo json_encode($jobs, JSON_PRETTY_PRINT) . PHP_EOL;

    echo "--- RECENT CLIPS ---" . PHP_EOL;
    $stmt = $pdo->query("SELECT * FROM clips ORDER BY created_at DESC LIMIT 10");
    $clips = $stmt->fetchAll();
    echo json_encode($clips, JSON_PRETTY_PRINT) . PHP_EOL;

    echo "--- RECENT VIDEOS ---" . PHP_EOL;
    $stmt = $pdo->query("SELECT id, title, youtube_id, file_path, status, created_at FROM videos ORDER BY created_at DESC LIMIT 5");
    $videos = $stmt->fetchAll();
    echo json_encode($videos, JSON_PRETTY_PRINT) . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
