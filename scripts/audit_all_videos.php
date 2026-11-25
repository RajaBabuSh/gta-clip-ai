<?php
// scripts/audit_all_videos.php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

$pdo = Database::getConnection();
$videos = $pdo->query("SELECT id, title, source_type, file_path, duration_seconds FROM videos")->fetchAll(PDO::FETCH_ASSOC);

echo "Auditing all videos in database with FFprobe:\n";
echo "=============================================\n";

foreach ($videos as $v) {
    echo "Video ID: {$v['id']}\n";
    echo "  Title: {$v['title']}\n";
    echo "  Source Type: {$v['source_type']}\n";
    echo "  Stored File Path: {$v['file_path']}\n";

    $rawPath = $v['file_path'];
    $cleanPath = str_replace('\\', '/', $rawPath);
    $fullPath = ROOT_PATH . '/' . $cleanPath;

    $exists = file_exists($fullPath);
    echo "  File Exists on Disk: " . ($exists ? "YES (" . filesize($fullPath) . " bytes)" : "NO ($fullPath)") . "\n";

    if ($exists) {
        $cmd = "ffprobe.exe -v error -show_entries format=duration -show_streams -of json \"$fullPath\"";
        $probe = shell_exec($cmd);
        $json = json_decode($probe, true);
        $hasVideo = false;
        $hasAudio = false;
        if (!empty($json['streams'])) {
            foreach ($json['streams'] as $s) {
                if ($s['codec_type'] === 'video') $hasVideo = true;
                if ($s['codec_type'] === 'audio') $hasAudio = true;
            }
        }
        echo "  FFprobe Streams: Video=" . ($hasVideo ? 'YES' : 'NO') . " | Audio=" . ($hasAudio ? 'YES' : 'NO') . "\n";
        echo "  FFprobe Duration: " . ($json['format']['duration'] ?? 'UNKNOWN') . "s\n";
    }
    echo "\n";
}
