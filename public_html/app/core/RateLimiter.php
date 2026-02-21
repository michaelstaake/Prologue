<?php

class RateLimiter {
    private const API_LIMIT_GET_PER_MINUTE = 240;
    private const API_LIMIT_MUTATION_PER_MINUTE = 120;
    private const CLEANUP_CHANCE_DENOMINATOR = 100;
    private const RETAIN_MINUTES = 15;

    private static $tableReady = false;

    public static function enforceApiLimit(string $path, string $method): void {
        self::ensureTable();

        $normalizedPath = self::normalizePath($path);
        $normalizedMethod = strtoupper(trim($method));
        $bucketStart = date('Y-m-d H:i:00');
        $ipAddress = substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $limit = self::methodLimit($normalizedMethod);

        Database::query(
            "INSERT INTO api_rate_limits (bucket_start, ip_address, user_id, route_key, method, request_count)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = CURRENT_TIMESTAMP",
            [$bucketStart, $ipAddress, $userId, $normalizedPath, $normalizedMethod]
        );

        $currentCount = (int)Database::query(
            "SELECT request_count FROM api_rate_limits
             WHERE bucket_start = ? AND ip_address = ? AND user_id = ? AND route_key = ? AND method = ?
             LIMIT 1",
            [$bucketStart, $ipAddress, $userId, $normalizedPath, $normalizedMethod]
        )->fetchColumn();

        self::maybeCleanup();

        if ($currentCount <= $limit) {
            return;
        }

        header('Retry-After: 60');
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Too Many Requests']);
        exit;
    }

    private static function methodLimit(string $method): int {
        if ($method === 'GET' || $method === 'HEAD') {
            return self::API_LIMIT_GET_PER_MINUTE;
        }

        return self::API_LIMIT_MUTATION_PER_MINUTE;
    }

    private static function normalizePath(string $path): string {
        $normalized = strtolower(trim($path));
        if ($normalized === '') {
            return '/';
        }

        $normalized = preg_replace('/\/[0-9]+(?=\/|$)/', '/:id', $normalized);
        $normalized = preg_replace('/\/[a-f0-9]{24,}(?=\/|$)/i', '/:token', $normalized);

        return $normalized ?: '/';
    }

    private static function ensureTable(): void {
        if (self::$tableReady) {
            return;
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS api_rate_limits (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bucket_start DATETIME NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_id INT NOT NULL DEFAULT 0,
                route_key VARCHAR(191) NOT NULL,
                method VARCHAR(10) NOT NULL,
                request_count INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_api_rate_limit (bucket_start, ip_address, user_id, route_key, method),
                INDEX idx_api_rate_limit_bucket (bucket_start),
                INDEX idx_api_rate_limit_user (user_id, bucket_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$tableReady = true;
    }

    private static function maybeCleanup(): void {
        if (random_int(1, self::CLEANUP_CHANCE_DENOMINATOR) !== 1) {
            return;
        }

        Database::query(
            "DELETE FROM api_rate_limits WHERE bucket_start < (NOW() - INTERVAL " . self::RETAIN_MINUTES . " MINUTE)"
        );
    }
}
