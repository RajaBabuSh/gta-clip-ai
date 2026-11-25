<?php
// scripts/verify_superadmin_suite.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Admin.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/CostService.php';
require_once __DIR__ . '/../app/models/SystemSetting.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';

echo "=========================================================\n";
echo "GTA CLIP AI ULTIMATE - SUPER ADMIN SUITE VERIFICATION\n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function report(string $name, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo " [PASS] " . str_pad($name, 45) . " - " . ($details ?: "OK") . "\n";
    } else {
        $failCount++;
        echo "![FAIL] " . str_pad($name, 45) . " - " . ($details ?: "FAILED") . "\n";
    }
}

$pdo = Database::getConnection();

// Test 1: CostService::getMonthlyTotals()
try {
    $monthly = CostService::getMonthlyTotals();
    $ok = is_array($monthly) && isset($monthly['total_jobs'], $monthly['total_gpu_seconds'], $monthly['total_cost_usd']);
    report("1. CostService getMonthlyTotals()", $ok, "Jobs: {$monthly['total_jobs']}, GPU Sec: {$monthly['total_gpu_seconds']}, Spend: \${$monthly['total_cost_usd']}");
} catch (Throwable $e) {
    report("1. CostService getMonthlyTotals()", false, $e->getMessage());
}

// Test 2: cloud_cost_records schema
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cloud_cost_records")->fetchAll(PDO::FETCH_COLUMN);
    $hasCols = in_array('gpu_seconds', $cols) && in_array('actual_cost_usd', $cols);
    report("2. cloud_cost_records GPU columns", $hasCols, "Found " . count($cols) . " columns including gpu_seconds");
} catch (Throwable $e) {
    report("2. cloud_cost_records GPU columns", false, $e->getMessage());
}

// Test 3: Roles and Permissions tables
try {
    $rolesCount = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    $permsCount = (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    $ok = ($rolesCount >= 6 && $permsCount >= 13);
    report("3. RBAC Roles & Permissions Tables", $ok, "Roles: {$rolesCount}, Permissions: {$permsCount}");
} catch (Throwable $e) {
    report("3. RBAC Roles & Permissions Tables", false, $e->getMessage());
}

// Test 4: Super Admin Account Exists
try {
    $hasSuper = Admin::hasSuperAdmin();
    $superCount = Admin::countSuperAdmins();
    report("4. Super Admin Account Verification", $hasSuper && $superCount >= 1, "Active SuperAdmins: {$superCount}");
} catch (Throwable $e) {
    report("4. Super Admin Account Verification", false, $e->getMessage());
}

// Test 5: Permission Resolution Check
try {
    $superAdm = $pdo->query("SELECT * FROM admins WHERE role = 'superadmin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $superCanManageUsers = AdminMiddleware::hasPermission('users.manage', $superAdm);
    $superCanConfigAi = AdminMiddleware::hasPermission('ai.config', $superAdm);
    
    // Viewer should only have logs.view
    $viewerPerms = Admin::getPermissions('dummy_id', 'viewer');
    $viewerCanManageUsers = in_array('users.manage', $viewerPerms);
    $viewerCanViewLogs = in_array('logs.view', $viewerPerms);

    $ok = ($superCanManageUsers && $superCanConfigAi && !$viewerCanManageUsers && $viewerCanViewLogs);
    report("5. RBAC Permission Engine", $ok, "SuperAdmin: unrestricted | Viewer: restricted (logs only)");
} catch (Throwable $e) {
    report("5. RBAC Permission Engine", false, $e->getMessage());
}

// Test 6: System Settings Access
try {
    $siteName = SystemSetting::get('site_name');
    $workerUrl = SystemSetting::get('cloud_worker_url');
    $ok = (!empty($siteName) && !empty($workerUrl));
    report("6. SystemSetting Engine", $ok, "Site: {$siteName} | Worker: {$workerUrl}");
} catch (Throwable $e) {
    report("6. SystemSetting Engine", false, $e->getMessage());
}

// Test 7-14: Admin Pages Render Check (Simulate Authenticated Super Admin)
@session_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
$superAdm = $pdo->query("SELECT id FROM admins WHERE role='superadmin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['admin_id'] = $superAdm['id'];

$pagesToTest = [
    'admin/index.php' => 'Platform Master Dashboard',
    'admin/users.php' => 'Creator User Directory',
    'admin/admins.php' => 'Administrator & Staff Management',
    'admin/roles.php' => 'Role-Based Access Control',
    'admin/jobs.php' => 'AI Processing Job Queue',
    'admin/clips.php' => 'All Generated Video Clips',
    'admin/youtube.php' => 'YouTube Channel & Auto-Publishing Suite',
    'admin/ai-settings.php' => 'AI Engine & Model Settings',
    'admin/customization.php' => 'Website Branding & Visuals',
    'admin/system-settings.php' => 'Platform Controls & Security Parameters',
    'admin/infrastructure.php' => 'Hardware, Microservices & Storage Cluster',
    'admin/costs.php' => 'Financial Control & GPU Cost Analytics',
    'admin/logs.php' => 'Security & Administrative Audit Logs',
    'admin/backups.php' => 'Backup & Disaster Recovery',
    'admin/install.php' => 'Installer Locked'
];

foreach ($pagesToTest as $file => $expectedSnippet) {
    try {
        ob_start();
        include __DIR__ . '/../' . $file;
        $html = ob_get_clean();
        $ok = str_contains($html, $expectedSnippet);
        report("Page: {$file}", $ok, "Bytes: " . strlen($html));
    } catch (Throwable $e) {
        ob_end_clean();
        report("Page: {$file}", false, $e->getMessage());
    }
}

// Test Services Connectivity
$httpTests = [
    'Next.js Frontend (port 3000)' => 'http://localhost:3000',
    'FastAPI Worker (port 8000)' => 'http://127.0.0.1:8000/docs',
    'Apache Core PHP (port 80)' => 'http://localhost/New%20folder/'
];

foreach ($httpTests as $serviceName => $testUrl) {
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    report("Service: {$serviceName}", $code === 200, "HTTP Status: {$code}");
}

echo "\n=========================================================\n";
echo "SUMMARY: Passed {$passCount} / " . ($passCount + $failCount) . " tests.\n";
echo "=========================================================\n";
