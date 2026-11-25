<?php
// database/migrations/20261119_superadmin_and_costs.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = Database::getConnection();

echo "Running Migration: Super Admin, Roles, Permissions, Costs, and Settings...\n";

// 1. Alter cloud_cost_records to add missing columns expected by CostService
$existingCols = [];
$colStmt = $pdo->query("SHOW COLUMNS FROM cloud_cost_records");
while ($row = $colStmt->fetch(PDO::FETCH_ASSOC)) {
    $existingCols[] = $row['Field'];
}

$colsToAdd = [
    'gpu_instance_type' => "VARCHAR(64) DEFAULT 'rtx4090'",
    'gpu_seconds' => "DECIMAL(10,2) DEFAULT 0.0",
    'whisper_seconds' => "DECIMAL(10,2) DEFAULT 0.0",
    'ffmpeg_render_seconds' => "DECIMAL(10,2) DEFAULT 0.0",
    'estimated_cost_usd' => "DECIMAL(8,4) DEFAULT 0.0000",
    'actual_cost_usd' => "DECIMAL(8,4) DEFAULT 0.0000"
];

foreach ($colsToAdd as $col => $definition) {
    if (!in_array($col, $existingCols)) {
        $pdo->exec("ALTER TABLE cloud_cost_records ADD COLUMN {$col} {$definition}");
        echo "Added column {$col} to cloud_cost_records.\n";
    }
}

// Sync existing rows if compute_duration_seconds exists
$pdo->exec("UPDATE cloud_cost_records SET gpu_seconds = compute_duration_seconds WHERE gpu_seconds = 0.0 AND compute_duration_seconds > 0");
$pdo->exec("UPDATE cloud_cost_records SET actual_cost_usd = total_cost_usd WHERE actual_cost_usd = 0.0 AND total_cost_usd > 0");

// 2. Expand admins table role column to varchar(32) so it supports 6 roles
$pdo->exec("ALTER TABLE admins MODIFY COLUMN role VARCHAR(32) NOT NULL DEFAULT 'admin'");
echo "Updated admins.role to VARCHAR(32).\n";

// 3. Create roles table
$pdo->exec("
CREATE TABLE IF NOT EXISTS roles (
    id VARCHAR(36) PRIMARY KEY,
    slug VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 4. Create permissions table
$pdo->exec("
CREATE TABLE IF NOT EXISTS permissions (
    id VARCHAR(36) PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'general',
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 5. Create role_permissions table
$pdo->exec("
CREATE TABLE IF NOT EXISTS role_permissions (
    role_slug VARCHAR(32) NOT NULL,
    permission_slug VARCHAR(64) NOT NULL,
    PRIMARY KEY (role_slug, permission_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 6. Create admin_permissions table (for explicit per-admin grants)
$pdo->exec("
CREATE TABLE IF NOT EXISTS admin_permissions (
    admin_id VARCHAR(36) NOT NULL,
    permission_slug VARCHAR(64) NOT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id, permission_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 7. Seed 6 Standard Roles
$roles = [
    ['superadmin', 'Super Admin', 'Full unrestricted platform access, permissions management, and infrastructure control.'],
    ['admin', 'Admin', 'Manages users, clipping jobs, videos, YouTube uploads, and day-to-day operations.'],
    ['moderator', 'Moderator', 'Reviews content, generated clips, moderation flags, and user management.'],
    ['support', 'Support', 'Handles user tickets, views jobs and logs, assists with failed renders.'],
    ['finance_admin', 'Finance Admin', 'Manages cloud costs, API usage records, and billing settings.'],
    ['viewer', 'Viewer', 'Read-only access to dashboards, reports, and analytics.']
];

$roleStmt = $pdo->prepare("INSERT INTO roles (id, slug, name, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
foreach ($roles as $r) {
    $roleStmt->execute([generate_uuid(), $r[0], $r[1], $r[2]]);
}

// 8. Seed Standard Permissions
$permissions = [
    ['users.manage', 'Manage Users', 'users', 'Create, view, suspend, activate, reset password, and delete users.'],
    ['admins.manage', 'Manage Administrators', 'admins', 'Create, update, assign roles and revoke administrator access.'],
    ['jobs.manage', 'Manage AI Clipping Jobs', 'jobs', 'View, retry, cancel, delete, and configure clipping pipeline jobs.'],
    ['clips.manage', 'Manage Generated Clips', 'clips', 'View, stream, publish, and delete rendered video clips.'],
    ['api.config', 'Configure APIs', 'settings', 'Manage external API keys (Gemini, Redis, OAuth).'],
    ['ai.config', 'Configure AI Worker', 'settings', 'Manage FastAPI worker URL, Whisper models, FFmpeg presets.'],
    ['youtube.manage', 'Manage YouTube Channels', 'youtube', 'Connect channels, configure auto-publish, manage uploads.'],
    ['infra.manage', 'Manage Infrastructure', 'system', 'Monitor server health, Redis, worker nodes, and disk space.'],
    ['finance.manage', 'Financial & Cost Control', 'finance', 'View GPU expenses, API costs, per-user usage and billing.'],
    ['customization.manage', 'Website Customization', 'branding', 'Edit website logo, theme colors, announcements, copy.'],
    ['settings.manage', 'System Settings', 'system', 'Configure registration, storage, limits, maintenance mode.'],
    ['logs.view', 'View Audit Logs', 'security', 'View admin actions, security events, and worker errors.'],
    ['backups.manage', 'Manage Backups', 'system', 'Create, download, and manage system database/file backups.']
];

$permStmt = $pdo->prepare("INSERT INTO permissions (id, slug, name, category, description) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), description=VALUES(description)");
foreach ($permissions as $p) {
    $permStmt->execute([generate_uuid(), $p[0], $p[1], $p[2], $p[3]]);
}

// 9. Map default permissions per role
$rolePermMap = [
    'superadmin' => [
        'users.manage', 'admins.manage', 'jobs.manage', 'clips.manage', 'api.config',
        'ai.config', 'youtube.manage', 'infra.manage', 'finance.manage',
        'customization.manage', 'settings.manage', 'logs.view', 'backups.manage'
    ],
    'admin' => [
        'users.manage', 'jobs.manage', 'clips.manage', 'youtube.manage',
        'logs.view', 'customization.manage'
    ],
    'moderator' => [
        'users.manage', 'clips.manage', 'logs.view'
    ],
    'support' => [
        'users.manage', 'jobs.manage', 'clips.manage', 'logs.view'
    ],
    'finance_admin' => [
        'finance.manage', 'logs.view'
    ],
    'viewer' => [
        'logs.view'
    ]
];

$rpStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, permission_slug) VALUES (?, ?)");
foreach ($rolePermMap as $roleSlug => $perms) {
    foreach ($perms as $permSlug) {
        $rpStmt->execute([$roleSlug, $permSlug]);
    }
}

// 10. Seed Default Settings
$defaultSettings = [
    // Customization & Branding
    ['site_name', 'GTA CLIP AI ULTIMATE', 'branding', 'The public name of the platform.', 1],
    ['site_tagline', 'AI Stream Highlights & Shorts Generator', 'branding', 'Platform slogan/tagline.', 1],
    ['site_logo_url', '/assets/images/logo.png', 'branding', 'Path or URL to primary website logo.', 1],
    ['site_favicon_url', '/favicon.ico', 'branding', 'Favicon path.', 1],
    ['theme_accent_color', '#6366f1', 'branding', 'Primary brand color hex.', 1],
    ['theme_bg_color', '#0a0d14', 'branding', 'Background color hex.', 1],
    ['announcement_banner_enabled', '0', 'branding', 'Show top banner alert.', 1],
    ['announcement_banner_text', '🚀 Welcome to GTA CLIP AI ULTIMATE! AI GPU Worker is online.', 'branding', 'Banner message.', 1],
    ['announcement_banner_type', 'info', 'branding', 'Banner type: info, warning, success, danger.', 1],
    ['footer_copyright_text', '© 2026 GTA CLIP AI ULTIMATE. All rights reserved.', 'branding', 'Footer copyright text.', 1],
    ['homepage_headline', 'Turn 4-Hour Gaming Streams Into Viral Clips in Seconds', 'branding', 'Hero section title.', 1],
    ['homepage_subheadline', 'Automatic speech recognition, high-energy peak detection, portrait 9:16 re-framing, and YouTube Shorts automation.', 'branding', 'Hero section description.', 1],

    // AI & API
    ['gemini_model', 'gemini-1.5-flash', 'ai', 'Active Google Gemini model.', 0],
    ['gemini_api_key', '', 'ai', 'Google Gemini API Key.', 0],
    ['whisper_model', 'base', 'ai', 'Whisper STT model (tiny, base, small, medium).', 0],
    ['cloud_worker_url', 'http://127.0.0.1:8000', 'ai', 'FastAPI GPU Worker endpoint URL.', 0],
    ['ai_worker_auth_token', 'worker-secret-token-key-2026', 'ai', 'Worker authorization bearer token.', 0],
    ['redis_url', 'redis://127.0.0.1:6379/0', 'ai', 'Redis connection URL for queue.', 0],
    ['default_clip_quantity', '10', 'ai', 'Default number of clips to generate.', 0],
    ['max_clip_quantity', '40', 'ai', 'Maximum clips allowed per job.', 0],
    ['max_concurrent_jobs', '3', 'ai', 'Maximum simultaneous jobs processed.', 0],
    ['job_timeout_seconds', '1800', 'ai', 'Timeout for clipping jobs.', 0],

    // YouTube Automation
    ['youtube_default_privacy', 'private', 'youtube', 'Default privacy for auto uploads (private/unlisted/public).', 0],
    ['youtube_auto_publish_enabled', '1', 'youtube', 'Allow scheduled automatic publishing.', 0],
    ['youtube_max_daily_uploads', '10', 'youtube', 'Max uploads allowed per day per user.', 0],

    // System & Security
    ['allow_registration', '1', 'auth', 'Allow public user sign-ups.', 1],
    ['require_email_verification', '0', 'auth', 'Require email confirmation before access.', 0],
    ['max_upload_size_mb', '2048', 'system', 'Max direct MP4 upload size in MB.', 1],
    ['session_lifetime_minutes', '120', 'system', 'Admin session inactivity timeout.', 0],
    ['maintenance_mode', '0', 'general', 'Put platform into maintenance mode.', 1],
    ['maintenance_message', 'GTA CLIP AI ULTIMATE is currently undergoing scheduled maintenance. We will be back shortly.', 'general', 'Maintenance display message.', 1],
    ['max_login_attempts', '5', 'security', 'Max failed login attempts before temporary lockout.', 0]
];

$setStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group, description, is_public) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description), setting_group=VALUES(setting_group)");
foreach ($defaultSettings as $s) {
    $setStmt->execute([$s[0], $s[1], $s[2], $s[3], $s[4]]);
}

echo "Migration completed successfully!\n";
