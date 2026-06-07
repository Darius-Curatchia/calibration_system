<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — Calibration Management System</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function () {
    var collapsed = localStorage.getItem('sb-state') === '1';
    document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    if (document.body) document.body.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
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
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15);--accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7;--bg-card:#ffffff;--bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10);--border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d;--text-2:#4a6070;--text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px;--r-md:12px;--r-lg:16px;--r-xl:20px;
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);
    --shadow-lg:0 8px 40px rgba(5,48,79,0.14);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg-page);color:var(--text);-webkit-font-smoothing:antialiased;}

/* ── Hero ── */
.about-hero {
    background: linear-gradient(135deg, var(--navy) 0%, #1a6fa8 100%);
    border-radius: var(--r-xl);
    padding: 48px 44px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.about-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
}
.about-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 70% at 90% 110%, rgba(26,144,217,0.25) 0%, transparent 60%);
    pointer-events: none;
}
.hero-inner { position: relative; z-index: 1; }
.hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 4px 13px;
    border: 1px solid rgba(255,255,255,0.20); border-radius: 40px;
    color: rgba(255,255,255,0.70);
    font-size: 10.5px; font-weight: 600; letter-spacing: 0.10em; text-transform: uppercase;
    margin-bottom: 18px; font-family: var(--mono);
}
.hero-badge .dot {
    width: 6px; height: 6px;
    background: var(--accent); border-radius: 50%;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(1.4);} }
.about-hero h1 {
    font-size: clamp(1.7rem, 3.5vw, 2.5rem);
    font-weight: 700; color: #fff; line-height: 1.18; letter-spacing: -0.02em; margin-bottom: 14px;
}
.about-hero h1 span { color: #7dd3fc; }
.about-hero p { font-size: 14.5px; font-weight: 400; color: rgba(255,255,255,0.62); line-height: 1.72; max-width: 500px; }

/* ── Secret hint ── */
.secret-hint {
    position: absolute; bottom: 16px; right: 20px; z-index: 2;
    font-size: 9.5px; font-family: var(--mono);
    color: rgba(255,255,255,0.13);
    letter-spacing: 0.06em;
    user-select: none;
    transition: color 0.4s;
    display: flex; align-items: center; gap: 4px;
}
.secret-hint.glow { color: rgba(125,211,252,0.85); }
.key-cap {
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 4px; padding: 1px 6px;
    font-size: 9px; font-family: var(--mono); min-width: 18px;
    transition: background 0.25s, border-color 0.25s, color 0.25s, box-shadow 0.25s;
}
.key-cap.lit {
    background: rgba(125,211,252,0.22);
    border-color: rgba(125,211,252,0.65);
    color: #7dd3fc;
    box-shadow: 0 0 8px rgba(125,211,252,0.35);
}

/* particles */
.hero-particles { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
.particle {
    position: absolute; border-radius: 50%;
    background: rgba(255,255,255,0.12);
    animation: float-particle linear infinite;
}
@keyframes float-particle {
    0%   { transform:translateY(0) rotate(0deg); opacity:0; }
    10%  { opacity:1; }
    90%  { opacity:1; }
    100% { transform:translateY(-300px) rotate(360deg); opacity:0; }
}

/* ── Cards ── */
.card {
    background: var(--bg-card); border-radius: var(--r-xl);
    box-shadow: var(--shadow-sm); border: 1px solid var(--border);
    margin-bottom: 20px; overflow: hidden;
    transition: box-shadow 0.22s ease, transform 0.22s ease;
}
.card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.card-header {
    padding: 18px 24px; border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);
    display: flex; align-items: center; gap: 14px;
}
.card-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--navy), var(--accent));
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(5,48,79,0.20);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.card:hover .card-icon { transform: rotate(8deg) scale(1.08); box-shadow: 0 4px 14px rgba(5,48,79,0.30); }
.card-icon svg { width: 18px; height: 18px; fill: #fff; }
.card-title { font-size: 14px; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: 0.2px; }
.card-body { padding: 20px 24px; }
.card-body p { font-size: 14px; line-height: 1.75; color: var(--text-2); }

/* ── Tetris easter egg icon ── */
#tetrisEasterEgg {
    animation: subtle-pulse 3s ease-in-out infinite;
    cursor: pointer; text-decoration: none;
}
#tetrisEasterEgg:hover {
    transform: rotate(12deg) scale(1.15) !important;
    box-shadow: 0 6px 18px rgba(26,144,217,0.45) !important;
    animation: none;
}
@keyframes subtle-pulse {
    0%,100% { box-shadow: 0 2px 8px rgba(5,48,79,0.20); }
    50%      { box-shadow: 0 2px 14px rgba(26,144,217,0.40); }
}

/* feature list */
.feature-list { list-style:none; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
@media(max-width:560px){.feature-list{grid-template-columns:1fr;}}
.feature-list li {
    display:flex; align-items:flex-start; gap:10px; padding:11px 13px;
    background:var(--bg-raised); border-radius:var(--r-sm);
    font-size:13px; font-weight:500; color:var(--text); border:1px solid var(--border);
    transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}
.feature-list li:hover { background:var(--accent-soft); border-color:rgba(26,144,217,0.22); color:var(--navy); }
.feature-list li svg { width:14px; height:14px; flex-shrink:0; margin-top:1px; color:var(--accent); fill:none; stroke:currentColor; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }

/* contact */
.contact-list { display:flex; flex-direction:column; gap:8px; }
.contact-item {
    display:flex; align-items:center; gap:14px; padding:12px 16px;
    border-radius:var(--r-md); border:1px solid var(--border-mid); background:var(--bg-raised);
    text-decoration:none; color:var(--text);
    transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}
.contact-item:hover { background:var(--navy); border-color:var(--navy); transform:translateX(4px); }
.contact-icon {
    width:34px; height:34px; background:var(--bg-card); border-radius:var(--r-sm);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
    box-shadow:0 1px 4px rgba(5,48,79,0.08); transition:background 0.18s ease;
}
.contact-item:hover .contact-icon { background:rgba(255,255,255,0.14); }
.contact-icon svg { width:16px; height:16px; }
.contact-icon svg path { fill:var(--navy); transition:fill 0.18s ease; }
.contact-item:hover .contact-icon svg path { fill:#fff; }
.contact-meta { display:flex; flex-direction:column; gap:1px; }
.contact-label { font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-3); font-family:var(--mono); transition:color 0.18s ease; }
.contact-item:hover .contact-label { color:rgba(255,255,255,0.52); }
.contact-value { font-size:13.5px; font-weight:500; color:var(--text); transition:color 0.18s ease; }
.contact-item:hover .contact-value { color:#fff; }

/* footer */
.footer-note { text-align:center; padding:14px 0 4px; font-size:12px; color:var(--text-3); font-family:var(--mono); }
.footer-note span { color:var(--accent); font-weight:600; }

/* ── Toast ── */
.toast {
    position: fixed; bottom: 28px; right: 28px;
    background: var(--navy); color: #fff;
    padding: 12px 18px; border-radius: var(--r-md);
    font-size: 13px; font-weight: 600;
    box-shadow: var(--shadow-lg); z-index: 9997;
    transform: translateY(80px); opacity: 0;
    transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.35s ease;
    max-width: 300px; display:flex; align-items:center; gap:10px;
    pointer-events: none;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast-emoji { font-size: 18px; }

/* ── Konami overlay ── */
.konami-overlay {
    position: fixed; inset: 0;
    background: rgba(5,48,79,0.92);
    z-index: 10000; display: none;
    justify-content: center; align-items: center;
    flex-direction: column; gap: 20px; text-align: center;
}
.konami-overlay.show { display: flex; }
.konami-overlay h2 { font-size: 2rem; font-weight: 700; color: #fff; letter-spacing: -0.02em; }
.konami-overlay p { color: rgba(255,255,255,0.6); font-size: 14px; }
.konami-close {
    margin-top: 10px; padding: 10px 24px;
    background: var(--accent); color: #fff; border: none; border-radius: var(--r-sm);
    font-size: 13px; font-weight: 600; font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer;
}
.konami-close:hover { background: #1480c5; }
.konami-rain { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.konami-drop { position: absolute; top: -40px; animation: rain-fall linear forwards; }
@keyframes rain-fall { to { transform: translateY(110vh) rotate(360deg); opacity: 0; } }

/* ═══════════════════════════════
   SNAKE GAME OVERLAY v2.0
═══════════════════════════════ */
.game-overlay {
    position: fixed; inset: 0;
    background: rgba(2, 10, 18, 0.97);
    z-index: 10001;
    display: none;
    justify-content: center; align-items: center;
}
.game-overlay.show { display: flex; }

.game-shell {
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    animation: game-pop 0.45s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes game-pop {
    from { transform: scale(0.75) translateY(-30px); opacity: 0; }
    to   { transform: scale(1) translateY(0); opacity: 1; }
}

.game-header {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 0 2px;
}
.game-title {
    font-family: var(--mono); font-size: 11px; font-weight: 700;
    color: rgba(125,211,252,0.75); letter-spacing: 0.14em; text-transform: uppercase;
}
.game-scores { display: flex; gap: 22px; }
.game-score-item { text-align: center; }
.score-lbl { font-size: 9px; font-family: var(--mono); color: rgba(255,255,255,0.28); text-transform: uppercase; letter-spacing: 0.08em; }
.score-val { font-family: var(--mono); font-size: 22px; font-weight: 700; color: #fff; line-height: 1.1; }
.score-val.hi { color: #fbbf24; }

.combo-display {
    font-family: var(--mono); font-size: 11px; color: #fbbf24;
    letter-spacing: 0.06em; min-height: 16px; text-align: center; width: 100%;
}

.powerup-bar {
    height: 3px; border-radius: 2px;
    background: rgba(255,255,255,0.08);
    width: 100%; overflow: hidden; display: none;
}
.powerup-fill {
    height: 100%; border-radius: 2px;
    background: linear-gradient(90deg, #a78bfa, #7c3aed);
    transition: width 0.1s linear;
}

.game-canvas-wrap {
    position: relative;
    border: 2px solid rgba(26,144,217,0.35);
    border-radius: 10px; overflow: hidden;
    box-shadow: 0 0 40px rgba(26,144,217,0.12), inset 0 0 40px rgba(0,0,0,0.6);
}
.game-canvas-wrap::before {
    content:''; position:absolute; inset:0;
    background: linear-gradient(135deg, rgba(26,144,217,0.03) 0%, transparent 50%);
    pointer-events:none; z-index:1;
}
canvas#gameCanvas { display:block; image-rendering:pixelated; }

.game-screen {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
    z-index: 2; background: rgba(2,10,18,0.86); backdrop-filter: blur(3px);
    transition: opacity 0.2s;
}
.game-screen.hidden { opacity: 0; pointer-events: none; }
.gs-title { font-family: var(--mono); font-size: clamp(1.1rem,3vw,1.55rem); font-weight: 700; color: #fff; letter-spacing: -0.01em; text-align: center; }
.gs-sub { font-size: 12px; font-family: var(--mono); color: rgba(255,255,255,0.38); text-align: center; line-height: 1.65; }
.gs-sub .k { display:inline-block; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:4px; padding:1px 6px; font-size:11px; }
.game-btn {
    margin-top: 6px; padding: 10px 28px;
    background: var(--accent); color: #fff; border: none; border-radius: var(--r-sm);
    font-size: 13px; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; transition: background 0.15s, transform 0.15s;
}
.game-btn:hover { background: #1480c5; transform: scale(1.04); }
.game-btn.ghost {
    background: transparent; border: 1px solid rgba(255,255,255,0.18);
    color: rgba(255,255,255,0.5); font-size: 12px; padding: 8px 20px; margin-top: 0;
}
.game-btn.ghost:hover { background: rgba(255,255,255,0.06); color: #fff; }

.go-score { font-family:var(--mono); font-size:3rem; font-weight:700; color:var(--accent); line-height:1; }
.go-hi { font-family:var(--mono); font-size:11px; color:#fbbf24; }
.go-hi.hidden { opacity:0; }
.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; width: 240px; }
.stat-box { background: rgba(255,255,255,0.05); border-radius: 6px; padding: 8px 10px; text-align: center; }
.stat-val { font-family: var(--mono); font-size: 15px; font-weight: 700; color: #fff; }
.stat-lbl { font-size: 9px; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.07em; margin-top: 2px; }

.opts-row { display: flex; gap: 12px; align-items: center; }
.game-toggle {
    display: flex; align-items: center; gap: 6px;
    font-family: var(--mono); font-size: 10px;
    color: rgba(255,255,255,0.4); cursor: pointer; user-select: none;
}
.game-toggle input[type=checkbox] { accent-color: var(--accent); width: 12px; height: 12px; }
.game-toggle.active { color: rgba(125,211,252,0.8); }

.level-row { width:100%; display:flex; align-items:center; gap:8px; padding:0 2px; }
.level-lbl { font-family:var(--mono); font-size:9px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:0.08em; flex-shrink:0; }
.pips { display:flex; gap:3px; }
.pip { width:16px; height:5px; border-radius:3px; background:rgba(255,255,255,0.09); transition:background 0.3s; }
.pip.on { background:var(--accent); }
.pip.on.warn { background:#f59e0b; }
.pip.on.danger { background:#ef4444; }

.dpad { display:none; grid-template-columns:44px 44px 44px; grid-template-rows:44px 44px; gap:4px; margin-top:2px; }
@media(pointer:coarse){.dpad{display:grid;}}
.dp {
    background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.13);
    border-radius:8px; color:#fff; font-size:18px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; user-select:none; -webkit-user-select:none;
    touch-action:manipulation; transition:background 0.1s;
}
.dp:active{background:rgba(26,144,217,0.3);}
.dp-up{grid-column:2;grid-row:1;} .dp-left{grid-column:1;grid-row:2;}
.dp-down{grid-column:2;grid-row:2;} .dp-right{grid-column:3;grid-row:2;}

.game-footer { font-size:10px; font-family:var(--mono); color:rgba(255,255,255,0.18); }
.game-footer kbd { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.14); border-radius:3px; padding:1px 5px; font-size:9px; }
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast" id="toast">
    <span class="toast-emoji" id="toastEmoji">🎉</span>
    <span id="toastMsg">Hello!</span>
</div>

<!-- Konami overlay -->
<div class="konami-overlay" id="konamiOverlay">
    <div class="konami-rain" id="konamiRain"></div>
    <div style="position:relative;z-index:1;">
        <div style="font-size:4rem;margin-bottom:12px;">🎮</div>
        <h2>Cheat Code Activated!</h2>
        <p>↑ ↑ ↓ ↓ ← → ← → B A<br>You found the Konami Code. Congrats.</p>
        <p style="margin-top:8px;font-family:var(--mono);font-size:12px;color:rgba(255,255,255,0.4);">Achievement unlocked: <strong style="color:#7dd3fc;">Keyboard Warrior</strong></p>
        <button class="konami-close" id="konamiClose">Nice. Now close this.</button>
    </div>
</div>

<!-- ═══════════ SNAKE GAME v2.0 ═══════════ -->
<div class="game-overlay" id="gameOverlay">
    <div class="game-shell">

        <div class="game-header">
            <div class="game-title">🐍 CaliSnake v2.0</div>
            <div class="game-scores">
                <div class="game-score-item">
                    <div class="score-lbl">Score</div>
                    <div class="score-val" id="scoreDisplay">0</div>
                </div>
                <div class="game-score-item">
                    <div class="score-lbl">Best</div>
                    <div class="score-val hi" id="hiDisplay">0</div>
                </div>
            </div>
        </div>

        <div class="level-row">
            <span class="level-lbl">Speed</span>
            <div class="pips" id="pips">
                <div class="pip"></div><div class="pip"></div><div class="pip"></div><div class="pip"></div>
                <div class="pip"></div><div class="pip"></div><div class="pip"></div><div class="pip"></div>
            </div>
        </div>

        <div class="combo-display" id="comboDisplay"></div>

        <div class="powerup-bar" id="powerupBar">
            <div class="powerup-fill" id="powerupFill" style="width:100%;"></div>
        </div>

        <div class="game-canvas-wrap">
            <canvas id="gameCanvas" width="340" height="340"></canvas>

            <!-- Start -->
            <div class="game-screen" id="screenStart">
                <div style="font-size:3rem">🐍</div>
                <div class="gs-title">CaliSnake v2.0</div>
                <div class="opts-row">
                    <label class="game-toggle active" id="wrapToggleLabel">
                        <input type="checkbox" id="wrapMode" checked> Wall wrap
                    </label>
                    <label class="game-toggle active" id="ghostToggleLabel">
                        <input type="checkbox" id="ghostMode" checked> Ghost trail
                    </label>
                </div>
                <div class="gs-sub">
                    <span class="k">↑</span> <span class="k">↓</span> <span class="k">←</span> <span class="k">→</span> to move &nbsp;·&nbsp; <span class="k">Shift</span> burst<br>
                    Eat <strong style="color:#fbbf24">🔧</strong> to grow &nbsp;·&nbsp; Grab <strong style="color:#a78bfa">⚡</strong> for invincibility<br>
                    Dodge <strong style="color:#ef4444">💀</strong> poison &nbsp;·&nbsp; Chain fast for combos
                </div>
                <button class="game-btn" id="btnStart">Start Game</button>
                <button class="game-btn ghost" id="btnClose1">Exit  (Esc)</button>
            </div>

            <!-- Game over -->
            <div class="game-screen hidden" id="screenOver">
                <div class="gs-title">Game Over</div>
                <div class="go-score" id="finalScore">0</div>
                <div class="go-hi" id="newHiBadge">🏆 New High Score!</div>
                <div class="stats-grid" id="statsGrid"></div>
                <div class="gs-sub" id="overMsg">Better luck next time</div>
                <button class="game-btn" id="btnRestart">Play Again</button>
                <button class="game-btn ghost" id="btnClose2">Exit  (Esc)</button>
            </div>

            <!-- Paused -->
            <div class="game-screen hidden" id="screenPause">
                <div style="font-size:2.5rem">⏸️</div>
                <div class="gs-title">Paused</div>
                <div class="gs-sub">Press <span class="k">P</span> or <span class="k">Space</span> to resume</div>
            </div>
        </div>

        <!-- Mobile d-pad -->
        <div class="dpad" id="dpad">
            <button class="dp dp-up"    data-dir="UP">↑</button>
            <button class="dp dp-left"  data-dir="LEFT">←</button>
            <button class="dp dp-down"  data-dir="DOWN">↓</button>
            <button class="dp dp-right" data-dir="RIGHT">→</button>
        </div>

        <div class="game-footer">
            <kbd>Esc</kbd> exit &nbsp;·&nbsp; <kbd>P</kbd> / <kbd>Space</kbd> pause &nbsp;·&nbsp; <kbd>Shift</kbd> speed burst
        </div>

    </div>
</div>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <!-- ── Hero ── -->
    <div class="about-hero" id="hero">
        <div class="hero-particles" id="heroParticles"></div>

        <div class="secret-hint" id="secretHint">
            <span class="sh-label"></span>
            <span class="key-cap" data-i="0">S</span>
            <span class="key-cap" data-i="1">N</span>
            <span class="key-cap" data-i="2">A</span>
            <span class="key-cap" data-i="3">K</span>
            <span class="key-cap" data-i="4">E</span>
        </div>

        <div class="hero-inner">
            <div class="hero-badge">
                <span class="dot"></span>
                Calibration Management System
            </div>
            <h1>Built for <span>Precision.</span><br>Designed for People.</h1>
            <p>Streamlining calibration tracking, inspection reports, and sample management — so your team can focus on what matters.</p>
        </div>
    </div>

    <!-- ── Purpose ── -->
    <div class="card">
        <div class="card-header">
            <a href="tetris.php" class="card-icon" id="tetrisEasterEgg" title="🧱 Play CaliTetris">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
            </a>
            <div class="card-title">Purpose</div>
        </div>
        <div class="card-body">
            <p>This system streamlines calibration and inspection workflows, reduces human error, and provides instant access to history, reports, and sample management — all in one place. Save time, cut paperwork, and keep your team productive.</p>
        </div>
    </div>

    <!-- ── Features ── -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 14H6V6h12v11z"/></svg>
            </div>
            <div class="card-title">Features</div>
        </div>
        <div class="card-body">
            <ul class="feature-list">
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Dashboard overview of calibration schedules</li>
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Calibration status reports</li>
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Inspection tracking with schedule</li>
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Sample &amp; standard sample management</li>
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Sticker printing</li>
                <li><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Full audit history</li>
            </ul>
        </div>
    </div>

    <!-- ── Contact ── -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div class="card-title">Contact the Developer</div>
        </div>
        <div class="card-body">
            <div class="contact-list">
                <a class="contact-item" href="tel:09165774198">
                    <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.11-.21c1.21.49 2.53.76 3.88.76a1 1 0 011 1v3.5a1 1 0 01-1 1C10.07 21.5 2.5 13.93 2.5 4a1 1 0 011-1H7a1 1 0 011 1c0 1.35.26 2.67.76 3.88a1 1 0 01-.21 1.11l-2.2 2.2z"/></svg></div>
                    <div class="contact-meta"><span class="contact-label">Phone</span><span class="contact-value">0916-577-4198</span></div>
                </a>
                <a class="contact-item" href="mailto:curatchiad@gmail.com">
                    <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg></div>
                    <div class="contact-meta"><span class="contact-label">Email</span><span class="contact-value">curatchiad@gmail.com</span></div>
                </a>
                <a class="contact-item" href="https://www.facebook.com/dariuslanze.curatchia" target="_blank" rel="noopener">
                    <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-6.99H7.9v-2.89h2.54V9.8c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34v6.99C18.34 21.12 22 16.99 22 12z"/></svg></div>
                    <div class="contact-meta"><span class="contact-label">Facebook</span><span class="contact-value">Darius Lanze Curatchia</span></div>
                </a>
            </div>
        </div>
    </div>

    <p class="footer-note">Made with <span>♥</span> by Darius Lanze Curatchia</p>

</div><!-- /.main-content -->

<script>
/* ══════════════════════════
   TOAST
══════════════════════════ */
function showToast(msg, emoji='🎉', dur=3000) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent   = msg;
    document.getElementById('toastEmoji').textContent = emoji;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), dur);
}

/* ══════════════════════════
   PARTICLES
══════════════════════════ */
(function(){
    const c = document.getElementById('heroParticles');
    function spawn(){
        const p=document.createElement('div'); p.className='particle';
        const s=4+Math.random()*10;
        p.style.cssText=`width:${s}px;height:${s}px;left:${Math.random()*100}%;bottom:-10px;animation-duration:${4+Math.random()*6}s;animation-delay:${Math.random()*2}s;opacity:${0.05+Math.random()*0.12};`;
        c.appendChild(p); setTimeout(()=>p.remove(),12000);
    }
    setInterval(spawn,600); for(let i=0;i<6;i++) spawn();
})();

/* ══════════════════════════
   KONAMI  ↑↑↓↓←→←→BA
══════════════════════════ */
const KONAMI=['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
let ki=0;
document.addEventListener('keydown',function(e){
    if(gameOpen()) return;
    if(konamiOpen()) return;
    if(e.key===KONAMI[ki]){ki++;if(ki===KONAMI.length){ki=0;triggerKonami();}}else ki=0;
});
function konamiOpen(){ return document.getElementById('konamiOverlay').classList.contains('show'); }
function gameOpen(){   return document.getElementById('gameOverlay').classList.contains('show'); }

function triggerKonami(){
    const ov=document.getElementById('konamiOverlay');
    const rain=document.getElementById('konamiRain'); rain.innerHTML='';
    const em=['🎮','⭐','💥','🔥','✨','🎊','🏆','💎','🚀','🎯'];
    for(let i=0;i<40;i++){
        const d=document.createElement('div'); d.className='konami-drop';
        d.textContent=em[Math.floor(Math.random()*em.length)];
        d.style.cssText=`left:${Math.random()*100}%;animation-duration:${1.5+Math.random()*2}s;animation-delay:${Math.random()*1.5}s;font-size:${18+Math.random()*20}px;`;
        rain.appendChild(d);
    }
    ov.classList.add('show');
}
document.getElementById('konamiClose').addEventListener('click',()=>document.getElementById('konamiOverlay').classList.remove('show'));

/* ══════════════════════════
   SNAKE TRIGGER — type "SNAKE"
══════════════════════════ */
const SCODE = ['s','n','a','k','e'];
let si = 0;
const hint    = document.getElementById('secretHint');
const keyCaps = hint.querySelectorAll('.key-cap');
let resetTimer = null;

document.addEventListener('keydown', function(e) {
    if (gameOpen() || konamiOpen()) return;
    const k = e.key.toLowerCase();
    if (k === SCODE[si]) {
        keyCaps[si].classList.add('lit');
        hint.classList.add('glow');
        si++;
        clearTimeout(resetTimer);
        if (si === SCODE.length) {
            si = 0;
            setTimeout(openGame, 320);
        } else {
            resetTimer = setTimeout(resetHint, 1800);
        }
    } else {
        resetHint();
    }
});

function resetHint() {
    si = 0;
    hint.classList.remove('glow');
    keyCaps.forEach(k => k.classList.remove('lit'));
}

/* ══════════════════════════════════════════════════════
   CALISNAKE v2.0 — GAME ENGINE
══════════════════════════════════════════════════════ */
const GRID = 20;
const SZ   = 340 / GRID; // 17px per cell
const canvas = document.getElementById('gameCanvas');
const ctx    = canvas.getContext('2d');

// Correct font size + middle baseline for reliable emoji centering
const EMOJI_FONT = `${SZ * 0.85}px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif`;

const C = {
    bg:   '#050e18',
    grid: 'rgba(26,144,217,0.055)',
    head: '#38bdf8',
    poi:  '#ef4444',
    pow:  '#a78bfa',
};

const HI_KEY = 'calisnake2_hi';
const loadHi = () => parseInt(localStorage.getItem(HI_KEY)||'0',10);
const saveHi = s  => localStorage.setItem(HI_KEY, s);

let snake, dir, nextDir, food, poisons, powerup, score, gameLoop, gstate, speed;
let invincible = false, invTimer = 0;
const INVDUR = 3000;

let comboCount = 0, lastEatTime = 0;
const COMBODUR = 3000;

let bestCombo = 0, itemsEaten = 0, startTime = 0;
let shiftHeld = false, burstCD = 0;
let floaters = [], shakeFrames = 0, ghostTrail = [], tickCount = 0;
let wrapMode = true, ghostMode = true;

function getSpeed(sc) { return Math.max(60, 200 - sc * 5); }
function getLevel(sc) { return Math.min(8, 1 + Math.floor(sc / 5)); }

function updatePips(sc) {
    const lv = getLevel(sc);
    document.querySelectorAll('.pip').forEach((p,i) => {
        if (i < lv) {
            p.classList.add('on');
            p.classList.toggle('warn', lv >= 5 && lv < 7);
            p.classList.toggle('danger', lv >= 7);
        } else {
            p.classList.remove('on','warn','danger');
        }
    });
}

function rndCell(excl=[]) {
    let p;
    do { p = {x:Math.floor(Math.random()*GRID), y:Math.floor(Math.random()*GRID)}; }
    while (excl.some(e => e.x===p.x && e.y===p.y));
    return p;
}

function spawnFood() { return rndCell([...snake, ...poisons]); }

function spawnPoisons() {
    const n = Math.min(5, Math.floor(score/6) + 1), arr = [];
    for (let i = 0; i < n; i++) {
        const p = rndCell([...snake, food, ...arr]);
        arr.push({x:p.x, y:p.y, moveTimer:0, moveInterval:Math.max(4, 12 - Math.floor(score/8))});
    }
    return arr;
}

function maybeSpawnPowerup() {
    if (!powerup && Math.random() < 0.12)
        powerup = rndCell([...snake, food, ...poisons]);
}

function initGame() {
    wrapMode  = document.getElementById('wrapMode').checked;
    ghostMode = document.getElementById('ghostMode').checked;
    const m = Math.floor(GRID / 2);
    snake   = [{x:m,y:m},{x:m-1,y:m},{x:m-2,y:m}];
    dir     = {x:1,y:0}; nextDir = {x:1,y:0};
    score   = 0; poisons = []; powerup = null;
    floaters = []; ghostTrail = [];
    invincible = false; invTimer = 0;
    comboCount = 0; lastEatTime = 0; bestCombo = 0; itemsEaten = 0;
    startTime = Date.now(); shakeFrames = 0; tickCount = 0;
    food = spawnFood();
    refreshScore(); updatePips(0);
    document.getElementById('powerupBar').style.display = 'none';
}

function refreshScore() {
    document.getElementById('scoreDisplay').textContent = score;
    document.getElementById('hiDisplay').textContent    = Math.max(score, loadHi());
}

function setScreen(s) {
    gstate = s;
    document.getElementById('screenStart').classList.toggle('hidden', s !== 'start');
    document.getElementById('screenOver').classList.toggle('hidden',  s !== 'over');
    document.getElementById('screenPause').classList.toggle('hidden', s !== 'pause');
}

function startGame() {
    initGame(); setScreen('playing');
    clearInterval(gameLoop);
    speed = getSpeed(0); scheduleLoop();
}

function scheduleLoop() {
    clearInterval(gameLoop);
    gameLoop = setInterval(tick, speed);
}

function tick() {
    if (gstate !== 'playing') return;
    tickCount++;

    // Drift poisons toward snake head at score >= 10
    if (score >= 10) {
        poisons.forEach(p => {
            p.moveTimer++;
            if (p.moveTimer >= p.moveInterval) {
                p.moveTimer = 0;
                const hd = snake[0];
                const dx = Math.sign(hd.x - p.x), dy = Math.sign(hd.y - p.y);
                const moveX = Math.abs(hd.x - p.x) >= Math.abs(hd.y - p.y);
                const nx = p.x + (moveX ? dx : 0);
                const ny = p.y + (moveX ? 0  : dy);
                const blocked = [...snake, ...poisons.filter(q=>q!==p), food].some(e=>e.x===nx&&e.y===ny);
                if (!blocked) { p.x = nx; p.y = ny; }
            }
        });
    }

    dir = {...nextDir};
    let hx = snake[0].x + dir.x;
    let hy = snake[0].y + dir.y;

    if (wrapMode) {
        hx = (hx + GRID) % GRID;
        hy = (hy + GRID) % GRID;
    } else if (hx < 0 || hx >= GRID || hy < 0 || hy >= GRID) {
        endGame(); return;
    }

    const h = {x:hx, y:hy};

    if (!invincible && snake.some(s => s.x===h.x && s.y===h.y)) { endGame(); return; }
    if (!invincible && poisons.some(p => p.x===h.x && p.y===h.y)) { endGame(); return; }

    if (ghostMode) {
        ghostTrail.unshift({x:snake[snake.length-1].x, y:snake[snake.length-1].y, a:0.35});
        if (ghostTrail.length > 8) ghostTrail.pop();
    }
    ghostTrail.forEach(g => { g.a -= 0.04; });
    ghostTrail = ghostTrail.filter(g => g.a > 0);

    snake.unshift(h);
    let grew = false;

    if (h.x === food.x && h.y === food.y) {
        score++; itemsEaten++; grew = true;
        const now = Date.now();
        if (now - lastEatTime < COMBODUR) {
            comboCount++;
            if (comboCount > bestCombo) bestCombo = comboCount;
        } else {
            comboCount = 1;
        }
        lastEatTime = now;
        if (comboCount >= 2) {
            score += comboCount - 1;
            floaters.push({x:food.x, y:food.y, text:`+${comboCount}x COMBO`, a:1, dy:-0.08, color:'#fbbf24'});
        }
        refreshScore();
        food = spawnFood();
        if (score % 4 === 0) poisons = spawnPoisons();
        maybeSpawnPowerup();
        const ns = getSpeed(score);
        if (ns !== speed) { speed = ns; scheduleLoop(); }
        updatePips(score);
        updateComboDisplay();
    }

    if (powerup && h.x === powerup.x && h.y === powerup.y) {
        invincible = true; invTimer = Date.now(); powerup = null; grew = true;
        document.getElementById('powerupBar').style.display = 'block';
        floaters.push({x:h.x, y:h.y, text:'INVINCIBLE!', a:1, dy:-0.1, color:'#a78bfa'});
    }

    if (!grew) snake.pop();

    if (invincible) {
        const elapsed = Date.now() - invTimer;
        const pct = Math.max(0, 1 - elapsed / INVDUR);
        document.getElementById('powerupFill').style.width = (pct * 100) + '%';
        if (elapsed >= INVDUR) {
            invincible = false;
            document.getElementById('powerupBar').style.display = 'none';
        }
    }

    if (shiftHeld && score > 0 && burstCD <= 0) {
        score = Math.max(0, score - 1);
        refreshScore();
        clearInterval(gameLoop);
        gameLoop = setInterval(tick, Math.max(40, speed - 30));
        burstCD = 8;
    }
    if (burstCD > 0) burstCD--;

    if (shakeFrames > 0) shakeFrames--;
    draw();
}

function updateComboDisplay() {
    const d = document.getElementById('comboDisplay');
    d.textContent = comboCount >= 2 ? `🔥 ${comboCount}x COMBO — +${comboCount - 1} bonus pts` : '';
    clearTimeout(d._t);
    d._t = setTimeout(() => { d.textContent = ''; }, COMBODUR);
}

function endGame() {
    clearInterval(gameLoop);
    shakeFrames = 8;
    setTimeout(() => {
        setScreen('over');
        document.getElementById('finalScore').textContent = score;
        const hi = loadHi(), isNew = score > hi;
        if (isNew) saveHi(score);
        document.getElementById('hiDisplay').textContent = isNew ? score : hi;
        document.getElementById('newHiBadge').classList.toggle('hidden', !isNew);
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const mm = Math.floor(elapsed / 60).toString().padStart(2,'0');
        const ss = (elapsed % 60).toString().padStart(2,'0');
        document.getElementById('statsGrid').innerHTML = `
            <div class="stat-box"><div class="stat-val">${snake.length}</div><div class="stat-lbl">Length</div></div>
            <div class="stat-box"><div class="stat-val">${mm}:${ss}</div><div class="stat-lbl">Time alive</div></div>
            <div class="stat-box"><div class="stat-val">${itemsEaten}</div><div class="stat-lbl">Items eaten</div></div>
            <div class="stat-box"><div class="stat-val">${bestCombo >= 2 ? bestCombo+'x' : '—'}</div><div class="stat-lbl">Best combo</div></div>
        `;
        const msgs = [
            [0,  'Maybe stick to calibration 😅'],
            [5,  'Not bad. Try again!'],
            [10, 'Getting the hang of it 🔥'],
            [20, 'Solid run! Can you beat it?'],
            [30, 'Are you even working right now? 😂'],
            [50, 'Snake legend. Respect. 🏆'],
        ];
        const m = [...msgs].reverse().find(([n]) => score >= n);
        document.getElementById('overMsg').textContent = m ? m[1] : 'Better luck next time';
    }, 120);
}

/* ── Draw helpers ── */

// Always force globalAlpha=1 inside drawEmoji — nothing bleeds through
function drawEmoji(emoji, cx, cy) {
    ctx.globalAlpha = 1;
    ctx.font = EMOJI_FONT;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(emoji, cx, cy);
}

function draw() {
    ctx.save();

    if (shakeFrames > 0) {
        ctx.translate((Math.random()-.5)*7, (Math.random()-.5)*7);
    }

    // 1. Background — always full opacity first
    ctx.globalAlpha = 1;
    ctx.fillStyle = C.bg;
    ctx.fillRect(0, 0, 340, 340);

    // 2. Grid
    ctx.strokeStyle = 'rgba(26,144,217,0.055)';
    ctx.lineWidth = 0.5;
    for (let i = 0; i <= GRID; i++) {
        ctx.beginPath(); ctx.moveTo(i*SZ,0);   ctx.lineTo(i*SZ,340);   ctx.stroke();
        ctx.beginPath(); ctx.moveTo(0,i*SZ);   ctx.lineTo(340,i*SZ);   ctx.stroke();
    }

    // 3. Ghost trail — use alpha on fillStyle, NOT globalAlpha, to avoid bleed
    if (ghostMode) {
        ghostTrail.forEach(g => {
            ctx.globalAlpha = 1;
            ctx.fillStyle = `rgba(14,165,233,${g.a * 0.3})`;
            ctx.beginPath();
            ctx.arc(g.x*SZ + SZ/2, g.y*SZ + SZ/2, SZ * 0.3, 0, Math.PI*2);
            ctx.fill();
        });
    }

    // 4. Snake — drawn BEFORE items so items always render on top of the snake
    const flashing = invincible && Math.floor(tickCount * 0.5) % 2 === 0;
    snake.forEach((seg, i) => {
        ctx.globalAlpha = 1;
        const bx = seg.x * SZ, by = seg.y * SZ;
        if (i === 0) {
            ctx.fillStyle = invincible ? (flashing ? '#a78bfa' : '#38bdf8') : '#38bdf8';
            if (invincible && !flashing) { ctx.shadowColor = '#a78bfa'; ctx.shadowBlur = 12; }
        } else {
            const t = i / snake.length;
            const v = Math.floor(20 + (1-t) * 200).toString(16).padStart(2,'0');
            ctx.fillStyle = `#0a${v}e8`;
        }
        const pad = i===0 ? 1 : 2, r = i===0 ? 5 : 3;
        rr(bx+pad, by+pad, SZ-pad*2, SZ-pad*2, r);
        ctx.fill();
        ctx.shadowBlur = 0;

        if (i === 0) {
            ctx.fillStyle = '#fff';
            let e1x=bx+SZ*.3,e1y=by+SZ*.32,e2x=bx+SZ*.7,e2y=by+SZ*.32;
            if (dir.x===1)  { e1x=bx+SZ*.65;e2x=bx+SZ*.65;e1y=by+SZ*.28;e2y=by+SZ*.65; }
            if (dir.x===-1) { e1x=bx+SZ*.35;e2x=bx+SZ*.35;e1y=by+SZ*.28;e2y=by+SZ*.65; }
            if (dir.y===1)  { e1y=by+SZ*.65;e2y=by+SZ*.65; }
            ctx.beginPath(); ctx.arc(e1x,e1y,1.8,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.arc(e2x,e2y,1.8,0,Math.PI*2); ctx.fill();
        }
    });

    // 5. Poisons — drawn AFTER snake so skull is always visible on top
    poisons.forEach(p => {
        ctx.globalAlpha = 1;
        ctx.fillStyle = 'rgba(239,68,68,0.15)';
        ctx.fillRect(p.x*SZ, p.y*SZ, SZ, SZ);
        drawEmoji('💀', p.x*SZ + SZ/2, p.y*SZ + SZ/2);
    });

    // 6. Power-up — pulse ONLY the background highlight rect, emoji always full opacity
    if (powerup) {
        const pulse = 0.7 + 0.3 * Math.sin(tickCount * 0.3);
        ctx.globalAlpha = pulse;
        ctx.fillStyle = 'rgba(167,139,250,0.18)';
        ctx.fillRect(powerup.x*SZ, powerup.y*SZ, SZ, SZ);
        // Reset BEFORE emoji so it's never dimmed by the pulse
        ctx.globalAlpha = 1;
        drawEmoji('⚡', powerup.x*SZ + SZ/2, powerup.y*SZ + SZ/2);
    }

    // 7. Food — absolutely last, always full opacity, always on top of everything
    ctx.globalAlpha = 1;
    drawEmoji('🔧', food.x*SZ + SZ/2, food.y*SZ + SZ/2);

    // 8. Floating combo text
    floaters.forEach(f => {
        ctx.globalAlpha = f.a;
        ctx.fillStyle   = f.color || '#fff';
        ctx.font = 'bold 9px monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(f.text, f.x*SZ + SZ/2, (f.y*SZ + SZ/2) - (1-f.a)*30);
        f.a -= 0.022; f.y += f.dy||0;
    });
    floaters = floaters.filter(f => f.a > 0);

    // Always restore globalAlpha before ctx.restore()
    ctx.globalAlpha = 1;
    ctx.restore();
}

function rr(x,y,w,h,r){
    ctx.beginPath();
    ctx.moveTo(x+r,y);
    ctx.lineTo(x+w-r,y); ctx.quadraticCurveTo(x+w,y,x+w,y+r);
    ctx.lineTo(x+w,y+h-r); ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
    ctx.lineTo(x+r,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-r);
    ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y);
    ctx.closePath();
}

// Idle draw
(function(){
    ctx.fillStyle = C.bg; ctx.fillRect(0,0,340,340);
    ctx.strokeStyle='rgba(26,144,217,0.055)'; ctx.lineWidth=0.5;
    for(let i=0;i<=GRID;i++){
        ctx.beginPath();ctx.moveTo(i*SZ,0);ctx.lineTo(i*SZ,340);ctx.stroke();
        ctx.beginPath();ctx.moveTo(0,i*SZ);ctx.lineTo(340,i*SZ);ctx.stroke();
    }
})();

/* ── Controls ── */
const DMAP = {
    ArrowUp:{x:0,y:-1},ArrowDown:{x:0,y:1},ArrowLeft:{x:-1,y:0},ArrowRight:{x:1,y:0},
    w:{x:0,y:-1},s:{x:0,y:1},a:{x:-1,y:0},d:{x:1,y:0}
};

document.addEventListener('keydown', function(e) {
    if (!gameOpen()) return;
    if (e.key==='Escape') { closeGame(); return; }
    const pause = e.key==='p'||e.key==='P'||e.key===' ';
    if (pause && gstate==='playing') { clearInterval(gameLoop); setScreen('pause'); return; }
    if (pause && gstate==='pause')   { setScreen('playing'); scheduleLoop(); return; }
    if (e.key==='Enter' && (gstate==='start'||gstate==='over')) { startGame(); return; }
    const d = DMAP[e.key];
    if (d && gstate==='playing') {
        if (d.x !== -dir.x || d.y !== -dir.y) nextDir = d;
        e.preventDefault();
    }
});

document.addEventListener('keydown', e => { if(e.key==='Shift') shiftHeld=true; });
document.addEventListener('keyup',   e => { if(e.key==='Shift') shiftHeld=false; });
window.addEventListener('keydown', e => {
    if (gameOpen() && ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight',' '].includes(e.key)) e.preventDefault();
});

document.querySelectorAll('.dp').forEach(btn => {
    btn.addEventListener('click', function() {
        if (gstate !== 'playing') return;
        const m = {UP:{x:0,y:-1},DOWN:{x:0,y:1},LEFT:{x:-1,y:0},RIGHT:{x:1,y:0}};
        const d = m[this.dataset.dir];
        if (d && (d.x !== -dir.x || d.y !== -dir.y)) nextDir = d;
    });
});

document.getElementById('wrapMode').addEventListener('change', function() {
    document.getElementById('wrapToggleLabel').classList.toggle('active', this.checked);
});
document.getElementById('ghostMode').addEventListener('change', function() {
    document.getElementById('ghostToggleLabel').classList.toggle('active', this.checked);
});

document.getElementById('btnStart').addEventListener('click',   startGame);
document.getElementById('btnRestart').addEventListener('click', startGame);
document.getElementById('btnClose1').addEventListener('click',  closeGame);
document.getElementById('btnClose2').addEventListener('click',  closeGame);

function openGame() {
    document.getElementById('hiDisplay').textContent    = loadHi();
    document.getElementById('scoreDisplay').textContent = '0';
    document.getElementById('comboDisplay').textContent = '';
    updatePips(0);
    setScreen('start');
    document.getElementById('gameOverlay').classList.add('show');
    showToast('CaliSnake v2.0 unlocked! 🐍', '🎮', 3500);
    resetHint();
}

function closeGame() {
    clearInterval(gameLoop);
    document.getElementById('gameOverlay').classList.remove('show');
}
</script>
</body>
</html>