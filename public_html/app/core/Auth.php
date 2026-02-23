<?php
class Auth {
    public static function user() {
        if (!isset($_SESSION['user_id'])) {
            self::tryRestoreFromRememberCookie();
        }

        if (!isset($_SESSION['user_id'])) return null;

        $sessionToken = trim((string)($_SESSION['auth_session_token'] ?? ''));
        if ($sessionToken === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionToken = self::registerLoginSession((int)$_SESSION['user_id'], $ip, $userAgent);
            $_SESSION['auth_session_token'] = $sessionToken;
        }

        $sessionRow = Database::query(
            "SELECT id FROM user_sessions WHERE user_id = ? AND session_token = ? AND revoked_at IS NULL LIMIT 1",
            [(int)$_SESSION['user_id'], $sessionToken]
        )->fetch();

        if (!$sessionRow) {
            self::clearSessionState();
            return null;
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            self::clearSessionState();
            return null;
        }

        if (User::isBanned($user)) {
            User::deleteAllSessionsForUser((int)$user->id);
            self::clearSessionState();
            return null;
        }

        $lastTouchAt = (int)($_SESSION['last_activity_touch_at'] ?? 0);
        $now = time();
        if ($lastTouchAt <= 0 || ($now - $lastTouchAt) >= 60) {
            User::touchActivity((int)$user->id);
            Database::query(
                "UPDATE user_sessions SET last_seen_at = NOW() WHERE user_id = ? AND session_token = ? AND revoked_at IS NULL",
                [(int)$user->id, $sessionToken]
            );
            $_SESSION['last_activity_touch_at'] = $now;
            $user->last_active_at = date('Y-m-d H:i:s', $now);
        }

        return User::attachEffectiveStatus($user);
    }

    public static function requireAuth() {
        $user = self::user();
        if (!$user) {
            header("Location: " . base_url('/login'));
            exit;
        }

        $emailVerificationRequired = (string)(Setting::get('email_verification_required') ?? '1') === '1';
        if ($emailVerificationRequired && empty($user->email_verified_at)) {
            $_SESSION['email_verification_user'] = $user->id;
            unset($_SESSION['user_id']);
            header("Location: " . base_url('/verify-email'));
            exit;
        }
    }

    public static function needs2FA($userId, $ip) {
        $stmt = Database::query("SELECT id FROM user_trusted_ips WHERE user_id = ? AND ip = ? AND last_login > NOW() - INTERVAL 14 DAY", [$userId, $ip]);
        return $stmt->rowCount() === 0;
    }

    public static function markTrustedIP($userId, $ip) {
        Database::query("INSERT INTO user_trusted_ips (user_id, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_login = NOW()", [$userId, $ip]);
    }

    public static function csrfValidate() {
        if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF validation failed");
        }
    }

    public static function registerLoginSession($userId, $ip, $userAgent) {
        $token = bin2hex(random_bytes(32));
        Database::query(
            "INSERT INTO user_sessions (user_id, session_token, ip_address, browser) VALUES (?, ?, ?, ?)",
            [(int)$userId, $token, (string)$ip, self::browserLabel($userAgent)]
        );

        return $token;
    }

    public static function revokeCurrentSession() {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $sessionToken = trim((string)($_SESSION['auth_session_token'] ?? ''));
        if ($userId <= 0 || $sessionToken === '') {
            return;
        }

        User::revokeSessionByToken($userId, $sessionToken);
    }

    private static function clearSessionState() {
        unset($_SESSION['user_id'], $_SESSION['auth_session_token'], $_SESSION['last_activity_touch_at']);
    }

    public static function setRememberCookie(int $userId, string $sessionToken): void {
        $rememberToken = bin2hex(random_bytes(32));
        Database::query(
            "UPDATE user_sessions SET remember_token = ? WHERE user_id = ? AND session_token = ? AND revoked_at IS NULL",
            [$rememberToken, $userId, $sessionToken]
        );
        $isHttps = is_https_request();
        setcookie('remember_me', $rememberToken, [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearRememberCookie(): void {
        $isHttps = is_https_request();
        setcookie('remember_me', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function tryRestoreFromRememberCookie(): void {
        $token = $_COOKIE['remember_me'] ?? '';
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }

        $row = Database::query(
            "SELECT user_id, session_token FROM user_sessions WHERE remember_token = ? AND revoked_at IS NULL LIMIT 1",
            [$token]
        )->fetch();

        if (!$row) {
            self::clearRememberCookie();
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row->user_id;
        $_SESSION['auth_session_token'] = $row->session_token;
        $_SESSION['last_activity_touch_at'] = 0;

        // Refresh cookie expiry (rolling 30-day window)
        $isHttps = is_https_request();
        setcookie('remember_me', $token, [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function browserLabel($userAgent) {
        $ua = strtolower(trim((string)$userAgent));
        if ($ua === '') {
            return 'Unknown browser';
        }

        if (strpos($ua, 'edg/') !== false) return 'Microsoft Edge';
        if (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) return 'Opera';
        if (strpos($ua, 'firefox/') !== false) return 'Firefox';
        if (strpos($ua, 'chrome/') !== false && strpos($ua, 'chromium') === false) return 'Chrome';
        if (strpos($ua, 'safari/') !== false && strpos($ua, 'chrome/') === false) return 'Safari';

        return 'Unknown browser';
    }
}