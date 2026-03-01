<?php
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class AdminController extends Controller {
    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    public function saveAccountSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        Setting::set('invite_code_required', isset($_POST['invite_code_required']) ? '1' : '0');
        Setting::set('invites_enabled', isset($_POST['invites_enabled']) ? '1' : '0');
        Setting::set('invite_codes_per_user', (string)max(0, (int)($_POST['invite_codes_per_user'] ?? 3)));
        Setting::set('email_verification_required', isset($_POST['email_verification_required']) ? '1' : '0');
        Setting::set('new_user_notification', isset($_POST['new_user_notification']) ? '1' : '0');

        $this->flash('success', 'accounts_saved');
        $this->redirect('/controlpanel');
    }

    public function saveAnnouncementSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        $rawAnnouncement = (string)($_POST['announcement_message'] ?? '');
        $announcement = strip_tags(trim($rawAnnouncement));
        if (function_exists('mb_substr')) {
            $announcement = mb_substr($announcement, 0, 1000);
        } else {
            $announcement = substr($announcement, 0, 1000);
        }

        Setting::set('announcement_message', $announcement);

        $this->flash('success', 'announcement_saved');
        $this->redirect('/controlpanel');
    }

    public function saveMoreSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        Setting::set('error_display', isset($_POST['error_display']) ? '1' : '0');
        ErrorHandler::setDebugMode(isset($_POST['error_display']));
        Setting::set('attachment_logging', isset($_POST['attachment_logging']) ? '1' : '0');
        Setting::set('check_for_updates', isset($_POST['check_for_updates']) ? '1' : '0');

        $this->flash('success', 'more_saved');
        $this->redirect('/controlpanel');
    }

    public function saveAttachmentSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        $allTypes = ['png', 'jpg', 'webp', 'mp4', 'webm', 'pdf', 'odt', 'doc', 'docx', 'zip', '7z'];
        $types = [];
        foreach ($allTypes as $type) {
            if (isset($_POST['type_' . $type])) $types[] = $type;
        }
        Setting::set('attachments_accepted_file_types', implode(',', $types));

        $maxMb = (int)($_POST['attachments_maximum_file_size_mb'] ?? 10);
        if ($maxMb < 1) $maxMb = 1;
        Setting::set('attachments_maximum_file_size_mb', (string)$maxMb);

        $this->flash('success', 'attachments_saved');
        $this->redirect('/controlpanel');
    }

    public function saveMailSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        $mailHost = trim((string)($_POST['mail_host'] ?? ''));
        $mailPort = trim((string)($_POST['mail_port'] ?? ''));
        $mailUser = trim((string)($_POST['mail_user'] ?? ''));
        $mailPass = trim((string)($_POST['mail_pass'] ?? ''));
        $mailFrom = trim((string)($_POST['mail_from'] ?? ''));
        $mailFromName = trim((string)($_POST['mail_from_name'] ?? ''));

        Setting::set('mail_host', $mailHost);
        Setting::set('mail_port', $mailPort !== '' ? $mailPort : '587');
        Setting::set('mail_user', $mailUser);
        if ($mailPass !== '') {
            Setting::set('mail_pass', $mailPass);
        }
        Setting::set('mail_from', $mailFrom);
        Setting::set('mail_from_name', $mailFromName);

        $this->flash('success', 'mail_saved');
        $this->redirect('/controlpanel');
    }

    public function sendTestMail() {
        $user = $this->requireAdminUser();
        Auth::csrfValidate();

        $mailHost = (string)(Setting::get('mail_host') ?? '');
        $mailUser = (string)(Setting::get('mail_user') ?? '');

        if ($mailHost === '' || $mailUser === '') {
            $this->flash('mail_test_error', 'Mail host and username must be configured before sending a test email.');
            $this->redirect('/controlpanel');
        }

        $mailPort = (int)(Setting::get('mail_port') ?? 587);
        $mailPass = (string)(Setting::get('mail_pass') ?? '');
        $mailFrom = (string)(Setting::get('mail_from') ?? '');
        $mailFromName = (string)(Setting::get('mail_from_name') ?? '');

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUser;
            $mail->Password = $mailPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailPort;

            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($user->email);
            $mail->isHTML(false);
            $mail->Subject = 'Prologue mail test';
            $mail->Body = 'This is a test email from your Prologue installation. If you received this, mail is configured correctly.';
            $mail->send();

            $this->flash('success', 'mail_test_sent');
        } catch (MailException $e) {
            $this->flash('mail_test_error', $e->getMessage());
        }

        $this->redirect('/controlpanel');
    }

        public function recalculateStorageStats() {
            $this->requireAdminUser();
            Auth::csrfValidate();

            try {
                $stats = $this->calculateStorageStats();

                Setting::set('storage_total_bytes_cached', (string)$stats['total_storage_bytes']);
                Setting::set('storage_dedup_saved_bytes_cached', (string)$stats['dedup_saved_bytes']);
                Setting::set('storage_stats_last_recalculated_at', gmdate('Y-m-d H:i:s'));

                $this->flash('success', 'storage_recalculated');
            } catch (Throwable $exception) {
                $this->flash('error', 'storage_recalculate_failed');
            }

            $this->redirect('/controlpanel');
        }

    public function checkForUpdatesNow() {
        $user = $this->requireAdminUser();
        Auth::csrfValidate();

        $result = UpdateChecker::checkForAdminUser((int)$user->id, true);

        if (($result['status'] ?? '') === 'update_available') {
            $latestVersion = (string)($result['latest_version'] ?? '');
            $this->flash('success', 'update_check_update_available:' . $latestVersion);
            $this->redirect('/controlpanel');
        }

        if (($result['status'] ?? '') === 'up_to_date') {
            $this->flash('success', 'update_check_up_to_date');
            $this->redirect('/controlpanel');
        }

        $this->flash('error', 'update_check_failed');
        $this->redirect('/controlpanel');
    }

    private function storageRoot(): string {
        return rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/');
    }

    private function calculateStorageStats(): array {
        $storageSizeBytes = $this->calculateDirectorySizeBytes($this->storageRoot());

        $dedupSavedBytes = (int)Database::query(
            "SELECT COALESCE(SUM(file_size), 0) FROM attachments WHERE dedup_source_id IS NOT NULL"
        )->fetchColumn();

        if ($dedupSavedBytes < 0) {
            $dedupSavedBytes = 0;
        }

        return [
            'total_storage_bytes' => $storageSizeBytes,
            'dedup_saved_bytes' => $dedupSavedBytes,
        ];
    }

    private function calculateDirectorySizeBytes(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $fileSize = (int)$fileInfo->getSize();
                if ($fileSize > 0) {
                    $size += $fileSize;
                }
            }
        } catch (Throwable $exception) {
            return max(0, $size);
        }

        return max(0, $size);
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
