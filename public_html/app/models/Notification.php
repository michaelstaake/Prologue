<?php
class Notification extends Model {
    public static function create($userId, $type, $title, $message, $link = null) {
        Database::query("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)", [$userId, $type, $title, $message, $link]);
    }
}