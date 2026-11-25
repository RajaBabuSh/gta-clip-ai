<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT id, job_id, clip_index, title, virality_score, file_path, thumbnail_path, file_size_bytes FROM clips WHERE job_id = 'e01d5f87-e54e-4da2-99fa-ada16e311a42'");
$stmt->execute();
$clips = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Clips in DB for Job e01d5f87-e54e-4da2-99fa-ada16e311a42:\n";
foreach ($clips as $c) {
    $filePath = 'C:/xampp/htdocs/New folder/' . $c['file_path'];
    $thumbPath = 'C:/xampp/htdocs/New folder/' . $c['thumbnail_path'];
    $fileEx = file_exists($filePath);
    $thumbEx = file_exists($thumbPath);
    echo "Clip #{$c['clip_index']}: {$c['title']}\n";
    echo "  - File: {$c['file_path']} (Exists: " . ($fileEx ? "YES" : "NO") . ", Size: " . ($fileEx ? filesize($filePath) : 0) . " bytes)\n";
    echo "  - Thumb: {$c['thumbnail_path']} (Exists: " . ($thumbEx ? "YES" : "NO") . ")\n";
    echo "  - Virality: {$c['virality_score']}%\n";
}
