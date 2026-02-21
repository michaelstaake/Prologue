<?php
class Setting extends Model {
    public static function get($key) {
        $row = Database::query("SELECT `value` FROM settings WHERE `key` = ?", [$key])->fetchColumn();
        return $row !== false ? $row : null;
    }

    public static function set($key, $value) {
        Database::query("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$key, $value]);
    }
}