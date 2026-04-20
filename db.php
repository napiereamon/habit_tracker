<?php
$host     = 'localhost';
$dbname   = 'habit_tracker';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS habits (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        color      VARCHAR(7)   NOT NULL DEFAULT '#6366f1',
        icon       VARCHAR(10)  NOT NULL DEFAULT '⭐',
        created_at DATE         NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS habit_logs (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        habit_id   INT  NOT NULL,
        log_date   DATE NOT NULL,
        UNIQUE KEY unique_log (habit_id, log_date),
        FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration: add user_id to habits for existing installs
$col = $pdo->query("SHOW COLUMNS FROM habits LIKE 'user_id'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE habits
                ADD COLUMN user_id INT NULL AFTER id,
                ADD INDEX  idx_habits_user (user_id)");
}

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
}
