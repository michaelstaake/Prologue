CREATE DATABASE prologue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prologue;

-- Settings
-- Global settings use plain keys (e.g. 'invites_enabled').
-- Per-user settings use keys suffixed with the user ID (e.g. 'timezone_1', 'browser_notifications_1').
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) UNIQUE NOT NULL,
    `value` TEXT NOT NULL
);
INSERT INTO settings (`key`, `value`) VALUES ('invite_codes_per_user', '3');
INSERT INTO settings (`key`, `value`) VALUES ('invites_enabled', '1');
INSERT INTO settings (`key`, `value`) VALUES ('attachments_accepted_file_types', 'png,jpg');
INSERT INTO settings (`key`, `value`) VALUES ('attachments_maximum_file_size_mb', '10');
INSERT INTO settings (`key`, `value`) VALUES ('database_version', '0.0.0');
INSERT INTO settings (`key`, `value`) VALUES ('mail_host', '');
INSERT INTO settings (`key`, `value`) VALUES ('mail_port', '587');
INSERT INTO settings (`key`, `value`) VALUES ('mail_user', '');
INSERT INTO settings (`key`, `value`) VALUES ('mail_pass', '');
INSERT INTO settings (`key`, `value`) VALUES ('mail_from', '');
INSERT INTO settings (`key`, `value`) VALUES ('mail_from_name', 'Prologue');
INSERT INTO settings (`key`, `value`) VALUES ('invite_code_required', '1');
INSERT INTO settings (`key`, `value`) VALUES ('email_verification_required', '1');
INSERT INTO settings (`key`, `value`) VALUES ('error_display', '0');
INSERT INTO settings (`key`, `value`) VALUES ('new_user_notification', '0');

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) UNIQUE NOT NULL,
    username_changed_at TIMESTAMP NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_number CHAR(16) UNIQUE NOT NULL,
    avatar_filename VARCHAR(64) NULL,
    presence_status ENUM('online','busy','offline') NOT NULL DEFAULT 'online',
    last_active_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    email_verified_at TIMESTAMP NULL,
    role ENUM('user','admin') DEFAULT 'user',
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_users_username_format CHECK (username REGEXP '^[a-z][a-z0-9]{3,31}$')
);

-- Username history (usernames can never be reused by another account)
CREATE TABLE username_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(32) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Email verification codes
CREATE TABLE email_verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code CHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id)
);

-- Pending email change verification codes
CREATE TABLE email_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    code CHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id),
    UNIQUE KEY (new_email)
);

-- Trusted IPs for 2FA
CREATE TABLE user_trusted_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, ip)
);

-- User sessions
CREATE TABLE user_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token CHAR(64) NOT NULL,
    remember_token CHAR(64) NULL,
    ip_address VARCHAR(45) NOT NULL,
    browser VARCHAR(120) NOT NULL,
    logged_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (session_token),
    UNIQUE KEY (remember_token),
    KEY idx_user_sessions_user_active (user_id, revoked_at, logged_in_at)
);

-- IP-based auth attempt limits
CREATE TABLE auth_attempt_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action_type ENUM('login_failed','register_invite_failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_attempt_limits_lookup (ip_address, action_type, created_at)
);

-- 2FA codes
CREATE TABLE twofa_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code CHAR(6) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password resets
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Invite codes
CREATE TABLE invite_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    creator_id INT NOT NULL,
    used_by INT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Friends
CREATE TABLE friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending','accepted','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, friend_id)
);

-- Friend favorites
CREATE TABLE friend_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    favorite_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (favorite_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, favorite_user_id)
);

-- Chats
CREATE TABLE chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_number CHAR(16) UNIQUE NOT NULL,
    type ENUM('personal','group') DEFAULT 'personal',
    title VARCHAR(80) NULL,
    created_by INT NOT NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_chats_deleted_at (deleted_at)
);

-- Chat members
CREATE TABLE chat_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    last_seen_message_id INT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (chat_id, user_id)
);

-- Messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    quoted_message_id INT NULL,
    quoted_user_id INT NULL,
    quoted_content TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (quoted_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    FOREIGN KEY (quoted_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message reactions
CREATE TABLE message_reactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_code VARCHAR(12) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_message_reactions_message_user (message_id, user_id),
    KEY idx_message_reactions_message (message_id),
    KEY idx_message_reactions_user (user_id),
    CONSTRAINT chk_message_reactions_code CHECK (reaction_code IN ('1F44D','1F44E','2665','1F923','1F622','1F436','1F4A9'))
);

-- Profile posts
CREATE TABLE posts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_posts_user_created (user_id, created_at),
    CONSTRAINT chk_posts_content_length CHECK (CHAR_LENGTH(content) BETWEEN 1 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profile post reactions
CREATE TABLE post_reactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    reaction_code VARCHAR(12) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_post_reactions_post_user (post_id, user_id),
    KEY idx_post_reactions_post (post_id),
    KEY idx_post_reactions_user (user_id),
    CONSTRAINT chk_post_reactions_code CHECK (reaction_code IN ('1F44D','1F44E','2665','1F923','1F622','1F436','1F4A9'))
);

-- Message attachments
CREATE TABLE attachments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    message_id INT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_name CHAR(16) NOT NULL,
    file_extension VARCHAR(8) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    file_size BIGINT NOT NULL,
    width INT NULL,
    height INT NULL,
    status ENUM('pending','submitted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_attachment_user_file (user_id, file_name),
    KEY idx_attachments_message (message_id),
    KEY idx_attachments_pending (user_id, status, created_at)
);

-- Chat system events (e.g. rename announcements)
CREATE TABLE chat_system_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    KEY idx_chat_system_events_chat (chat_id)
);

-- Chat typing status
CREATE TABLE chat_typing_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (chat_id, user_id)
);

-- Calls
CREATE TABLE calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    started_by INT NOT NULL,
    status ENUM('active','ended') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (chat_id) REFERENCES chats(id),
    FOREIGN KEY (started_by) REFERENCES users(id)
);

-- Call participants (signaling data stored here)
CREATE TABLE call_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    call_id INT NOT NULL,
    user_id INT NOT NULL,
    offer TEXT NULL,
    answer TEXT NULL,
    ice_candidates TEXT NULL,
    muted TINYINT(1) DEFAULT 0,
    video TINYINT(1) DEFAULT 0,
    screen_sharing TINYINT(1) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

-- Targeted call signaling packets for multi-peer WebRTC negotiation
CREATE TABLE call_signals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    call_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    signal_type ENUM('offer','answer','ice','meta') NOT NULL,
    payload LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_call_signals_to (call_id, to_user_id, id),
    INDEX idx_call_signals_from (call_id, from_user_id, id),
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('message','call','friend_request','friend_request_accepted','report') NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    `read` TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    target_type ENUM('user','chat','message') NOT NULL,
    target_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id)
);

-- Servers (backend foundation for upcoming Discord-like server support)
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_number CHAR(16) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE server_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner','member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (server_id, user_id),
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_number CHAR(16) UNIQUE NOT NULL,
    server_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('text','voice') DEFAULT 'text',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- API tokens for mobile
CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);