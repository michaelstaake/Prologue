<?php
class PushSubscription extends Model {
    public static function upsertForUser($userId, $endpoint, $p256dhKey, $authKey, $userAgent = null) {
        $userId = (int)$userId;
        $endpoint = trim((string)$endpoint);
        $p256dhKey = trim((string)$p256dhKey);
        $authKey = trim((string)$authKey);
        $userAgent = trim((string)$userAgent);

        if ($userId <= 0 || $endpoint === '' || $p256dhKey === '' || $authKey === '') {
            return false;
        }

        Database::query(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent, fail_count, failed_at, last_error, last_http_status, last_used_at)
             VALUES (?, ?, ?, ?, ?, 0, NULL, NULL, NULL, NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                p256dh_key = VALUES(p256dh_key),
                auth_key = VALUES(auth_key),
                user_agent = VALUES(user_agent),
                fail_count = 0,
                failed_at = NULL,
                last_error = NULL,
                last_http_status = NULL,
                last_used_at = NOW()",
            [$userId, $endpoint, $p256dhKey, $authKey, $userAgent !== '' ? $userAgent : null]
        );

        return true;
    }

    public static function removeForUserByEndpoint($userId, $endpoint) {
        $userId = (int)$userId;
        $endpoint = trim((string)$endpoint);
        if ($userId <= 0 || $endpoint === '') {
            return 0;
        }

        $stmt = Database::query(
            "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?",
            [$userId, $endpoint]
        );

        return (int)$stmt->rowCount();
    }

    public static function getActiveForUser($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [];
        }

        return Database::query(
            "SELECT *
             FROM push_subscriptions
             WHERE user_id = ?
               AND (failed_at IS NULL OR fail_count < 3)
             ORDER BY id ASC",
            [$userId]
        )->fetchAll();
    }

    public static function markSuccess($subscriptionId) {
        $subscriptionId = (int)$subscriptionId;
        if ($subscriptionId <= 0) {
            return;
        }

        Database::query(
            "UPDATE push_subscriptions
             SET fail_count = 0,
                 failed_at = NULL,
                 last_error = NULL,
                 last_http_status = 201,
                 last_used_at = NOW()
             WHERE id = ?",
            [$subscriptionId]
        );

        self::logDelivery($subscriptionId, 'success', 201, null);
    }

    public static function markRetryableFailure($subscriptionId, $httpStatus = null, $errorMessage = null) {
        $subscriptionId = (int)$subscriptionId;
        if ($subscriptionId <= 0) {
            return;
        }

        $httpStatus = $httpStatus !== null ? (int)$httpStatus : null;
        $errorMessage = self::normalizeErrorText($errorMessage);

        Database::query(
            "UPDATE push_subscriptions
             SET fail_count = fail_count + 1,
                 failed_at = NOW(),
                 last_http_status = ?,
                 last_error = ?,
                 last_used_at = NOW()
             WHERE id = ?",
            [$httpStatus, $errorMessage, $subscriptionId]
        );

        self::logDelivery($subscriptionId, 'retryable', $httpStatus, $errorMessage);
    }

    public static function markPermanentFailure($subscriptionId, $httpStatus = null, $errorMessage = null) {
        $subscriptionId = (int)$subscriptionId;
        if ($subscriptionId <= 0) {
            return;
        }

        $httpStatus = $httpStatus !== null ? (int)$httpStatus : null;
        $errorMessage = self::normalizeErrorText($errorMessage);

        Database::query(
            "UPDATE push_subscriptions
             SET fail_count = 5,
                 failed_at = NOW(),
                 last_http_status = ?,
                 last_error = ?,
                 last_used_at = NOW()
             WHERE id = ?",
            [$httpStatus, $errorMessage, $subscriptionId]
        );

        self::logDelivery($subscriptionId, 'permanent_fail', $httpStatus, $errorMessage);
    }

    public static function removeById($subscriptionId) {
        $subscriptionId = (int)$subscriptionId;
        if ($subscriptionId <= 0) {
            return;
        }

        Database::query("DELETE FROM push_subscriptions WHERE id = ?", [$subscriptionId]);
    }

    public static function countForUser($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return 0;
        }

        return (int)Database::query(
            "SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?",
            [$userId]
        )->fetchColumn();
    }

    private static function logDelivery($subscriptionId, $status, $httpStatus, $errorMessage) {
        Database::query(
            "INSERT INTO push_delivery_logs (subscription_id, status, http_status, error_message)
             VALUES (?, ?, ?, ?)",
            [(int)$subscriptionId, (string)$status, $httpStatus !== null ? (int)$httpStatus : null, self::normalizeErrorText($errorMessage)]
        );
    }

    private static function normalizeErrorText($errorMessage) {
        $text = trim((string)$errorMessage);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 1000) {
            return mb_substr($text, 0, 1000);
        }

        return $text;
    }
}