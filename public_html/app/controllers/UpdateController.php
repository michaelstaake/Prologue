<?php

class UpdateController extends Controller {

    private const UPDATE_LOCK_TTL_SECONDS = 120;
    private const UPDATE_LOCK_FILENAME = 'db_update.lock';

    /**
     * Map of version => SQL statements to run when migrating TO that version.
     * Add entries here for each new version that requires schema changes.
     * Versions below 0.0.3 are intentionally empty — no installs exist at those versions.
     */
    private static function getMigrations(): array {
        return [
            '0.0.6' => [
                "ALTER TABLE attachments ADD COLUMN file_hash CHAR(64) NULL AFTER height, ADD COLUMN dedup_source_id BIGINT NULL AFTER file_hash, ADD KEY idx_attachments_hash (file_hash, file_extension)",
                "ALTER TABLE attachments ADD CONSTRAINT fk_attachments_dedup_source FOREIGN KEY (dedup_source_id) REFERENCES attachments(id) ON DELETE SET NULL",
            ],
            '0.1.0' => [
                "DROP TABLE IF EXISTS api_tokens",
                "CREATE TABLE IF NOT EXISTS api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    api_key CHAR(64) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    type ENUM('bot','user') NOT NULL,
                    status ENUM('active','expired') NOT NULL DEFAULT 'active',
                    allowed_ips TEXT NULL,
                    allowed_chats TEXT NULL,
                    expires_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    KEY idx_api_keys_user (user_id, status),
                    KEY idx_api_keys_lookup (api_key, status)
                )",
                "CREATE TABLE IF NOT EXISTS api_key_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    api_key_id INT NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    endpoint VARCHAR(191) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
                    KEY idx_api_key_logs_key (api_key_id, created_at)
                )",
                "ALTER TABLE messages ADD COLUMN bot_name VARCHAR(100) NULL AFTER quoted_content",
            ],
            '0.1.1' => [
                "CREATE TABLE IF NOT EXISTS totp_secrets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    secret_encrypted TEXT NOT NULL,
                    confirmed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY (user_id)
                )",
                "CREATE TABLE IF NOT EXISTS totp_recovery_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    code_hash CHAR(64) NOT NULL,
                    used_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    KEY idx_totp_recovery_user (user_id)
                )",
            ],
            '0.1.3' => [
                "ALTER TABLE messages ADD COLUMN edited_at TIMESTAMP NULL AFTER created_at",
            ],
            '0.2.1' => [
                "ALTER TABLE chats ADD COLUMN non_member_visibility ENUM('none','requestable','public') NOT NULL DEFAULT 'none' AFTER title",
                "ALTER TABLE chat_members ADD COLUMN role ENUM('member','moderator') NOT NULL DEFAULT 'member' AFTER user_id",
                "ALTER TABLE chat_members ADD COLUMN is_muted TINYINT(1) NOT NULL DEFAULT 0 AFTER role",
                "ALTER TABLE chat_members ADD COLUMN muted_by INT NULL AFTER is_muted",
                "ALTER TABLE chat_members ADD COLUMN muted_at TIMESTAMP NULL AFTER muted_by",
                "ALTER TABLE chat_members ADD CONSTRAINT fk_chat_members_muted_by FOREIGN KEY (muted_by) REFERENCES users(id) ON DELETE SET NULL",
                "ALTER TABLE chat_members ADD KEY idx_chat_members_role (chat_id, role)",
                "CREATE TABLE IF NOT EXISTS group_join_requests (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    chat_id INT NOT NULL,
                    requester_user_id INT NOT NULL,
                    status ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
                    handled_by INT NULL,
                    handled_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
                    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY uniq_group_join_requests_chat_user (chat_id, requester_user_id),
                    KEY idx_group_join_requests_status (chat_id, status)
                )",
                "UPDATE chat_members cm
                 JOIN chats c ON c.id = cm.chat_id
                 SET cm.role = 'moderator'
                 WHERE c.type = 'group' AND c.created_by = cm.user_id",
            ],
            '0.2.2' => [
                "ALTER TABLE attachments ADD COLUMN expires_at TIMESTAMP NULL AFTER status",
                "ALTER TABLE attachments ADD COLUMN deleted_at TIMESTAMP NULL AFTER expires_at",
                "ALTER TABLE attachments ADD COLUMN delete_reason ENUM('manual','expired','message_deleted') NULL AFTER deleted_at",
                "ALTER TABLE attachments ADD KEY idx_attachments_expiry (status, deleted_at, expires_at)",
            ],
            '0.2.4' => [
                "CREATE TABLE IF NOT EXISTS polls (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    chat_id INT NOT NULL,
                    creator_user_id INT NOT NULL,
                    question VARCHAR(40) NOT NULL,
                    status ENUM('active','expired') NOT NULL DEFAULT 'active',
                    expires_at TIMESTAMP NOT NULL,
                    expired_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
                    FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    KEY idx_polls_chat_status (chat_id, status, expires_at, created_at)
                )",
                "CREATE TABLE IF NOT EXISTS poll_options (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    poll_id BIGINT NOT NULL,
                    option_text VARCHAR(40) NOT NULL,
                    sort_order TINYINT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
                    UNIQUE KEY uniq_poll_options_order (poll_id, sort_order)
                )",
                "CREATE TABLE IF NOT EXISTS poll_votes (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    poll_id BIGINT NOT NULL,
                    poll_option_id BIGINT NOT NULL,
                    user_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
                    FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY uniq_poll_votes_poll_user (poll_id, user_id),
                    KEY idx_poll_votes_option (poll_option_id, poll_id)
                )",
            ],
        ];
    }

    private function getUpdateLockPath(): string {
        return rtrim((string)APP_LOG_DIRECTORY, '/') . '/' . self::UPDATE_LOCK_FILENAME;
    }

    private function getRemainingLockSeconds(): int {
        $path = $this->getUpdateLockPath();
        if (!is_file($path)) {
            return 0;
        }

        $startedAt = (int)trim((string)@file_get_contents($path));
        if ($startedAt <= 0) {
            return 0;
        }

        $elapsed = time() - $startedAt;
        if ($elapsed >= self::UPDATE_LOCK_TTL_SECONDS) {
            return 0;
        }

        return self::UPDATE_LOCK_TTL_SECONDS - $elapsed;
    }

    private function tryAcquireUpdateLock(): int {
        $logDirectory = rtrim((string)APP_LOG_DIRECTORY, '/');
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }

        $path = $this->getUpdateLockPath();
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return 0;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return 0;
            }

            rewind($handle);
            $existingValue = stream_get_contents($handle);
            $startedAt = (int)trim((string)$existingValue);

            if ($startedAt > 0) {
                $elapsed = time() - $startedAt;
                if ($elapsed < self::UPDATE_LOCK_TTL_SECONDS) {
                    return self::UPDATE_LOCK_TTL_SECONDS - $elapsed;
                }
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)time());
            fflush($handle);

            return 0;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function renderUpdateView(string $dbVersion, ?string $errorMessage = null): void {
        $lockRemaining = $this->getRemainingLockSeconds();

        $this->viewRaw('update', [
            'csrf'          => $this->csrfToken(),
            'dbVersion'     => $dbVersion,
            'appVersion'    => APP_VERSION,
            'errorMessage'  => $errorMessage,
            'lockRemaining' => $lockRemaining,
        ]);
    }

    public function showUpdate() {
        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
        }

        $this->renderUpdateView($dbVersion);
    }

    public function runUpdate() {
        Auth::csrfValidate();

        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
        }

        $lockRemaining = $this->tryAcquireUpdateLock();
        if ($lockRemaining > 0) {
            $this->renderUpdateView(
                $dbVersion,
                'Another update attempt is already in progress. Please try again in about ' . $lockRemaining . ' seconds.'
            );
            return;
        }

        $migrations = self::getMigrations();
        $versions = array_keys($migrations);
        usort($versions, 'version_compare');

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            foreach ($versions as $version) {
                if (version_compare($version, APP_VERSION, '>')) {
                    continue;
                }

                if (version_compare($dbVersion, $version, '<')) {
                    foreach ($migrations[$version] as $sql) {
                        Database::query($sql);
                    }
                    Setting::set('database_version', $version);
                    $dbVersion = $version;
                }
            }

            // Advance DB version to app version even if no schema migrations were needed
            if (version_compare($dbVersion, APP_VERSION, '<')) {
                Setting::set('database_version', APP_VERSION);
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->renderUpdateView($dbVersion, 'Update failed at version ' . $dbVersion . ': ' . $e->getMessage());
            return;
        }

        $this->flash('success', 'update_complete');
        $this->redirect('/');
    }
}
