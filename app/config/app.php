<?php
// app/config/app.php
declare(strict_types=1);

// Root directories
define('ROOT_PATH', dirname(__DIR__, 2));
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'storage');
define('UPLOADS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('CLIPS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'clips');

// Load root .env if present
$envFile = ROOT_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (!isset($_SERVER[$k]) && !isset($_ENV[$k])) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

// Detect base URL dynamically for local XAMPP and production
$envAppUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: null;
if ($envAppUrl) {
    define('APP_URL', rtrim($envAppUrl, '/'));
    $parsedPath = parse_url($envAppUrl, PHP_URL_PATH) ?: '';
    define('BASE_PATH', rtrim($parsedPath, '/'));
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (PHP_SAPI === 'cli') {
        $basePath = '/New folder';
    } else {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = preg_replace('#/(admin|api|scripts).*$#', '', $scriptDir);
        $basePath = rtrim($basePath, '/');
    }
    define('APP_URL', $protocol . $host . $basePath);
    define('BASE_PATH', $basePath);
}

// Application Identity & Branding Constants
define('APP_NAME', $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'EiziClip');
define('APP_TAGLINE', $_ENV['APP_TAGLINE'] ?? getenv('APP_TAGLINE') ?: 'Clip while you watch.');
define('APP_VERSION', '2.0.0');

// AI Worker Microservice Endpoint (Remote Cloud Worker or Local FastAPI)
define('DEFAULT_AI_WORKER_URL', $_ENV['AI_WORKER_URL'] ?? getenv('AI_WORKER_URL') ?: 'http://127.0.0.1:8000');
define('AI_WORKER_API_TOKEN', $_ENV['AI_WORKER_API_TOKEN'] ?? getenv('AI_WORKER_API_TOKEN') ?: 'gtaclip_gpu_secret_token_2026');
define('AI_WORKER_FALLBACK_URL', $_ENV['AI_WORKER_FALLBACK_URL'] ?? getenv('AI_WORKER_FALLBACK_URL') ?: '');

// Quotas and Limits
define('DEFAULT_MONTHLY_QUOTA', 100);
define('MAX_UPLOAD_SIZE_BYTES', 500 * 1024 * 1024); // 500 MB

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400'); // 24 hours
    session_start();
}
