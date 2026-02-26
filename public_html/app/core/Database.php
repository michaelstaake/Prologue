<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);

        $charsetStatements = [
            "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            "SET NAMES utf8mb4",
            "SET character_set_connection = utf8mb4",
            "SET character_set_client = utf8mb4",
            "SET character_set_results = utf8mb4"
        ];

        foreach ($charsetStatements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (Throwable $e) {
            }
        }

        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}