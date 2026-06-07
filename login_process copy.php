<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
session_start();
require "db.php";
require_once "audit_helper.php";
require_once "check_stale_sessions.php";

// ── Ensure required columns exist ─────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN session_token TEXT DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL");
} catch (Exception $e) {}

// ── File-based lock helpers ────────────────────────────────────────────────────
// The lock file sits next to the SQLite DB on the HOST so both PCs see the same file.
function getLoginLockFile(PDO $pdo): string {
    $row = $pdo->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
    $dbDir = dirname($row['file'] ?? __DIR__ . '/calibration_db.sqlite');
    return $dbDir . '/login.lock';
}

function acquireLoginLock(string $lockFile, int $timeoutSeconds = 8): bool {
    $start = time();
    while (time() - $start < $timeoutSeconds) {
        // 'x' mode = create only if not exists — atomic even over SMB
        $fp = @fopen($lockFile, 'x');
        if ($fp !== false) {
            fwrite($fp, (string)time());
            fclose($fp);
            return true;
        }
        // Remove stale lock if older than 15 seconds
        if (file_exists($lockFile)) {
            $age = time() - (int)@file_get_contents($lockFile);
            if ($age > 15) {
                @unlink($lockFile);
                continue;
            }
        }
        usleep(200000); // wait 200ms then retry
    }
    return false;
}

function releaseLoginLock(string $lockFile): void {
    @unlink($lockFile);
}

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

        $lockFile = getLoginLockFile($pdo);

        // ── Acquire file-based lock before checking/writing session ───────────
        if (!acquireLoginLock($lockFile)) {
            $_SESSION['login_error'] = "Login is busy, please try again in a moment.";
            header("Location: login.php");
            exit();
        }

        try {
            // Re-read row fresh now that we hold the lock
            $freshStmt = $pdo->prepare("SELECT session_token, last_seen FROM users WHERE id = ?");
            $freshStmt->execute([$user['id']]);
            $freshRow = $freshStmt->fetch(PDO::FETCH_ASSOC);

            // Check if there is an active session (last_seen within 2 minutes)
            $isActiveElsewhere = false;
            if (!empty($freshRow['session_token']) && !empty($freshRow['last_seen'])) {
                $lastSeen = strtotime($freshRow['last_seen']);
                $isActiveElsewhere = $lastSeen && (time() - $lastSeen) < 120;
            }

            if ($isActiveElsewhere) {
                releaseLoginLock($lockFile);

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
                    ? "Already logged in on: $machine. Please log out from that device first."
                    : "This account is already logged in on another device. Please log out first.";

                header("Location: login.php");
                exit();
            }

            // Safe — write new token and timestamp
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET session_token = ?, last_seen = datetime('now') WHERE id = ?")
                ->execute([$token, $user['id']]);

            // TEMP DEBUG — remove after testing
            error_log("LOGIN DEBUG — user: {$user['id']}, token written: $token, time: " . date('Y-m-d H:i:s'));

        } finally {
            // Always release the lock even if something throws
            releaseLoginLock($lockFile);
        }

        // ── Session secured — set PHP session vars ────────────────────────────
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