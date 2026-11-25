<?php
// app/models/CostService.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class CostService {
    // Standard cloud GPU rate defaults ($/hour)
    private const GPU_HOURLY_RATES = [
        'rtx3050'   => 0.20,
        'rtx4090'   => 0.45,
        'a10g'      => 1.00,
        'a100_80gb' => 2.20
    ];

    public static function estimateJobCost(float $durationSeconds, int $clipCount = 10, string $gpuType = 'rtx4090'): array {
        $hourlyRate = self::GPU_HOURLY_RATES[$gpuType] ?? 0.45;
        
        // Processing ratio: typically 8-12% of real-time duration with chunking & NVENC
        $estimatedComputeSeconds = ($durationSeconds * 0.08) + ($clipCount * 2.5);
        $computeHours = $estimatedComputeSeconds / 3600;
        
        $gpuCost = $computeHours * $hourlyRate;
        $storageCost = ($clipCount * 15 * 1024 * 1024 / (1024*1024*1024*1024)) * 0.02; // AWS S3 standard
        $bandwidthCost = ($clipCount * 15 * 1024 * 1024 / (1024*1024*1024)) * 0.05; // 5c per GB egress
        
        $totalCost = $gpuCost + $storageCost + $bandwidthCost;
        $suggestedPrice = max(1.99, $totalCost * 3.5); // 70% gross profit margin

        return [
            'estimated_compute_seconds' => round($estimatedComputeSeconds, 1),
            'gpu_type' => $gpuType,
            'gpu_hourly_rate' => $hourlyRate,
            'gpu_cost_usd' => round($gpuCost, 4),
            'storage_cost_usd' => round($storageCost, 4),
            'bandwidth_cost_usd' => round($bandwidthCost, 4),
            'total_cost_usd' => round($totalCost, 4),
            'suggested_retail_price_usd' => round($suggestedPrice, 2),
            'gross_margin_percent' => round((($suggestedPrice - $totalCost) / $suggestedPrice) * 100, 1)
        ];
    }

    public static function logJobCost(string $jobId, ?string $userId, array $metrics): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO cloud_cost_records (
                    id, job_id, user_id, worker_type, gpu_instance_type, gpu_seconds,
                    compute_duration_seconds, whisper_seconds, ffmpeg_render_seconds,
                    rate_per_hour_usd, total_cost_usd, estimated_cost_usd, actual_cost_usd
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $gpuSec = (float)($metrics['gpu_seconds'] ?? 0.0);
            $estCost = (float)($metrics['estimated_cost'] ?? 0.00);
            $actCost = (float)($metrics['actual_cost'] ?? $estCost);
            $hourlyRate = (float)(self::GPU_HOURLY_RATES[$metrics['gpu_type'] ?? 'rtx4090'] ?? 0.45);

            $stmt->execute([
                generate_uuid(),
                $jobId,
                $userId,
                $metrics['worker_type'] ?? 'GPU_NVENC',
                $metrics['gpu_type'] ?? 'rtx4090',
                $gpuSec,
                $gpuSec,
                (float)($metrics['whisper_seconds'] ?? 0.0),
                (float)($metrics['ffmpeg_seconds'] ?? 0.0),
                $hourlyRate,
                $actCost,
                $estCost,
                $actCost
            ]);
        } catch (Exception $e) {
            error_log("Log Job Cost Error: " . $e->getMessage());
        }
    }

    public static function getMonthlyTotals(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("
                SELECT
                    COUNT(id) as total_jobs,
                    COALESCE(SUM(gpu_seconds), 0) as total_gpu_seconds,
                    COALESCE(SUM(actual_cost_usd), SUM(total_cost_usd), 0) as total_cost_usd
                FROM cloud_cost_records
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['total_jobs' => 0, 'total_gpu_seconds' => 0.0, 'total_cost_usd' => 0.0];
        } catch (Exception $e) {
            error_log("getMonthlyTotals Error: " . $e->getMessage());
            return ['total_jobs' => 0, 'total_gpu_seconds' => 0.0, 'total_cost_usd' => 0.0];
        }
    }

    public static function getRecentRecords(int $limit = 20): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT c.*, u.email as user_email
                FROM cloud_cost_records c
                LEFT JOIN users u ON c.user_id = u.id
                ORDER BY c.created_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getRecentRecords Error: " . $e->getMessage());
            return [];
        }
    }

    public static function getUserCostBreakdown(int $limit = 10): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT 
                    u.id as user_id,
                    u.email,
                    u.full_name,
                    COUNT(c.id) as job_count,
                    COALESCE(SUM(c.gpu_seconds), 0) as total_gpu_seconds,
                    COALESCE(SUM(c.actual_cost_usd), SUM(c.total_cost_usd), 0) as total_cost_usd
                FROM cloud_cost_records c
                JOIN users u ON c.user_id = u.id
                GROUP BY u.id, u.email, u.full_name
                ORDER BY total_cost_usd DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getUserCostBreakdown Error: " . $e->getMessage());
            return [];
        }
    }
}
