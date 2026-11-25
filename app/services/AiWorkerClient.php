<?php
// app/services/AiWorkerClient.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

class AiWorkerClient {
    public static function getWorkerBaseUrl(): string {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key IN ('ai_worker_endpoint', 'cloud_worker_url') AND setting_value IS NOT NULL AND setting_value != '' ORDER BY (setting_key = 'cloud_worker_url') DESC LIMIT 1");
            $stmt->execute();
            $url = $stmt->fetchColumn();
            if (!empty($url)) {
                return rtrim($url, '/');
            }
        } catch (Exception $e) {
            // fallback
        }
        return DEFAULT_AI_WORKER_URL;
    }

    public static function getHealth(): array {
        return self::checkHealth();
    }

    public static function checkHealth(): array {
        $url = self::getWorkerBaseUrl() . '/api/v1/health';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return [
                'online' => true,
                'status_code' => 200,
                'data' => $data,
                'worker_url' => self::getWorkerBaseUrl()
            ];
        }

        return [
            'online' => false,
            'status_code' => $httpCode,
            'error' => $error ?: "Worker returned HTTP $httpCode",
            'worker_url' => self::getWorkerBaseUrl()
        ];
    }

    public static function verifyVideo(string $urlOrVideoId): array {
        $endpoint = self::getWorkerBaseUrl() . '/api/v1/videos/youtube/verify';
        return self::postJson($endpoint, ['url' => $urlOrVideoId], 30);
    }

    public static function dispatchJob(string $videoId, int $targetClips = 10, string $style = 'auto', string $aspectRatio = '9:16'): array {
        $endpoint = self::getWorkerBaseUrl() . '/api/v1/clips/generate';
        return self::postJson($endpoint, [
            'video_id' => $videoId,
            'target_count' => $targetClips,
            'target_clips' => $targetClips,
            'burn_subtitles' => true,
            'style' => $style,
            'aspect_ratio' => $aspectRatio
        ], 15);
    }

    public static function getJobStatus(string $jobId): array {
        $endpoint = self::getWorkerBaseUrl() . '/api/v1/jobs/' . urlencode($jobId);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return json_decode($response, true) ?: ['status' => 'error', 'message' => 'Invalid JSON'];
        }

        return ['status' => 'error', 'message' => "Worker unreachable (HTTP $httpCode)"];
    }

    public static function cancelJob(string $jobId): array {
        $endpoint = self::getWorkerBaseUrl() . '/api/v1/clips/cancel/' . urlencode($jobId);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['success' => $httpCode >= 200 && $httpCode < 300];
    }

    public static function postJson(string $url, array $payload, int $timeout = 15, int $retries = 1): array {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = self::getWorkerBaseUrl() . '/' . ltrim($url, '/');
        }
        $attempts = 0;
        $maxAttempts = $retries + 1;
        $json = json_encode($payload);
        $httpCode = 0;
        $error = '';

        while ($attempts < $maxAttempts) {
            $attempts++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json)
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response) {
                $data = json_decode($response, true);
                if ($data !== null) {
                    return ['success' => $httpCode >= 200 && $httpCode < 300, 'code' => $httpCode, 'data' => $data];
                }
            }

            if ($attempts < $maxAttempts) {
                usleep(250000); // 250ms backoff before retry
            }
        }

        return ['success' => false, 'code' => $httpCode, 'error' => $error ?: 'No response from AI worker'];
    }
}
