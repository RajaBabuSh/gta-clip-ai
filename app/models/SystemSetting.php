<?php
// app/models/SystemSetting.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class SystemSetting {
    private static array $cache = [];

    public static function get(string $key, $default = null) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                self::$cache[$key] = $val;
                return $val;
            }
        } catch (Exception $e) {
            error_log("SystemSetting::get Error: " . $e->getMessage());
        }
        return $default;
    }

    public static function set(string $key, string $value, string $group = 'general', ?string $description = null, int $isPublic = 0): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_group, description, is_public)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)
            ");
            $res = $stmt->execute([$key, $value, $group, $description, $isPublic]);
            self::$cache[$key] = $value;
            return $res;
        } catch (Exception $e) {
            error_log("SystemSetting::set Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getByGroup(string $group): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value, description, is_public FROM system_settings WHERE setting_group = ?");
            $stmt->execute([$group]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("SystemSetting::getByGroup Error: " . $e->getMessage());
            return [];
        }
    }

    public static function getAll(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value, setting_group, description, is_public FROM system_settings ORDER BY setting_group ASC, setting_key ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rows as $r) {
                $grouped[$r['setting_group']][$r['setting_key']] = $r['setting_value'];
            }
            return $grouped;
        } catch (Exception $e) {
            error_log("SystemSetting::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    public static function setMultiple(array $keyValuePairs, string $group = 'general'): void {
        foreach ($keyValuePairs as $k => $v) {
            self::set((string)$k, (string)$v, $group);
        }
    }
}
