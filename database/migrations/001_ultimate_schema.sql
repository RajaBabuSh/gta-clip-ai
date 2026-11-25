-- ====================================================================
-- GTA CLIP AI ULTIMATE - Complete Database Normalization & Migration
-- Engine: MySQL 8+ / MariaDB 10.4+
-- Preserves all existing records in `videos`, `processing_jobs`, and `clips`
-- ====================================================================

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(36) NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(128) NULL,
  `avatar_url` VARCHAR(512) NULL,
  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `plan_tier` VARCHAR(32) NOT NULL DEFAULT 'free',
  `monthly_clip_quota` INT NOT NULL DEFAULT 50,
  `clips_generated_this_month` INT NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME NULL,
  `remember_token` VARCHAR(128) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`),
  UNIQUE KEY `idx_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Administrators Table (Separate Security Boundary)
CREATE TABLE IF NOT EXISTS `admins` (
  `id` VARCHAR(36) NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(128) NULL,
  `role` ENUM('superadmin', 'admin', 'moderator') NOT NULL DEFAULT 'admin',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admins_email` (`email`),
  UNIQUE KEY `idx_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. User Sessions Table (Persistent & Active Session Tracking)
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(36) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` TEXT NULL,
  `last_activity` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Projects Table (User Project Workspaces)
CREATE TABLE IF NOT EXISTS `projects` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(64) NOT NULL DEFAULT 'Gaming',
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `total_videos` INT NOT NULL DEFAULT 0,
  `total_clips` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projects_user_id` (`user_id`),
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Safe Alterations on Existing `videos` Table
ALTER TABLE `videos`
  ADD COLUMN IF NOT EXISTS `user_id` VARCHAR(36) NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `project_id` VARCHAR(36) NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `platform` VARCHAR(32) NOT NULL DEFAULT 'YOUTUBE' AFTER `source_type`,
  ADD COLUMN IF NOT EXISTS `view_count` BIGINT NULL AFTER `thumbnail_url`,
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(32) NOT NULL DEFAULT 'READY' AFTER `view_count`;

-- 6. Safe Alterations on Existing `processing_jobs` Table
ALTER TABLE `processing_jobs`
  ADD COLUMN IF NOT EXISTS `user_id` VARCHAR(36) NULL AFTER `video_id`,
  ADD COLUMN IF NOT EXISTS `worker_id` VARCHAR(64) NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `worker_type` VARCHAR(32) NOT NULL DEFAULT 'GPU_NVENC' AFTER `worker_id`,
  ADD COLUMN IF NOT EXISTS `compute_time_seconds` FLOAT NOT NULL DEFAULT 0.0 AFTER `clips_total_target`,
  ADD COLUMN IF NOT EXISTS `estimated_cost_usd` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `compute_time_seconds`;

-- 7. Video Chunks Table (Granular Chunk Tracking)
CREATE TABLE IF NOT EXISTS `video_chunks` (
  `id` VARCHAR(36) NOT NULL,
  `job_id` VARCHAR(36) NOT NULL,
  `video_id` VARCHAR(36) NOT NULL,
  `chunk_index` INT NOT NULL,
  `start_time` FLOAT NOT NULL,
  `end_time` FLOAT NOT NULL,
  `duration` FLOAT NOT NULL,
  `status` ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED') NOT NULL DEFAULT 'PENDING',
  `moments_found` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chunks_job_id` (`job_id`),
  KEY `idx_chunks_video_id` (`video_id`),
  CONSTRAINT `fk_chunks_job` FOREIGN KEY (`job_id`) REFERENCES `processing_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Detected Moments Table (Multi-Signal Peak Highlights)
CREATE TABLE IF NOT EXISTS `detected_moments` (
  `id` VARCHAR(36) NOT NULL,
  `job_id` VARCHAR(36) NOT NULL,
  `video_id` VARCHAR(36) NOT NULL,
  `chunk_id` VARCHAR(36) NULL,
  `peak_time` FLOAT NOT NULL,
  `start_time` FLOAT NOT NULL,
  `end_time` FLOAT NOT NULL,
  `duration` FLOAT NOT NULL,
  `virality_score` FLOAT NOT NULL,
  `excitement_type` VARCHAR(64) NOT NULL DEFAULT 'action',
  `transcript_snippet` TEXT NULL,
  `words_json` LONGTEXT NULL,
  `is_selected` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_moments_job` (`job_id`),
  KEY `idx_moments_video` (`video_id`),
  CONSTRAINT `fk_moments_job` FOREIGN KEY (`job_id`) REFERENCES `processing_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Safe Alterations on Existing `clips` Table
ALTER TABLE `clips`
  ADD COLUMN IF NOT EXISTS `user_id` VARCHAR(36) NULL AFTER `video_id`,
  ADD COLUMN IF NOT EXISTS `project_id` VARCHAR(36) NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved' AFTER `virality_score`,
  ADD COLUMN IF NOT EXISTS `thumbnail_path` VARCHAR(512) NULL AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `views_count` INT NOT NULL DEFAULT 0 AFTER `thumbnail_path`,
  ADD COLUMN IF NOT EXISTS `downloads_count` INT NOT NULL DEFAULT 0 AFTER `views_count`,
  ADD COLUMN IF NOT EXISTS `editor_settings_json` LONGTEXT NULL AFTER `downloads_count`;

-- 10. Downloads Log Table
CREATE TABLE IF NOT EXISTS `downloads` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NULL,
  `clip_id` VARCHAR(36) NULL,
  `job_id` VARCHAR(36) NULL,
  `download_type` ENUM('mp4', 'zip') NOT NULL,
  `file_size_bytes` BIGINT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_downloads_user` (`user_id`),
  KEY `idx_downloads_clip` (`clip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Storage Files Registry Table
CREATE TABLE IF NOT EXISTS `storage_files` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NULL,
  `file_key` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(512) NOT NULL,
  `file_size_bytes` BIGINT NOT NULL,
  `storage_type` ENUM('local', 's3', 'r2', 'wasabi') NOT NULL DEFAULT 'local',
  `mime_type` VARCHAR(64) NOT NULL,
  `is_temporary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_storage_key` (`file_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link_url` VARCHAR(512) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. System Settings Table (Dynamic Platform Configuration)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `setting_group` VARCHAR(32) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. API Settings Table
CREATE TABLE IF NOT EXISTS `api_settings` (
  `id` VARCHAR(36) NOT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `api_key_encrypted` TEXT NULL,
  `api_secret_encrypted` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `rate_limit_per_minute` INT NOT NULL DEFAULT 60,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_api_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Worker Status Table (Remote Cloud AI Worker Nodes)
CREATE TABLE IF NOT EXISTS `worker_status` (
  `worker_id` VARCHAR(64) NOT NULL,
  `worker_name` VARCHAR(128) NOT NULL,
  `worker_type` ENUM('CPU', 'GPU_NVENC', 'GPU_CUDA', 'CLOUD_AUTOSCALE') NOT NULL DEFAULT 'GPU_NVENC',
  `host_ip` VARCHAR(45) NOT NULL,
  `port` INT NOT NULL DEFAULT 8000,
  `status` ENUM('online', 'busy', 'offline') NOT NULL DEFAULT 'online',
  `current_job_id` VARCHAR(36) NULL,
  `jobs_completed` INT NOT NULL DEFAULT 0,
  `gpu_model` VARCHAR(128) NULL,
  `gpu_vram_mb` INT NULL,
  `last_heartbeat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Cloud Usage & Cost Records Table
CREATE TABLE IF NOT EXISTS `cloud_cost_records` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NULL,
  `job_id` VARCHAR(36) NULL,
  `worker_type` VARCHAR(32) NOT NULL DEFAULT 'GPU_NVENC',
  `compute_duration_seconds` FLOAT NOT NULL,
  `rate_per_hour_usd` DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
  `total_cost_usd` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cost_user` (`user_id`),
  KEY `idx_cost_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Audit Logs Table (Administrative & Security Event Logging)
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` VARCHAR(36) NOT NULL,
  `admin_id` VARCHAR(36) NULL,
  `user_id` VARCHAR(36) NULL,
  `action` VARCHAR(64) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Login History Table
CREATE TABLE IF NOT EXISTS `login_history` (
  `id` VARCHAR(36) NOT NULL,
  `user_id` VARCHAR(36) NULL,
  `email` VARCHAR(191) NOT NULL,
  `role_type` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `status` ENUM('success', 'failed') NOT NULL,
  `failure_reason` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`),
  KEY `idx_login_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial platform settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`, `is_public`) VALUES
('site_name', 'GTA CLIP AI ULTIMATE', 'general', 'Platform Name', 1),
('site_tagline', 'Turn long streams and gaming gameplay into viral Shorts & Reels in seconds.', 'general', 'SaaS Tagline', 1),
('maintenance_mode', '0', 'general', 'Enable or disable platform maintenance mode', 1),
('allow_registration', '1', 'auth', 'Allow public user registrations', 1),
('default_clip_quantity', '10', 'ai', 'Default number of viral clips to cut', 1),
('max_clip_quantity', '40', 'ai', 'Maximum clips allowed per generation job', 1),
('cloud_worker_url', 'http://127.0.0.1:8000', 'ai', 'Remote Python AI Worker Microservice URL', 0),
('whisper_model', 'base', 'ai', 'Open-source Whisper model (tiny, base, small, medium)', 0),
('monthly_free_credits', '100', 'billing', 'Monthly free credits allocated to users', 1)
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;
