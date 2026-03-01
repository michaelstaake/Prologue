<?php

class ApiKey extends Model {

    public static function generateKey(): string {
        return bin2hex(random_bytes(32));
    }

    public static function findByKey(string $apiKey) {
        return self::query(
            "SELECT ak.*, u.is_banned
             FROM api_keys ak
             JOIN users u ON u.id = ak.user_id
             WHERE ak.api_key = ? LIMIT 1",
            [$apiKey]
        )->fetch();
    }

    public static function getAllForUser(int $userId): array {
        return self::query(
            "SELECT id, name, type, status, allowed_ips, allowed_chats,
                    LEFT(api_key, 8) AS key_prefix, expires_at, created_at
             FROM api_keys
             WHERE user_id = ?
             ORDER BY status ASC, created_at DESC",
            [$userId]
        )->fetchAll();
    }

    public static function create(int $userId, string $rawKey, string $name,
                                   string $type, ?string $allowedIps,
                                   ?string $allowedChats, ?string $expiresAt): int {
        self::query(
            "INSERT INTO api_keys (user_id, api_key, name, type, allowed_ips, allowed_chats, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $rawKey, $name, $type, $allowedIps, $allowedChats, $expiresAt]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function expireKey(int $keyId, int $userId): void {
        self::query(
            "UPDATE api_keys SET status = 'expired', expires_at = COALESCE(expires_at, NOW()) WHERE id = ? AND user_id = ?",
            [$keyId, $userId]
        );
    }

    public static function expireAllForUser(int $userId): void {
        self::query(
            "UPDATE api_keys SET status = 'expired', expires_at = COALESCE(expires_at, NOW()) WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
    }

    public static function deleteAllForUser(int $userId): void {
        self::query("DELETE FROM api_keys WHERE user_id = ?", [$userId]);
    }

    public static function cleanupExpiredKeys(int $userId): void {
        self::query(
            "DELETE FROM api_keys
             WHERE user_id = ? AND status = 'expired'
               AND expires_at IS NOT NULL
               AND expires_at < (NOW() - INTERVAL 1 YEAR)",
            [$userId]
        );
    }

    public static function logUsage(int $apiKeyId, string $ipAddress, string $endpoint): void {
        self::query(
            "INSERT INTO api_key_logs (api_key_id, ip_address, endpoint) VALUES (?, ?, ?)",
            [$apiKeyId, $ipAddress, $endpoint]
        );
    }

    public static function isIpAllowed(?string $allowedIps, string $clientIp): bool {
        if ($allowedIps === null || $allowedIps === '') {
            return true;
        }
        $allowed = array_map('trim', explode(',', $allowedIps));
        $allowed = array_filter($allowed, function ($ip) { return $ip !== ''; });
        if (empty($allowed)) {
            return true;
        }
        return in_array($clientIp, $allowed, true);
    }

    public static function isChatAllowed(?string $allowedChats, int $chatId): bool {
        if ($allowedChats === null || $allowedChats === '') {
            return false;
        }
        $allowed = array_map('trim', explode(',', $allowedChats));
        return in_array((string)$chatId, $allowed, true);
    }

    public static function activeCountForUser(int $userId): int {
        return (int)self::query(
            "SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND status = 'active'",
            [$userId]
        )->fetchColumn();
    }
}
