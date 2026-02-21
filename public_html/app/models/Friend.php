<?php
class Friend extends Model {
    public static function getFriends($userId) {
        return Database::query("SELECT u.* FROM friends f JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) = u.id WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'", [$userId, $userId, $userId])->fetchAll();
    }
}