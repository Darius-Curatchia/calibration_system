<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$leaderboard     = [];
$personal_best   = 0;

try {
    require_once __DIR__ . '/db.php';
    $db = $pdo;

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

    $rows = $db->query("
        SELECT u.id,
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
    $leaderboard = $rows;

    $stmt = $db->prepare("SELECT MAX(score) AS best FROM tetris_scores WHERE user_id = :uid");
    $stmt->execute([':uid' => $current_user_id]);
    $row           = $stmt->fetch(PDO::FETCH_ASSOC);
    $personal_best = $row ? (int)$row['best'] : 0;

} catch (Exception $e) {
    // silently fail
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CaliTetris — Calibration Management System</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function () {
    var collapsed = localStorage.getItem('sb-state') === '1';
    document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    document.addEventListener('DOMContentLoaded', function () {
        document.body.dataset.sidebar = document.documentElement.dataset.sidebar;
    });
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
:root {
    --navy:#05304f; --navy-mid:#0a4570; --accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15); --accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7; --bg-card:#ffffff; --bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10); --border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d; --text-2:#4a6070; --text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px; --r-md:12px; --r-lg:16px; --r-xl:20px;
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);
    --shadow-lg:0 8px 40px rgba(5,48,79,0.14);
    --pvp:#7c3aed; --pvp-soft:rgba(124,58,237,0.10); --pvp-glow:rgba(124,58,237,0.35);
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',system-ui,sans-serif; background:var(--bg-page); color:var(--text); -webkit-font-smoothing:antialiased; }

.tetris-page { min-height:100vh; display:flex; flex-direction:column; }

.tetris-topbar { display:flex; align-items:center; justify-content:space-between; padding:14px 0 18px; flex-shrink:0; }
.topbar-left  { display:flex; align-items:center; gap:12px; }
.topbar-right { display:flex; align-items:center; gap:10px; }
.back-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:7px 14px; background:var(--bg-card); border:1px solid var(--border-mid);
    border-radius:var(--r-md); color:var(--text-2);
    font-size:13px; font-weight:600; font-family:'Plus Jakarta Sans',sans-serif;
    cursor:pointer; text-decoration:none;
    transition:background 0.18s,border-color 0.18s,color 0.18s,transform 0.15s;
    box-shadow:var(--shadow-sm);
}
.back-btn:hover { background:var(--navy); border-color:var(--navy); color:#fff; transform:translateX(-2px); }
.back-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }
.page-badge {
    display:inline-flex; align-items:center; gap:6px; padding:5px 12px;
    background:linear-gradient(135deg,var(--navy),var(--accent));
    border-radius:40px; color:#fff;
    font-size:10.5px; font-weight:700; letter-spacing:0.10em; text-transform:uppercase;
    font-family:var(--mono); box-shadow:0 2px 8px rgba(26,144,217,0.30);
}
.pvp-btn {
    display:inline-flex; align-items:center; gap:6px; padding:7px 16px;
    background:linear-gradient(135deg,var(--pvp),#a855f7); border:none;
    border-radius:var(--r-md); color:#fff; font-size:13px; font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer;
    transition:opacity 0.18s,transform 0.15s,box-shadow 0.15s;
    box-shadow:0 2px 10px var(--pvp-glow); letter-spacing:0.01em;
}
.pvp-btn:hover  { opacity:0.88; transform:translateY(-1px); box-shadow:0 4px 18px var(--pvp-glow); }
.pvp-btn.active { background:linear-gradient(135deg,#4c1d95,var(--pvp)); box-shadow:0 0 0 3px rgba(124,58,237,0.25); }

.tetris-layout {
    display:grid;
    grid-template-columns: 280px 1fr 280px;
    gap:20px;
    align-items:flex-start;
    flex:1;
}
@media(max-width:1100px) {
    .tetris-layout { grid-template-columns: 1fr 220px; }
    .left-panel-slot { grid-column: 1 / -1; }
}
@media(max-width:700px) { .tetris-layout { grid-template-columns: 1fr; } }

.left-panel-slot { display:flex; flex-direction:column; }

.game-panel {
    background:var(--bg-card); border-radius:var(--r-xl);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    padding:20px; display:flex; flex-direction:column;
    align-items:center; gap:14px; flex-shrink:0;
}
.canvas-wrap {
    position:relative; border:2px solid rgba(26,144,217,0.35);
    border-radius:var(--r-lg); overflow:hidden;
    box-shadow:inset 0 0 30px rgba(5,48,79,0.06); background:#050e18;
}
canvas#tetrisCanvas { display:block; image-rendering:pixelated; }

.t-screen {
    position:absolute; inset:0; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:12px; z-index:2;
    background:rgba(5,14,24,0.90); backdrop-filter:blur(4px);
    transition:opacity 0.2s; padding:24px;
}
.t-screen.hidden { opacity:0; pointer-events:none; }
.ts-icon  { font-size:3rem; line-height:1; }
.ts-title { font-size:1.4rem; font-weight:700; color:#fff; letter-spacing:-0.02em; text-align:center; }
.ts-sub   { font-size:12px; font-family:var(--mono); color:rgba(255,255,255,0.40); text-align:center; line-height:1.7; }
.ts-sub .k { display:inline-block; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.20); border-radius:4px; padding:1px 6px; font-size:11px; }
.t-btn {
    padding:10px 28px; background:var(--accent); color:#fff; border:none;
    border-radius:var(--r-sm); font-size:13px; font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;
    cursor:pointer; transition:background 0.15s,transform 0.15s; margin-top:4px;
}
.t-btn:hover { background:#1480c5; transform:scale(1.04); }
.t-btn.ghost { background:transparent; border:1px solid rgba(255,255,255,0.18); color:rgba(255,255,255,0.50); font-size:12px; padding:8px 20px; margin-top:0; }
.t-btn.ghost:hover  { background:rgba(255,255,255,0.06); color:#fff; }
.t-btn.pvp-win      { background:linear-gradient(135deg,#059669,#10b981); }
.t-btn.pvp-win:hover{ background:#047857; }
.t-btn.accept       { background:linear-gradient(135deg,#059669,#10b981); box-shadow:0 2px 8px rgba(5,150,105,0.35); }
.t-btn.accept:hover { background:#047857; }
.t-btn.decline      { background:transparent; border:1px solid rgba(239,68,68,0.40); color:#ef4444; padding:8px 20px; margin-top:0; }
.t-btn.decline:hover{ background:rgba(239,68,68,0.08); }

.go-score { font-family:var(--mono); font-size:2.8rem; font-weight:700; color:var(--accent); line-height:1; }
.go-hi    { font-family:var(--mono); font-size:11px; color:#fbbf24; }
.go-hi.hidden { opacity:0; }

.cdnum { font-size:5rem!important; font-family:var(--mono)!important; color:var(--accent)!important; line-height:1!important; animation:cdPop 0.5s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes cdPop { from{transform:scale(2);opacity:0;} to{transform:scale(1);opacity:1;} }
.cdgo { color:#34d399!important; }

.level-bar { width:100%; display:flex; align-items:center; gap:8px; }
.lb-label  { font-family:var(--mono); font-size:9px; color:var(--text-3); text-transform:uppercase; letter-spacing:0.08em; flex-shrink:0; }
.pips { display:flex; gap:3px; }
.pip  { width:18px; height:5px; border-radius:3px; background:var(--border-mid); transition:background 0.3s; }
.pip.on        { background:var(--accent); }
.pip.on.warn   { background:#f59e0b; }
.pip.on.danger { background:#ef4444; }

.pause-chip {
    display:flex; align-items:center; gap:6px;
    background:var(--bg-raised); border:1px solid var(--border);
    border-radius:40px; padding:5px 14px;
    font-size:12px; font-weight:600; color:var(--text-2); font-family:var(--mono);
    cursor:pointer; transition:background 0.18s,color 0.18s; margin-top:4px;
}
.pause-chip:hover { background:var(--navy); color:#fff; }
.pause-dot         { width:7px; height:7px; border-radius:50%; background:var(--accent); }
.pause-dot.paused  { background:#ef4444; }
.pause-dot.pvp-live{ background:#a855f7; animation:blink 1s ease-in-out infinite; }

.dpad { display:none; grid-template-columns:50px 50px 50px; grid-template-rows:50px 50px; gap:5px; }
@media(pointer:coarse) { .dpad { display:grid; } }
.dp {
    background:var(--bg-card); border:1px solid var(--border-mid);
    border-radius:var(--r-sm); color:var(--navy); font-size:20px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; user-select:none; touch-action:manipulation;
    transition:background 0.1s,transform 0.1s; box-shadow:var(--shadow-sm);
}
.dp:active { background:var(--accent-soft); transform:scale(0.94); }
.dp-up   { grid-column:2; grid-row:1; }
.dp-left { grid-column:1; grid-row:2; }
.dp-down { grid-column:2; grid-row:2; }
.dp-right{ grid-column:3; grid-row:2; }
.dp-drop { grid-column:3; grid-row:1; font-size:13px; }

.game-footer { font-size:10px; font-family:var(--mono); color:rgba(255,255,255,0.18); }
.game-footer kbd { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.14); border-radius:3px; padding:1px 5px; font-size:9px; }

.side-panel { display:flex; flex-direction:column; gap:14px; }
.stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--r-lg); box-shadow:var(--shadow-sm); padding:16px 18px; transition:box-shadow 0.2s,transform 0.2s; }
.stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
.stat-lbl { font-size:10px; font-weight:700; letter-spacing:0.09em; text-transform:uppercase; color:var(--text-3); font-family:var(--mono); margin-bottom:4px; }
.stat-val { font-size:26px; font-weight:700; color:var(--navy); font-family:var(--mono); line-height:1.1; }
.stat-val.accent { color:var(--accent); }
.stat-val.gold   { color:#d97706; }
.next-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--r-lg); box-shadow:var(--shadow-sm); padding:16px 18px; }
.next-card .stat-lbl { margin-bottom:10px; }
.next-wrap { display:flex; justify-content:center; }
canvas#nextCanvas { border-radius:var(--r-sm); background:#050e18; border:1px solid rgba(26,144,217,0.20); }
.controls-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--r-lg); box-shadow:var(--shadow-sm); padding:16px 18px; }
.ctrl-title { font-size:10px; font-weight:700; letter-spacing:0.09em; text-transform:uppercase; color:var(--text-3); font-family:var(--mono); margin-bottom:10px; }
.ctrl-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.ctrl-row:last-child { margin-bottom:0; }
.ctrl-key { display:inline-flex; align-items:center; justify-content:center; background:var(--bg-raised); border:1px solid var(--border-mid); border-radius:5px; padding:2px 7px; font-size:10px; font-family:var(--mono); font-weight:700; color:var(--navy); min-width:28px; box-shadow:0 1px 0 var(--border-mid); }
.ctrl-desc { font-size:12px; color:var(--text-2); }

.leaderboard-panel { background:var(--bg-card); border-radius:var(--r-xl); border:1px solid var(--border); box-shadow:var(--shadow-sm); overflow:hidden; display:flex; flex-direction:column; }
.lb-header { padding:18px 20px 14px; border-bottom:1px solid var(--border); background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%); display:flex; align-items:center; justify-content:space-between; gap:10px; }
.lb-header-left { display:flex; align-items:center; gap:10px; }
.lb-icon { width:32px; height:32px; background:linear-gradient(135deg,var(--navy),var(--accent)); border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(5,48,79,0.20); flex-shrink:0; font-size:15px; line-height:1; }
.lb-title    { font-size:13px; font-weight:700; color:var(--navy); text-transform:uppercase; letter-spacing:0.15px; }
.lb-subtitle { font-size:10px; color:var(--text-3); font-family:var(--mono); margin-top:1px; }
.lb-refresh { width:28px; height:28px; border-radius:var(--r-sm); background:var(--bg-raised); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background 0.18s,transform 0.3s; flex-shrink:0; }
.lb-refresh:hover    { background:var(--accent-soft); }
.lb-refresh.spinning { animation:spin 0.6s linear; }
@keyframes spin { to { transform:rotate(360deg); } }
.lb-refresh svg { width:13px; height:13px; stroke:var(--text-3); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.lb-list { padding:10px 12px; display:flex; flex-direction:column; gap:6px; flex:1; }
.lb-empty { text-align:center; padding:32px 20px; font-size:12px; color:var(--text-3); font-family:var(--mono); line-height:1.8; }
.lb-empty-icon { font-size:2.2rem; margin-bottom:10px; }
.lb-entry { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--r-md); border:1px solid transparent; transition:background 0.18s,border-color 0.18s,transform 0.18s; position:relative; }
.lb-entry:hover  { background:var(--bg-raised); border-color:var(--border); transform:translateX(2px); }
.lb-entry.is-me  { background:var(--accent-soft); border-color:rgba(26,144,217,0.22); }
.lb-entry.is-me:hover { background:rgba(26,144,217,0.12); }
.lb-rank          { width:24px; text-align:center; flex-shrink:0; font-family:var(--mono); font-size:11px; font-weight:700; color:var(--text-3); }
.lb-rank.gold     { color:#d97706; font-size:16px; }
.lb-rank.silver   { color:#64748b; font-size:16px; }
.lb-rank.bronze   { color:#b45309; font-size:16px; }
.lb-avatar { width:36px; height:36px; border-radius:50%; flex-shrink:0; overflow:hidden; border:2px solid var(--border-mid); background:linear-gradient(135deg,var(--navy),var(--accent)); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; transition:border-color 0.18s; }
.lb-entry.is-me .lb-avatar { border-color:var(--accent); }
.lb-avatar img { width:100%; height:100%; object-fit:cover; }
.lb-user { flex:1; min-width:0; }
.lb-name { font-size:13px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lb-entry.is-me .lb-name { color:var(--navy); }
.lb-meta { font-size:10px; color:var(--text-3); font-family:var(--mono); margin-top:1px; }
.lb-you-badge { display:inline-block; background:var(--accent); color:#fff; font-size:8px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:1px 5px; border-radius:3px; margin-left:5px; vertical-align:middle; }
.lb-score-col { text-align:right; flex-shrink:0; }
.lb-score-val { font-family:var(--mono); font-size:15px; font-weight:700; color:var(--navy); line-height:1; }
.lb-entry.is-me .lb-score-val { color:var(--accent); }
.lb-score-sub { font-size:9px; color:var(--text-3); font-family:var(--mono); margin-top:2px; }
.lb-saving { display:flex; align-items:center; gap:6px; font-size:11px; font-family:var(--mono); color:var(--text-3); padding:8px 12px; margin:0 12px 8px; background:var(--bg-raised); border-radius:var(--r-sm); border:1px solid var(--border); transition:opacity 0.3s; }
.lb-saving.hidden { opacity:0; pointer-events:none; }
.lb-saving-dot { width:6px; height:6px; border-radius:50%; background:var(--accent); animation:blink 1s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.2;} }

.pvp-overlay { position:fixed; inset:0; background:rgba(5,14,24,0.72); backdrop-filter:blur(6px); z-index:900; display:flex; align-items:center; justify-content:center; transition:opacity 0.2s; }
.pvp-overlay.hidden { opacity:0; pointer-events:none; }
.pvp-modal { background:var(--bg-card); border-radius:var(--r-xl); border:1px solid var(--border); box-shadow:var(--shadow-lg); width:440px; max-width:94vw; overflow:hidden; }
.pvp-mhead { display:flex; align-items:center; justify-content:space-between; padding:18px 20px 16px; background:linear-gradient(135deg,#4c1d95,var(--pvp)); color:#fff; }
.pvp-mtitle { font-size:16px; font-weight:700; letter-spacing:-0.02em; }
.pvp-msub   { font-size:10px; opacity:0.55; font-family:var(--mono); margin-top:3px; }
.pvp-mclose { width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.15s; flex-shrink:0; }
.pvp-mclose:hover { background:rgba(255,255,255,0.28); }
.pvp-mbody { padding:16px 18px 20px; display:flex; flex-direction:column; gap:14px; min-height:120px; }
.pvp-sec-lbl { font-size:10px; font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:var(--text-3); font-family:var(--mono); }
.pvp-player-list { display:flex; flex-direction:column; gap:6px; }
.pvp-empty-msg { font-size:12px; color:var(--text-3); font-family:var(--mono); text-align:center; padding:20px 16px; border:1px dashed var(--border-mid); border-radius:var(--r-md); line-height:1.8; }
.pvp-player-row { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--r-md); border:1px solid var(--border); background:var(--bg-raised); transition:border-color 0.15s,background 0.15s; }
.pvp-player-row:hover { border-color:rgba(124,58,237,0.30); background:var(--pvp-soft); }
.pvp-pav { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--navy),var(--accent)); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; border:2px solid var(--border-mid); }
.pvp-pav img  { width:100%; height:100%; object-fit:cover; }
.pvp-pname    { font-size:13px; font-weight:600; color:var(--text); flex:1; }
.pvp-chal-btn { padding:5px 13px; background:linear-gradient(135deg,var(--pvp),#a855f7); border:none; border-radius:var(--r-sm); color:#fff; font-size:11px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; transition:opacity 0.15s,transform 0.12s; white-space:nowrap; }
.pvp-chal-btn:hover { opacity:0.85; transform:scale(1.04); }
.pvp-status-box { display:flex; flex-direction:column; align-items:center; gap:10px; padding:16px; text-align:center; }
.pvp-spinner { width:26px; height:26px; border:3px solid var(--border-mid); border-top-color:var(--pvp); border-radius:50%; animation:spin 0.75s linear infinite; }
.pvp-st-text { font-size:13px; font-weight:600; color:var(--text); }
.pvp-st-sub  { font-size:11px; color:var(--text-3); font-family:var(--mono); }
.pvp-lobby-footer { display:flex; align-items:center; gap:8px; padding-top:10px; border-top:1px solid var(--border); }
.pvp-lobby-dot { width:6px; height:6px; border-radius:50%; background:#22c55e; animation:blink 1.2s ease-in-out infinite; flex-shrink:0; }
.pvp-lobby-txt { font-size:11px; color:var(--text-3); font-family:var(--mono); }
.pvp-inc-box   { display:flex; flex-direction:column; align-items:center; gap:12px; padding:8px; }
.pvp-inc-av { width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg,var(--pvp),#a855f7); display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:#fff; overflow:hidden; border:3px solid rgba(168,85,247,0.35); box-shadow:0 0 24px rgba(124,58,237,0.30); }
.pvp-inc-av img  { width:100%; height:100%; object-fit:cover; }
.pvp-inc-name    { font-size:18px; font-weight:700; color:var(--navy); }
.pvp-inc-sub     { font-size:12px; color:var(--text-3); font-family:var(--mono); }
.pvp-respond-row { display:flex; gap:8px; justify-content:center; }

.opponent-panel { background:var(--bg-card); border-radius:var(--r-xl); border:1px solid rgba(124,58,237,0.20); box-shadow:var(--shadow-sm),0 0 0 1px rgba(124,58,237,0.08); overflow:hidden; display:flex; flex-direction:column; }
.opp-header { padding:14px 16px; display:flex; align-items:center; gap:10px; background:linear-gradient(135deg,#4c1d95,var(--pvp)); color:#fff; flex-shrink:0; }
.opp-av { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,0.18); border:2px solid rgba(255,255,255,0.28); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0; overflow:hidden; }
.opp-av img { width:100%; height:100%; object-fit:cover; }
.opp-name { font-size:13px; font-weight:700; }
.opp-sub  { font-size:10px; opacity:0.55; font-family:var(--mono); margin-top:1px; }
.opp-stats { display:grid; grid-template-columns:repeat(3,1fr); border-bottom:1px solid var(--border); }
.opp-stat  { padding:9px 10px; text-align:center; border-right:1px solid var(--border); }
.opp-stat:last-child { border-right:none; }
.opp-slbl  { font-size:9px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-3); font-family:var(--mono); }
.opp-sval  { font-size:16px; font-weight:700; color:var(--navy); font-family:var(--mono); margin-top:2px; transition:color 0.3s; }
.opp-board-wrap { display:flex; justify-content:center; padding:14px; background:#080f1a; flex:1; position:relative; }
.opp-lost-msg { position:absolute; inset:14px; background:rgba(5,14,24,0.80); backdrop-filter:blur(3px); display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700; color:#ef4444; border-radius:var(--r-sm); font-family:var(--mono); letter-spacing:0.06em; }
.opp-lost-msg.hidden { display:none; }
.opp-ping { display:flex; align-items:center; gap:5px; padding:6px 12px; font-size:9px; color:var(--text-3); font-family:var(--mono); border-top:1px solid var(--border); }
.opp-ping-dot       { width:5px; height:5px; border-radius:50%; background:#22c55e; animation:blink 1.5s ease-in-out infinite; }
.opp-ping-dot.stale { background:#ef4444; animation:none; }

.hidden { display:none !important; }
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="tetris-page">

        <div class="tetris-topbar">
            <div class="topbar-left">
                <a href="about.php" class="back-btn">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to About
                </a>
                <div class="page-badge">🧱 CaliTetris</div>
            </div>
            <div class="topbar-right">
                <button class="pvp-btn" id="pvpOpenBtn">⚔️ Challenge</button>
            </div>
        </div>

        <div class="tetris-layout">

            <div class="left-panel-slot">

                <div class="leaderboard-panel" id="leaderboardPanel">
                    <div class="lb-header">
                        <div class="lb-header-left">
                            <div class="lb-icon">🏆</div>
                            <div>
                                <div class="lb-title">Leaderboard</div>
                                <div class="lb-subtitle">Top 10 all-time scores</div>
                            </div>
                        </div>
                        <div class="lb-refresh" id="lbRefresh" title="Refresh">
                            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </div>
                    </div>
                    <div class="lb-saving hidden" id="lbSaving">
                        <div class="lb-saving-dot"></div>
                        <span>Saving your score…</span>
                    </div>
                    <div class="lb-list" id="lbList">
                        <?php if (empty($leaderboard)): ?>
                        <div class="lb-empty">
                            <div class="lb-empty-icon">🧱</div>
                            No scores yet.<br>Be the first to play!
                        </div>
                        <?php else: ?>
                        <?php
                        $medals      = ['🥇','🥈','🥉'];
                        $rankClasses = ['gold','silver','bronze'];
                        foreach ($leaderboard as $i => $row):
                            $isMe      = ((int)$row['id'] === $current_user_id);
                            $rank      = $i + 1;
                            $initials  = strtoupper(substr($row['display_name'], 0, 2));
                            $score_fmt = number_format((int)$row['best_score']);
                        ?>
                        <div class="lb-entry <?= $isMe ? 'is-me' : '' ?>" data-uid="<?= (int)$row['id'] ?>">
                            <div class="lb-rank <?= isset($rankClasses[$i]) ? $rankClasses[$i] : '' ?>">
                                <?= isset($medals[$i]) ? $medals[$i] : $rank ?>
                            </div>
                            <div class="lb-avatar">
                                <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($row['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>
                            <div class="lb-user">
                                <div class="lb-name">
                                    <?= htmlspecialchars($row['display_name']) ?>
                                    <?php if ($isMe): ?><span class="lb-you-badge">You</span><?php endif; ?>
                                </div>
                                <div class="lb-meta">Lv <?= (int)$row['best_level'] ?> · <?= (int)$row['best_lines'] ?> lines · <?= (int)$row['games_played'] ?> game<?= $row['games_played'] != 1 ? 's' : '' ?></div>
                            </div>
                            <div class="lb-score-col">
                                <div class="lb-score-val"><?= $score_fmt ?></div>
                                <div class="lb-score-sub">best score</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="opponent-panel hidden" id="opponentPanel">
                    <div class="opp-header">
                        <div class="opp-av" id="oppAv">??</div>
                        <div>
                            <div class="opp-name" id="oppName">Opponent</div>
                            <div class="opp-sub">⚔️ playing vs you</div>
                        </div>
                    </div>
                    <div class="opp-stats">
                        <div class="opp-stat"><div class="opp-slbl">Score</div><div class="opp-sval" id="oppScore">0</div></div>
                        <div class="opp-stat"><div class="opp-slbl">Lines</div><div class="opp-sval" id="oppLines">0</div></div>
                        <div class="opp-stat"><div class="opp-slbl">Level</div><div class="opp-sval" id="oppLevel">1</div></div>
                    </div>
                    <div class="opp-board-wrap">
                        <canvas id="oppCanvas" width="220" height="440"></canvas>
                        <div class="opp-lost-msg hidden" id="oppLostMsg">💀 TOPPED OUT</div>
                    </div>
                    <div class="opp-ping">
                        <div class="opp-ping-dot" id="oppPingDot"></div>
                        <span id="oppPingTxt">syncing…</span>
                    </div>
                </div>

            </div>

            <div class="game-panel">
                <div class="canvas-wrap">
                    <canvas id="tetrisCanvas" width="240" height="480"></canvas>

                    <div class="t-screen" id="screenStart">
                        <div class="ts-icon">🧱</div>
                        <div class="ts-title">CaliTetris</div>
                        <div class="ts-sub">
                            <span class="k">←</span> <span class="k">→</span> move &nbsp;·&nbsp; <span class="k">↑</span> rotate<br>
                            <span class="k">↓</span> soft drop &nbsp;·&nbsp; <span class="k">Space</span> hard drop<br>
                            <span style="color:rgba(125,211,252,0.55);">Speed increases every 10 lines</span>
                        </div>
                        <button class="t-btn" id="btnStart">Start Game</button>
                        <a href="about.php" class="t-btn ghost">Go back</a>
                    </div>

                    <div class="t-screen hidden" id="screenOver">
                        <div class="ts-title">Game Over</div>
                        <div class="go-score" id="goScore">0</div>
                        <div class="go-hi" id="goHi">🏆 New High Score!</div>
                        <div class="ts-sub" id="goMsg">Keep stacking!</div>
                        <button class="t-btn" id="btnRestart">Play Again</button>
                        <a href="about.php" class="t-btn ghost">Go back</a>
                    </div>

                    <div class="t-screen hidden" id="screenPause">
                        <div class="ts-icon">⏸️</div>
                        <div class="ts-title">Paused</div>
                        <div class="ts-sub">Press <span class="k">P</span> or <span class="k">Space</span> to resume</div>
                        <button class="t-btn" id="btnResume">Resume</button>
                    </div>

                    <div class="t-screen hidden" id="screenCountdown">
                        <div class="ts-sub" style="color:rgba(168,85,247,0.70);letter-spacing:0.10em;text-transform:uppercase;font-size:10px;">⚔️ PvP Match Starting</div>
                        <div class="ts-icon cdnum" id="cdNum">3</div>
                        <div class="ts-sub" id="cdSub">Get ready to battle!</div>
                    </div>

                    <div class="t-screen hidden" id="screenPvpOver">
                        <div class="ts-icon" id="pvpOverIcon">🏆</div>
                        <div class="ts-title" id="pvpOverTitle">You Won!</div>
                        <div class="go-score" id="pvpOverScore">0</div>
                        <div class="ts-sub" id="pvpOverSub">Great game!</div>
                        <button class="t-btn pvp-win" id="btnPvpAgain">Challenge Again</button>
                        <button class="t-btn ghost" id="btnPvpQuit">Back to Solo</button>
                    </div>
                </div>

                <div class="level-bar" style="width:240px;">
                    <span class="lb-label">Level</span>
                    <div class="pips" id="pips">
                        <div class="pip"></div><div class="pip"></div><div class="pip"></div>
                        <div class="pip"></div><div class="pip"></div><div class="pip"></div>
                        <div class="pip"></div><div class="pip"></div><div class="pip"></div><div class="pip"></div>
                    </div>
                </div>

                <div class="pause-chip" id="pauseChip">
                    <div class="pause-dot" id="pauseDot"></div>
                    <span id="pauseLabel">Playing</span>
                </div>

                <div class="dpad">
                    <button class="dp dp-up"   data-dir="ROTATE">↺</button>
                    <button class="dp dp-drop" data-dir="DROP">⬇</button>
                    <button class="dp dp-left" data-dir="LEFT">←</button>
                    <button class="dp dp-down" data-dir="DOWN">↓</button>
                    <button class="dp dp-right"data-dir="RIGHT">→</button>
                </div>

                <div class="game-footer">
                    <kbd>Esc</kbd> exit &nbsp;·&nbsp; <kbd>P</kbd> / <kbd>Space</kbd> pause
                </div>
            </div>

            <div class="side-panel">
                <div class="stat-card">
                    <div class="stat-lbl">Score</div>
                    <div class="stat-val accent" id="scoreDisplay">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Your Best</div>
                    <div class="stat-val gold" id="hiDisplay"><?= number_format($personal_best) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Lines</div>
                    <div class="stat-val" id="linesDisplay">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Level</div>
                    <div class="stat-val" id="levelDisplay">1</div>
                </div>
                <div class="next-card">
                    <div class="stat-lbl">Next</div>
                    <div class="next-wrap"><canvas id="nextCanvas" width="100" height="100"></canvas></div>
                </div>
                <div class="controls-card">
                    <div class="ctrl-title">Controls</div>
                    <div class="ctrl-row"><span class="ctrl-key">←→</span><span class="ctrl-desc">Move</span></div>
                    <div class="ctrl-row"><span class="ctrl-key">↑</span><span class="ctrl-desc">Rotate</span></div>
                    <div class="ctrl-row"><span class="ctrl-key">↓</span><span class="ctrl-desc">Soft drop</span></div>
                    <div class="ctrl-row"><span class="ctrl-key">Spc</span><span class="ctrl-desc">Hard drop</span></div>
                    <div class="ctrl-row"><span class="ctrl-key">P</span><span class="ctrl-desc">Pause</span></div>
                    <div class="ctrl-row"><span class="ctrl-key">Esc</span><span class="ctrl-desc">Back</span></div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="pvp-overlay hidden" id="pvpOverlay">
    <div class="pvp-modal">
        <div class="pvp-mhead">
            <div>
                <div class="pvp-mtitle">⚔️ PvP Challenge</div>
                <div class="pvp-msub" id="pvpModalSub">Find an opponent</div>
            </div>
            <button class="pvp-mclose" id="pvpCloseBtn">✕</button>
        </div>
        <div class="pvp-mbody" id="pvpModalBody">
            <div class="pvp-status-box">
                <div class="pvp-spinner"></div>
                <div class="pvp-st-text">Joining lobby…</div>
            </div>
        </div>
    </div>
</div>

<script>
/* ── PvP state declared FIRST so setScreen() can safely reference pvp.mode ── */
const pvp = {
    mode: false, ended: false, sessionId: null,
    opponentName: '', opponentAvatar: null,
    lobbyOpen: false, heartTimer: null, pushTimer: null,
};

/* ════════════ TETRIS ENGINE ════════════ */
const COLS = 12, ROWS = 24, BLOCK = 240 / COLS;
const canvas  = document.getElementById('tetrisCanvas');
const ctx     = canvas.getContext('2d');
const nCanvas = document.getElementById('nextCanvas');
const nCtx    = nCanvas.getContext('2d');
const CURRENT_USER_ID = <?= $current_user_id ?>;

const PIECES = [
    {shape:[[1,1,1,1]],       color:'#38bdf8'},
    {shape:[[1,1],[1,1]],     color:'#fbbf24'},
    {shape:[[0,1,0],[1,1,1]], color:'#a78bfa'},
    {shape:[[0,1,1],[1,1,0]], color:'#34d399'},
    {shape:[[1,1,0],[0,1,1]], color:'#f87171'},
    {shape:[[1,0,0],[1,1,1]], color:'#fb923c'},
    {shape:[[0,0,1],[1,1,1]], color:'#1a90d9'},
];
const SCORES_TABLE = [0, 100, 300, 500, 800];

function rotate(shape) {
    const r = shape[0].length, c = shape.length;
    const out = Array.from({length:r}, () => Array(c).fill(0));
    for (let y = 0; y < c; y++) for (let x = 0; x < r; x++) out[x][c-1-y] = shape[y][x];
    return out;
}
function newPiece(idx) {
    const p = PIECES[idx];
    return {shape: p.shape.map(r => [...r]), color: p.color, x: Math.floor((COLS - p.shape[0].length) / 2), y: 0};
}
function randIdx() { return Math.floor(Math.random() * PIECES.length); }

let board, current, next, score, lines, level, gameLoop, gstate, dropCounter, lastTime;

/* FIX: gameOver guard prevents endGame() firing twice */
let gameOverFired = false;

function getSpeed(lv) { return Math.max(80, 800 - (lv - 1) * 70); }
function getLevel(ln) { return Math.min(10, 1 + Math.floor(ln / 10)); }
function initBoard()  { board = Array.from({length: ROWS}, () => Array(COLS).fill(null)); }

function startGame() {
    initBoard(); score = 0; lines = 0; level = 1; dropCounter = 0; lastTime = 0;
    gameOverFired = false;
    current = newPiece(randIdx()); next = newPiece(randIdx());
    setScreen('playing'); refreshStats(); updatePips(1);
    if (gameLoop) cancelAnimationFrame(gameLoop);
    gameLoop = requestAnimationFrame(loop);
}

function setScreen(s) {
    gstate = s;
    document.getElementById('screenStart').classList.toggle('hidden',     s !== 'start');
    document.getElementById('screenOver').classList.toggle('hidden',      s !== 'over');
    document.getElementById('screenPause').classList.toggle('hidden',     s !== 'pause');
    document.getElementById('screenCountdown').classList.toggle('hidden', s !== 'countdown');
    document.getElementById('screenPvpOver').classList.toggle('hidden',   s !== 'pvpover');
    const paused = (s === 'pause');
    document.getElementById('pauseDot').classList.toggle('paused',   paused);
    document.getElementById('pauseDot').classList.toggle('pvp-live', s === 'playing' && pvp.mode);
    document.getElementById('pauseLabel').textContent =
        paused ? 'Paused' : s === 'playing' ? (pvp.mode ? '⚔️ PvP' : 'Playing') : '—';
}

function refreshStats() {
    document.getElementById('scoreDisplay').textContent = score.toLocaleString();
    document.getElementById('linesDisplay').textContent = lines;
    document.getElementById('levelDisplay').textContent = level;
}

function updatePips(lv) {
    document.querySelectorAll('.pip').forEach((p, i) => {
        if (i < lv) { p.classList.add('on'); p.classList.toggle('warn', lv>=6&&lv<8); p.classList.toggle('danger', lv>=8); }
        else p.classList.remove('on', 'warn', 'danger');
    });
}

function collides(piece, brd, ox = 0, oy = 0) {
    for (let y = 0; y < piece.shape.length; y++)
        for (let x = 0; x < piece.shape[y].length; x++)
            if (piece.shape[y][x]) {
                const nx = piece.x + x + ox, ny = piece.y + y + oy;
                if (nx < 0 || nx >= COLS || ny >= ROWS) return true;
                if (ny >= 0 && brd[ny][nx]) return true;
            }
    return false;
}

function lock() {
    current.shape.forEach((row, y) => row.forEach((v, x) => {
        if (v) {
            const by = current.y + y;
            if (by < 0) { endGame(); return; }
            board[by][current.x + x] = current.color;
        }
    }));
    /* FIX: only proceed to spawn next piece if game didn't end during lock */
    if (gameOverFired) return;
    clearLines();
    current = {shape: next.shape, color: next.color, x: next.x, y: next.y};
    next = newPiece(randIdx());
    if (collides(current, board)) endGame();
}

function clearLines() {
    let cleared = 0;
    for (let y = ROWS - 1; y >= 0; y--) {
        if (board[y].every(c => c !== null)) {
            board.splice(y, 1); board.unshift(Array(COLS).fill(null)); cleared++; y++;
        }
    }
    if (cleared > 0) {
        lines += cleared; score += SCORES_TABLE[cleared] * level;
        level = getLevel(lines); refreshStats(); updatePips(level);
    }
}

function endGame() {
    /* FIX: guard against being called twice in the same game */
    if (gameOverFired) return;
    gameOverFired = true;

    if (gameLoop) { cancelAnimationFrame(gameLoop); gameLoop = null; }

    // PvP loss path
    if (pvp.mode && !pvp.ended) {
        clearInterval(pvp.pushTimer);
        pvpCall('push_state', {session_id: pvp.sessionId, board: getBoardWithCurrent(), score, lines, level, gstatus: 'lost'})
            .then(() => endPvpGame(false));
        return;
    }

    // Solo game over
    setScreen('over');
    document.getElementById('goScore').textContent = score.toLocaleString();
    const curHi = parseInt(document.getElementById('hiDisplay').textContent.replace(/,/g, '')) || 0;
    const isNew = score > curHi;
    document.getElementById('goHi').classList.toggle('hidden', !isNew);
    if (isNew) document.getElementById('hiDisplay').textContent = score.toLocaleString();
    const msgs = [[0,"Keep stacking! You've got this."],[5,'Getting there — try again!'],[10,'Nice run! 🔥'],[25,'Solid stacker. Respect.'],[50,'Are you even working? 😂'],[100,'Tetris legend. Respect 🏆']];
    const m = [...msgs].reverse().find(([n]) => score / 100 >= n);
    document.getElementById('goMsg').textContent = m ? m[1] : 'Keep stacking!';
    saveScore(score, lines, level);
}

function saveScore(sc, ln, lv) {
    const saving = document.getElementById('lbSaving');
    saving.classList.remove('hidden');
    fetch('save_score.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({score:sc, lines:ln, level:lv})})
        .then(r => r.json())
        .then(data => {
            saving.classList.add('hidden');
            if (data.ok && data.leaderboard) renderLeaderboard(data.leaderboard);
            if (data.personal && data.personal.best)
                document.getElementById('hiDisplay').textContent = parseInt(data.personal.best).toLocaleString();
        })
        .catch(() => saving.classList.add('hidden'));
}

function renderLeaderboard(rows) {
    const list = document.getElementById('lbList');
    const medals = ['🥇','🥈','🥉'], rankCls = ['gold','silver','bronze'];
    if (!rows || rows.length === 0) {
        list.innerHTML = '<div class="lb-empty"><div class="lb-empty-icon">🧱</div>No scores yet.<br>Be the first to play!</div>';
        return;
    }
    list.innerHTML = rows.map((row, i) => {
        const isMe = parseInt(row.id) === CURRENT_USER_ID;
        const initials = (row.display_name || '?').substring(0, 2).toUpperCase();
        const scoreVal = parseInt(row.best_score).toLocaleString();
        const avatarHtml = row.avatar ? `<img src="${escHtml(row.avatar)}" alt="">` : escHtml(initials);
        return `<div class="lb-entry ${isMe?'is-me':''}" data-uid="${parseInt(row.id)}">
            <div class="lb-rank ${rankCls[i]||''}">${medals[i]||String(i+1)}</div>
            <div class="lb-avatar">${avatarHtml}</div>
            <div class="lb-user">
                <div class="lb-name">${escHtml(row.display_name||'Unknown')}${isMe?'<span class="lb-you-badge">You</span>':''}</div>
                <div class="lb-meta">Lv ${parseInt(row.best_level)} · ${parseInt(row.best_lines)} lines · ${parseInt(row.games_played)} game${row.games_played!=1?'s':''}</div>
            </div>
            <div class="lb-score-col"><div class="lb-score-val">${scoreVal}</div><div class="lb-score-sub">best score</div></div>
        </div>`;
    }).join('');
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('lbRefresh').addEventListener('click', function () {
    this.classList.add('spinning');
    const btn = this;
    fetch('save_score.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({score:0, lines:0, level:1, refresh_only:true})})
        .then(r => r.json())
        .then(data => { btn.classList.remove('spinning'); if (data.leaderboard) renderLeaderboard(data.leaderboard); })
        .catch(() => btn.classList.remove('spinning'));
});

function loop(ts) {
    if (gstate !== 'playing') { gameLoop = requestAnimationFrame(loop); return; }
    const dt = ts - lastTime; lastTime = ts;
    dropCounter += dt;
    if (dropCounter >= getSpeed(level)) {
        dropCounter = 0;
        if (!collides(current, board, 0, 1)) current.y++;
        else lock();
    }
    /* FIX: stop scheduling new frames if game ended during this tick */
    if (gameOverFired && !pvp.mode) return;
    draw(); gameLoop = requestAnimationFrame(loop);
}

const BG = '#050e18', GRIDCOL = 'rgba(26,144,217,0.05)', GHOST = 'rgba(255,255,255,0.08)';

function drawBlock(c, x, y, color) {
    c.fillStyle = color; c.beginPath(); roundRect(c, x*BLOCK+1, y*BLOCK+1, BLOCK-2, BLOCK-2, 3); c.fill();
    c.fillStyle = 'rgba(255,255,255,0.18)'; c.beginPath(); roundRect(c, x*BLOCK+1, y*BLOCK+1, BLOCK-2, 5, {tl:3,tr:3,bl:0,br:0}); c.fill();
}
function roundRect(c, x, y, w, h, r) {
    if (typeof r === 'number') r = {tl:r, tr:r, bl:r, br:r};
    c.moveTo(x+r.tl,y); c.lineTo(x+w-r.tr,y); c.quadraticCurveTo(x+w,y,x+w,y+r.tr);
    c.lineTo(x+w,y+h-r.br); c.quadraticCurveTo(x+w,y+h,x+w-r.br,y+h);
    c.lineTo(x+r.bl,y+h); c.quadraticCurveTo(x,y+h,x,y+h-r.bl);
    c.lineTo(x,y+r.tl); c.quadraticCurveTo(x,y,x+r.tl,y); c.closePath();
}
function getGhostY() { let gy = current.y; while (!collides(current, board, 0, gy-current.y+1)) gy++; return gy; }

function draw() {
    ctx.fillStyle = BG; ctx.fillRect(0, 0, 240, 480);
    ctx.strokeStyle = GRIDCOL; ctx.lineWidth = 0.5;
    for (let x = 0; x <= COLS; x++) { ctx.beginPath(); ctx.moveTo(x*BLOCK,0); ctx.lineTo(x*BLOCK,480); ctx.stroke(); }
    for (let y = 0; y <= ROWS; y++) { ctx.beginPath(); ctx.moveTo(0,y*BLOCK); ctx.lineTo(240,y*BLOCK); ctx.stroke(); }
    board.forEach((row, y) => row.forEach((c, x) => { if (c) drawBlock(ctx, x, y, c); }));
    if (gstate === 'playing') {
        const gy = getGhostY();
        if (gy !== current.y) current.shape.forEach((row, dy) => row.forEach((v, dx) => {
            if (v) { ctx.fillStyle = GHOST; ctx.fillRect((current.x+dx)*BLOCK+1,(gy+dy)*BLOCK+1,BLOCK-2,BLOCK-2); }
        }));
        current.shape.forEach((row, dy) => row.forEach((v, dx) => { if (v) drawBlock(ctx, current.x+dx, current.y+dy, current.color); }));
    }
    drawNext();
}
function drawNext() {
    nCtx.fillStyle = BG; nCtx.fillRect(0, 0, 100, 100);
    const NB = 18, sh = next.shape, sw = sh[0].length, sh2 = sh.length;
    const ox = Math.floor((100/NB-sw)/2+0.5), oy = Math.floor((100/NB-sh2)/2+0.5);
    sh.forEach((row, y) => row.forEach((v, x) => {
        if (v) {
            nCtx.fillStyle = next.color; nCtx.beginPath();
            nCtx.moveTo((ox+x)*NB+2,(oy+y)*NB+2); nCtx.lineTo((ox+x+1)*NB-2,(oy+y)*NB+2);
            nCtx.lineTo((ox+x+1)*NB-2,(oy+y+1)*NB-2); nCtx.lineTo((ox+x)*NB+2,(oy+y+1)*NB-2);
            nCtx.closePath(); nCtx.fill();
        }
    }));
}

(function () {
    ctx.fillStyle = BG; ctx.fillRect(0,0,240,480);
    ctx.strokeStyle = GRIDCOL; ctx.lineWidth = 0.5;
    for (let x = 0; x <= COLS; x++) { ctx.beginPath(); ctx.moveTo(x*BLOCK,0); ctx.lineTo(x*BLOCK,480); ctx.stroke(); }
    for (let y = 0; y <= ROWS; y++) { ctx.beginPath(); ctx.moveTo(0,y*BLOCK); ctx.lineTo(240,y*BLOCK); ctx.stroke(); }
    nCtx.fillStyle = BG; nCtx.fillRect(0,0,100,100);
})();

setScreen('start');

const MOVE_REPEAT = 80, MOVE_DELAY = 150;
let moveKey = null, moveTimer = null, moveRepeat = null;

function startMove(dir) {
    doMove(dir); clearTimeout(moveTimer); clearInterval(moveRepeat); moveKey = dir;
    moveTimer = setTimeout(() => { moveRepeat = setInterval(() => doMove(dir), MOVE_REPEAT); }, MOVE_DELAY);
}
function stopMove() { clearTimeout(moveTimer); clearInterval(moveRepeat); moveKey = null; }

function doMove(dir) {
    if (gstate !== 'playing') return;
    if (dir === 'LEFT'   && !collides(current, board, -1, 0)) current.x--;
    if (dir === 'RIGHT'  && !collides(current, board,  1, 0)) current.x++;
    if (dir === 'DOWN')  { if (!collides(current, board, 0, 1)) current.y++; else lock(); dropCounter = 0; }
    if (dir === 'ROTATE') {
        const rot = rotate(current.shape), old = current.shape; current.shape = rot;
        if (collides(current, board)) {
            if      (!collides(current, board,  1, 0)) current.x++;
            else if (!collides(current, board, -1, 0)) current.x--;
            else if (!collides(current, board,  2, 0)) current.x += 2;
            else if (!collides(current, board, -2, 0)) current.x -= 2;
            else current.shape = old;
        }
    }
    if (dir === 'DROP') { while (!collides(current, board, 0, 1)) { current.y++; score += 2; } lock(); refreshStats(); dropCounter = 0; }
    draw();
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (pvp.mode) { pvpForfeit(); } else { window.location.href = 'about.php'; } return; }
    if ((e.key === 'p' || e.key === 'P') && gstate === 'playing')  { setScreen('pause');   return; }
    if ((e.key === 'p' || e.key === 'P') && gstate === 'pause')    { setScreen('playing'); return; }
    if (e.key === ' ' && gstate === 'pause') { setScreen('playing'); e.preventDefault(); return; }
    if (e.key === 'Enter' && (gstate === 'start' || gstate === 'over')) { startGame(); return; }
    const map = {ArrowLeft:'LEFT', ArrowRight:'RIGHT', ArrowDown:'DOWN', ArrowUp:'ROTATE', ' ':'DROP'};
    const dir = map[e.key];
    if (dir && gstate === 'playing') {
        if (['ArrowLeft','ArrowRight','ArrowDown','ArrowUp',' '].includes(e.key)) e.preventDefault();
        if (dir === 'DROP' || dir === 'ROTATE') { doMove(dir); return; }
        if (moveKey !== dir) startMove(dir);
    }
});
document.addEventListener('keyup', function (e) {
    const map = {ArrowLeft:'LEFT', ArrowRight:'RIGHT', ArrowDown:'DOWN'};
    if (map[e.key] === moveKey) stopMove();
});
document.querySelectorAll('.dp').forEach(btn => btn.addEventListener('click', function () { doMove(this.dataset.dir); }));
document.getElementById('btnStart').addEventListener('click',   startGame);
document.getElementById('btnRestart').addEventListener('click', startGame);
document.getElementById('btnResume').addEventListener('click',  () => { if (gstate === 'pause') setScreen('playing'); });
document.getElementById('pauseChip').addEventListener('click',  () => {
    if (gstate === 'playing') setScreen('pause');
    else if (gstate === 'pause') setScreen('playing');
});

/* ════════════ PvP ENGINE ════════════ */
const OPP_W = 220, OPP_H = 440, OPP_COLS = 12, OPP_ROWS = 24;
const OB = OPP_W / OPP_COLS;
const oppCanvas = document.getElementById('oppCanvas');
const oppCtx    = oppCanvas.getContext('2d');

function pvpCall(action, data = {}) {
    return fetch('tetris_pvp.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action, ...data})})
        .then(r => r.json()).catch(() => null);
}

document.getElementById('pvpOpenBtn').addEventListener('click', openPvpLobby);
document.getElementById('pvpCloseBtn').addEventListener('click', closePvpLobby);

function openPvpLobby() {
    if (pvp.mode) return;
    if (gstate === 'playing') setScreen('pause');
    document.getElementById('pvpOverlay').classList.remove('hidden');
    document.getElementById('pvpOpenBtn').classList.add('active');
    pvp.lobbyOpen = true;
    pvpHeartbeat();
    pvp.heartTimer = setInterval(pvpHeartbeat, 4000);
}

function closePvpLobby() {
    pvp.lobbyOpen = false;
    clearInterval(pvp.heartTimer);
    document.getElementById('pvpOverlay').classList.add('hidden');
    document.getElementById('pvpOpenBtn').classList.remove('active');
    pvpCall('leave_lobby');
}

function pvpHeartbeat() {
    pvpCall('heartbeat').then(data => {
        if (!data || !pvp.lobbyOpen) return;
        if (data.session && data.session.status === 'playing' && !pvp.mode) {
            const s = data.session;
            pvp.opponentName = s.other_name || '?';
            pvp.opponentAvatar = s.other_avatar || null;
            closePvpLobby();
            startPvpCountdown(s.id, pvp.opponentName, pvp.opponentAvatar);
            return;
        }
        if (data.incoming) { renderModalIncoming(data.incoming); return; }
        if (data.session && data.session.status === 'pending' && parseInt(data.session.p1_id) === CURRENT_USER_ID) {
            renderModalSent(data.session.other_name || 'opponent'); return;
        }
        renderModalLobby(data.waiters || []);
    });
}

function setModalSub(t) { document.getElementById('pvpModalSub').textContent = t; }

function renderModalLobby(waiters) {
    setModalSub('Find an opponent');
    let html = `<div class="pvp-sec-lbl">Online Players</div>`;
    if (!waiters.length) {
        html += `<div class="pvp-empty-msg">No one else is waiting right now.<br>Ask a colleague to join!</div>`;
    } else {
        html += `<div class="pvp-player-list">`;
        waiters.forEach(p => {
            const init = (p.name||'?').substring(0,2).toUpperCase();
            const av = p.avatar ? `<img src="${escHtml(p.avatar)}" alt="">` : escHtml(init);
            html += `<div class="pvp-player-row">
                <div class="pvp-pav">${av}</div>
                <div class="pvp-pname">${escHtml(p.name)}</div>
                <button class="pvp-chal-btn" onclick="sendChallenge(${parseInt(p.user_id)},'${escHtml(p.name).replace(/'/g,"\\'")}')">⚔️ Challenge</button>
            </div>`;
        });
        html += `</div>`;
    }
    html += `<div class="pvp-lobby-footer"><div class="pvp-lobby-dot"></div><span class="pvp-lobby-txt">You're in the lobby — opponents can challenge you</span></div>`;
    document.getElementById('pvpModalBody').innerHTML = html;
}

function renderModalSent(oppName) {
    setModalSub('Challenge sent');
    document.getElementById('pvpModalBody').innerHTML = `
        <div class="pvp-status-box">
            <div class="pvp-spinner"></div>
            <div class="pvp-st-text">Waiting for <strong>${escHtml(oppName)}</strong> to respond…</div>
            <div class="pvp-st-sub">They'll see your challenge in the lobby</div>
            <button class="t-btn ghost" onclick="cancelChallenge()" style="margin-top:6px;">Cancel</button>
        </div>`;
}

function renderModalIncoming(c) {
    setModalSub('Incoming challenge!');
    const init = (c.challenger_name||'?').substring(0,2).toUpperCase();
    const av = c.challenger_avatar ? `<img src="${escHtml(c.challenger_avatar)}" alt="">` : escHtml(init);
    document.getElementById('pvpModalBody').innerHTML = `
        <div class="pvp-inc-box">
            <div class="pvp-inc-av">${av}</div>
            <div class="pvp-inc-name">${escHtml(c.challenger_name)}</div>
            <div class="pvp-inc-sub">wants to challenge you to Tetris!</div>
            <div class="pvp-respond-row">
                <button class="t-btn accept"  onclick="respondChallenge(${parseInt(c.id)},true)">✓ Accept</button>
                <button class="t-btn decline" onclick="respondChallenge(${parseInt(c.id)},false)">✗ Decline</button>
            </div>
        </div>`;
}

function sendChallenge(targetId, targetName) {
    pvpCall('challenge', {target_id: targetId}).then(data => { if (data && data.ok) renderModalSent(targetName); });
}
function cancelChallenge() { pvpCall('cancel_challenge').then(() => pvpHeartbeat()); }
function respondChallenge(sessionId, accept) {
    pvpCall('respond', {session_id: sessionId, accept}).then(data => {
        if (data && data.ok && accept) {
            pvp.opponentName   = data.opponent?.n      || '?';
            pvp.opponentAvatar = data.opponent?.avatar || null;
            closePvpLobby();
            startPvpCountdown(data.session_id, pvp.opponentName, pvp.opponentAvatar);
        } else if (!accept) { pvpHeartbeat(); }
    });
}

function startPvpCountdown(sessionId, oppName, oppAvatar) {
    pvp.sessionId = sessionId; pvp.opponentName = oppName; pvp.opponentAvatar = oppAvatar; pvp.ended = false;
    document.getElementById('leaderboardPanel').classList.add('hidden');
    document.getElementById('opponentPanel').classList.remove('hidden');
    document.getElementById('oppName').textContent = oppName;
    const avEl = document.getElementById('oppAv');
    avEl.innerHTML = oppAvatar ? `<img src="${escHtml(oppAvatar)}" alt="">` : oppName.substring(0,2).toUpperCase();
    document.getElementById('oppScore').textContent = '0';
    document.getElementById('oppLines').textContent = '0';
    document.getElementById('oppLevel').textContent = '1';
    document.getElementById('oppLostMsg').classList.add('hidden');
    oppCtx.fillStyle = '#050e18'; oppCtx.fillRect(0,0,OPP_W,OPP_H);
    setScreen('countdown');
    let n = 3;
    const cdEl = document.getElementById('cdNum');
    cdEl.classList.remove('cdgo'); cdEl.textContent = n;
    const tick = () => {
        n--;
        if (n > 0) {
            cdEl.textContent = n; cdEl.style.animation = 'none';
            requestAnimationFrame(() => { cdEl.style.animation = 'cdPop 0.5s cubic-bezier(0.34,1.56,0.64,1)'; });
            setTimeout(tick, 1000);
        } else {
            cdEl.textContent = 'GO!'; cdEl.classList.add('cdgo'); cdEl.style.animation = 'none';
            requestAnimationFrame(() => { cdEl.style.animation = 'cdPop 0.5s cubic-bezier(0.34,1.56,0.64,1)'; });
            setTimeout(startPvpGame, 700);
        }
    };
    setTimeout(tick, 1000);
}

function startPvpGame() {
    pvp.mode = true;
    startGame();
    pvp.pushTimer = setInterval(() => pushPvpState('playing'), 500);
}

function getBoardWithCurrent() {
    const b = board.map(r => [...r]);
    if (gstate === 'playing' && current) {
        current.shape.forEach((row, dy) => row.forEach((v, dx) => {
            if (v) { const ny=current.y+dy, nx=current.x+dx; if (ny>=0&&ny<ROWS&&nx>=0&&nx<COLS) b[ny][nx]=current.color; }
        }));
    }
    return b;
}

function pushPvpState(gstatus) {
    if (!pvp.sessionId) return;
    pvpCall('push_state', {session_id: pvp.sessionId, board: getBoardWithCurrent(), score, lines, level, gstatus})
        .then(data => {
            if (!data || !pvp.mode || pvp.ended) return;
            if (data.session && data.session.status === 'finished') {
                const iWon = parseInt(data.session.winner_id) === CURRENT_USER_ID;
                endPvpGame(iWon); return;
            }
            if (data.opponent) renderOppBoard(data.opponent);
        });
}

/* FIX: removed the broken timestamp comparison that caused the board to stop rendering.
   Now we always render the opponent board regardless of how old the data is —
   we just update the ping indicator colour to show staleness. */
function renderOppBoard(opp) {
    document.getElementById('oppScore').textContent = parseInt(opp.score).toLocaleString();
    document.getElementById('oppLines').textContent = opp.lines;
    document.getElementById('oppLevel').textContent = opp.level;

    /* Ping: compare server timestamp to local clock without assuming timezone.
       The DB stores UTC-like values; we strip any trailing Z before re-adding it
       so we don't double-apply timezone offset. */
    const rawTs = (opp.updated_at || '').replace(' ', 'T').replace(/Z$/,'') + 'Z';
    const secsSince = (Date.now() - new Date(rawTs).getTime()) / 1000;
    const pingDot = document.getElementById('oppPingDot');
    const pingTxt = document.getElementById('oppPingTxt');
    pingDot.classList.toggle('stale', secsSince > 5);
    pingTxt.textContent = secsSince > 5 ? 'connection slow…' : `synced ${Math.round(secsSince*10)/10}s ago`;

    /* Always draw the board — stale or not */
    if (opp.gstatus === 'lost') document.getElementById('oppLostMsg').classList.remove('hidden');

    oppCtx.fillStyle = '#050e18'; oppCtx.fillRect(0,0,OPP_W,OPP_H);
    oppCtx.strokeStyle = 'rgba(26,144,217,0.04)'; oppCtx.lineWidth = 0.5;
    for (let x = 0; x <= OPP_COLS; x++) { oppCtx.beginPath(); oppCtx.moveTo(x*OB,0); oppCtx.lineTo(x*OB,OPP_H); oppCtx.stroke(); }
    for (let y = 0; y <= OPP_ROWS; y++) { oppCtx.beginPath(); oppCtx.moveTo(0,y*OB); oppCtx.lineTo(OPP_W,y*OB); oppCtx.stroke(); }

    let bd; try { bd = JSON.parse(opp.board_json || '[]'); } catch (e) { bd = []; }
    bd.forEach((row, y) => {
        if (!Array.isArray(row)) return;
        row.forEach((color, x) => {
            if (color) {
                oppCtx.fillStyle = color; oppCtx.fillRect(x*OB+1, y*OB+1, OB-2, OB-2);
                oppCtx.fillStyle = 'rgba(255,255,255,0.15)'; oppCtx.fillRect(x*OB+1, y*OB+1, OB-2, 4);
            }
        });
    });
}

function endPvpGame(won) {
    if (pvp.ended) return;
    pvp.ended = true; pvp.mode = false;
    clearInterval(pvp.pushTimer);
    if (gameLoop) { cancelAnimationFrame(gameLoop); gameLoop = null; }
    document.getElementById('pvpOverIcon').textContent  = won ? '🏆' : '💀';
    document.getElementById('pvpOverTitle').textContent = won ? 'You Won!' : 'You Lost';
    document.getElementById('pvpOverScore').textContent = score.toLocaleString();
    document.getElementById('pvpOverSub').textContent   = won
        ? `${pvp.opponentName} topped out first — great stacking!`
        : `${pvp.opponentName} outlasted you. Rematch?`;
    setScreen('pvpover');
}

function pvpForfeit() {
    if (pvp.mode && pvp.sessionId) {
        clearInterval(pvp.pushTimer);
        pvpCall('forfeit', {session_id: pvp.sessionId}).then(() => {
            pvp.mode = false; pvp.ended = true;
            document.getElementById('leaderboardPanel').classList.remove('hidden');
            document.getElementById('opponentPanel').classList.add('hidden');
            setScreen('start');
        });
    }
}

document.getElementById('btnPvpAgain').addEventListener('click', () => {
    pvp.sessionId = null; pvp.ended = false;
    document.getElementById('opponentPanel').classList.add('hidden');
    setScreen('start'); openPvpLobby();
});
document.getElementById('btnPvpQuit').addEventListener('click', () => {
    pvp.sessionId = null; pvp.ended = false;
    document.getElementById('leaderboardPanel').classList.remove('hidden');
    document.getElementById('opponentPanel').classList.add('hidden');
    setScreen('start');
});

window.addEventListener('beforeunload', () => {
    if (pvp.lobbyOpen) navigator.sendBeacon('tetris_pvp.php', JSON.stringify({action:'leave_lobby'}));
    if (pvp.mode && pvp.sessionId) navigator.sendBeacon('tetris_pvp.php', JSON.stringify({action:'forfeit', session_id:pvp.sessionId}));
});
</script>
</body>
</html>