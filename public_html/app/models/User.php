<?php
class User extends Model {
    public static function normalizeUsername($username) {
        return strtolower(trim((string)$username));
    }

    public static function isUsernameFormatValid($username) {
        $normalized = self::normalizeUsername($username);
        return (bool)preg_match('/^[a-z][a-z0-9]{3,31}$/', $normalized);
    }

    public static function find($id) {
        $stmt = self::query("SELECT * FROM users WHERE id = ?", [$id]);
        return $stmt->fetch();
    }

    public static function findByUsername($username) {
        $normalized = self::normalizeUsername($username);
        $stmt = self::query("SELECT * FROM users WHERE username = ?", [$normalized]);
        return $stmt->fetch();
    }

    public static function findByEmail($email) {
        $stmt = self::query("SELECT * FROM users WHERE email = ?", [trim((string)$email)]);
        return $stmt->fetch();
    }

    public static function isBanned($user) {
        if (!$user) {
            return false;
        }

        return (int)($user->is_banned ?? 0) === 1;
    }

    public static function findUsernameHistoryOwner($username) {
        $normalized = self::normalizeUsername($username);
        $stmt = self::query("SELECT user_id FROM username_history WHERE username = ? LIMIT 1", [$normalized]);
        return $stmt->fetch();
    }

    public static function isUsernameAvailableForUser($username, $userId = null, $currentUsername = null) {
        $normalizedUsername = self::normalizeUsername($username);
        if (!self::isUsernameFormatValid($normalizedUsername)) {
            return false;
        }

        if ($currentUsername !== null && $normalizedUsername === self::normalizeUsername($currentUsername)) {
            return false;
        }

        $historyOwner = self::findUsernameHistoryOwner($normalizedUsername);
        if ($historyOwner && (int)$historyOwner->user_id !== (int)$userId) {
            return false;
        }

        $currentOwner = self::findByUsername($normalizedUsername);
        if ($currentOwner && (int)$currentOwner->id !== (int)$userId) {
            return false;
        }

        return true;
    }

    public static function recordUsernameHistory($userId, $username) {
        self::query(
            "INSERT INTO username_history (user_id, username) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id",
            [(int)$userId, self::normalizeUsername($username)]
        );
    }

    public static function findByUserNumber($number) {
        $stmt = self::query("SELECT * FROM users WHERE user_number = ?", [$number]);
        return $stmt->fetch();
    }

    public static function formatUserNumber($num) {
        return substr($num, 0, 4) . '-' . substr($num, 4, 4) . '-' . substr($num, 8, 4) . '-' . substr($num, 12, 4);
    }

    public static function avatarUrl($user) {
        if (!$user) {
            return null;
        }

        $filename = trim((string)($user->avatar_filename ?? ''));
        if ($filename === '') {
            return null;
        }

        $safeFilename = basename($filename);
        if ($safeFilename !== $filename || !preg_match('/^u\d+\.(jpg|jpeg|png)$/i', $safeFilename)) {
            return null;
        }

        $path = rtrim((string)(defined('STORAGE_FILESYSTEM_ROOT') ? STORAGE_FILESYSTEM_ROOT : (dirname(__DIR__, 3) . '/storage')), '/') . '/avatars/' . $safeFilename;
        if (!is_file($path)) {
            return null;
        }

        return base_url('/storage/avatars/' . rawurlencode($safeFilename)) . '?v=' . (int)filemtime($path);
    }

    public static function avatarInitial($username) {
        $username = trim((string)$username);
        if ($username === '') {
            return '?';
        }

        return strtoupper(mb_substr($username, 0, 1));
    }

    public static function avatarColorClasses($stableValue) {
        $palette = [
            'bg-emerald-700 text-emerald-100',
            'bg-blue-700 text-blue-100',
            'bg-violet-700 text-violet-100',
            'bg-amber-700 text-amber-100',
            'bg-cyan-700 text-cyan-100',
            'bg-fuchsia-700 text-fuchsia-100',
            'bg-rose-700 text-rose-100',
            'bg-indigo-700 text-indigo-100'
        ];

        $value = (string)$stableValue;
        if ($value === '') {
            return $palette[0];
        }

        $index = ((int)sprintf('%u', crc32($value))) % count($palette);

        return $palette[$index];
    }

    public static function normalizePresenceStatus($status) {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'busy') {
            return 'busy';
        }

        if ($normalized === 'online') {
            return 'online';
        }

        if ($normalized === 'offline') {
            return 'offline';
        }

        return null;
    }

    public static function presenceStatusLabel($status) {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'busy') {
            return 'Busy';
        }

        if ($normalized === 'offline') {
            return 'Offline';
        }

        return 'Online';
    }

    public static function presenceStatusTextClass($status) {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'busy') {
            return 'text-amber-400';
        }

        if ($normalized === 'offline') {
            return 'text-red-400';
        }

        return 'text-emerald-400';
    }

    public static function presenceStatusDotClass($status) {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'busy') {
            return 'bg-amber-500';
        }

        if ($normalized === 'offline') {
            return 'bg-red-500';
        }

        return 'bg-emerald-500';
    }

    public static function hasActiveCall($userId) {
        $active = self::query(
            "SELECT cp.id
             FROM call_participants cp
             JOIN calls c ON c.id = cp.call_id
             WHERE cp.user_id = ?
               AND cp.left_at IS NULL
               AND c.status = 'active'
             LIMIT 1",
            [(int)$userId]
        )->fetch();

        return (bool)$active;
    }

    public static function effectivePresenceStatus($user) {
        if (!$user) {
            return 'offline';
        }

        $userId = (int)($user->id ?? 0);
        if ($userId > 0 && self::hasActiveCall($userId)) {
            return 'busy';
        }

        if (self::normalizePresenceStatus($user->presence_status ?? null) === 'offline') {
            return 'offline';
        }

        $lastActiveAt = trim((string)($user->last_active_at ?? ''));
        if ($lastActiveAt === '') {
            return 'offline';
        }

        $lastActiveTimestamp = strtotime($lastActiveAt);
        if ($lastActiveTimestamp === false || $lastActiveTimestamp < (time() - 3600)) {
            return 'offline';
        }

        return self::normalizePresenceStatus($user->presence_status ?? null) ?? 'online';
    }

    public static function attachEffectiveStatus($user) {
        if (!$user) {
            return null;
        }

        $status = self::effectivePresenceStatus($user);
        $user->effective_status = $status;
        $user->effective_status_label = self::presenceStatusLabel($status);
        $user->effective_status_text_class = self::presenceStatusTextClass($status);
        $user->effective_status_dot_class = self::presenceStatusDotClass($status);

        return $user;
    }

    public static function attachEffectiveStatusList($users) {
        if (!is_array($users)) {
            return $users;
        }

        foreach ($users as $user) {
            self::attachEffectiveStatus($user);
        }

        return $users;
    }

    public static function touchActivity($userId) {
        self::query("UPDATE users SET last_active_at = NOW() WHERE id = ?", [(int)$userId]);
    }

    public static function activeSessionCount($userId) {
        return (int) self::query(
            "SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL",
            [(int)$userId]
        )->fetchColumn();
    }

    public static function getActiveSessions($userId) {
        return self::query(
            "SELECT id, ip_address, browser, logged_in_at, last_seen_at, session_token
             FROM user_sessions
             WHERE user_id = ? AND revoked_at IS NULL
             ORDER BY logged_in_at DESC",
            [(int)$userId]
        )->fetchAll();
    }

    public static function revokeSessionById($userId, $sessionId) {
        $result = self::query(
            "UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE id = ? AND user_id = ? AND revoked_at IS NULL",
            [(int)$sessionId, (int)$userId]
        );

        return $result->rowCount() > 0;
    }

    public static function revokeSessionByToken($userId, $sessionToken) {
        $result = self::query(
            "UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE user_id = ? AND session_token = ? AND revoked_at IS NULL",
            [(int)$userId, (string)$sessionToken]
        );

        return $result->rowCount() > 0;
    }

    public static function deleteAllSessionsForUser($userId) {
        self::query(
            "DELETE FROM user_sessions WHERE user_id = ?",
            [(int)$userId]
        );
    }

    public static function markLoggedIn($userId) {
        self::query(
            "UPDATE users SET presence_status = 'online', last_active_at = NOW() WHERE id = ?",
            [(int)$userId]
        );
    }

    public static function markLoggedOut($userId) {
        self::query("UPDATE users SET last_active_at = NULL WHERE id = ?", [(int)$userId]);
    }

    public static function setPresenceStatus($userId, $status) {
        $normalized = self::normalizePresenceStatus($status);
        if ($normalized === null) {
            return false;
        }

        self::query("UPDATE users SET presence_status = ? WHERE id = ?", [$normalized, (int)$userId]);
        return true;
    }
}