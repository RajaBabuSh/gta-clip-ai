<?php
// scripts/test_authenticated_render.php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/Video.php';

$_SESSION['user_id'] = '982b428b-f3a6-41cc-a8d3-5c60edd0c443'; // rajababu22

$clips = Clip::getByUser($_SESSION['user_id'], 100);
echo "Total clips fetched for rajababu22: " . count($clips) . PHP_EOL;

$valid_count = 0;
$with_posters = 0;
foreach ($clips as $c) {
    if ($c['file_size'] > 100000) {
        $valid_count++;
    }
    if (!empty($c['thumbnail_url'])) {
        $with_posters++;
    }
}
echo "Clips with valid file size (>100KB): " . $valid_count . PHP_EOL;
echo "Clips with thumbnail posters: " . $with_posters . PHP_EOL;

if (!empty($clips)) {
    $first = $clips[0];
    echo "\nSample Clip #1:\n";
    echo "  Title: " . $first['title'] . PHP_EOL;
    echo "  Duration: " . $first['duration'] . "s" . PHP_EOL;
    echo "  Size: " . round($first['file_size'] / (1024*1024), 2) . " MB" . PHP_EOL;
    echo "  Video URL: " . $first['video_url'] . PHP_EOL;
    echo "  Stream URL: " . $first['stream_url'] . PHP_EOL;
    echo "  Thumb URL: " . $first['thumbnail_url'] . PHP_EOL;
}
