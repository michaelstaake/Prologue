<?php
class Notification extends Model {
    public static function create($userId, $type, $title, $message, $link = null) {
        Database::query("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)", [$userId, $type, $title, $message, $link]);

        try {
            WebPushService::sendForNotification($userId, $type, $title, $message, $link);
        } catch (Throwable $exception) {
            // Push delivery errors must never block core notification persistence.
        }
    }
}