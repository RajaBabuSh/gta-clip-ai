<?php
// api/stream.php - High-Performance Range-Request Video Streaming Controller
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/Video.php';

// Turn off output buffering and compression for raw byte streaming
if (ob_get_level()) {
    ob_end_clean();
}

$clipId = $_GET['id'] ?? $_GET['clip_id'] ?? null;
$videoId = $_GET['video_id'] ?? null;
$filePathParam = $_GET['file'] ?? null;
$isThumb = isset($_GET['thumb']) || isset($_GET['poster']);
$isDownload = isset($_GET['download']);

$physicalPath = null;
$mimeType = 'video/mp4';
$downloadFilename = 'clip.mp4';

// 1. Resolve Clip file
if ($clipId) {
    $clip = Clip::findById($clipId);
    if (!$clip) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found: Clip not found.";
        exit;
    }

    if ($isThumb) {
        $candidate = $clip['thumbnail_path'] ?? null;
        if ($candidate) {
            $physicalPath = resolve_storage_path($candidate);
        }
        if (!$physicalPath || !file_exists($physicalPath)) {
            // Generate or fallback to default
            $thumbAlt = str_replace('.mp4', '_thumb.jpg', $clip['output_path'] ?? $clip['file_path'] ?? '');
            $physicalPath = resolve_storage_path($thumbAlt);
        }
        $mimeType = 'image/jpeg';
    } else {
        $candidate = $clip['output_path'] ?? $clip['file_path'] ?? $clip['video_path'] ?? null;
        if ($candidate) {
            $physicalPath = resolve_storage_path($candidate);
        }
        $mimeType = 'video/mp4';
        $downloadFilename = 'clip_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($clip['title'] ?? 'video', 0, 30)) . '.mp4';
    }
} elseif ($videoId) {
    $video = Video::findById($videoId);
    if (!$video) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found: Video not found.";
        exit;
    }

    if ($isThumb) {
        $candidate = $video['thumbnail_url'] ?? $video['thumbnail_path'] ?? null;
        if ($candidate && !str_starts_with($candidate, 'http')) {
            $physicalPath = resolve_storage_path($candidate);
        }
        $mimeType = 'image/jpeg';
    } else {
        $candidate = $video['file_path'] ?? null;
        if ($candidate) {
            $physicalPath = resolve_storage_path($candidate);
        }
        $mimeType = $video['mime_type'] ?? 'video/mp4';
        $downloadFilename = 'source_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($video['title'] ?? 'video', 0, 30)) . '.mp4';
    }
} elseif ($filePathParam) {
    // Sanitize path to prevent directory traversal
    $cleaned = str_replace(['..', "\0"], '', $filePathParam);
    $physicalPath = resolve_storage_path($cleaned);
    $ext = strtolower(pathinfo($physicalPath, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $mimeType = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
    } else {
        $mimeType = 'video/mp4';
    }
}

function resolve_storage_path(string $rel): ?string {
    $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    $candidates = [
        ROOT_PATH . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR),
        ROOT_PATH . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR),
        STORAGE_PATH . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR),
        ROOT_PATH . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . basename($rel),
        ROOT_PATH . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rel),
        ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . basename($rel),
        ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rel),
    ];
    foreach ($candidates as $c) {
        if (file_exists($c) && is_file($c)) {
            return $c;
        }
    }
    return null;
}

if (!$physicalPath || !file_exists($physicalPath) || !is_readable($physicalPath)) {
    if ($isThumb) {
        // Output a 1x1 neutral transparent GIF or 404
        header('Content-Type: image/jpeg');
        // Serve a minimal black JPEG or redirect to default placeholder
        $defaultPlaceholder = ROOT_PATH . '/assets/img/default-video.jpg';
        if (file_exists($defaultPlaceholder)) {
            readfile($defaultPlaceholder);
            exit;
        }
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found: Media file is unavailable on storage.";
    exit;
}

$fileSize = filesize($physicalPath);
$fileHandle = fopen($physicalPath, 'rb');

if (!$fileHandle) {
    http_response_code(500);
    echo "500 Internal Server Error: Failed to open stream.";
    exit;
}

// Support HTTP HEAD requests for quick duration/metadata probing
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    fclose($fileHandle);
    exit;
}

// Serve Images directly with caching
if (str_starts_with($mimeType, 'image/')) {
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($physicalPath)));
    readfile($physicalPath);
    fclose($fileHandle);
    exit;
}

// Handle Partial Content Range Requests for HTML5 Video
$start = 0;
$end = $fileSize - 1;
$isRangeRequest = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    $rangeHeader = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $rangeHeader, $matches)) {
        $isRangeRequest = true;
        $start = (int)$matches[1];
        if (!empty($matches[2])) {
            $end = (int)$matches[2];
        }
        if ($start > $end || $start >= $fileSize) {
            http_response_code(416); // Requested Range Not Satisfiable
            header("Content-Range: bytes */$fileSize");
            fclose($fileHandle);
            exit;
        }
        $end = min($end, $fileSize - 1);
    }
}

$length = $end - $start + 1;

if ($isRangeRequest) {
    http_response_code(206); // Partial Content
    header("Content-Range: bytes $start-$end/$fileSize");
} else {
    http_response_code(200);
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache, must-revalidate');

if ($isDownload) {
    header('Content-Disposition: attachment; filename="' . addslashes($downloadFilename) . '"');
} else {
    header('Content-Disposition: inline; filename="' . addslashes($downloadFilename) . '"');
}

// Fast buffered chunk streaming (64KB chunks)
fseek($fileHandle, $start);
$bytesRemaining = $length;
$chunkSize = 64 * 1024; // 64 KB

while ($bytesRemaining > 0 && !feof($fileHandle) && !connection_aborted()) {
    $bytesToRead = min($bytesRemaining, $chunkSize);
    $data = fread($fileHandle, $bytesToRead);
    if ($data === false) {
        break;
    }
    echo $data;
    flush();
    $bytesRemaining -= strlen($data);
}

fclose($fileHandle);
exit;
