<?php
// api/settings.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/GeminiService.php';

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

// 1. Test Gemini Connection
if ($action === 'test_gemini') {
    $apiKey = $_GET['api_key'] ?? null;
    $res = GeminiService::verifyApiKey($apiKey);
    json_response($res);
}

// 2. Save Gemini Settings (Admin Only)
if ($action === 'save_gemini') {
    $admin = get_auth_admin();
    if (!$admin) {
        json_response(['success' => false, 'message' => 'Admin authorization required.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $apiKey = trim($input['api_key'] ?? '');
    $model = trim($input['model'] ?? 'gemini-1.5-flash');
    $enabled = !empty($input['enabled']) ? '1' : '0';

    $pdo = Database::getConnection();

    // Helper upsert
    $upsert = function($key, $val, $desc) use ($pdo) {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_group, description, is_public, updated_at)
            VALUES (?, ?, 'api', ?, 0, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([$key, $val, $desc]);
    };

    if (!empty($apiKey)) {
        $upsert('gemini_api_key', $apiKey, 'Google Gemini API Key');
    }
    $upsert('gemini_model', $model, 'Configured Gemini Model');
    $upsert('gemini_enabled', $enabled, 'Gemini API Enabled Flag');

    log_audit('gemini_settings_updated', null, $admin['id'], 'system_settings', 'gemini_api');

    json_response([
        'success' => true,
        'message' => 'Gemini API settings saved successfully.'
    ]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
