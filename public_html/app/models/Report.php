<?php
class Report extends Model {
    public const TARGET_TYPES = ['user', 'chat', 'message', 'post'];

    public static function create(?int $reporterId, string $targetType, int $targetId, string $reason): ?int {
        $normalizedType = strtolower(trim($targetType));
        $normalizedReason = self::normalizeReason($reason);

        if (!in_array($normalizedType, self::TARGET_TYPES, true) || $targetId <= 0 || $normalizedReason === '') {
            return null;
        }

        Database::query(
            'INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)',
            [
                ($reporterId !== null && $reporterId > 0) ? $reporterId : null,
                $normalizedType,
                $targetId,
                $normalizedReason,
            ]
        );

        $reportId = (int)Database::getInstance()->lastInsertId();
        return $reportId > 0 ? $reportId : null;
    }

    public static function createAutomated(string $targetType, int $targetId, string $reason): ?int {
        $reportId = self::create(null, $targetType, $targetId, $reason);
        if ($reportId !== null) {
            self::notifyAdmins('AI Content Alert', 'AI flagged content and created a report for review.', '/reports');
        }

        return $reportId;
    }

    public static function notifyAdmins(string $title = 'New Report', string $message = 'A new report was submitted and is pending review.', string $link = '/reports'): void {
        $admins = Database::query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $admin) {
            $adminId = (int)($admin->id ?? 0);
            if ($adminId <= 0) {
                continue;
            }

            Notification::create($adminId, 'report', $title, $message, $link);
        }
    }

    private static function normalizeReason(string $reason): string {
        $normalized = trim($reason);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, 1000, 'UTF-8');
        }

        return substr($normalized, 0, 1000);
    }
}