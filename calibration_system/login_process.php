<?php
session_start();
require "db.php";
require_once "audit_helper.php";
require_once "check_stale_sessions.php";

// ── Ensure session_token column exists ────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN session_token TEXT DEFAULT NULL");
} catch (Exception $e) {}

/* =========================
   GUEST LOGIN
========================= */
if (isset($_POST['guest_login']) && $_POST['guest_login'] == 1) {

    $_SESSION['user_id'] = 0;
    $_SESSION['username'] = 'Guest';
    $_SESSION['role'] = 'guest';

    log_audit($pdo, 'LOGIN', null, null, 'Guest login');

    header("Location: dashboard.php");
    exit();
}

/* =========================
   NORMAL LOGIN
========================= */
if (isset($_POST['username'], $_POST['password'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        // ── Block if already actively logged in on another device ─────────────
        if (!empty($user['session_token'])) {
            $activeStmt = $pdo->prepare("
                SELECT COUNT(*) FROM users
                WHERE id = ?
                  AND last_seen >= datetime('now', '-2 minutes')
                  AND session_token IS NOT NULL
            ");
            $activeStmt->execute([$user['id']]);

            if ((int)$activeStmt->fetchColumn() > 0) {
                $machineStmt = $pdo->prepare("
                    SELECT ip_address FROM audit_log
                    WHERE user_id = ?
                      AND action = 'LOGIN'
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $machineStmt->execute([$user['id']]);
                $machine = $machineStmt->fetchColumn();

                $_SESSION['login_error'] = $machine
                    ? "Already logged in on: " . $machine . ". Please log out from that device first."
                    : "This account is already logged in on another device. Please log out first.";

                header("Location: login.php");
                exit();
            }
        }

        // ── No active session elsewhere — proceed with login ──────────────────
        $token = bin2hex(random_bytes(32));

        // Set session_token AND last_seen immediately so the block works right away
        // without waiting for the first heartbeat ping (60s delay)
        $pdo->prepare("UPDATE users SET session_token = ?, last_seen = datetime('now') WHERE id = ?")
            ->execute([$token, $user['id']]);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['session_token'] = $token;

        check_stale_sessions($pdo);

        log_audit($pdo, 'LOGIN', null, null, 'Logged in — role: ' . $user['role']);

        header("Location: dashboard.php");
        exit();
    }
}

/* =========================
   LOGIN FAILED
========================= */
$_SESSION['login_error'] = "Invalid username or password.";
header("Location: login.php");
exit();