-- database/migration_master_upgrade.sql
-- GTA CLIP AI ULTIMATE - MASTER SCHEMA EXPANSION

-- 1. Clip Metadata Table
CREATE TABLE IF NOT EXISTS clip_metadata (
    id VARCHAR(36) PRIMARY KEY,
    clip_id VARCHAR(36) NOT NULL,
    youtube_title VARCHAR(255) NOT NULL,
    instagram_caption TEXT NULL,
    description TEXT NULL,
    hashtags JSON NULL,
    keywords JSON NULL,
    suggested_category VARCHAR(64) DEFAULT 'Gaming',
    thumbnail_text VARCHAR(128) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_meta_clip (clip_id),
    FOREIGN KEY (clip_id) REFERENCES clips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. YouTube Accounts Table (Google OAuth 2.0)
CREATE TABLE IF NOT EXISTS youtube_accounts (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    channel_id VARCHAR(128) NOT NULL,
    channel_title VARCHAR(255) NOT NULL,
    channel_thumbnail_url VARCHAR(512) NULL,
    subscriber_count INT DEFAULT 0,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    scope VARCHAR(512) NULL,
    is_connected TINYINT(1) DEFAULT 1,
    connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_channel (user_id, channel_id),
    INDEX idx_yt_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. YouTube OAuth Tokens Table
CREATE TABLE IF NOT EXISTS youtube_oauth_tokens (
    id VARCHAR(36) PRIMARY KEY,
    account_id VARCHAR(36) NOT NULL,
    token_type VARCHAR(32) DEFAULT 'Bearer',
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES youtube_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. YouTube Uploads Table
CREATE TABLE IF NOT EXISTS youtube_uploads (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    clip_id VARCHAR(36) NOT NULL,
    youtube_account_id VARCHAR(36) NULL,
    youtube_video_id VARCHAR(64) NULL,
    youtube_url VARCHAR(255) NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    privacy_status ENUM('private', 'unlisted', 'public') DEFAULT 'private',
    upload_status ENUM('pending', 'uploading', 'published', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_upload_user (user_id),
    INDEX idx_upload_clip (clip_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (clip_id) REFERENCES clips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Upload Schedule Table (Twice-Daily Automated Scheduler)
CREATE TABLE IF NOT EXISTS upload_schedule (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    daily_quantity INT DEFAULT 2,
    slot_1_time TIME DEFAULT '12:00:00',
    slot_2_time TIME DEFAULT '19:00:00',
    timezone VARCHAR(64) DEFAULT 'Asia/Kolkata',
    preferred_privacy ENUM('private', 'unlisted', 'public') DEFAULT 'private',
    title_template VARCHAR(255) DEFAULT '{title} #Shorts #GTA5',
    auto_metadata TINYINT(1) DEFAULT 1,
    publishing_mode ENUM('manual_approval', 'auto_publishing') DEFAULT 'manual_approval',
    is_paused TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedule_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Upload History Table
CREATE TABLE IF NOT EXISTS upload_history (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    clip_id VARCHAR(36) NOT NULL,
    youtube_upload_id VARCHAR(36) NULL,
    status ENUM('scheduled', 'published', 'skipped', 'failed') DEFAULT 'published',
    scheduled_for DATETIME NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    view_count INT DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hist_user (user_id),
    INDEX idx_hist_clip (clip_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (clip_id) REFERENCES clips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. API Usage Tracking Table (Gemini, YouTube API, Whisper, NVENC)
CREATE TABLE IF NOT EXISTS api_usage (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NULL,
    service ENUM('gemini', 'youtube_api', 'whisper', 'nvenc', 'storage') NOT NULL,
    operation VARCHAR(64) NOT NULL,
    tokens_used INT DEFAULT 0,
    units_consumed INT DEFAULT 0,
    estimated_cost_usd DECIMAL(10, 6) DEFAULT 0.000000,
    status_code INT DEFAULT 200,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_user (user_id),
    INDEX idx_usage_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Views for 100% specification naming compatibility
CREATE OR REPLACE VIEW source_videos AS SELECT * FROM videos;
CREATE OR REPLACE VIEW generated_clips AS SELECT * FROM clips;
