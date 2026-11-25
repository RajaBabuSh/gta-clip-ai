<?php
require_once __DIR__ . '/../app/config/database.php';

$pdo = Database::getConnection();

// Inspect videos and jobs for user_ids
echo "--- VIDEOS ---" . PHP_EOL;
$stmt = $pdo->query("SELECT id, user_id, project_id, title FROM videos WHERE id = '68b543a6-1b1d-4962-a6d1-d2d5dd74b1be'");
print_r($stmt->fetch());

echo "--- JOBS ---" . PHP_EOL;
$stmt = $pdo->query("SELECT id, user_id, video_id, status FROM processing_jobs WHERE id = '600a303f-42f4-4da4-9795-23d043dff5a7'");
print_r($stmt->fetch());

// Update clips where user_id is null based on job or video
$affected = $pdo->exec("
    UPDATE clips c
    INNER JOIN processing_jobs j ON c.job_id = j.id
    SET c.user_id = j.user_id
    WHERE c.user_id IS NULL AND j.user_id IS NOT NULL
");
echo "Updated clips from jobs: $affected" . PHP_EOL;

$affected2 = $pdo->exec("
    UPDATE clips c
    INNER JOIN videos v ON c.video_id = v.id
    SET c.user_id = v.user_id
    WHERE c.user_id IS NULL AND v.user_id IS NOT NULL
");
echo "Updated clips from videos: $affected2" . PHP_EOL;

// Check user accounts
echo "--- USERS ---" . PHP_EOL;
$stmt = $pdo->query("SELECT id, username, email FROM users");
print_r($stmt->fetchAll());
