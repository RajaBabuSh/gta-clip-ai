<?php
require_once __DIR__ . '/../app/config/database.php';

$pdo = Database::getConnection();

// 1. Update clip 1
$pdo->prepare("
    UPDATE clips 
    SET file_path = 'storage/output/clip_68b543a6_1_ed7f90.mp4',
        thumbnail_path = 'storage/output/clip_68b543a6_1_ed7f90_thumb.jpg',
        user_id = 'usr_8feadafa7957d740a996'
    WHERE id = 'ed7f90d5-9db6-4436-a879-cc47dbdbf459'
")->execute();

// 2. Update clip 2
$pdo->prepare("
    UPDATE clips 
    SET file_path = 'storage/output/clip_68b543a6_2_05b668.mp4',
        thumbnail_path = 'storage/output/clip_68b543a6_2_05b668_thumb.jpg',
        user_id = 'usr_8feadafa7957d740a996'
    WHERE id = '05b668fc-2f17-4ad4-be87-01f7fde627a9'
")->execute();

// Also link usr_8feadafa7957d740a996 (user@gtaclip.ai) to this job if needed, or allow both
$pdo->prepare("
    UPDATE processing_jobs 
    SET status = 'PARTIALLY_COMPLETED',
        current_step = '2 of 10 clips successfully generated.',
        user_id = 'usr_8feadafa7957d740a996'
    WHERE id = '600a303f-42f4-4da4-9795-23d043dff5a7'
")->execute();

// Normalize all other clip file paths to use forward slashes
$pdo->exec("UPDATE clips SET file_path = REPLACE(file_path, '\\\\', '/')");

echo "Database updated successfully with thumbnails, forward slashes, and accurate status." . PHP_EOL;
