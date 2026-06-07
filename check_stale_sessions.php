<?php
/**
 * check_stale_sessions.php
 * Call once at the top of your login success handler (after session is set).
 *
 * Only flags a user as FORCE_CLOSE if ALL of these are true:
 *   1. Their last_seen heartbeat exists and is older than 2 minutes
 *   2. Their very last audit entry was LOGIN (they never logged out cleanly)
 *   3. No LOGOUT or FORCE_CLOSE already written in the last 10 minutes (double-log guard)
 */

if (!function_exists('log_audit')) {
    include __DIR__ . '/audit_helper.php';
}

function check_stale_sessions(PDO $pdo): void {
    try {
        // Find users whose heartbeat went stale
        $stale = $pdo->query("
            SELECT id, username
            FROM users
            WHERE last_seen IS NOT NULL
              AND last_seen < datetime('now', '-2 minutes')
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stale as $u) {

            // Always clear last_seen first so this user isn't re-processed
            // even if we decide to skip logging below
            $pdo->prepare("UPDATE users SET last_seen = NULL WHERE id = ?")
                ->execute([$u['id']]);

            // Only write FORCE_CLOSE if the last audit action was LOGIN
            // A clean logout always writes LOGOUT before clearing last_seen,
            // so if we see anything other than LOGIN here the user already exited cleanly
            $lastAction = $pdo->prepare("
                SELECT action FROM audit_log
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $lastAction->execute([$u['id']]);
            $last = strtoupper((string)$lastAction->fetchColumn());

            if ($last !== 'LOGIN') {
                continue; // Already logged out cleanly or no login on record
            }

            // Guard against double-logging (sendBeacon may have already fired)
            $recentFC = $pdo->prepare("
                SELECT id FROM audit_log
                WHERE user_id = ?
                  AND action IN ('LOGOUT', 'FORCE_CLOSE')
                  AND created_at >= datetime('now', '-10 minutes')
                LIMIT 1
            ");
            $recentFC->execute([$u['id']]);
            if ($recentFC->fetch()) {
                continue; // sendBeacon already logged it
            }

            // Temporarily spoof session so log_audit records the correct user
            $origUserId   = $_SESSION['user_id']  ?? null;
            $origUsername = $_SESSION['username'] ?? null;

            $_SESSION['user_id']  = $u['id'];
            $_SESSION['username'] = $u['username'];

            log_audit(
                $pdo,
                'FORCE_CLOSE',
                null,
                null,
                'Session went stale — app closed without logout (detected on next login)'
            );

            // Restore the current (new) user's session
            $_SESSION['user_id']  = $origUserId;
            $_SESSION['username'] = $origUsername;
        }

    } catch (Exception $e) {
        error_log('check_stale_sessions error: ' . $e->getMessage());
    }
}

// FOR FORCE CLOSE DETECTION TO WORK:
// 1. Call check_stale_sessions($pdo) once at the top of your login success handler (after session is set).
// 2. Implement a heartbeat mechanism on the client that updates users.last_seen every 1-2 minutes while the app is open (see heartbeat.php for an example).
// 3. Implement a sendBeacon call on page unload that hits heartbeat.php?action=force_close to log FORCE_CLOSE immediately when a user 
// closes the tab or app without logging out. This minimizes the chance of false positives and ensures more accurate logging of session ends.  