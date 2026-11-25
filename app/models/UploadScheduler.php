<?php
// app/models/UploadScheduler.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UploadScheduler {
    public static function getSchedule(string $userId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM upload_schedule WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            // Default 12:00 PM and 7:00 PM IST configuration
            return [
                'user_id' => $userId,
                'daily_quantity' => 2,
                'slot_1_time' => '12:00:00',
                'slot_2_time' => '19:00:00',
                'timezone' => 'Asia/Kolkata',
                'preferred_privacy' => 'private',
                'title_template' => '{title} #Shorts #GTA5',
                'auto_metadata' => 1,
                'publishing_mode' => 'manual_approval',
                'is_paused' => 0
            ];
        }

        return $row;
    }

    public static function saveSchedule(string $userId, array $data): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM upload_schedule WHERE user_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE upload_schedule SET
                    daily_quantity = ?,
                    slot_1_time = ?,
                    slot_2_time = ?,
                    timezone = ?,
                    preferred_privacy = ?,
                    title_template = ?,
                    auto_metadata = ?,
                    publishing_mode = ?,
                    is_paused = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            return $stmt->execute([
                (int)($data['daily_quantity'] ?? 2),
                $data['slot_1_time'] ?? '12:00:00',
                $data['slot_2_time'] ?? '19:00:00',
                $data['timezone'] ?? 'Asia/Kolkata',
                $data['preferred_privacy'] ?? 'private',
                $data['title_template'] ?? '{title} #Shorts #GTA5',
                (int)($data['auto_metadata'] ?? 1),
                $data['publishing_mode'] ?? 'manual_approval',
                (int)($data['is_paused'] ?? 0),
                $userId
            ]);
        }

        $id = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("
            INSERT INTO upload_schedule (
                id, user_id, daily_quantity, slot_1_time, slot_2_time,
                timezone, preferred_privacy, title_template, auto_metadata,
                publishing_mode, is_paused, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        return $stmt->execute([
            $id,
            $userId,
            (int)($data['daily_quantity'] ?? 2),
            $data['slot_1_time'] ?? '12:00:00',
            $data['slot_2_time'] ?? '19:00:00',
            $data['timezone'] ?? 'Asia/Kolkata',
            $data['preferred_privacy'] ?? 'private',
            $data['title_template'] ?? '{title} #Shorts #GTA5',
            (int)($data['auto_metadata'] ?? 1),
            $data['publishing_mode'] ?? 'manual_approval',
            (int)($data['is_paused'] ?? 0)
        ]);
    }

    public static function togglePause(string $userId): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE upload_schedule 
            SET is_paused = IF(is_paused = 1, 0, 1), updated_at = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }

    public static function getNextSlot(array $schedule): array {
        $tz = new DateTimeZone($schedule['timezone'] ?? 'Asia/Kolkata');
        $now = new DateTime('now', $tz);
        
        $slot1 = new DateTime($now->format('Y-m-d') . ' ' . $schedule['slot_1_time'], $tz);
        $slot2 = new DateTime($now->format('Y-m-d') . ' ' . $schedule['slot_2_time'], $tz);

        if ($now < $slot1) {
            return ['slot' => 1, 'time' => $slot1->format('h:i A'), 'datetime' => $slot1->format('Y-m-d H:i:s')];
        } elseif ($now < $slot2) {
            return ['slot' => 2, 'time' => $slot2->format('h:i A'), 'datetime' => $slot2->format('Y-m-d H:i:s')];
        } else {
            $slot1Tomorrow = clone $slot1;
            $slot1Tomorrow->modify('+1 day');
            return ['slot' => 1, 'time' => $slot1Tomorrow->format('h:i A') . ' (Tomorrow)', 'datetime' => $slot1Tomorrow->format('Y-m-d H:i:s')];
        }
    }
}
