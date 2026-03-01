<?php

class ControlPanelController extends Controller {
    public function index() {
        Auth::requireAuth();
        $user = Auth::user();
        $isAdmin = strtolower((string)($user->role ?? '')) === 'admin';
        $userId = (int)$user->id;
        $settingPrefix = $userId;

        // --- Account data (all users) ---

        $this->clearExpiredEmailChangeRequest($userId);
        $pendingEmailChange = $this->getPendingEmailChangeRequest($userId);

        $browserNotif = (string)(Setting::get('browser_notifications_' . $settingPrefix) ?? '0');
        $friendRequestSoundNotif = (string)(Setting::get('sound_friend_request_' . $settingPrefix) ?? '1');
        $newMessageSoundNotif = (string)(Setting::get('sound_new_message_' . $settingPrefix) ?? '1');
        $otherNotificationSoundNotif = (string)(Setting::get('sound_other_notifications_' . $settingPrefix) ?? '1');
        $outgoingCallRingSoundNotif = (string)(Setting::get('sound_outgoing_call_ring_' . $settingPrefix) ?? '1');

        $usernameChangeAvailableAt = $this->usernameChangeAvailableAt($user);

        $invitesEnabled = (string)(Setting::get('invites_enabled') ?? '1') === '1';
        $inviteLimit = (int)(Setting::get('invite_codes_per_user') ?? 0);
        $inviteCount = 0;
        $invites = [];
        if ($invitesEnabled) {
            $inviteCount = (int)Database::query("SELECT COUNT(*) FROM invite_codes WHERE creator_id = ?", [$userId])->fetchColumn();
            $invites = Database::query("SELECT ic.code, ic.used_by, u.username AS used_by_username, ic.used_at, ic.created_at FROM invite_codes ic LEFT JOIN users u ON u.id = ic.used_by WHERE ic.creator_id = ? ORDER BY ic.created_at DESC", [$userId])->fetchAll();
        }

        $currentSessionToken = (string)($_SESSION['auth_session_token'] ?? '');
        $sessions = User::getActiveSessions($userId);
        foreach ($sessions as $session) {
            $session->is_current = ($currentSessionToken !== '' && hash_equals($currentSessionToken, (string)$session->session_token));
        }

        $userTimezone = (string)(Setting::get('timezone_' . $settingPrefix) ?? 'UTC+0');

        $viewData = [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'csrf' => $this->csrfToken(),
            'browserNotif' => (int)$browserNotif,
            'friendRequestSoundNotif' => (int)$friendRequestSoundNotif,
            'newMessageSoundNotif' => (int)$newMessageSoundNotif,
            'otherNotificationSoundNotif' => (int)$otherNotificationSoundNotif,
            'outgoingCallRingSoundNotif' => (int)$outgoingCallRingSoundNotif,
            'userTimezone' => $userTimezone,
            'usernameCanChangeNow' => $usernameChangeAvailableAt === null,
            'usernameChangeAvailableAt' => $usernameChangeAvailableAt,
            'invitesEnabled' => $invitesEnabled,
            'inviteLimit' => $inviteLimit,
            'inviteCount' => $inviteCount,
            'invites' => $invites,
            'sessions' => $sessions,
            'pendingEmailChange' => $pendingEmailChange,
        ];

        // --- Admin data ---

        if ($isAdmin) {
            $pendingReportCount = (int)Database::query(
                "SELECT COUNT(*) FROM reports WHERE status = 'pending'"
            )->fetchColumn();

            $bruteForceProtection = $this->getBruteForceProtectionData();
            $storageRoot = $this->storageRoot();

            $storageStatsLastRecalculatedAt = trim((string)(Setting::get('storage_stats_last_recalculated_at') ?? ''));
            $hasStorageStats = $storageStatsLastRecalculatedAt !== '';

            $storageTotalBytes = $hasStorageStats ? max(0, (int)(Setting::get('storage_total_bytes_cached') ?? 0)) : null;
            $storageDedupSavedBytes = $hasStorageStats ? max(0, (int)(Setting::get('storage_dedup_saved_bytes_cached') ?? 0)) : null;

            $storageDedupSavedPercent = null;
            if ($hasStorageStats && $storageTotalBytes !== null && $storageDedupSavedBytes !== null) {
                $withoutDedupBytes = $storageTotalBytes + $storageDedupSavedBytes;
                $storageDedupSavedPercent = $withoutDedupBytes > 0
                    ? (($storageDedupSavedBytes / $withoutDedupBytes) * 100)
                    : 0.0;
            }

            $viewData = array_merge($viewData, [
                'pendingReportCount' => $pendingReportCount,
                'announcement_message' => (string)(Setting::get('announcement_message') ?? ''),
                'mail_host' => Setting::get('mail_host') ?? '',
                'mail_port' => Setting::get('mail_port') ?? '587',
                'mail_user' => Setting::get('mail_user') ?? '',
                'mail_from' => Setting::get('mail_from') ?? '',
                'mail_from_name' => Setting::get('mail_from_name') ?? '',
                'invite_code_required' => (string)(Setting::get('invite_code_required') ?? '1') === '1',
                'admin_invites_enabled' => (string)(Setting::get('invites_enabled') ?? '1') === '1',
                'invite_codes_per_user' => (int)(Setting::get('invite_codes_per_user') ?? 3),
                'email_verification_required' => (string)(Setting::get('email_verification_required') ?? '1') === '1',
                'attachments_accepted_file_types' => (string)(Setting::get('attachments_accepted_file_types') ?? 'png,jpg'),
                'attachments_maximum_file_size_mb' => (int)(Setting::get('attachments_maximum_file_size_mb') ?? 10),
                'error_display' => (string)(Setting::get('error_display') ?? '0') === '1',
                'attachment_logging' => (string)(Setting::get('attachment_logging') ?? '0') === '1',
                'check_for_updates' => (string)(Setting::get('check_for_updates') ?? '0') === '1',
                'new_user_notification' => (string)(Setting::get('new_user_notification') ?? '0') === '1',
                'failed_login_attempts_24h' => $bruteForceProtection['failed_login_attempts_24h'],
                'failed_registration_attempts_24h' => $bruteForceProtection['failed_registration_attempts_24h'],
                'active_banned_ips' => $bruteForceProtection['active_banned_ips'],
                'storage_writable' => is_dir($storageRoot) && is_writable($storageRoot),
                'storage_total_size_label' => $hasStorageStats && $storageTotalBytes !== null ? $this->formatBytes($storageTotalBytes) : 'N/A',
                'storage_dedup_saved_label' => $hasStorageStats && $storageDedupSavedBytes !== null && $storageDedupSavedPercent !== null
                    ? ($this->formatBytes($storageDedupSavedBytes) . ' (' . number_format($storageDedupSavedPercent, 2) . '%)')
                    : 'N/A',
                'storage_stats_last_recalculated_label' => $this->formatLastRecalculatedLabel($storageStatsLastRecalculatedAt),
                'php_version' => PHP_VERSION,
                'php_ext_fileinfo' => extension_loaded('fileinfo'),
                'php_ext_gd' => extension_loaded('gd'),
                'php_ext_mbstring' => extension_loaded('mbstring'),
                'php_file_uploads' => (bool)ini_get('file_uploads'),
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'php_memory_limit' => ini_get('memory_limit'),
                'app_version' => APP_VERSION,
                'database_version' => (string)(Setting::get('database_version') ?? 'unknown'),
            ]);
        }

        $this->view('controlpanel', $viewData);
    }

    public function apikeys() {
        Auth::requireAuth();
        $this->view('apikeys', [
            'user' => Auth::user(),
            'csrf' => $this->csrfToken(),
        ]);
    }

    private function usernameChangeAvailableAt($user) {
        if (!$user || empty($user->username_changed_at)) {
            return null;
        }

        $changedAt = strtotime((string)$user->username_changed_at);
        if ($changedAt === false) {
            return null;
        }

        $availableAt = strtotime('+30 days', $changedAt);
        if ($availableAt === false || time() >= $availableAt) {
            return null;
        }

        return date('F j, Y \a\t g:i A', $availableAt);
    }

    private function clearExpiredEmailChangeRequest($userId) {
        Database::query("DELETE FROM email_change_requests WHERE user_id = ? AND expires_at <= NOW()", [(int)$userId]);
    }

    private function getPendingEmailChangeRequest($userId) {
        return Database::query(
            "SELECT new_email, expires_at, GREATEST(TIMESTAMPDIFF(SECOND, NOW(), expires_at), 0) AS seconds_remaining FROM email_change_requests WHERE user_id = ? AND expires_at > NOW() LIMIT 1",
            [(int)$userId]
        )->fetch();
    }

    private function storageRoot(): string {
        return rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/');
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2) . ' ' . $unit;
            }
        }

        return number_format($bytes) . ' B';
    }

    private function formatLastRecalculatedLabel(string $raw): string {
        if ($raw === '') {
            return 'N/A';
        }

        try {
            $dateTime = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
            return 'Last recalculated ' . $dateTime->format('Y-m-d H:i:s') . ' UTC';
        } catch (Throwable $exception) {
            return 'Last recalculated ' . $raw;
        }
    }

    private function getBruteForceProtectionData() {
        $result = [
            'failed_login_attempts_24h' => 0,
            'failed_registration_attempts_24h' => 0,
            'active_banned_ips' => [],
        ];

        try {
            $result['failed_login_attempts_24h'] = (int)Database::query(
                "SELECT COUNT(*) FROM auth_attempt_limits WHERE action_type = 'login_failed' AND created_at >= (NOW() - INTERVAL 24 HOUR)"
            )->fetchColumn();

            $result['failed_registration_attempts_24h'] = (int)Database::query(
                "SELECT COUNT(*) FROM auth_attempt_limits WHERE action_type = 'register_invite_failed' AND created_at >= (NOW() - INTERVAL 24 HOUR)"
            )->fetchColumn();

            $result['active_banned_ips'] = Database::query(
                "WITH ranked_attempts AS (
                    SELECT
                        ip_address,
                        action_type,
                        created_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY ip_address, action_type
                            ORDER BY created_at DESC
                        ) AS row_num
                    FROM auth_attempt_limits
                    WHERE created_at >= (NOW() - INTERVAL 1 MINUTE)
                ),
                active_action_bans AS (
                    SELECT
                        ip_address,
                        action_type,
                        DATE_ADD(MIN(created_at), INTERVAL 1 MINUTE) AS ban_expires_at
                    FROM ranked_attempts
                    WHERE row_num <= 5
                    GROUP BY ip_address, action_type
                    HAVING COUNT(*) = 5 AND DATE_ADD(MIN(created_at), INTERVAL 1 MINUTE) > NOW()
                )
                SELECT
                    ip_address,
                    CEIL(GREATEST(TIMESTAMPDIFF(SECOND, NOW(), MAX(ban_expires_at)), 0) / 60) AS minutes_left
                FROM active_action_bans
                GROUP BY ip_address
                ORDER BY minutes_left DESC, ip_address ASC"
            )->fetchAll();
        } catch (Throwable $exception) {
            return $result;
        }

        return $result;
    }
}
