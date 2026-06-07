<?php
// lock.php — FOR OTHER PCs (network path version)
define('LOCK_FILE', '\\\\SDP-DP-115\\Calibration_db\\app.lock');
define('LOCK_TIMEOUT', 30);

function acquireLock() {
    // If we already own the lock, just refresh and return true
    if (isLockOwner()) {
        refreshLock();
        return true;
    }

    // If lock file exists but belongs to someone else
    if (file_exists(LOCK_FILE)) {
        $data = json_decode(file_get_contents(LOCK_FILE), true);
        $age  = time() - ($data['time'] ?? 0);

        // Lock expired — clear it
        if ($age > LOCK_TIMEOUT) {
            unlink(LOCK_FILE);
        } else {
            // Someone else holds it
            return false;
        }
    }

    // Write our lock
    file_put_contents(LOCK_FILE, json_encode([
        'session'  => session_id(),
        'user'     => $_SESSION['username'] ?? 'unknown',
        'time'     => time(),
        'computer' => gethostname(),
    ]));
    return true;
}

function releaseLock() {
    if (file_exists(LOCK_FILE)) {
        $data = json_decode(file_get_contents(LOCK_FILE), true);
        if (($data['session'] ?? '') === session_id()) {
            unlink(LOCK_FILE);
            return true;
        }
    }
    return false;
}

function isLockOwner() {
    if (!file_exists(LOCK_FILE)) return false;
    $data = json_decode(file_get_contents(LOCK_FILE), true);
    return ($data['session'] ?? '') === session_id();
}

function getLockInfo() {
    if (!file_exists(LOCK_FILE)) return null;
    return json_decode(file_get_contents(LOCK_FILE), true);
}

function refreshLock() {
    if (isLockOwner()) {
        $data = json_decode(file_get_contents(LOCK_FILE), true);
        $data['time'] = time();
        file_put_contents(LOCK_FILE, json_encode($data));
    }
}