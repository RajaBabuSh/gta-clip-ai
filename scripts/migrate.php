<?php
// scripts/migrate.php
// Run database migrations for GTA CLIP AI ULTIMATE

$host = '127.0.0.1';
$port = 3306;
$dbname = 'gtaclipai';
$user = 'root';
$pass = '';

echo "Connecting to MySQL database $dbname at $host:$port...\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ensure database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    echo "Database `$dbname` selected.\n";

    $migrationFile = __DIR__ . '/../database/migrations/001_ultimate_schema.sql';
    if (!file_exists($migrationFile)) {
        die("Error: Migration file not found at $migrationFile\n");
    }

    $sql = file_get_contents($migrationFile);

    // Split SQL by statements safely or execute in blocks
    // Note: PDO multi-query can be executed directly with exec
    echo "Executing 001_ultimate_schema.sql...\n";
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    
    // Execute line by line / statement by statement to provide clear error reporting
    $statements = explode(";\n", $sql);
    $executed = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                // If column exists or similar benign warning, note it
                echo "Warning on statement: " . substr($stmt, 0, 60) . "...\n  -> " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Executed $executed SQL statements successfully.\n";

    // Show tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTotal tables in `$dbname`: " . count($tables) . "\n";
    foreach ($tables as $t) {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
        $count = $countStmt->fetchColumn();
        echo "  - $t ($count records)\n";
    }

} catch (PDOException $e) {
    echo "Fatal Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
