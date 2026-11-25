<?php
// scripts/setup_admin.php
// Setup initial Admin and User accounts for GTA CLIP AI ULTIMATE

$host = '127.0.0.1';
$port = 3306;
$dbname = 'gtaclipai';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 1. Setup Admin Account
$adminEmail = 'admin@gtaclip.ai';
$adminPass = 'Admin@12345';
$adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
$adminId = 'adm_' . bin2hex(random_bytes(10));

$stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
$stmt->execute([$adminEmail]);
$existingAdmin = $stmt->fetch();

if (!$existingAdmin) {
    $ins = $pdo->prepare("INSERT INTO admins (id, username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'superadmin', 'active')");
    $ins->execute([$adminId, 'superadmin', $adminEmail, $adminHash, 'Master Administrator']);
    echo "[+] Superadmin created successfully!\n";
    echo "    Email: $adminEmail\n";
    echo "    Password: $adminPass\n";
} else {
    $upd = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE email = ?");
    $upd->execute([$adminHash, $adminEmail]);
    echo "[*] Superadmin password reset to: $adminPass\n";
}

// 2. Setup Default Active User Account
$userEmail = 'user@gtaclip.ai';
$userPass = 'User@12345';
$userHash = password_hash($userPass, PASSWORD_BCRYPT);
$userId = 'usr_' . bin2hex(random_bytes(10));

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$userEmail]);
$existingUser = $stmt->fetch();

if (!$existingUser) {
    $ins = $pdo->prepare("INSERT INTO users (id, username, email, password_hash, full_name, plan_tier, monthly_clip_quota, status) VALUES (?, ?, ?, ?, ?, 'pro', 200, 'active')");
    $ins->execute([$userId, 'gtaclipper', $userEmail, $userHash, 'GTA Clip Creator']);
    echo "[+] Default Pro User created successfully!\n";
    echo "    Email: $userEmail\n";
    echo "    Password: $userPass\n";
    $actualUserId = $userId;
} else {
    $actualUserId = $existingUser['id'];
    $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $upd->execute([$userHash, $userEmail]);
    echo "[*] Default User password reset to: $userPass\n";
}

// 3. Associate any existing unassigned videos and clips to this user
$pdo->prepare("UPDATE videos SET user_id = ? WHERE user_id IS NULL")->execute([$actualUserId]);
$pdo->prepare("UPDATE processing_jobs SET user_id = ? WHERE user_id IS NULL")->execute([$actualUserId]);
$pdo->prepare("UPDATE clips SET user_id = ? WHERE user_id IS NULL")->execute([$actualUserId]);

// 4. Create a default project for existing videos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
$stmt->execute([$actualUserId]);
if ($stmt->fetchColumn() == 0) {
    $projId = 'prj_' . bin2hex(random_bytes(10));
    $ins = $pdo->prepare("INSERT INTO projects (id, user_id, title, description, category, total_videos, total_clips) VALUES (?, ?, 'GTA VI Stream Clips', 'Highlight clips extracted from GTA VI live streams', 'Gaming', 7, 28)");
    $ins->execute([$projId, $actualUserId]);
    $pdo->prepare("UPDATE videos SET project_id = ? WHERE user_id = ?")->execute([$projId, $actualUserId]);
    echo "[+] Default Project initialized: 'GTA VI Stream Clips' (ID: $projId)\n";
}

echo "\n[✓] Setup complete. Ready for Core PHP authentication!\n";
