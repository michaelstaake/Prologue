<?php
class Call extends Model {
    public static function findActive($chatId) {
        return Database::query("SELECT * FROM calls WHERE chat_id = ? AND status = 'active'", [$chatId])->fetch();
    }
}