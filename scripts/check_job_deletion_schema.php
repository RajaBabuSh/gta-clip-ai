<?php
require_once __DIR__ . '/../app/config/database.php';
$pdo = Database::getConnection();

echo "--- Foreign Keys to processing_jobs ---\n";
$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_NAME = 'processing_jobs' AND TABLE_SCHEMA = 'gtaclipai'
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']} -> {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']} ({$row['CONSTRAINT_NAME']})\n";
}

echo "\n--- Tables with job_id columns ---\n";
$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE COLUMN_NAME = 'job_id' AND TABLE_SCHEMA = 'gtaclipai'
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']}\n";
}

echo "\n--- Clips columns ---\n";
$stmt = $pdo->query("DESCRIBE clips");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} ({$row['Type']})\n";
}

echo "\n--- Referential Constraints ---\n";
$stmt = $pdo->query("SELECT CONSTRAINT_NAME, TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'gtaclipai'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['TABLE_NAME']} {$r['CONSTRAINT_NAME']}: {$r['DELETE_RULE']}\n";
}
