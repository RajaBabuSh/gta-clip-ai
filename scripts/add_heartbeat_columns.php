<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();

try {
    $pdo->exec("ALTER TABLE processing_jobs ADD COLUMN worker_heartbeat_at DATETIME NULL AFTER updated_at");
    echo "Added worker_heartbeat_at column.\n";
} catch (Exception $e) {
    echo "worker_heartbeat_at might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE processing_jobs ADD COLUMN current_stage VARCHAR(64) NULL DEFAULT 'Initialized' AFTER current_step");
    echo "Added current_stage column.\n";
} catch (Exception $e) {
    echo "current_stage might already exist: " . $e->getMessage() . "\n";
}

echo "Database columns verified.\n";
