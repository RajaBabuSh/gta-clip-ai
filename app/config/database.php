<?php
// app/config/database.php
declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    private const HOST = '127.0.0.1';
    private const PORT = 3306;
    private const DBNAME = 'gtaclipai';
    private const USER = 'root';
    private const PASS = '';

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . self::HOST . ";port=" . self::PORT . ";dbname=" . self::DBNAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            try {
                self::$instance = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                die(json_encode([
                    'error' => 'Database connection failed',
                    'message' => 'Unable to connect to MySQL database. Please verify MySQL service is running.'
                ]));
            }
        }
        return self::$instance;
    }

    // Prevent instantiation and cloning
    private function __construct() {}
    private function __clone() {}
}
