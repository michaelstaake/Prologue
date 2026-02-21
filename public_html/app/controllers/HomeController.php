<?php
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class HomeController extends Controller {
    public function index() {
        Auth::requireAuth();
        $userId = Auth::user()->id;
        $selectedTab = strtolower(trim((string)($_GET['tab'] ?? 'all')));
        if (!in_array($selectedTab, ['favorites', 'online', 'all', 'requests'], true)) {
            $selectedTab = 'all';
        }

        $selectedRequestsTab = strtolower(trim((string)($_GET['requests'] ?? 'incoming')));
        if (!in_array($selectedRequestsTab, ['incoming', 'outgoing'], true)) {
            $selectedRequestsTab = 'incoming';
        }

        $friends = Database::query(
            "SELECT u.*, f.status,
                    CASE WHEN ff.id IS NULL THEN 0 ELSE 1 END AS is_favorite
             FROM friends f
             JOIN users u ON (f.friend_id = u.id OR f.user_id = u.id)
             LEFT JOIN friend_favorites ff ON ff.user_id = ? AND ff.favorite_user_id = u.id
             WHERE (f.user_id = ? OR f.friend_id = ?) AND u.id != ? AND f.status = 'accepted'
             ORDER BY u.username ASC",
            [$userId, $userId, $userId, $userId]
        )->fetchAll();
        $pendingIncoming = Database::query(
            "SELECT f.user_id AS requester_id,
                    u.id,
                    u.username,
                    u.user_number,
                    u.avatar_filename,
                    u.presence_status,
                    u.last_active_at,
                    f.created_at
             FROM friends f
             JOIN users u ON f.user_id = u.id
             WHERE f.friend_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        )->fetchAll();
        $pendingOutgoing = Database::query(
            "SELECT f.friend_id AS target_user_id,
                u.id,
                    u.username,
                    u.user_number,
                    u.avatar_filename,
                    u.presence_status,
                    u.last_active_at,
                    f.created_at
             FROM friends f
             JOIN users u ON f.friend_id = u.id
             WHERE f.user_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        )->fetchAll();
        $incomingRequestCount = count($pendingIncoming);

        User::attachEffectiveStatusList($friends);
        User::attachEffectiveStatusList($pendingIncoming);
        User::attachEffectiveStatusList($pendingOutgoing);

        $visibleFriends = $friends;
        if ($selectedTab === 'favorites') {
            $visibleFriends = array_values(array_filter($friends, static function($friend) {
                return (int)($friend->is_favorite ?? 0) === 1;
            }));
        } elseif ($selectedTab === 'online') {
            $visibleFriends = array_values(array_filter($friends, static function($friend) {
                return ($friend->effective_status ?? 'offline') !== 'offline';
            }));
        }

        $this->view('dashboard', [
            'friends' => $friends,
            'visibleFriends' => $visibleFriends,
            'pendingIncoming' => $pendingIncoming,
            'pendingOutgoing' => $pendingOutgoing,
            'incomingRequestCount' => $incomingRequestCount,
            'selectedTab' => $selectedTab,
            'selectedRequestsTab' => $selectedRequestsTab,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function settings() {
        Auth::requireAuth();
        $user = Auth::user();
        $this->clearExpiredEmailChangeRequest((int)$user->id);
        $pendingEmailChange = $this->getPendingEmailChangeRequest((int)$user->id);
        $settingPrefix = (int)$user->id;
        $browserNotif = (string)(Setting::get('browser_notifications_' . $settingPrefix) ?? '0');
        $friendRequestSoundNotif = (string)(Setting::get('sound_friend_request_' . $settingPrefix) ?? '1');
        $newMessageSoundNotif = (string)(Setting::get('sound_new_message_' . $settingPrefix) ?? '1');
        $otherNotificationSoundNotif = (string)(Setting::get('sound_other_notifications_' . $settingPrefix) ?? '1');
        $outgoingCallRingSoundNotif = (string)(Setting::get('sound_outgoing_call_ring_' . $settingPrefix) ?? '1');
        $usernameChangeAvailableAt = $this->usernameChangeAvailableAt($user);
        $invitesEnabled = (string)(Setting::get('invites_enabled') ?? '1') === '1';
        $inviteLimit = (int) (Setting::get('invite_codes_per_user') ?? 0);
        $inviteCount = 0;
        $invites = [];
        if ($invitesEnabled) {
            $inviteCount = (int) Database::query("SELECT COUNT(*) FROM invite_codes WHERE creator_id = ?", [$user->id])->fetchColumn();
            $invites = Database::query("SELECT ic.code, ic.used_by, u.username AS used_by_username, ic.used_at, ic.created_at FROM invite_codes ic LEFT JOIN users u ON u.id = ic.used_by WHERE ic.creator_id = ? ORDER BY ic.created_at DESC", [$user->id])->fetchAll();
        }
        $currentSessionToken = (string)($_SESSION['auth_session_token'] ?? '');
        $sessions = User::getActiveSessions((int)$user->id);
        foreach ($sessions as $session) {
            $session->is_current = ($currentSessionToken !== '' && hash_equals($currentSessionToken, (string)$session->session_token));
        }

        $pendingReportCount = 0;
        if (strtolower((string)($user->role ?? '')) === 'admin') {
            $pendingReportCount = (int) Database::query(
                "SELECT COUNT(*) FROM reports WHERE status = 'pending'"
            )->fetchColumn();
        }

        $userTimezone = (string)(Setting::get('timezone_' . $settingPrefix) ?? 'UTC+0');

        $this->view('settings', [
            'user' => $user,
            'browserNotif' => (int) $browserNotif,
            'friendRequestSoundNotif' => (int) $friendRequestSoundNotif,
            'newMessageSoundNotif' => (int) $newMessageSoundNotif,
            'otherNotificationSoundNotif' => (int) $otherNotificationSoundNotif,
            'outgoingCallRingSoundNotif' => (int) $outgoingCallRingSoundNotif,
            'userTimezone' => $userTimezone,
            'usernameCanChangeNow' => $usernameChangeAvailableAt === null,
            'usernameChangeAvailableAt' => $usernameChangeAvailableAt,
            'invitesEnabled' => $invitesEnabled,
            'inviteLimit' => $inviteLimit,
            'inviteCount' => $inviteCount,
            'invites' => $invites,
            'sessions' => $sessions,
            'pendingReportCount' => $pendingReportCount,
            'pendingEmailChange' => $pendingEmailChange,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function system() {
        Auth::requireAuth();
        $user = Auth::user();

        $this->view('system', [
            'user' => $user,
            'appVersion' => APP_VERSION,
            'databaseVersion' => (string)(Setting::get('database_version') ?? 'unknown'),
            'csrf' => $this->csrfToken()
        ]);
    }

    public function exitSession() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId <= 0) {
            $this->flash('error', 'session_not_found');
            $this->redirect('/settings');
        }

        $session = Database::query(
            "SELECT id, session_token FROM user_sessions WHERE id = ? AND user_id = ? AND revoked_at IS NULL LIMIT 1",
            [$sessionId, $user->id]
        )->fetch();

        if (!$session) {
            $this->flash('error', 'session_not_found');
            $this->redirect('/settings');
        }

        $currentToken = (string)($_SESSION['auth_session_token'] ?? '');
        $isCurrentSession = $currentToken !== '' && hash_equals($currentToken, (string)$session->session_token);

        if (!User::revokeSessionById((int)$user->id, $sessionId)) {
            $this->flash('error', 'session_not_found');
            $this->redirect('/settings');
        }

        if (User::activeSessionCount((int)$user->id) === 0) {
            User::markLoggedOut((int)$user->id);
        }

        if ($isCurrentSession) {
            session_regenerate_id(true);
            $_SESSION = [];
            $this->flash('success', 'session_exited');
            $this->redirect('/login');
        }

        $this->flash('success', 'session_exited');
        $this->redirect('/settings');
    }

    public function generateInvite() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $invitesEnabled = (string)(Setting::get('invites_enabled') ?? '1') === '1';
        if (!$invitesEnabled) {
            $this->flash('error', 'invite_disabled');
            $this->redirect('/settings');
        }

        $inviteLimit = (int) (Setting::get('invite_codes_per_user') ?? 0);
        if ($inviteLimit <= 0) {
            $this->flash('error', 'invite_disabled');
            $this->redirect('/settings');
        }

        $inviteCount = (int) Database::query("SELECT COUNT(*) FROM invite_codes WHERE creator_id = ?", [$user->id])->fetchColumn();
        if ($inviteCount >= $inviteLimit) {
            $this->flash('error', 'invite_limit');
            $this->redirect('/settings');
        }

        $newCode = $this->generateInviteCode();
        Database::query("INSERT INTO invite_codes (code, creator_id) VALUES (?, ?)", [$newCode, $user->id]);

        $this->flash('success', 'invite_created');
        $this->redirect('/settings');
    }

    public function deleteInvite() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $invitesEnabled = (string)(Setting::get('invites_enabled') ?? '1') === '1';
        if (!$invitesEnabled) {
            $this->flash('error', 'invite_disabled');
            $this->redirect('/settings');
        }

        $user = Auth::user();
        $inviteCode = trim($_POST['invite_code'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}$/', $inviteCode)) {
            $this->flash('error', 'invite_delete_unavailable');
            $this->redirect('/settings');
        }

        $invite = Database::query(
            "SELECT id FROM invite_codes WHERE code = ? AND creator_id = ? AND used_by IS NULL LIMIT 1",
            [$inviteCode, $user->id]
        )->fetch();

        if (!$invite) {
            $this->flash('error', 'invite_delete_unavailable');
            $this->redirect('/settings');
        }

        $deleted = Database::query(
            "DELETE FROM invite_codes WHERE id = ? AND creator_id = ? AND used_by IS NULL",
            [$invite->id, $user->id]
        );

        if ($deleted->rowCount() < 1) {
            $this->flash('error', 'invite_delete_unavailable');
            $this->redirect('/settings');
        }

        $this->flash('success', 'invite_deleted');
        $this->redirect('/settings');
    }

    public function saveAccountEmail() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $this->clearExpiredEmailChangeRequest((int)$user->id);
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'email_invalid');
            $this->redirect('/settings');
        }

        if (strcasecmp($email, (string)$user->email) === 0) {
            Database::query("DELETE FROM email_change_requests WHERE user_id = ?", [$user->id]);
            $this->flash('success', 'email_saved');
            $this->redirect('/settings');
        }

        $existingEmail = Database::query("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $user->id])->fetch();
        if ($existingEmail) {
            $this->flash('error', 'email_taken');
            $this->redirect('/settings');
        }

        $emailPendingForAnotherUser = Database::query(
            "SELECT user_id FROM email_change_requests WHERE new_email = ? AND user_id <> ? AND expires_at > NOW() LIMIT 1",
            [$email, $user->id]
        )->fetch();
        if ($emailPendingForAnotherUser) {
            $this->flash('error', 'email_taken');
            $this->redirect('/settings');
        }

        $code = $this->generateSixDigitCode();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        Database::query(
            "INSERT INTO email_change_requests (user_id, new_email, code, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE new_email = VALUES(new_email), code = VALUES(code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP",
            [$user->id, $email, $code, $expires]
        );

        $this->sendEmail(
            $email,
            'Confirm your new Prologue email',
            "Your email change verification code is <b>{$code}</b> (valid 10 minutes)."
        );

        $this->flash('success', 'email_change_sent');
        $this->redirect('/settings');
    }

    public function verifyAccountEmailChange() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $this->clearExpiredEmailChangeRequest((int)$user->id);

        $code = trim($_POST['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->flash('error', 'email_change_code_invalid');
            $this->redirect('/settings');
        }

        $pendingRequest = Database::query(
            "SELECT new_email FROM email_change_requests WHERE user_id = ? AND code = ? AND expires_at > NOW() LIMIT 1",
            [$user->id, $code]
        )->fetch();

        if (!$pendingRequest) {
            $pendingExists = Database::query(
                "SELECT id FROM email_change_requests WHERE user_id = ? AND expires_at > NOW() LIMIT 1",
                [$user->id]
            )->fetch();

            if (!$pendingExists) {
                $this->flash('error', 'email_change_expired');
                $this->redirect('/settings');
            }

            $this->flash('error', 'email_change_code_invalid');
            $this->redirect('/settings');
        }

        $newEmail = trim((string)$pendingRequest->new_email);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Database::query("DELETE FROM email_change_requests WHERE user_id = ?", [$user->id]);
            $this->flash('error', 'email_change_expired');
            $this->redirect('/settings');
        }

        $existingEmail = Database::query("SELECT id FROM users WHERE email = ? AND id <> ?", [$newEmail, $user->id])->fetch();
        if ($existingEmail) {
            Database::query("DELETE FROM email_change_requests WHERE user_id = ?", [$user->id]);
            $this->flash('error', 'email_taken');
            $this->redirect('/settings');
        }

        Database::query("UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?", [$newEmail, $user->id]);
        Database::query("DELETE FROM email_change_requests WHERE user_id = ?", [$user->id]);

        $this->flash('success', 'email_saved');
        $this->redirect('/settings');
    }

    public function saveAccountPassword() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, (string)$user->password)) {
            $this->flash('error', 'password_current_invalid');
            $this->redirect('/settings');
        }

        if (strlen($newPassword) < 8) {
            $this->flash('error', 'password_invalid');
            $this->redirect('/settings');
        }

        if (!hash_equals($newPassword, $confirmPassword)) {
            $this->flash('error', 'password_mismatch');
            $this->redirect('/settings');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user->id]);
        $this->flash('success', 'password_saved');
        $this->redirect('/settings');
    }

    public function saveProfileUsername() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $username = User::normalizeUsername($_POST['username'] ?? '');

        if (!User::isUsernameFormatValid($username)) {
            $this->flash('error', 'username_invalid');
            $this->redirect('/settings');
        }

        if ($username === User::normalizeUsername((string)$user->username)) {
            $this->flash('error', 'username_same');
            $this->redirect('/settings');
        }

        $usernameChangeAvailableAt = $this->usernameChangeAvailableAt($user);
        if ($usernameChangeAvailableAt !== null) {
            $this->flash('error', 'username_cooldown');
            $this->redirect('/settings');
        }

        if (!User::isUsernameAvailableForUser($username, (int)$user->id, (string)$user->username)) {
            $this->flash('error', 'username_taken');
            $this->redirect('/settings');
        }

        Database::query(
            "UPDATE users SET username = ?, username_changed_at = NOW() WHERE id = ?",
            [$username, $user->id]
        );
        User::recordUsernameHistory((int)$user->id, $username);

        $this->flash('success', 'username_saved');
        $this->redirect('/settings');
    }

    public function saveAvatarSettings() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = Auth::user()->id;
        $hasAvatarUpload = isset($_FILES['avatar'])
            && is_array($_FILES['avatar'])
            && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avatarRemoved = false;

        if (isset($_POST['remove_avatar']) && !$hasAvatarUpload) {
            $avatarRemoved = $this->removeAvatarForUser($userId);
        }

        if (isset($_FILES['avatar']) && is_array($_FILES['avatar'])) {
            $avatarError = $this->handleAvatarUpload($userId, $_FILES['avatar']);
            if ($avatarError !== null) {
                $this->flash('error', $avatarError);
                $this->redirect('/settings');
            }
        }

        $this->flash('success', $avatarRemoved ? 'avatar_removed' : 'avatar_saved');
        $this->redirect('/settings');
    }

    public function saveNotificationSettings() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = (int)Auth::user()->id;

        $allowedSettings = [
            'browser_notifications' => 'browser_notifications_',
            'sound_friend_request' => 'sound_friend_request_',
            'sound_new_message' => 'sound_new_message_',
            'sound_other_notifications' => 'sound_other_notifications_',
            'sound_outgoing_call_ring' => 'sound_outgoing_call_ring_'
        ];

        $requestedSetting = trim((string)($_POST['setting'] ?? ''));
        if ($requestedSetting !== '') {
            if (!isset($allowedSettings[$requestedSetting])) {
                $this->json(['error' => 'Invalid notification setting'], 400);
            }

            $enabledRaw = (string)($_POST['enabled'] ?? '0');
            $enabled = $enabledRaw === '1' ? '1' : '0';
            Setting::set($allowedSettings[$requestedSetting] . $userId, $enabled);

            $this->json([
                'success' => true,
                'setting' => $requestedSetting,
                'enabled' => $enabled === '1'
            ]);
        }

        $enableBrowser = isset($_POST['browser_notifications']) ? '1' : '0';
        Setting::set('browser_notifications_' . $userId, $enableBrowser);

        $acceptHeader = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($isAjax || strpos($acceptHeader, 'application/json') !== false) {
            $this->json(['success' => true, 'setting' => 'browser_notifications', 'enabled' => $enableBrowser === '1']);
        }

        $this->flash('success', 'notifications_saved');
        $this->redirect('/settings');
    }

    public function saveTimezoneSettings() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = (int)Auth::user()->id;

        $allowedTimezones = [
            'UTC-12:00','UTC-11:00','UTC-10:00','UTC-9:30','UTC-9:00',
            'UTC-8:00','UTC-7:00','UTC-6:00','UTC-5:00','UTC-4:30',
            'UTC-4:00','UTC-3:30','UTC-3:00','UTC-2:00','UTC-1:00',
            'UTC+0','UTC+1:00','UTC+2:00','UTC+3:00','UTC+3:30',
            'UTC+4:00','UTC+4:30','UTC+5:00','UTC+5:30','UTC+5:45',
            'UTC+6:00','UTC+6:30','UTC+7:00','UTC+8:00','UTC+8:30',
            'UTC+8:45','UTC+9:00','UTC+9:30','UTC+10:00','UTC+10:30',
            'UTC+11:00','UTC+12:00','UTC+12:45','UTC+13:00','UTC+14:00'
        ];

        $timezone = trim((string)($_POST['timezone'] ?? 'UTC+0'));
        if (!in_array($timezone, $allowedTimezones, true)) {
            $timezone = 'UTC+0';
        }

        Setting::set('timezone_' . $userId, $timezone);
        $this->flash('success', 'timezone_saved');
        $this->redirect('/settings');
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

    private function removeAvatarForUser($userId) {
        $oldFilename = Database::query("SELECT avatar_filename FROM users WHERE id = ?", [$userId])->fetchColumn();
        $safeOldFilename = $this->sanitizeAvatarFilename((string)$oldFilename);
        Database::query("UPDATE users SET avatar_filename = NULL WHERE id = ?", [$userId]);

        if ($safeOldFilename) {
            $storageRoot = rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/');
            $oldPath = $storageRoot . '/avatars/' . $safeOldFilename;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
            return true;
        }

        return false;
    }

    private function handleAvatarUpload($userId, $avatarFile) {
        $errorCode = (int)($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return 'avatar_upload_failed';
        }

        $tmpPath = $avatarFile['tmp_name'] ?? '';
        if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return 'avatar_upload_failed';
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo) {
            return 'avatar_invalid_type';
        }

        $mime = strtolower((string)($imageInfo['mime'] ?? ''));
        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];

        if (!isset($allowedMime[$mime])) {
            return 'avatar_invalid_type';
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 100 || $height > 100) {
            return 'avatar_invalid_size';
        }

        $storageRoot = rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/');
        $avatarsDir = $storageRoot . '/avatars';
        if (!is_dir($avatarsDir) && !mkdir($avatarsDir, 0755, true) && !is_dir($avatarsDir)) {
            return 'avatar_upload_failed';
        }

        $newFilename = 'u' . (int)$userId . '.' . $allowedMime[$mime];
        $targetPath = $avatarsDir . '/' . $newFilename;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            return 'avatar_upload_failed';
        }

        @chmod($targetPath, 0644);

        $oldFilename = Database::query("SELECT avatar_filename FROM users WHERE id = ?", [$userId])->fetchColumn();
        Database::query("UPDATE users SET avatar_filename = ? WHERE id = ?", [$newFilename, $userId]);

        $safeOldFilename = $this->sanitizeAvatarFilename((string)$oldFilename);
        if ($safeOldFilename && $safeOldFilename !== $newFilename) {
            $oldPath = $avatarsDir . '/' . $safeOldFilename;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        return null;
    }

    private function sanitizeAvatarFilename($filename) {
        $filename = trim($filename);
        if ($filename === '') {
            return null;
        }

        $safeFilename = basename($filename);
        if ($safeFilename !== $filename) {
            return null;
        }

        if (!preg_match('/^u\d+\.(jpg|jpeg|png)$/i', $safeFilename)) {
            return null;
        }

        return $safeFilename;
    }

    private function generateInviteCode() {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
            $exists = Database::query("SELECT id FROM invite_codes WHERE code = ?", [$code])->fetch();
            if (!$exists) {
                return $code;
            }
        }

        throw new RuntimeException('Failed to generate a unique invite code.');
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

    private function generateSixDigitCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendEmail($to, $subject, $body) {
        $mailHost = (string)(Setting::get('mail_host') ?? '');
        $mailUser = (string)(Setting::get('mail_user') ?? '');

        if ($mailHost === '' || $mailUser === '') {
            return;
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
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Exception $e) {
            // log error in production
        }
    }
}