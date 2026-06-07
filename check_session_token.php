<?php
/**
 * check_session_token.php
 *
 * Validates that the current session token matches the one stored in the DB.
 * If not, someone else logged in on another device — force logout.
 *
 * Safe to include mid-page — uses JS redirect as fallback when headers
 * have already been sent.
 */

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
    return; // not logged in or guest — nothing to check
}

if (empty($_SESSION['session_token'])) {
    $reason = 'session_expired';
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.php?reason=$reason");
        exit();
    }
    echo "<script>window.location.href='login.php?reason=$reason';</script>";
    exit();
}

try {
    if (!isset($pdo)) require_once __DIR__ . '/db.php';

    $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['session_token'] !== $_SESSION['session_token']) {
        $reason = 'logged_in_elsewhere';
        session_destroy();
        if (!headers_sent()) {
            header("Location: login.php?reason=$reason");
            exit();
        }
        echo "<script>window.location.href='login.php?reason=$reason';</script>";
        exit();
    }

} catch (Exception $e) {
    // DB error — fail silently, don't lock out the user
}