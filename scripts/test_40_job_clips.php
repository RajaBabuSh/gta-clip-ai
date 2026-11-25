<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Clip.php';

$jobId = 'a03b7f43-74d3-40d0-b8e2-5f592f92cd53';
$clips = Clip::getByJob($jobId);
echo "Clips in 40-clip job: " . count($clips) . PHP_EOL;

$valid = 0;
$total_mb = 0;
foreach ($clips as $c) {
    if ($c['file_size'] > 100000) {
        $valid++;
        $total_mb += ($c['file_size'] / (1024*1024));
    }
}
echo "Valid clips (>100KB): {$valid} / " . count($clips) . PHP_EOL;
echo "Total rendered video data: " . round($total_mb, 2) . " MB" . PHP_EOL;
echo "\nFirst 3 clips:\n";
for ($i = 0; $i < 3; $i++) {
    $c = $clips[$i];
    echo "  #{$c['clip_index']}: {$c['title']} | " . round($c['file_size'] / (1024*1024), 2) . " MB | {$c['duration']}s\n";
    echo "      Video: {$c['video_url']}\n";
    echo "      Thumb: {$c['thumbnail_url']}\n";
}
