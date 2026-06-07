<?php
/**
 * heartbeat.php
 * Actions:
 *   POST action=ping              — update last_seen for the active session
 *   POST action=force_close       — log a FORCE_CLOSE event (sendBeacon on unload)
 *   POST action=cancel_force_close — delete the most recent FORCE_CLOSE if the
 *                                    page reloaded/navigated (sessionStorage handshake)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(204);
    exit();
}

include 'db.php';
include 'audit_helper.php';

// ── Ensure the heartbeat column exists ───────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL");
} catch (Exception $e) {}

$action = $_POST['action'] ?? '';

// ── PING: just update last_seen timestamp ────────────────────────────────────
if ($action === 'ping') {
    $pdo->prepare("UPDATE users SET last_seen = datetime('now') WHERE id = ?")
        ->execute([$_SESSION['user_id']]);
    http_response_code(204);
    exit();
}

// ── FORCE_CLOSE: log immediately (called via sendBeacon on unload) ────────────
if ($action === 'force_close') {
    // Avoid double-logging if user already clicked Logout within the last 5s
    $recent = $pdo->prepare("
        SELECT id FROM audit_log
        WHERE user_id = ?
          AND action IN ('LOGOUT', 'FORCE_CLOSE')
          AND created_at >= datetime('now', '-5 seconds')
        LIMIT 1
    ");
    $recent->execute([$_SESSION['user_id']]);

    if (!$recent->fetch()) {
        log_audit($pdo, 'FORCE_CLOSE', null, null, 'Window closed or app exited without logout');
    }

    // Clear both last_seen AND session_token so the account is free to log in again
    $pdo->prepare("UPDATE users SET last_seen = NULL, session_token = NULL WHERE id = ?")
        ->execute([$_SESSION['user_id']]);

    http_response_code(204);
    exit();
}

// ── CANCEL_FORCE_CLOSE: page reloaded/navigated — undo the last FORCE_CLOSE ──
if ($action === 'cancel_force_close') {
    $stmt = $pdo->prepare("
        SELECT id FROM audit_log
        WHERE user_id = ?
          AND action = 'FORCE_CLOSE'
          AND created_at >= datetime('now', '-10 seconds')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare("DELETE FROM audit_log WHERE id = ?")
            ->execute([$row['id']]);
        // Restore last_seen so the session is considered active again
        $pdo->prepare("UPDATE users SET last_seen = datetime('now') WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
    }

    http_response_code(204);
    exit();
}

http_response_code(400);
exit();