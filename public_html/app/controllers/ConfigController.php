<?php
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class ConfigController extends Controller {
    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    public function index() {
        $user = $this->requireAdminUser();
        $bruteForceProtection = $this->getBruteForceProtectionData();
        $storageRoot = rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/');

        $this->view('config', [
            'user' => $user,
            'csrf' => $this->csrfToken(),
            'mail_host' => Setting::get('mail_host') ?? '',
            'mail_port' => Setting::get('mail_port') ?? '587',
            'mail_user' => Setting::get('mail_user') ?? '',
            'mail_from' => Setting::get('mail_from') ?? '',
            'mail_from_name' => Setting::get('mail_from_name') ?? '',
            'invite_code_required' => (string)(Setting::get('invite_code_required') ?? '1') === '1',
            'invites_enabled' => (string)(Setting::get('invites_enabled') ?? '1') === '1',
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
            'php_version' => PHP_VERSION,
            'php_file_uploads' => (bool)ini_get('file_uploads'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_memory_limit' => ini_get('memory_limit'),
            'app_version' => APP_VERSION,
            'database_version' => (string)(Setting::get('database_version') ?? 'unknown'),
        ]);
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
        $this->redirect('/config');
    }

    public function saveMoreSettings() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        Setting::set('error_display', isset($_POST['error_display']) ? '1' : '0');
        ErrorHandler::setDebugMode(isset($_POST['error_display']));
        Setting::set('attachment_logging', isset($_POST['attachment_logging']) ? '1' : '0');
        Setting::set('check_for_updates', isset($_POST['check_for_updates']) ? '1' : '0');

        $this->flash('success', 'more_saved');
        $this->redirect('/config');
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
        $this->redirect('/config');
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
        $this->redirect('/config');
    }

    public function sendTestMail() {
        $user = $this->requireAdminUser();
        Auth::csrfValidate();

        $mailHost = (string)(Setting::get('mail_host') ?? '');
        $mailUser = (string)(Setting::get('mail_user') ?? '');

        if ($mailHost === '' || $mailUser === '') {
            $this->flash('mail_test_error', 'Mail host and username must be configured before sending a test email.');
            $this->redirect('/config');
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

        $this->redirect('/config');
    }

    public function checkForUpdatesNow() {
        $user = $this->requireAdminUser();
        Auth::csrfValidate();

        $result = UpdateChecker::checkForAdminUser((int)$user->id, true);

        if (($result['status'] ?? '') === 'update_available') {
            $latestVersion = (string)($result['latest_version'] ?? '');
            $this->flash('success', 'update_check_update_available:' . $latestVersion);
            $this->redirect('/config');
        }

        if (($result['status'] ?? '') === 'up_to_date') {
            $this->flash('success', 'update_check_up_to_date');
            $this->redirect('/config');
        }

        $this->flash('error', 'update_check_failed');
        $this->redirect('/config');
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
