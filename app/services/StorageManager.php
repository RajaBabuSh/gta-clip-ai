<?php
// app/services/StorageManager.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class StorageManager {
    public const DRIVER_LOCAL = 'local';
    public const DRIVER_R2 = 'cloudflare_r2';

    /**
     * Get active storage driver ('local' or 'cloudflare_r2')
     */
    public static function getDriver(): string {
        return SystemSetting::get('storage_driver', self::DRIVER_LOCAL);
    }

    /**
     * Get signing secret for local signed URLs
     */
    public static function getSigningSecret(): string {
        $secret = SystemSetting::get('storage_signed_url_secret', '');
        if (empty($secret)) {
            $secret = hash('sha256', (string)APP_URL . '-storage-secret-key-2026');
            SystemSetting::set('storage_signed_url_secret', $secret, 'storage', 'Secret key for signing private download URLs');
        }
        return $secret;
    }

    /**
     * Format a public or direct URL for a given relative or absolute file path
     */
    public static function getUrl(string $path): string {
        if (empty($path)) return '';

        // If path is already a full HTTP(S) URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $driver = self::getDriver();
        $cleanPath = str_replace('\\', '/', $path);
        $cleanPath = ltrim($cleanPath, '/');

        if ($driver === self::DRIVER_R2) {
            $publicDomain = SystemSetting::get('r2_public_domain', '');
            if (!empty($publicDomain)) {
                $domain = rtrim($publicDomain, '/');
                $filename = basename($cleanPath);
                return "$domain/$cleanPath";
            }
        }

        // Default to local URL
        return url($cleanPath);
    }

    /**
     * Generate a tamper-proof signed download URL for private files
     */
    public static function getSignedDownloadUrl(string $path, int $ttlSeconds = 3600, ?string $downloadName = null): string {
        $driver = self::getDriver();

        if ($driver === self::DRIVER_R2) {
            $r2Signed = self::getR2PresignedUrl($path, $ttlSeconds);
            if ($r2Signed) return $r2Signed;
        }

        // Local signed URL
        $expires = time() + $ttlSeconds;
        $secret = self::getSigningSecret();
        $dataToSign = "$path|$expires";
        $signature = hash_hmac('sha256', $dataToSign, $secret);

        $params = [
            'file' => $path,
            'expires' => $expires,
            'sig' => $signature
        ];
        if ($downloadName) {
            $params['name'] = $downloadName;
        }

        return url('api/download.php?' . http_build_query($params));
    }

    /**
     * Verify a signed download request
     */
    public static function verifySignedUrl(string $path, int $expires, string $providedSignature): bool {
        if (time() > $expires) {
            return false; // Expired
        }

        $secret = self::getSigningSecret();
        $expectedSignature = hash_hmac('sha256', "$path|$expires", $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Generate an S3 v4 presigned URL for Cloudflare R2 without requiring AWS SDK
     */
    public static function getR2PresignedUrl(string $objectKey, int $ttlSeconds = 3600): ?string {
        $accountId = SystemSetting::get('r2_account_id', '');
        $bucket = SystemSetting::get('r2_bucket_name', '');
        $accessKey = SystemSetting::get('r2_access_key_id', '');
        $secretKey = SystemSetting::get('r2_secret_access_key', '');

        if (empty($accountId) || empty($bucket) || empty($accessKey) || empty($secretKey)) {
            return null;
        }

        $region = 'auto';
        $service = 's3';
        $host = "{$accountId}.r2.cloudflarestorage.com";
        $endpoint = "https://{$host}/{$bucket}/" . ltrim(str_replace('\\', '/', $objectKey), '/');

        $now = time();
        $dateIso = gmdate('Ymd\THis\Z', $now);
        $dateShort = gmdate('Ymd', $now);

        $credentialScope = "{$dateShort}/{$region}/{$service}/aws4_request";

        $canonicalUri = '/' . $bucket . '/' . ltrim(str_replace('\\', '/', $objectKey), '/');
        $canonicalQuery = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$accessKey}/{$credentialScope}",
            'X-Amz-Date' => $dateIso,
            'X-Amz-Expires' => (string)$ttlSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($canonicalQuery);

        $queryString = http_build_query($canonicalQuery, '', '&', PHP_QUERY_RFC3986);
        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = "host";

        $canonicalRequest = "GET\n{$canonicalUri}\n{$queryString}\n{$canonicalHeaders}\n{$signedHeaders}\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$dateIso}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Derive signing key
        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $dateShort, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return "{$endpoint}?{$queryString}&X-Amz-Signature={$signature}";
    }

    /**
     * Test Cloudflare R2 S3 compatibility connection
     */
    public static function testR2Connection(array $config): array {
        $accountId = trim($config['r2_account_id'] ?? '');
        $bucket = trim($config['r2_bucket_name'] ?? '');
        $accessKey = trim($config['r2_access_key_id'] ?? '');
        $secretKey = trim($config['r2_secret_access_key'] ?? '');

        if (empty($accountId) || empty($bucket) || empty($accessKey) || empty($secretKey)) {
            return [
                'success' => false,
                'message' => 'All Cloudflare R2 credentials (Account ID, Bucket Name, Access Key, Secret Key) are required.'
            ];
        }

        $region = 'auto';
        $service = 's3';
        $host = "{$accountId}.r2.cloudflarestorage.com";
        $url = "https://{$host}/{$bucket}?max-keys=1";

        $now = time();
        $dateIso = gmdate('Ymd\THis\Z', $now);
        $dateShort = gmdate('Ymd', $now);

        $credentialScope = "{$dateShort}/{$region}/{$service}/aws4_request";
        $canonicalUri = '/' . $bucket;
        $canonicalQuery = 'max-keys=1';

        $payloadHash = hash('sha256', '');
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$dateIso}\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";

        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$dateIso}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $dateShort, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Host: {$host}",
                "x-amz-date: {$dateIso}",
                "x-amz-content-sha256: {$payloadHash}",
                "Authorization: {$authHeader}"
            ],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $error,
                'http_code' => $httpCode
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Successfully connected to Cloudflare R2 bucket: ' . $bucket,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => false,
            'message' => 'Cloudflare R2 returned HTTP ' . $httpCode . ': ' . strip_tags((string)$response),
            'http_code' => $httpCode,
            'raw' => substr((string)$response, 0, 300)
        ];
    }

    /**
     * Compute comprehensive storage statistics
     */
    public static function getStorageStats(): array {
        $storageRoot = STORAGE_PATH;
        $clipsDir = $storageRoot . DIRECTORY_SEPARATOR . 'clips';
        $uploadsDir = $storageRoot . DIRECTORY_SEPARATOR . 'uploads';
        $audioDir = $storageRoot . DIRECTORY_SEPARATOR . 'audio';
        $tempDir = $storageRoot . DIRECTORY_SEPARATOR . 'temp';

        $clipsSize = self::dirSize($clipsDir);
        $uploadsSize = self::dirSize($uploadsDir);
        $audioSize = self::dirSize($audioDir);
        $tempSize = self::dirSize($tempDir);

        $totalUsedBytes = $clipsSize + $uploadsSize + $audioSize + $tempSize;

        $freeBytes = @disk_free_space($storageRoot) ?: 0;
        $totalDiskBytes = @disk_total_space($storageRoot) ?: 0;

        return [
            'storage_root' => $storageRoot,
            'driver' => self::getDriver(),
            'clips_bytes' => $clipsSize,
            'clips_count' => self::fileCount($clipsDir),
            'uploads_bytes' => $uploadsSize,
            'uploads_count' => self::fileCount($uploadsDir),
            'audio_bytes' => $audioSize,
            'audio_count' => self::fileCount($audioDir),
            'temp_bytes' => $tempSize,
            'temp_count' => self::fileCount($tempDir),
            'total_used_bytes' => $totalUsedBytes,
            'disk_free_bytes' => $freeBytes,
            'disk_total_bytes' => $totalDiskBytes,
            'r2_free_tier_limit_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB Free Tier
        ];
    }

    /**
     * Purge temporary files, WAV slices, and processing cache
     */
    public static function purgeTempFiles(int $olderThanMinutes = 0): array {
        $storageRoot = STORAGE_PATH;
        $targets = [
            $storageRoot . DIRECTORY_SEPARATOR . 'temp',
            $storageRoot . DIRECTORY_SEPARATOR . 'audio',
            $storageRoot . DIRECTORY_SEPARATOR . 'subtitles'
        ];

        $deletedCount = 0;
        $freedBytes = 0;
        $thresholdTime = time() - ($olderThanMinutes * 60);

        foreach ($targets as $dir) {
            if (!is_dir($dir)) continue;
            $files = glob($dir . DIRECTORY_SEPARATOR . '*');
            if (!$files) continue;

            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($olderThanMinutes === 0 || filemtime($file) < $thresholdTime) {
                        $size = filesize($file) ?: 0;
                        if (@unlink($file)) {
                            $deletedCount++;
                            $freedBytes += $size;
                        }
                    }
                }
            }
        }

        return [
            'deleted_files' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'freed_formatted' => format_bytes($freedBytes)
        ];
    }

    private static function dirSize(string $dir): int {
        if (!is_dir($dir)) return 0;
        $size = 0;
        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                $size += filesize($file) ?: 0;
            } elseif (is_dir($file)) {
                $size += self::dirSize($file);
            }
        }
        return $size;
    }

    private static function fileCount(string $dir): int {
        if (!is_dir($dir)) return 0;
        $count = 0;
        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                $count++;
            }
        }
        return $count;
    }
}
