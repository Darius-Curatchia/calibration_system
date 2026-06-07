<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

// ── Clear session token so the account can log in again from another device ───
if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] !== 0) {
    $pdo->prepare("UPDATE users SET session_token = NULL, last_seen = NULL WHERE id = ?")
        ->execute([$_SESSION['user_id']]);
}

log_audit($pdo, 'LOGOUT', null, null, 'Logged out');

session_destroy();
header("Location: login.php");
exit();