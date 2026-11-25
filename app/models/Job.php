<?php
// app/models/Job.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../services/AiWorkerClient.php';

class Job {
    public static function findById(string $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT j.*, j.progress_percentage AS progress, j.clips_total_target AS clips_count,
                   v.title AS video_title, v.channel_title, v.thumbnail_url, v.youtube_id
            FROM processing_jobs j
            LEFT JOIN videos v ON j.video_id = v.id
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getByUser(string $userId, int $limit = 20): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT j.*, j.progress_percentage AS progress, j.clips_total_target AS clips_count,
                   v.title AS video_title, v.thumbnail_url, v.youtube_id
            FROM processing_jobs j
            LEFT JOIN videos v ON j.video_id = v.id
            WHERE j.user_id = ?
            ORDER BY j.created_at DESC LIMIT ?
        ");
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getAll(int $limit = 50, ?string $status = null): array {
        $pdo = Database::getConnection();
        $sql = "
            SELECT j.*, j.progress_percentage AS progress, j.clips_total_target AS clips_count,
                   v.title AS video_title, u.username, u.email
            FROM processing_jobs j
            LEFT JOIN videos v ON j.video_id = v.id
            LEFT JOIN users u ON j.user_id = u.id
            " . ($status ? "WHERE j.status = ? " : "") . "
            ORDER BY j.created_at DESC LIMIT ?
        ";
        $stmt = $pdo->prepare($sql);
        if ($status) {
            $stmt->bindValue(1, $status);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countActive(): int {
        $pdo = Database::getConnection();
        return (int)$pdo->query("SELECT COUNT(*) FROM processing_jobs WHERE status IN ('pending', 'processing')")->fetchColumn();
    }

    public static function cancel(string $id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE processing_jobs SET status = 'failed', error_message = 'Cancelled by administrator or user', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Permanently deletes a clipping job and its associated files, clips, and child records.
     * Enforces ownership checks unless $isAdmin is true.
     * Safely cancels running worker tasks before deletion.
     *
     * @param string $id Job UUID
     * @param string|null $userId User ID requesting deletion
     * @param bool $isAdmin Whether caller is administrator
     * @return array Result summary with success, message, and deletion counts
     */
    public static function delete(string $id, ?string $userId = null, bool $isAdmin = false): array {
        $pdo = Database::getConnection();

        // 1. Fetch job
        $stmt = $pdo->prepare("SELECT id, user_id, video_id, status FROM processing_jobs WHERE id = ?");
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        if (!$job) {
            return ['success' => false, 'error' => 'Job not found.', 'code' => 404];
        }

        // 2. Ownership / Authorization Check
        if (!$isAdmin) {
            if (empty($userId) || $job['user_id'] !== $userId) {
                return ['success' => false, 'error' => 'Unauthorized: You do not have permission to delete this job.', 'code' => 403];
            }
        }

        // 3. Stop running worker task safely if currently active
        $statusLower = strtolower($job['status'] ?? '');
        if (in_array($statusLower, ['pending', 'processing', 'queued', 'rendering', 'analyzing'])) {
            try {
                AiWorkerClient::cancelJob($id);
            } catch (\Throwable $e) {
                error_log("Failed to cancel active AI worker job before delete: " . $e->getMessage());
            }
        }

        // 4. Collect clip files and thumbnails before deleting DB rows
        $stmt = $pdo->prepare("SELECT id, file_path, thumbnail_path FROM clips WHERE job_id = ?");
        $stmt->execute([$id]);
        $clips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deletedFiles = [];
        $baseDir = dirname(__DIR__, 2); // C:\xampp\htdocs\New folder

        foreach ($clips as $c) {
            foreach (['file_path', 'thumbnail_path'] as $field) {
                $path = $c[$field] ?? '';
                if (!empty($path)) {
                    $normalized = str_replace('\\', '/', $path);
                    // Strictly check that file belongs to output directory, never uploads
                    if (str_contains($normalized, 'output/')) {
                        $candidates = [
                            $baseDir . '/' . ltrim($normalized, '/'),
                            $baseDir . '/backend/' . ltrim($normalized, '/'),
                        ];
                        foreach ($candidates as $cand) {
                            if (file_exists($cand) && is_file($cand)) {
                                $realCand = realpath($cand);
                                $realNorm = $realCand ? str_replace('\\', '/', $realCand) : '';
                                if ($realNorm && str_contains($realNorm, '/output/') && !str_contains($realNorm, '/uploads/')) {
                                    @unlink($realNorm);
                                    $deletedFiles[] = $realNorm;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Check for any leftover temporary slice or output files matching this job ID
        $outputDirs = [
            $baseDir . '/storage/output',
            $baseDir . '/backend/storage/output'
        ];
        foreach ($outputDirs as $outDir) {
            if (is_dir($outDir)) {
                $matches = glob($outDir . '/*' . $id . '*');
                if ($matches) {
                    foreach ($matches as $matchFile) {
                        if (is_file($matchFile)) {
                            $realMatch = realpath($matchFile);
                            $normMatch = $realMatch ? str_replace('\\', '/', $realMatch) : '';
                            if ($normMatch && str_contains($normMatch, '/output/') && !str_contains($normMatch, '/uploads/')) {
                                @unlink($normMatch);
                                $deletedFiles[] = $normMatch;
                            }
                        }
                    }
                }
            }
        }

        // 5. Database Deletion within Transaction
        $pdo->beginTransaction();
        try {
            // Delete child records cleanly
            $pdo->prepare("DELETE FROM downloads WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM cloud_cost_records WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM generated_clips WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM video_chunks WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM detected_moments WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM clips WHERE job_id = ?")->execute([$id]);

            // Delete job itself
            $delStmt = $pdo->prepare("DELETE FROM processing_jobs WHERE id = ?");
            $delStmt->execute([$id]);

            $pdo->commit();

            // Log audit
            log_audit('job_deleted', $userId, $isAdmin ? ($userId ?? 'admin') : null, 'processing_job', $id, [
                'deleted_clips_count' => count($clips),
                'deleted_files_count' => count(array_unique($deletedFiles))
            ]);

            return [
                'success' => true,
                'message' => 'Clipping job and associated files deleted permanently.',
                'job_id' => $id,
                'deleted_clips' => count($clips),
                'deleted_files' => count(array_unique($deletedFiles))
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log("Failed to delete job $id: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error while deleting job: ' . $e->getMessage(), 'code' => 500];
        }
    }
}
