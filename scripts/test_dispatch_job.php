<?php
// scripts/test_dispatch_job.php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/models/Video.php';

$v = Video::getRecent(1);
if (empty($v)) die("No video found\n");
$video = $v[0];
echo "Testing dispatch with video ID: " . $video['id'] . "\n";

$res = AiWorkerClient::dispatchJob($video['id'], 10, 'auto', '9:16');
print_r($res);
