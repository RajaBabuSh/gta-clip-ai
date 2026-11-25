<?php
// api/download.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/StorageManager.php';

$file = $_GET['file'] ?? '';
$expires = (int)($_GET['expires'] ?? 0);
$sig = $_GET['sig'] ?? '';
$downloadName = $_GET['name'] ?? null;

if (empty($file) || empty($expires) || empty($sig)) {
    http_response_code(400);
    die("Invalid download signature or missing parameters.");
}

if (!StorageManager::verifySignedUrl($file, $expires, $sig)) {
    http_response_code(403);
    die("Download link has expired or has an invalid security signature.");
}

// Normalize and prevent path traversal
$cleanRelPath = str_replace(['..', '\\'], ['', '/'], $file);
$cleanRelPath = ltrim($cleanRelPath, '/');

// Build absolute path
$fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRelPath);

if (!file_exists($fullPath) || !is_file($fullPath)) {
    // Try STORAGE_PATH directly
    $altPath = STORAGE_PATH . DIRECTORY_SEPARATOR . basename($cleanRelPath);
    if (file_exists($altPath) && is_file($altPath)) {
        $fullPath = $altPath;
    } else {
        http_response_code(404);
        die("Requested media file could not be found.");
    }
}

// Determine mime type
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'zip' => 'application/zip'
];
$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

$outFilename = $downloadName ? basename($downloadName) : basename($fullPath);
if (!str_contains($outFilename, '.')) {
    $outFilename .= '.' . $ext;
}

$filesize = filesize($fullPath);

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $outFilename . '"');
header('Content-Length: ' . $filesize);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

$fp = fopen($fullPath, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
    exit;
} else {
    http_response_code(500);
    die("Could not read media stream.");
}
