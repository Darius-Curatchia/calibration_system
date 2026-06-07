<?php
// session_manager.php — FOR OTHER PCs (network path version)

define('SESSION_TIMEOUT', 30);

function getWritePdo() {
    // Network path for other PCs
    $dbPath = "\\\\SDP-DP-115\\Calibration_db\\calibration_db.sqlite";

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function initSessionTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS active_sessions (
        user_id     INTEGER NOT NULL,
        session_id  TEXT NOT NULL,
        computer    TEXT,
        last_active INTEGER,
        PRIMARY KEY (user_id)
    )");
}

function registerSession($pdo, $user_id) {
    $wpdo = getWritePdo();
    initSessionTable($wpdo);

    $wpdo->prepare("DELETE FROM active_sessions WHERE last_active < ?")
         ->execute([time() - SESSION_TIMEOUT]);

    $stmt = $wpdo->prepare("SELECT session_id, computer FROM active_sessions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();

    if ($existing && $existing['session_id'] !== session_id()) {
        return [
            'allowed'  => false,
            'computer' => $existing['computer']
        ];
    }

    $wpdo->prepare("
        INSERT INTO active_sessions (user_id, session_id, computer, last_active)
        VALUES (?, ?, ?, ?)
        ON CONFLICT(user_id) DO UPDATE SET
            session_id  = excluded.session_id,
            computer    = excluded.computer,
            last_active = excluded.last_active
    ")->execute([$user_id, session_id(), gethostname(), time()]);

    return ['allowed' => true];
}

function refreshSession($pdo, $user_id) {
    $wpdo = getWritePdo();
    initSessionTable($wpdo);
    $wpdo->prepare("
        UPDATE active_sessions SET last_active = ? WHERE user_id = ? AND session_id = ?
    ")->execute([time(), $user_id, session_id()]);
}

function removeSession($pdo, $user_id) {
    $wpdo = getWritePdo();
    initSessionTable($wpdo);
    $wpdo->prepare("
        DELETE FROM active_sessions WHERE user_id = ? AND session_id = ?
    ")->execute([$user_id, session_id()]);
}