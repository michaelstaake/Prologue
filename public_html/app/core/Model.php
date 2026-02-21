<?php
class Model {
    protected static function db() {
        return Database::getInstance();
    }

    protected static function query($sql, $params = []) {
        return Database::query($sql, $params);
    }
}