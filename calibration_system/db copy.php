<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dbPath = "\\\\SDP-DP-115\\Users\\pesuser6\\Desktop\\phpdesktop\\phpdesktop-chrome-130.1-php-8.3\\www\\calibration_system\\calibration_db.sqlite";
                // file location:  \\SDP-DP-115\Users\pesuser6\Desktop\phpdesktop\phpdesktop-chrome-130.1-php-8.3\www\calibration_system\calibration_db.sqlite

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA temp_store = MEMORY');
    $pdo->exec('PRAGMA cache_size = -64000');
    $pdo->exec('PRAGMA mmap_size = 536870912');

} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}