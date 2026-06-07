<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/db.php';
$uid = (int)$_SESSION['user_id'];

/* ── Bootstrap tables ─────────────────────────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS tetris_pvp_lobby (
    user_id   INTEGER PRIMARY KEY,
    name      TEXT NOT NULL,
    avatar    TEXT,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tetris_pvp_sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    p1_id       INTEGER NOT NULL,
    p2_id       INTEGER NOT NULL,
    status      TEXT NOT NULL DEFAULT 'pending',
    winner_id   INTEGER,
    started_at  DATETIME,
    finished_at DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tetris_pvp_states (
    session_id INTEGER NOT NULL,
    user_id    INTEGER NOT NULL,
    board_json TEXT    DEFAULT '[]',
    score      INTEGER DEFAULT 0,
    lines      INTEGER DEFAULT 0,
    level      INTEGER DEFAULT 1,
    gstatus    TEXT    DEFAULT 'playing',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, user_id)
)");

// Prune stale lobby slots (heartbeat every 4s, 12s timeout)
$pdo->exec("DELETE FROM tetris_pvp_lobby WHERE joined_at < datetime('now','-12 seconds')");
// Expire old pending challenges
$pdo->exec("UPDATE tetris_pvp_sessions SET status='expired' WHERE status='pending' AND created_at < datetime('now','-60 seconds')");

$in  = json_decode(file_get_contents('php://input'), true) ?? [];
$act = $in['action'] ?? '';

switch ($act) {

    /* ── Lobby heartbeat ─────────────────────────────────────────────────── */
    case 'heartbeat': {
        $u = $pdo->prepare("SELECT COALESCE(display_name,username) AS n, avatar FROM users WHERE id=:id");
        $u->execute([':id' => $uid]);
        $me = $u->fetch();

        $pdo->prepare("INSERT OR REPLACE INTO tetris_pvp_lobby (user_id,name,avatar,joined_at) VALUES(:uid,:n,:av,CURRENT_TIMESTAMP)")
            ->execute([':uid' => $uid, ':n' => $me['n'], ':av' => $me['avatar']]);

        $s = $pdo->prepare("SELECT user_id, name, avatar FROM tetris_pvp_lobby WHERE user_id!=:uid ORDER BY joined_at");
        $s->execute([':uid' => $uid]);
        $waiters = $s->fetchAll();

        // Incoming challenge to me
        $s = $pdo->prepare("
            SELECT s.id, s.p1_id, COALESCE(u.display_name,u.username) AS challenger_name, u.avatar AS challenger_avatar
            FROM tetris_pvp_sessions s JOIN users u ON u.id=s.p1_id
            WHERE s.p2_id=:uid AND s.status='pending'
            ORDER BY s.created_at DESC LIMIT 1
        ");
        $s->execute([':uid' => $uid]);
        $incoming = $s->fetch() ?: null;

        // My outgoing challenge or active playing session
        $s = $pdo->prepare("
            SELECT s.*, COALESCE(u.display_name,u.username) AS other_name, u.avatar AS other_avatar
            FROM tetris_pvp_sessions s
            JOIN users u ON u.id = CASE WHEN s.p1_id=:uid THEN s.p2_id ELSE s.p1_id END
            WHERE (s.p1_id=:uid2 OR s.p2_id=:uid3) AND s.status IN ('pending','playing')
            ORDER BY s.created_at DESC LIMIT 1
        ");
        $s->execute([':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid]);
        $mySession = $s->fetch() ?: null;

        echo json_encode(['ok' => true, 'waiters' => $waiters, 'incoming' => $incoming, 'session' => $mySession]);
        break;
    }

    /* ── Leave lobby ─────────────────────────────────────────────────────── */
    case 'leave_lobby': {
        $pdo->prepare("DELETE FROM tetris_pvp_lobby WHERE user_id=:uid")->execute([':uid' => $uid]);
        $pdo->prepare("DELETE FROM tetris_pvp_sessions WHERE p1_id=:uid AND status='pending'")->execute([':uid' => $uid]);
        echo json_encode(['ok' => true]);
        break;
    }

    /* ── Send challenge ───────────────────────────────────────────────────── */
    case 'challenge': {
        $target = (int)($in['target_id'] ?? 0);
        if (!$target) { echo json_encode(['error' => 'No target']); break; }
        $pdo->prepare("DELETE FROM tetris_pvp_sessions WHERE p1_id=:uid AND status='pending'")->execute([':uid' => $uid]);
        $pdo->prepare("INSERT INTO tetris_pvp_sessions (p1_id,p2_id) VALUES(:p1,:p2)")->execute([':p1' => $uid, ':p2' => $target]);
        echo json_encode(['ok' => true, 'session_id' => (int)$pdo->lastInsertId()]);
        break;
    }

    /* ── Cancel challenge ────────────────────────────────────────────────── */
    case 'cancel_challenge': {
        $pdo->prepare("DELETE FROM tetris_pvp_sessions WHERE p1_id=:uid AND status='pending'")->execute([':uid' => $uid]);
        echo json_encode(['ok' => true]);
        break;
    }

    /* ── Respond to challenge ────────────────────────────────────────────── */
    case 'respond': {
        $sid    = (int)($in['session_id'] ?? 0);
        $accept = !empty($in['accept']);

        if ($accept) {
            $pdo->prepare("UPDATE tetris_pvp_sessions SET status='playing', started_at=CURRENT_TIMESTAMP
                           WHERE id=:id AND p2_id=:uid AND status='pending'")
                ->execute([':id' => $sid, ':uid' => $uid]);

            $s = $pdo->prepare("SELECT p1_id, p2_id FROM tetris_pvp_sessions WHERE id=:id");
            $s->execute([':id' => $sid]);
            $row = $s->fetch();

            if ($row) {
                $pdo->prepare("DELETE FROM tetris_pvp_lobby WHERE user_id=:a OR user_id=:b")
                    ->execute([':a' => $row['p1_id'], ':b' => $row['p2_id']]);
                $pdo->prepare("INSERT OR IGNORE INTO tetris_pvp_states (session_id,user_id) VALUES(:sid,:uid)")
                    ->execute([':sid' => $sid, ':uid' => $row['p1_id']]);
                $pdo->prepare("INSERT OR IGNORE INTO tetris_pvp_states (session_id,user_id) VALUES(:sid,:uid)")
                    ->execute([':sid' => $sid, ':uid' => $row['p2_id']]);

                $opp = $pdo->prepare("SELECT COALESCE(display_name,username) AS n, avatar FROM users WHERE id=:id");
                $opp->execute([':id' => $row['p1_id']]);
                echo json_encode(['ok' => true, 'session_id' => $sid, 'opponent' => $opp->fetch()]);
            } else {
                echo json_encode(['error' => 'Session not found']);
            }
        } else {
            $pdo->prepare("UPDATE tetris_pvp_sessions SET status='declined' WHERE id=:id AND p2_id=:uid")
                ->execute([':id' => $sid, ':uid' => $uid]);
            echo json_encode(['ok' => true]);
        }
        break;
    }

    /* ── Push game state (also returns opponent state) ───────────────────── */
    case 'push_state': {
        $sid     = (int)($in['session_id'] ?? 0);
        $board   = json_encode($in['board'] ?? []);
        $score   = (int)($in['score'] ?? 0);
        $lines   = (int)($in['lines'] ?? 0);
        $level   = (int)($in['level'] ?? 1);
        $gstatus = ($in['gstatus'] ?? 'playing') === 'lost' ? 'lost' : 'playing';

        // Upsert this player's state
        $pdo->prepare("INSERT OR REPLACE INTO tetris_pvp_states
                       (session_id,user_id,board_json,score,lines,level,gstatus,updated_at)
                       VALUES(:sid,:uid,:board,:score,:lines,:level,:gs,CURRENT_TIMESTAMP)")
            ->execute([
                ':sid'   => $sid,
                ':uid'   => $uid,
                ':board' => $board,
                ':score' => $score,
                ':lines' => $lines,
                ':level' => $level,
                ':gs'    => $gstatus,
            ]);

        // If this player just lost, mark the session finished and award win to opponent
        if ($gstatus === 'lost') {
            $s = $pdo->prepare("SELECT p1_id, p2_id FROM tetris_pvp_sessions WHERE id=:id AND status='playing'");
            $s->execute([':id' => $sid]);
            $row = $s->fetch();
            if ($row) {
                $winner = ((int)$row['p1_id'] === $uid) ? $row['p2_id'] : $row['p1_id'];
                $pdo->prepare("UPDATE tetris_pvp_sessions
                               SET status='finished', winner_id=:w, finished_at=CURRENT_TIMESTAMP
                               WHERE id=:id")
                    ->execute([':w' => $winner, ':id' => $sid]);
            }
        }

        // Fetch current session status (so surviving player detects finish)
        $s = $pdo->prepare("SELECT * FROM tetris_pvp_sessions WHERE id=:id");
        $s->execute([':id' => $sid]);
        $session = $s->fetch() ?: null;

        // Fetch opponent's latest state
        $s = $pdo->prepare("SELECT * FROM tetris_pvp_states WHERE session_id=:sid AND user_id!=:uid");
        $s->execute([':sid' => $sid, ':uid' => $uid]);
        $opponent = $s->fetch() ?: null;

        echo json_encode(['ok' => true, 'session' => $session, 'opponent' => $opponent]);
        break;
    }

    /* ── Forfeit (tab close / Escape mid-game) ───────────────────────────── */
    case 'forfeit': {
        $sid = (int)($in['session_id'] ?? 0);

        $s = $pdo->prepare("SELECT p1_id, p2_id FROM tetris_pvp_sessions WHERE id=:id AND status='playing'");
        $s->execute([':id' => $sid]);
        $row = $s->fetch();

        if ($row) {
            $winner = ((int)$row['p1_id'] === $uid) ? $row['p2_id'] : $row['p1_id'];
            $pdo->prepare("UPDATE tetris_pvp_sessions
                           SET status='finished', winner_id=:w, finished_at=CURRENT_TIMESTAMP
                           WHERE id=:id")
                ->execute([':w' => $winner, ':id' => $sid]);

            // Mark forfeiter as lost in states table so opponent's board shows correctly
            $pdo->prepare("UPDATE tetris_pvp_states SET gstatus='lost', updated_at=CURRENT_TIMESTAMP
                           WHERE session_id=:sid AND user_id=:uid")
                ->execute([':sid' => $sid, ':uid' => $uid]);
        }

        echo json_encode(['ok' => true]);
        break;
    }

    /* ── Unknown action ──────────────────────────────────────────────────── */
    default: {
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($act)]);
        break;
    }
}