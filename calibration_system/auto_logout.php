<?php
/**
 * auto_logout.php
 * Called via fetch() from heartbeat.js when the idle timeout fires.
 * Logs AUTO_LOGOUT to audit_log, clears last_seen and session_token, and destroys the session.
 */
session_start();

// Only handle authenticated, non-guest users
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === 0) {
    http_response_code(204);
    exit();
}

include 'db.php';
include 'audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_logout') {

    // Log the AUTO_LOGOUT event
    log_audit($pdo, 'AUTO_LOGOUT', null, null, 'Session automatically logged out after 30 minutes of inactivity');

    // Clear heartbeat timestamp and session token so account is free to log in again
    try {
        $pdo->prepare("UPDATE users SET last_seen = NULL, session_token = NULL WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}

    // Destroy the session completely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

http_response_code(204);
exit();