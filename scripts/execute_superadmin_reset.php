<?php
// scripts/execute_superadmin_reset.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/models/Admin.php';

$pdo = Database::getConnection();

// 1. Find existing superadmin
$stmt = $pdo->prepare("SELECT id, username, email, role, status FROM admins WHERE role = 'superadmin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "ERROR: No superadmin record found in database.\n";
    exit(1);
}

// 2. Generate strong temporary password
function generateStrongPassword(int $length = 16): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

$rawTempPassword = generateStrongPassword(16);
$hash = password_hash($rawTempPassword, PASSWORD_DEFAULT);

// 3. Update existing record ONLY - never create duplicates or delete records
$upd = $pdo->prepare("
    UPDATE admins 
    SET username = 'superadmin',
        email = 'admin@gtaclip.ai',
        password_hash = ?,
        force_password_change = 1,
        status = 'active',
        updated_at = NOW()
    WHERE id = ?
");
$upd->execute([$hash, $admin['id']]);

// 4. Log audit action
Admin::logAction($admin['id'], 'admin.credential_reset', 'Super Admin password securely reset with force_password_change enabled');

// 5. Store in session for recovery.php & login.php one-time view
$_SESSION['recovery_temp_password'] = $rawTempPassword;
$_SESSION['admin_temp_password_once'] = $rawTempPassword;

// 6. Output structured result
$result = [
    'success' => true,
    'admin_id' => $admin['id'],
    'username' => 'superadmin',
    'email' => 'admin@gtaclip.ai',
    'temporary_password' => $rawTempPassword,
    'hash_algorithm' => 'PASSWORD_DEFAULT (Bcrypt)',
    'force_password_change' => 1,
    'updated_at' => date('Y-m-d H:i:s')
];

echo json_encode($result, JSON_PRETTY_PRINT);
