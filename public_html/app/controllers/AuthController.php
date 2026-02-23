<?php
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends Controller {
    private const AUTH_ATTEMPT_LIMIT_PER_MINUTE = 5;
    private const AUTH_ATTEMPT_LOGIN_FAILED = 'login_failed';
    private const AUTH_ATTEMPT_REGISTER_INVITE_FAILED = 'register_invite_failed';

    public function showLogin() {
        if (Auth::user()) {
            $this->redirect('/');
        }

        $this->view('auth/login', ['csrf' => $this->csrfToken()]);
    }

    public function login() {
        if (Auth::user()) {
            $this->redirect('/');
        }

        Auth::csrfValidate();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->hasTooManyAuthAttempts($ip, self::AUTH_ATTEMPT_LOGIN_FAILED)) {
            $this->flash('error', 'too_many_attempts');
            $this->redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->recordAuthAttempt($ip, self::AUTH_ATTEMPT_LOGIN_FAILED);
            $this->flash('error', 'invalid');
            $this->redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user->password)) {
            $this->recordAuthAttempt($ip, self::AUTH_ATTEMPT_LOGIN_FAILED);
            $this->flash('error', 'invalid');
            $this->redirect('/login');
        }

        $this->clearAuthAttempts($ip, self::AUTH_ATTEMPT_LOGIN_FAILED);

        if (User::isBanned($user)) {
            $this->flash('error', 'banned');
            $this->redirect('/login');
        }

        if (empty($user->email_verified_at)) {
            $emailVerificationRequired = (string)(Setting::get('email_verification_required') ?? '1') === '1';
            if ($emailVerificationRequired) {
                $this->startEmailVerificationFlow((int)$user->id);
                $this->flash('success', 'sent');
                $this->redirect('/verify-email');
            }
            // Verification not required — auto-verify and continue login
            Database::query("UPDATE users SET email_verified_at = NOW() WHERE id = ?", [$user->id]);
            $user->email_verified_at = date('Y-m-d H:i:s');
        }

        $shouldBypassBootstrap2FA = $this->shouldBypassBootstrap2FA((int)$user->id);

        $rememberMe = !empty($_POST['remember_me']);

        if (!$shouldBypassBootstrap2FA && Auth::needs2FA($user->id, $ip)) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            Database::query("INSERT INTO twofa_codes (user_id, code, ip, expires_at) VALUES (?, ?, ?, ?)",
                [$user->id, $code, $ip, $expires]);

            $this->sendEmail($user->email, 'Your Prologue 2FA Code', "Your login code is <b>{$code}</b> (valid 10 minutes).");
            $_SESSION['2fa_pending_user'] = $user->id;
            $_SESSION['2fa_remember_me'] = $rememberMe;
            $this->redirect('/2fa');
        }

        Auth::markTrustedIP($user->id, $ip);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user->id;
        $_SESSION['auth_session_token'] = Auth::registerLoginSession((int)$user->id, $ip, $userAgent);
        if ($rememberMe) {
            Auth::setRememberCookie((int)$user->id, $_SESSION['auth_session_token']);
        }
        User::markLoggedIn((int)$user->id);
        Attachment::cleanupPendingForUser($user);
        $this->createSafariDesktopLoginNotice((int)$user->id, $userAgent);
        $_SESSION['last_activity_touch_at'] = time();
        $this->redirect('/');
    }

    public function show2FA() {
        if (!isset($_SESSION['2fa_pending_user'])) $this->redirect('/login');
        $this->view('auth/twofa', ['csrf' => $this->csrfToken()]);
    }

    public function verify2FA() {
        Auth::csrfValidate();
        if (!isset($_SESSION['2fa_pending_user'])) $this->redirect('/login');

        $code = trim($_POST['code'] ?? '');
        $userId = $_SESSION['2fa_pending_user'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $pendingUser = User::find((int)$userId);
        if (!$pendingUser || User::isBanned($pendingUser)) {
            unset($_SESSION['2fa_pending_user']);
            $this->flash('error', 'banned');
            $this->redirect('/login');
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            $this->flash('error', 'invalid');
            $this->redirect('/2fa');
        }

        $row = Database::query("SELECT * FROM twofa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() AND ip = ?",
            [$userId, $code, $ip])->fetch();

        if (!$row) {
            $this->flash('error', 'invalid');
            $this->redirect('/2fa');
        }

        // Clean up and trust IP
        Database::query("DELETE FROM twofa_codes WHERE user_id = ?", [$userId]);
        Auth::markTrustedIP($userId, $ip);

        $rememberMe = !empty($_SESSION['2fa_remember_me']);
        unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_remember_me']);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['auth_session_token'] = Auth::registerLoginSession((int)$userId, $ip, $userAgent);
        if ($rememberMe) {
            Auth::setRememberCookie((int)$userId, $_SESSION['auth_session_token']);
        }
        User::markLoggedIn((int)$userId);
        $authedUser = User::find((int)$userId);
        if ($authedUser) {
            Attachment::cleanupPendingForUser($authedUser);
        }
        $this->createSafariDesktopLoginNotice((int)$userId, $userAgent);
        $_SESSION['last_activity_touch_at'] = time();
        $this->redirect('/');
    }

    public function showRegister() {
        if (Auth::user()) {
            $this->redirect('/');
        }

        $inviteCodeRequired = (string)(Setting::get('invite_code_required') ?? '1') === '1';

        $this->view('auth/register', [
            'csrf' => $this->csrfToken(),
            'invitesEnabled' => $inviteCodeRequired
        ]);
    }

    public function register() {
        Auth::csrfValidate();
        $username = User::normalizeUsername($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rawPassword = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $inviteCodeRequired = (string)(Setting::get('invite_code_required') ?? '1') === '1';
        $emailVerificationRequired = (string)(Setting::get('email_verification_required') ?? '1') === '1';
        $inviteCode = trim($_POST['invite_code'] ?? '');

        if (!User::isUsernameFormatValid($username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($rawPassword) < 8) {
            $this->flash('error', 'invalid');
            $this->redirect('/register');
        }

        $password = password_hash($rawPassword, PASSWORD_DEFAULT);

        $invite = null;
        if ($inviteCodeRequired) {
            if ($this->hasTooManyAuthAttempts($ip, self::AUTH_ATTEMPT_REGISTER_INVITE_FAILED)) {
                $this->flash('error', 'too_many_attempts');
                $this->redirect('/register');
            }

            if (!preg_match('/^\d{4}-\d{4}$/', $inviteCode)) {
                $this->recordAuthAttempt($ip, self::AUTH_ATTEMPT_REGISTER_INVITE_FAILED);
                $this->flash('error', 'invalid_invite');
                $this->redirect('/register');
            }

            $invite = Database::query("SELECT * FROM invite_codes WHERE code = ? AND used_by IS NULL", [$inviteCode])->fetch();
            if (!$invite) {
                $this->recordAuthAttempt($ip, self::AUTH_ATTEMPT_REGISTER_INVITE_FAILED);
                $this->flash('error', 'invalid_invite');
                $this->redirect('/register');
            }
        }

        if (!User::isUsernameAvailableForUser($username)) {
            $this->flash('error', 'username_taken');
            $this->redirect('/register');
        }

        $existingEmail = Database::query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
        if ($existingEmail) {
            $this->flash('error', 'email_taken');
            $this->redirect('/register');
        }

        $userNumber = $this->generateUserNumber();

        if ($emailVerificationRequired) {
            Database::query("INSERT INTO users (username, email, password, user_number) VALUES (?, ?, ?, ?)",
                [$username, $email, $password, $userNumber]);
        } else {
            Database::query("INSERT INTO users (username, email, password, user_number, email_verified_at) VALUES (?, ?, ?, ?, NOW())",
                [$username, $email, $password, $userNumber]);
        }

        $newUserId = (int)Database::getInstance()->lastInsertId();
        User::recordUsernameHistory($newUserId, $username);

        if ($inviteCodeRequired && $invite) {
            Database::query("UPDATE invite_codes SET used_by = ?, used_at = NOW() WHERE id = ?", [$newUserId, $invite->id]);
        }

        if ($emailVerificationRequired) {
            $this->startEmailVerificationFlow($newUserId);
            $this->flash('success', 'sent');
            $this->redirect('/verify-email');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['auth_session_token'] = Auth::registerLoginSession($newUserId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');
        $newUser = User::find($newUserId);
        if ($newUser) {
            User::markLoggedIn($newUserId);
            Attachment::cleanupPendingForUser($newUser);
        }
        $_SESSION['last_activity_touch_at'] = time();
        $this->redirect('/');
    }

    public function showVerifyEmail() {
        $loggedIn = Auth::user();
        if ($loggedIn && empty($loggedIn->email_verified_at)) {
            $_SESSION['email_verification_user'] = $loggedIn->id;
            unset($_SESSION['user_id']);
        }

        $pendingUserId = $_SESSION['email_verification_user'] ?? null;
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $pendingUser = User::find((int)$pendingUserId);
        if (!$pendingUser) {
            unset($_SESSION['email_verification_user']);
            $this->redirect('/login');
        }

        if (User::isBanned($pendingUser)) {
            unset($_SESSION['email_verification_user']);
            $this->flash('error', 'banned');
            $this->redirect('/login');
        }

        if (!empty($pendingUser->email_verified_at)) {
            unset($_SESSION['email_verification_user']);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pendingUser->id;
            $_SESSION['auth_session_token'] = Auth::registerLoginSession((int)$pendingUser->id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $userAgent);
            User::markLoggedIn((int)$pendingUser->id);
            Attachment::cleanupPendingForUser($pendingUser);
            $this->createSafariDesktopLoginNotice((int)$pendingUser->id, $userAgent);
            $_SESSION['last_activity_touch_at'] = time();
            $this->redirect('/');
        }

        $this->view('auth/verify_email', [
            'csrf' => $this->csrfToken(),
            'email' => $pendingUser->email
        ]);
    }

    public function verifyEmail() {
        Auth::csrfValidate();

        $pendingUserId = $_SESSION['email_verification_user'] ?? null;
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $code = trim($_POST['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->flash('error', 'invalid');
            $this->redirect('/verify-email');
        }

        $row = Database::query(
            "SELECT id FROM email_verification_codes WHERE user_id = ? AND code = ? AND expires_at > NOW()",
            [$pendingUserId, $code]
        )->fetch();

        if (!$row) {
            $this->flash('error', 'invalid');
            $this->redirect('/verify-email');
        }

        $pendingUser = User::find((int)$pendingUserId);
        if (!$pendingUser || User::isBanned($pendingUser)) {
            unset($_SESSION['email_verification_user']);
            $this->flash('error', 'banned');
            $this->redirect('/login');
        }

        Database::query("UPDATE users SET email_verified_at = NOW() WHERE id = ?", [$pendingUserId]);
        Database::query("DELETE FROM email_verification_codes WHERE user_id = ?", [$pendingUserId]);

        unset($_SESSION['email_verification_user']);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$pendingUserId;
        $_SESSION['auth_session_token'] = Auth::registerLoginSession((int)$pendingUserId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $userAgent);
        User::markLoggedIn((int)$pendingUserId);
        $authedUser = User::find((int)$pendingUserId);
        if ($authedUser) {
            Attachment::cleanupPendingForUser($authedUser);
        }
        $this->createSafariDesktopLoginNotice((int)$pendingUserId, $userAgent);
        $_SESSION['last_activity_touch_at'] = time();
        $this->redirect('/');
    }

    public function resendVerifyEmail() {
        Auth::csrfValidate();

        $pendingUserId = $_SESSION['email_verification_user'] ?? null;
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $pendingUser = User::find((int)$pendingUserId);
        if (!$pendingUser) {
            unset($_SESSION['email_verification_user']);
            $this->redirect('/login');
        }

        if (User::isBanned($pendingUser)) {
            unset($_SESSION['email_verification_user']);
            $this->flash('error', 'banned');
            $this->redirect('/login');
        }

        if (!empty($pendingUser->email_verified_at)) {
            unset($_SESSION['email_verification_user']);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pendingUser->id;
            $_SESSION['auth_session_token'] = Auth::registerLoginSession((int)$pendingUser->id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $userAgent);
            User::markLoggedIn((int)$pendingUser->id);
            Attachment::cleanupPendingForUser($pendingUser);
            $this->createSafariDesktopLoginNotice((int)$pendingUser->id, $userAgent);
            $_SESSION['last_activity_touch_at'] = time();
            $this->redirect('/');
        }

        $this->startEmailVerificationFlow((int)$pendingUserId);
        $this->flash('success', 'resent');
        $this->redirect('/verify-email');
    }

    public function logout() {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($userId > 0) {
            Auth::revokeCurrentSession();
            if (User::activeSessionCount($userId) === 0) {
                User::markLoggedOut($userId);
            }
        }

        Auth::clearRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('/login');
    }

    public function showForgot() {
        if (Auth::user()) {
            $this->redirect('/');
        }

        $this->view('auth/forgot', ['csrf' => $this->csrfToken()]);
    }

    public function forgotPassword() {
        Auth::csrfValidate();
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('success', 'sent');
            $this->redirect('/forgot-password');
        }

        $user = Database::query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
        if (!$user) {
            $this->flash('success', 'sent');
            $this->redirect('/forgot-password');
        }

        Database::query("DELETE FROM password_resets WHERE user_id = ?", [$user->id]);

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        Database::query("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)", [$user->id, $token, $expires]);

        $resetLink = base_url('/reset-password?token=' . urlencode($token));
        $this->sendEmail($email, 'Prologue Password Reset', "Reset your password: <a href='{$resetLink}'>{$resetLink}</a>");

        $this->flash('success', 'sent');
        $this->redirect('/forgot-password');
    }

    public function showReset() {
        $this->view('auth/reset', ['csrf' => $this->csrfToken(), 'token' => $_GET['token'] ?? '']);
    }

    public function resetPassword() {
        Auth::csrfValidate();
        $token = trim($_POST['token'] ?? '');
        $newPassword = $_POST['password'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/', $token) || strlen($newPassword) < 8) {
            $this->flash('error', 'invalid');
            $this->redirect('/forgot-password');
        }

        $password = password_hash($newPassword, PASSWORD_DEFAULT);

        $row = Database::query("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()", [$token])->fetch();
        if (!$row) {
            $this->flash('error', 'invalid');
            $this->redirect('/forgot-password');
        }

        Database::query("UPDATE users SET password = ? WHERE id = ?", [$password, $row->user_id]);
        Database::query("DELETE FROM password_resets WHERE token = ?", [$token]);

        $this->flash('success', 'reset');
        $this->redirect('/login');
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

    private function shouldBypassBootstrap2FA(int $userId): bool {
        if ($userId !== 1) {
            return false;
        }

        if ($this->isEmailDeliveryConfigured()) {
            return false;
        }

        $hasAnyPriorSession = (int)Database::query(
            "SELECT COUNT(*) FROM user_sessions WHERE user_id = ?",
            [$userId]
        )->fetchColumn() > 0;

        return !$hasAnyPriorSession;
    }

    private function isEmailDeliveryConfigured(): bool {
        $mailHost = trim((string)(Setting::get('mail_host') ?? ''));
        $mailUser = trim((string)(Setting::get('mail_user') ?? ''));

        return $mailHost !== '' && $mailUser !== '';
    }

    private function generateUserNumber() {
        do {
            $userNumber = str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
            $exists = User::findByUserNumber($userNumber);
        } while ($exists);

        return $userNumber;
    }

    private function startEmailVerificationFlow($userId) {
        $user = User::find((int)$userId);
        if (!$user) {
            return;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        Database::query(
            "INSERT INTO email_verification_codes (user_id, code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP",
            [$user->id, $code, $expires]
        );

        $this->sendEmail(
            $user->email,
            'Verify your Prologue account',
            "Your verification code is <b>{$code}</b> (valid 10 minutes)."
        );

        $_SESSION['email_verification_user'] = (int)$user->id;
        unset($_SESSION['user_id']);
        unset($_SESSION['2fa_pending_user']);
    }

    private function createSafariDesktopLoginNotice(int $userId, string $userAgent): void {
        if (!$this->isSafariDesktopUserAgent($userAgent)) {
            return;
        }

        Notification::create(
            $userId,
            'report',
            'Browser Notice',
            'Safari does not follow web standards and some features may be limited or function inconsistently. Recommended browsers include Firefox, Edge, and Chrome.'
        );
    }

    private function isSafariDesktopUserAgent(string $userAgent): bool {
        $ua = strtolower(trim($userAgent));
        if ($ua === '') {
            return false;
        }

        $isMobile = str_contains($ua, 'mobile')
            || str_contains($ua, 'iphone')
            || str_contains($ua, 'ipad')
            || str_contains($ua, 'ipod')
            || str_contains($ua, 'android');
        if ($isMobile) {
            return false;
        }

        $containsSafari = str_contains($ua, 'safari');
        $isNotSafariEngineWrapper = !str_contains($ua, 'chrome')
            && !str_contains($ua, 'chromium')
            && !str_contains($ua, 'crios')
            && !str_contains($ua, 'fxios')
            && !str_contains($ua, 'edg')
            && !str_contains($ua, 'opr')
            && !str_contains($ua, 'opera');

        return $containsSafari && $isNotSafariEngineWrapper;
    }

    private function hasTooManyAuthAttempts($ip, $actionType) {
        $this->cleanupOldAuthAttempts();

        $attemptCount = (int)Database::query(
            "SELECT COUNT(*) FROM auth_attempt_limits WHERE ip_address = ? AND action_type = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)",
            [(string)$ip, (string)$actionType]
        )->fetchColumn();

        return $attemptCount >= self::AUTH_ATTEMPT_LIMIT_PER_MINUTE;
    }

    private function recordAuthAttempt($ip, $actionType) {
        $this->cleanupOldAuthAttempts();

        Database::query(
            "INSERT INTO auth_attempt_limits (ip_address, action_type) VALUES (?, ?)",
            [(string)$ip, (string)$actionType]
        );
    }

    private function clearAuthAttempts($ip, $actionType) {
        Database::query(
            "DELETE FROM auth_attempt_limits WHERE ip_address = ? AND action_type = ?",
            [(string)$ip, (string)$actionType]
        );
    }

    private function cleanupOldAuthAttempts() {
        Database::query(
            "DELETE FROM auth_attempt_limits WHERE created_at < (NOW() - INTERVAL 1 MINUTE)"
        );
    }
}