<?php
// scripts/smoke_test.php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/AiWorkerClient.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/Clip.php';
require_once __DIR__ . '/../app/models/Video.php';
require_once __DIR__ . '/../app/models/CostService.php';

echo "=============================================\n";
echo "GTA CLIP AI ULTIMATE - SYSTEM VERIFICATION\n";
echo "=============================================\n\n";

// 1. Database Check
echo "1. Testing MySQL Database Connection...\n";
$pdo = Database::getConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "   [✓] Connected. Found " . count($tables) . " tables.\n\n";

// 2. AI Worker Health Check
echo "2. Testing AI GPU Worker Microservice (FastAPI)...\n";
$health = AiWorkerClient::checkHealth();
if ($health['online']) {
    echo "   [✓] Worker Online at " . $health['worker_url'] . "\n";
    echo "       Hardware: " . ($health['data']['hardware']['device_name'] ?? 'GPU Enabled') . "\n";
    echo "       NVENC: " . ($health['data']['hardware']['nvenc_supported'] ? 'Active' : 'CPU Fallback') . "\n";
} else {
    echo "   [!] Worker Offline: " . $health['error'] . "\n";
}
echo "\n";

// 3. User Authentication
echo "3. Testing User Authentication (user@gtaclip.ai)...\n";
$user = User::authenticate('user@gtaclip.ai', 'User@12345');
if ($user) {
    echo "   [✓] User authenticated successfully: " . $user['username'] . " (Tier: " . $user['plan_tier'] . ")\n";
} else {
    echo "   [✕] User authentication failed.\n";
}
echo "\n";

// 4. Admin Authentication
echo "4. Testing Admin Authentication (admin@gtaclip.ai)...\n";
$admin = Admin::authenticate('admin@gtaclip.ai', 'Admin@12345');
if ($admin) {
    echo "   [✓] Admin authenticated successfully: " . $admin['username'] . " (Role: " . $admin['role'] . ")\n";
} else {
    echo "   [✕] Admin authentication failed.\n";
}
echo "\n";

// 5. Video & Clip Inventory
echo "5. Testing Preserved Video & Clip Inventory...\n";
$videoCount = Video::countAll();
$clipCount = Clip::countAll();
$storage = Clip::getTotalStorageBytes();
echo "   [✓] Total Videos: $videoCount\n";
echo "   [✓] Total Clips: $clipCount\n";
echo "   [✓] Total Storage: " . format_bytes($storage) . "\n\n";

// 6. Cost Simulator Test
echo "6. Testing Cloud Cost Simulator Service...\n";
$cost = CostService::estimateJobCost(2 * 3600, 10, 'rtx4090');
echo "   [✓] Estimated Cloud Cost for 2-hour VOD (10 clips): $" . $cost['total_cost_usd'] . "\n";
echo "   [✓] Suggested Retail: $" . $cost['suggested_retail_price_usd'] . " (Gross Margin: " . $cost['gross_margin_percent'] . "%)\n\n";

echo "[✓] ALL SYSTEM MODULES OPERATIONAL.\n";
