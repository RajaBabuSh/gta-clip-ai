<?php
require_once __DIR__ . '/../app/config/database.php';

try {
    $pdo = Database::getConnection();
    $sql = file_get_contents(__DIR__ . '/../database/migration_master_upgrade.sql');
    
    // Split statements safely
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo "Master schema migration applied successfully!" . PHP_EOL;

    // Verify created tables
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables (" . count($tables) . "): " . implode(', ', $tables) . PHP_EOL;

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . PHP_EOL;
}
