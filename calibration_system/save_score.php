<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input   = json_decode(file_get_contents('php://input'), true);
$score   = isset($input['score'])        ? (int)$input['score']  : 0;
$lines   = isset($input['lines'])        ? (int)$input['lines']  : 0;
$level   = isset($input['level'])        ? (int)$input['level']  : 1;
$refresh = !empty($input['refresh_only']);
$user_id = (int)$_SESSION['user_id'];

// ── Use the shared db.php so both PCs point at the same file ──
require_once __DIR__ . '/db.php';   // gives you $pdo
$db = $pdo;

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tetris_scores (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id   INTEGER NOT NULL,
            score     INTEGER NOT NULL DEFAULT 0,
            lines     INTEGER NOT NULL DEFAULT 0,
            level     INTEGER NOT NULL DEFAULT 1,
            played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Only insert a real score — skip on refresh-only calls
    if (!$refresh && $score > 0) {
        $stmt = $db->prepare("
            INSERT INTO tetris_scores (user_id, score, lines, level)
            VALUES (:uid, :score, :lines, :level)
        ");
        $stmt->execute([':uid' => $user_id, ':score' => $score, ':lines' => $lines, ':level' => $level]);
    }

    $lb = $db->query("
        SELECT
            u.id,
            COALESCE(u.display_name, u.username) AS display_name,
            u.avatar,
            MAX(ts.score)  AS best_score,
            MAX(ts.lines)  AS best_lines,
            MAX(ts.level)  AS best_level,
            COUNT(ts.id)   AS games_played
        FROM tetris_scores ts
        JOIN users u ON u.id = ts.user_id
        GROUP BY ts.user_id
        ORDER BY best_score DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $personalStmt = $db->prepare("
        SELECT MAX(score) AS best, COUNT(id) AS games
        FROM tetris_scores
        WHERE user_id = :uid
    ");
    $personalStmt->execute([':uid' => $user_id]);
    $personal = $personalStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'          => true,
        'leaderboard' => $lb,
        'personal'    => $personal,
        'saved_score' => $score,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}