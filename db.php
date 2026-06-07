<?php
try {
    // Database in project folder
    $dbPath = __DIR__ . "/calibration_db.sqlite";

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

    // SQLite performance settings
    $pdo->exec('PRAGMA journal_mode = WAL');         // allows read/write at the same time
    $pdo->exec('PRAGMA foreign_keys = ON');          // enforce FK constraints
    $pdo->exec('PRAGMA synchronous = NORMAL');       // faster writes, safe for desktop
    $pdo->exec('PRAGMA busy_timeout = 5000');        // wait up to 5s if db is locked
    $pdo->exec('PRAGMA temp_store = MEMORY');        // use RAM for temp tables

} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}