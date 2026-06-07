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
/* ─────────────────────────────────────────────
   DESIGN TOKENS
───────────────────────────────────────────── */
:root {
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-soft:rgba(26,144,217,0.08);
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


/* ═══════════════════════════════════════════
   DEEP SEA SIDEBAR OVERRIDE
   Only applies on about.php (body.page-about).
   Replaces the sidebar's background & text
   colours so it feels like a second monitor
   looking into the same underwater world.
═══════════════════════════════════════════ */
body.page-about .sidebar {
    background: linear-gradient(180deg,
        #020c18 0%,
        #021525 25%,
        #031c32 55%,
        #010b14 100%
    ) !important;
    background-image:
        linear-gradient(180deg,#020c18 0%,#021525 25%,#031c32 55%,#010b14 100%),
        none !important;
    border-right: 1px solid rgba(26,144,217,0.12) !important;
    box-shadow: 3px 0 32px rgba(0,0,0,0.55), inset -1px 0 0 rgba(26,144,217,0.06) !important;
    overflow: hidden;
}

/* Animated light rays inside sidebar */
body.page-about .sidebar::before {
    content: '';
    position: absolute;
    top: -40px; left: -20px;
    width: 60px; height: 200%;
    background: linear-gradient(180deg, rgba(26,144,217,0.10) 0%, transparent 60%);
    transform: rotate(8deg);
    animation: sb-ray 8s ease-in-out infinite alternate;
    pointer-events: none;
    z-index: 0;
}
body.page-about .sidebar::after {
    content: '';
    position: absolute;
    top: -40px; left: 40px;
    width: 30px; height: 200%;
    background: linear-gradient(180deg, rgba(125,211,252,0.07) 0%, transparent 50%);
    transform: rotate(6deg);
    animation: sb-ray 10s ease-in-out 2s infinite alternate;
    pointer-events: none;
    z-index: 0;
}
@keyframes sb-ray {
    0%   { opacity:0.4; transform:rotate(6deg) translateX(0px); }
    100% { opacity:1;   transform:rotate(10deg) translateX(6px); }
}

/* Ensure sidebar children sit above the pseudo-element rays */
body.page-about .sidebar > * { position: relative; z-index: 1; }

/* Brand text colour adjustments */
body.page-about .sidebar .sidebar-brand-name { color: #7dd3fc; }
body.page-about .sidebar .sidebar-brand-sub  { color: rgba(125,211,252,0.42); }

/* Collapse button */
body.page-about .sidebar .sidebar-collapse-btn {
    border-color: rgba(26,144,217,0.20);
    background: rgba(26,144,217,0.06);
}
body.page-about .sidebar .sidebar-collapse-btn:hover {
    background: rgba(26,144,217,0.14);
    border-color: rgba(26,144,217,0.35);
    color: #7dd3fc;
}

/* Section labels */
body.page-about .sidebar .sidebar-section-label span {
    color: rgba(125,211,252,0.38);
}
body.page-about .sidebar .sidebar-section-label span::after {
    background: rgba(26,144,217,0.15);
}

/* Nav links */
body.page-about .sidebar .sidebar-link {
    color: rgba(185,230,255,0.80);
}
body.page-about .sidebar .sidebar-link:hover {
    background: rgba(26,144,217,0.10);
    color: #e0f4ff;
}
body.page-about .sidebar .sidebar-link.active {
    background: rgba(26,144,217,0.16);
    color: #7dd3fc;
}
body.page-about .sidebar .sidebar-link.active::before {
    background: #1a90d9;
    box-shadow: 0 0 8px rgba(26,144,217,0.6);
}

/* Icons */
body.page-about .sidebar .sidebar-icon svg {
    fill: rgba(125,211,252,0.38);
}
body.page-about .sidebar .sidebar-link:hover .sidebar-icon svg {
    fill: rgba(185,230,255,0.90);
}
body.page-about .sidebar .sidebar-link.active .sidebar-icon svg {
    fill: #1a90d9;
    filter: drop-shadow(0 0 4px rgba(26,144,217,0.55));
}

/* About + logout footer icons */
body.page-about .sidebar .sidebar-link--about .sidebar-icon svg,
body.page-about .sidebar .sidebar-link--logout .sidebar-icon svg {
    fill: rgba(125,211,252,0.30);
}
body.page-about .sidebar .sidebar-link--logout:hover {
    background: rgba(220,50,50,0.12);
    color: #fca5a5;
}
body.page-about .sidebar .sidebar-link--logout:hover .sidebar-icon svg {
    fill: #fca5a5;
}

/* Footer divider */
body.page-about .sidebar .sidebar-footer-divider {
    background: rgba(26,144,217,0.14);
}

/* Tooltip dark bg */
body.page-about .sidebar .sidebar-link[data-label]::after,
body[data-sidebar="collapsed"].page-about .sidebar .sidebar-link[data-label]::after {
    background: #020c18;
    border-color: rgba(26,144,217,0.18);
    color: rgba(185,230,255,0.90);
}

/* Sidebar fish — decorative, only visible when expanded */
body.page-about .sidebar .sb-fish {
    position: absolute;
    z-index: 2;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}
body.page-about[data-sidebar="expanded"] .sidebar .sb-fish { opacity: 1; }
body.page-about .sidebar .sb-fish-1 {
    bottom: 22%;
    left: -30px;
    animation: sb-swim-1 14s linear 2s infinite;
}
body.page-about .sidebar .sb-fish-2 {
    bottom: 42%;
    left: -30px;
    animation: sb-swim-1 20s linear 8s infinite;
}
body.page-about .sidebar .sb-jelly {
    right: 8px;
    top: 30%;
    animation: sb-jelly-float 5s ease-in-out infinite;
}
@keyframes sb-swim-1 {
    0%   { transform: translateX(-30px) scaleX(-1); opacity:0; }
    5%   { opacity: 1; }
    90%  { opacity: 1; }
    100% { transform: translateX(290px) scaleX(-1); opacity:0; }
}
@keyframes sb-jelly-float {
    0%,100% { transform: translateY(0); }
    50%     { transform: translateY(-10px); }
}

/* Sidebar bubble canvas */
body.page-about #sbBubbleCanvas {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
    width: 100%;
    height: 100%;
}


/* ═══════════════════════════════════════════
   HERO — DEEP SEA
═══════════════════════════════════════════ */
.about-hero {
    background: linear-gradient(180deg, #020f1c 0%, #031929 30%, #042035 60%, #010c16 100%);
    border-radius: var(--r-xl);
    padding: 48px 44px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
    min-height: 220px;
}

/* Light rays */
.about-hero .hero-rays {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    pointer-events: none; z-index: 1;
}

/* Sea floor fade */
.about-hero .sea-floor {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 70px;
    background: linear-gradient(to top, rgba(1,8,18,0.65), transparent);
    pointer-events: none; z-index: 2;
}

/* Hero badge */
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 13px;
    border: 1px solid rgba(125,211,252,0.22);
    border-radius: 40px;
    color: rgba(185,230,255,0.70);
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    margin-bottom: 18px;
    font-family: var(--mono);
}
.hero-badge .dot {
    width: 6px; height: 6px;
    background: #1a90d9;
    border-radius: 50%;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100%{opacity:1;transform:scale(1);}
    50%{opacity:0.5;transform:scale(1.4);}
}

.about-hero h1 {
    font-size: clamp(1.7rem, 3.5vw, 2.5rem);
    font-weight: 700;
    color: #e0f4ff;
    line-height: 1.18;
    letter-spacing: -0.02em;
    margin-bottom: 14px;
}
.about-hero h1 span { color: #7dd3fc; }
.about-hero > .hero-inner > p {
    font-size: 14.5px;
    color: rgba(185,230,255,0.55);
    line-height: 1.72;
    max-width: 500px;
}

/* Konami hint */
.konami-hint {
    position: absolute;
    top: 16px; right: 20px;
    font-size: 9.5px;
    font-family: var(--mono);
    color: rgba(125,211,252,0.15);
    letter-spacing: 0.04em;
    z-index: 6;
    transition: color 0.4s;
    user-select: none;
}
.konami-hint.lit { color: rgba(125,211,252,0.65); }

/* Hero inner above everything */
.hero-inner { position: relative; z-index: 5; }

/* Kelp */
.hero-kelp {
    position: absolute; bottom: 0;
    pointer-events: none; z-index: 3;
}

/* Fish in hero */
.hero-fish {
    position: absolute;
    pointer-events: none;
    z-index: 4;
}
/* right-swimming */
.hero-fish.swim-right { animation: fish-swim-right linear infinite; }
/* left-swimming */
.hero-fish.swim-left  { animation: fish-swim-left  linear infinite; }

@keyframes fish-swim-right {
    0%   { left: -80px; }
    100% { left: calc(100% + 80px); }
}
@keyframes fish-swim-left {
    0%   { right: -80px; }
    100% { right: calc(100% + 80px); }
}

/* Jellyfish in hero */
.hero-jelly {
    position: absolute;
    pointer-events: none; z-index: 4;
    animation: jelly-bob ease-in-out infinite;
}
@keyframes jelly-bob {
    0%,100% { transform: translateY(0); }
    50%     { transform: translateY(-10px); }
}
@keyframes jelly-tent {
    0%,100% { transform: scaleY(1); }
    50%     { transform: scaleY(0.7); }
}

/* Bubbles in hero */
.hero-bubble {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(100,200,255,0.35);
    background: rgba(100,200,255,0.06);
    pointer-events: none; z-index: 4;
    animation: bubble-rise linear infinite;
}
@keyframes bubble-rise {
    0%   { transform: translateY(0) scale(1);   opacity:0.7; }
    100% { transform: translateY(-340px) scale(1.5); opacity:0; }
}

/* Sparkle particles */
.hero-particle {
    position: absolute;
    border-radius: 50%;
    background: rgba(125,211,252,0.3);
    pointer-events: none; z-index: 3;
    animation: sparkle ease-in-out infinite;
}
@keyframes sparkle {
    0%,100%{opacity:0.1;} 50%{opacity:0.85;}
}

/* Ray animation */
@keyframes ray-pulse {
    0%,100%{opacity:0.07;} 50%{opacity:0.16;}
}
@keyframes kelp-sway {
    0%,100%{ transform-origin:bottom center; transform:rotate(-4deg); }
    50%    { transform-origin:bottom center; transform:rotate(4deg); }
}


/* ═══════════════════════════════════════════
   CARDS
═══════════════════════════════════════════ */
.card {
    background: var(--bg-card);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow 0.22s ease, transform 0.22s ease;
}
.card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);
    display: flex; align-items: center; gap: 14px;
}
.card-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--navy), var(--accent));
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(5,48,79,0.20);
}
.card-icon svg { width: 18px; height: 18px; fill: #fff; }
.card-title { font-size: 14px; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: 0.2px; }
.card-body { padding: 20px 24px; }
.card-body p { font-size: 14px; line-height: 1.75; color: var(--text-2); }

/* Feature list */
.feature-list { list-style:none; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
@media(max-width:560px){.feature-list{grid-template-columns:1fr;}}
.feature-list li {
    display:flex; align-items:flex-start; gap:10px;
    padding:11px 13px;
    background:var(--bg-raised);
    border-radius:var(--r-sm);
    font-size:13px; font-weight:500; color:var(--text);
    border:1px solid var(--border);
    transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}
.feature-list li:hover { background:var(--accent-soft); border-color:rgba(26,144,217,0.22); color:var(--navy); }
.feature-list li svg { width:14px;height:14px;flex-shrink:0;margin-top:1px;color:var(--accent);fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round; }

/* Contact list */
.contact-list { display:flex; flex-direction:column; gap:8px; }
.contact-item {
    display:flex; align-items:center; gap:14px;
    padding:12px 16px;
    border-radius:var(--r-md);
    border:1px solid var(--border-mid);
    background:var(--bg-raised);
    text-decoration:none; color:var(--text);
    transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}
.contact-item:hover { background:var(--navy); border-color:var(--navy); transform:translateX(4px); }
.contact-icon {
    width:34px;height:34px;
    background:var(--bg-card); border-radius:var(--r-sm);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    box-shadow:0 1px 4px rgba(5,48,79,0.08);
    transition:background 0.18s ease;
}
.contact-item:hover .contact-icon { background:rgba(255,255,255,0.14); }
.contact-icon svg { width:16px;height:16px; }
.contact-icon svg path { fill:var(--navy); transition:fill 0.18s ease; }
.contact-item:hover .contact-icon svg path { fill:#fff; }
.contact-meta { display:flex;flex-direction:column;gap:1px; }
.contact-label { font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-3);font-family:var(--mono);transition:color 0.18s ease; }
.contact-item:hover .contact-label { color:rgba(255,255,255,0.52); }
.contact-value { font-size:13.5px;font-weight:500;color:var(--text);transition:color 0.18s ease; }
.contact-item:hover .contact-value { color:#fff; }

/* Footer note */
.footer-note { text-align:center;padding:14px 0 4px;font-size:12px;color:var(--text-3);font-family:var(--mono); }

/* Toast */
.toast {
    position:fixed; bottom:28px; right:28px;
    background:var(--navy); color:#fff;
    padding:12px 18px; border-radius:var(--r-md);
    font-size:13px; font-weight:600;
    box-shadow:var(--shadow-lg); z-index:9998;
    transform:translateY(80px); opacity:0;
    transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.35s ease;
    max-width:280px; display:flex; align-items:center; gap:10px;
    pointer-events:none;
}
.toast.show { transform:translateY(0); opacity:1; }
.toast-emoji { font-size:18px; }

/* Konami overlay */
.konami-overlay {
    position:fixed; inset:0;
    background:rgba(2,12,28,0.94);
    z-index:10000; display:none;
    justify-content:center; align-items:center;
    flex-direction:column; gap:20px; text-align:center;
}
.konami-overlay.show { display:flex; }
.konami-overlay h2 { font-size:2rem;font-weight:700;color:#7dd3fc;letter-spacing:-0.02em; }
.konami-overlay p { color:rgba(185,230,255,0.6);font-size:14px; }
.konami-close {
    margin-top:10px; padding:10px 24px;
    background:var(--accent); color:#fff; border:none; border-radius:var(--r-sm);
    font-size:13px; font-weight:600;
    font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer;
}
.konami-close:hover { background:#1480c5; }
.konami-rain { position:absolute;inset:0;pointer-events:none;overflow:hidden; }
.konami-drop {
    position:absolute; top:-40px; font-size:24px;
    animation:rain-fall linear forwards;
}
@keyframes rain-fall { to { transform:translateY(110vh) rotate(360deg); opacity:0; } }
</style>
</head>
<body class="page-about">

<?php include 'includes/sidebar.php'; ?>

<!-- Sidebar fish & bubbles — injected after sidebar is in DOM -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sb = document.querySelector('.sidebar');
    if (!sb) return;

    /* ── Small fish 1 ── */
    const f1 = document.createElementNS('http://www.w3.org/2000/svg','svg');
    f1.setAttribute('class','sb-fish sb-fish-1');
    f1.setAttribute('width','34'); f1.setAttribute('height','20');
    f1.setAttribute('viewBox','0 0 34 20');
    f1.innerHTML = `
        <ellipse cx="16" cy="10" rx="12" ry="6" fill="#1d4ed8" opacity="0.80"/>
        <path d="M28 10 Q34 4 32 10 Q34 16 28 10Z" fill="#1d4ed8" opacity="0.75"/>
        <circle cx="6" cy="8.5" r="1.8" fill="#0f172a" opacity="0.9"/>
        <circle cx="5.4" cy="7.9" r="0.6" fill="#fff" opacity="0.8"/>
    `;
    sb.appendChild(f1);

    /* ── Small fish 2 ── */
    const f2 = document.createElementNS('http://www.w3.org/2000/svg','svg');
    f2.setAttribute('class','sb-fish sb-fish-2');
    f2.setAttribute('width','26'); f2.setAttribute('height','16');
    f2.setAttribute('viewBox','0 0 26 16');
    f2.innerHTML = `
        <ellipse cx="13" cy="8" rx="10" ry="5" fill="#f97316" opacity="0.80"/>
        <path d="M23 8 Q26 3 25 8 Q26 13 23 8Z" fill="#f97316" opacity="0.75"/>
        <circle cx="5" cy="6.5" r="1.5" fill="#1a1a2e" opacity="0.9"/>
        <circle cx="4.5" cy="6" r="0.5" fill="#fff" opacity="0.8"/>
    `;
    sb.appendChild(f2);

    /* ── Tiny jellyfish ── */
    const jelly = document.createElementNS('http://www.w3.org/2000/svg','svg');
    jelly.setAttribute('class','sb-fish sb-jelly');
    jelly.setAttribute('width','22'); jelly.setAttribute('height','30');
    jelly.setAttribute('viewBox','0 0 22 30');
    jelly.setAttribute('style','right:10px;top:28%;');
    jelly.innerHTML = `
        <ellipse cx="11" cy="9" rx="9" ry="7" fill="rgba(147,210,255,0.16)" stroke="rgba(147,210,255,0.42)" stroke-width="0.8"/>
        <line x1="5" y1="15" x2="3" y2="30" stroke="rgba(147,210,255,0.30)" stroke-width="0.6"/>
        <line x1="8" y1="16" x2="7" y2="30" stroke="rgba(147,210,255,0.28)" stroke-width="0.6"/>
        <line x1="11" y1="16" x2="11" y2="30" stroke="rgba(147,210,255,0.30)" stroke-width="0.6"/>
        <line x1="14" y1="16" x2="15" y2="30" stroke="rgba(147,210,255,0.28)" stroke-width="0.6"/>
        <line x1="17" y1="15" x2="19" y2="30" stroke="rgba(147,210,255,0.30)" stroke-width="0.6"/>
    `;
    sb.appendChild(jelly);

    /* ── Bubble canvas ── */
    const canvas = document.createElement('canvas');
    canvas.id = 'sbBubbleCanvas';
    sb.appendChild(canvas);

    function resizeCanvas() {
        canvas.width  = sb.offsetWidth;
        canvas.height = sb.offsetHeight;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    const ctx = canvas.getContext('2d');
    const bubbles = Array.from({length: 10}, () => ({
        x: Math.random() * 60 + 4,
        y: canvas.height + Math.random() * 200,
        r: 2 + Math.random() * 4,
        speed: 0.4 + Math.random() * 0.7,
        opacity: 0.2 + Math.random() * 0.4,
    }));

    function drawBubbles() {
        resizeCanvas();
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        bubbles.forEach(b => {
            b.y -= b.speed;
            if (b.y + b.r < 0) {
                b.y = canvas.height + b.r;
                b.x = Math.random() * 60 + 4;
            }
            ctx.beginPath();
            ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
            ctx.strokeStyle = `rgba(100,200,255,${b.opacity})`;
            ctx.lineWidth = 0.8;
            ctx.stroke();
        });
        requestAnimationFrame(drawBubbles);
    }
    drawBubbles();
});
</script>

<!-- Toast -->
<div class="toast" id="toast">
    <span class="toast-emoji" id="toastEmoji">🎉</span>
    <span id="toastMsg">Hello!</span>
</div>

<!-- Konami overlay -->
<div class="konami-overlay" id="konamiOverlay">
    <div class="konami-rain" id="konamiRain"></div>
    <div style="position:relative;z-index:1;">
        <div style="font-size:4rem;margin-bottom:12px;">🐠</div>
        <h2>Cheat Code Activated!</h2>
        <p>↑ ↑ ↓ ↓ ← → ← → B A<br>You found the Konami Code easter egg. Congrats, you have no life.</p>
        <p style="margin-top:8px;font-family:var(--mono);font-size:12px;color:rgba(125,211,252,0.40);">Achievement unlocked: <strong style="color:#7dd3fc;">Keyboard Warrior</strong></p>
        <button class="konami-close" id="konamiClose">Close & Pretend This Never Happened</button>
    </div>
</div>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <!-- ══════════════════════════════
         DEEP SEA HERO
    ══════════════════════════════ -->
    <div class="about-hero" id="hero">

        <!-- Light rays SVG layer -->
        <svg class="hero-rays" viewBox="0 0 700 260" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="r1" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#1a90d9" stop-opacity="0.20"/>
                    <stop offset="100%" stop-color="#1a90d9" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="r2" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.14"/>
                    <stop offset="100%" stop-color="#7dd3fc" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <polygon points="60,0 110,0 180,260 10,260"  fill="url(#r1)" style="animation:ray-pulse 7s ease-in-out infinite"/>
            <polygon points="190,0 225,0 285,260 155,260" fill="url(#r2)" style="animation:ray-pulse 9s ease-in-out 1.5s infinite"/>
            <polygon points="360,0 405,0 500,260 265,260" fill="url(#r1)" style="animation:ray-pulse 6s ease-in-out 0.8s infinite"/>
            <polygon points="540,0 585,0 680,260 450,260" fill="url(#r2)" style="animation:ray-pulse 8s ease-in-out 3s infinite"/>
        </svg>

        <!-- Sea floor -->
        <div class="sea-floor"></div>

        <!-- Konami hint -->
        <span class="konami-hint" id="konamiHint">↑↑↓↓←→←→BA</span>

        <!-- ── Kelp ── -->
        <!-- Left kelp cluster -->
        <svg class="hero-kelp" style="left:3%;animation:kelp-sway 4.2s ease-in-out infinite;" width="20" height="100" viewBox="0 0 20 100">
            <path d="M10 100 Q4 78 10 60 Q16 44 10 28 Q4 14 10 0" stroke="#0c5535" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            <ellipse cx="5" cy="46" rx="8" ry="4.5" fill="#0a4a2e" opacity="0.85" transform="rotate(-22 5 46)"/>
            <ellipse cx="14" cy="24" rx="7" ry="3.5" fill="#0a4a2e" opacity="0.80" transform="rotate(18 14 24)"/>
        </svg>
        <svg class="hero-kelp" style="left:6%;animation:kelp-sway 5s ease-in-out 0.6s infinite;" width="14" height="74" viewBox="0 0 14 74">
            <path d="M7 74 Q2 57 7 43 Q12 30 7 16 Q3 6 7 0" stroke="#0b4c30" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <ellipse cx="11" cy="36" rx="6" ry="3" fill="#0a4a2e" opacity="0.75" transform="rotate(22 11 36)"/>
        </svg>
        <svg class="hero-kelp" style="left:10%;animation:kelp-sway 3.8s ease-in-out 1.1s infinite;" width="12" height="55" viewBox="0 0 12 55">
            <path d="M6 55 Q2 42 6 32 Q10 22 6 12 Q3 5 6 0" stroke="#0c5535" stroke-width="2.4" fill="none" stroke-linecap="round"/>
        </svg>
        <!-- Right kelp cluster -->
        <svg class="hero-kelp" style="right:2%;animation:kelp-sway 4.5s ease-in-out 0.4s infinite;" width="22" height="115" viewBox="0 0 22 115">
            <path d="M11 115 Q4 90 11 70 Q18 52 11 33 Q5 16 11 0" stroke="#0c5535" stroke-width="4" fill="none" stroke-linecap="round"/>
            <ellipse cx="16" cy="58" rx="9" ry="5" fill="#0a4a2e" opacity="0.85" transform="rotate(-16 16 58)"/>
            <ellipse cx="5" cy="32" rx="8" ry="4" fill="#0a4a2e" opacity="0.80" transform="rotate(20 5 32)"/>
        </svg>
        <svg class="hero-kelp" style="right:6%;animation:kelp-sway 5.2s ease-in-out 1s infinite;" width="15" height="80" viewBox="0 0 15 80">
            <path d="M7 80 Q2 62 7 47 Q12 33 7 18 Q3 7 7 0" stroke="#0b4c30" stroke-width="3" fill="none" stroke-linecap="round"/>
            <ellipse cx="12" cy="40" rx="6" ry="3.5" fill="#0a4a2e" opacity="0.78" transform="rotate(20 12 40)"/>
        </svg>
        <svg class="hero-kelp" style="right:10%;animation:kelp-sway 4s ease-in-out 0.9s infinite;" width="12" height="60" viewBox="0 0 12 60">
            <path d="M6 60 Q2 46 6 35 Q10 24 6 12 Q3 4 6 0" stroke="#0c5535" stroke-width="2.4" fill="none" stroke-linecap="round"/>
        </svg>

        <!-- ── Fish ── -->
        <!-- Orange tropical, right-swim -->
        <svg class="hero-fish swim-right" style="top:28%;animation-duration:16s;animation-delay:0s;" width="46" height="28" viewBox="0 0 46 28">
            <ellipse cx="21" cy="14" rx="17" ry="9" fill="#f97316" opacity="0.90"/>
            <ellipse cx="21" cy="14" rx="11" ry="5.5" fill="#fb923c" opacity="0.55"/>
            <path d="M38 14 Q46 6 44 14 Q46 22 38 14Z" fill="#f97316" opacity="0.85"/>
            <circle cx="7" cy="11.5" r="2.2" fill="#1a1a2e" opacity="0.9"/>
            <circle cx="6.3" cy="10.8" r="0.8" fill="#fff" opacity="0.85"/>
            <line x1="15" y1="6" x2="15" y2="22" stroke="#ea580c" stroke-width="0.8" opacity="0.55"/>
            <line x1="21" y1="5" x2="21" y2="23" stroke="#ea580c" stroke-width="0.8" opacity="0.45"/>
        </svg>

        <!-- Blue, right-swim -->
        <svg class="hero-fish swim-right" style="top:58%;animation-duration:24s;animation-delay:6s;" width="58" height="34" viewBox="0 0 58 34">
            <ellipse cx="27" cy="17" rx="21" ry="11" fill="#1d4ed8" opacity="0.85"/>
            <ellipse cx="23" cy="15" rx="13" ry="7" fill="#3b82f6" opacity="0.45"/>
            <path d="M48 17 Q58 7 55 17 Q58 27 48 17Z" fill="#1d4ed8" opacity="0.80"/>
            <circle cx="9" cy="14" r="2.8" fill="#0f172a" opacity="0.9"/>
            <circle cx="8.2" cy="13.2" r="1" fill="#fff" opacity="0.82"/>
            <line x1="22" y1="7" x2="22" y2="27" stroke="#1e40af" stroke-width="0.9" opacity="0.45"/>
            <line x1="29" y1="6" x2="29" y2="28" stroke="#1e40af" stroke-width="0.9" opacity="0.38"/>
        </svg>

        <!-- Green, left-swim -->
        <svg class="hero-fish swim-left" style="top:40%;animation-duration:20s;animation-delay:10s;" width="40" height="24" viewBox="0 0 40 24">
            <ellipse cx="24" cy="12" rx="15" ry="8" fill="#16a34a" opacity="0.88"/>
            <ellipse cx="26" cy="11" rx="9" ry="4.5" fill="#22c55e" opacity="0.48"/>
            <path d="M9 12 Q0 4 2 12 Q0 20 9 12Z" fill="#16a34a" opacity="0.85"/>
            <circle cx="35" cy="10" r="2" fill="#14532d" opacity="0.95"/>
            <circle cx="35.6" cy="9.4" r="0.7" fill="#fff" opacity="0.80"/>
            <line x1="24" y1="5" x2="24" y2="19" stroke="#15803d" stroke-width="0.8" opacity="0.48"/>
        </svg>

        <!-- Silver school fish (tiny), right-swim -->
        <svg class="hero-fish swim-right" style="top:74%;animation-duration:30s;animation-delay:3s;opacity:0.72;" width="28" height="16" viewBox="0 0 28 16">
            <ellipse cx="14" cy="8" rx="10" ry="5" fill="#94a3b8" opacity="0.82"/>
            <path d="M24 8 Q28 3 27 8 Q28 13 24 8Z" fill="#64748b" opacity="0.80"/>
            <circle cx="5.5" cy="6.5" r="1.5" fill="#1e293b" opacity="0.88"/>
        </svg>
        <!-- Another silver, right-swim, offset -->
        <svg class="hero-fish swim-right" style="top:80%;animation-duration:30s;animation-delay:4.5s;opacity:0.60;" width="22" height="13" viewBox="0 0 22 13">
            <ellipse cx="11" cy="6.5" rx="8" ry="4" fill="#94a3b8" opacity="0.78"/>
            <path d="M19 6.5 Q22 2.5 21 6.5 Q22 10.5 19 6.5Z" fill="#64748b" opacity="0.78"/>
            <circle cx="4" cy="5" r="1.2" fill="#1e293b" opacity="0.88"/>
        </svg>

        <!-- Deep purple fish, left-swim -->
        <svg class="hero-fish swim-left" style="top:18%;animation-duration:28s;animation-delay:14s;opacity:0.78;" width="36" height="22" viewBox="0 0 36 22">
            <ellipse cx="22" cy="11" rx="13" ry="7" fill="#7c3aed" opacity="0.82"/>
            <ellipse cx="24" cy="10" rx="7" ry="3.8" fill="#a78bfa" opacity="0.40"/>
            <path d="M9 11 Q0 4 2 11 Q0 18 9 11Z" fill="#7c3aed" opacity="0.78"/>
            <circle cx="32" cy="9" r="1.8" fill="#1e1b4b" opacity="0.92"/>
            <circle cx="32.5" cy="8.4" r="0.6" fill="#fff" opacity="0.78"/>
        </svg>

        <!-- ── Jellyfish ── -->
        <svg class="hero-jelly" style="right:16%;top:10%;animation-duration:4.8s;animation-delay:0s;opacity:0.72;" width="34" height="46" viewBox="0 0 34 46">
            <ellipse cx="17" cy="14" rx="15" ry="12" fill="rgba(147,210,255,0.18)" stroke="rgba(147,210,255,0.44)" stroke-width="1"/>
            <ellipse cx="17" cy="14" rx="9" ry="7" fill="rgba(147,210,255,0.10)"/>
            <g style="animation:jelly-tent 2.4s ease-in-out infinite">
                <line x1="8"  y1="26" x2="5"  y2="46" stroke="rgba(147,210,255,0.32)" stroke-width="0.8"/>
                <line x1="11" y1="27" x2="9"  y2="46" stroke="rgba(147,210,255,0.28)" stroke-width="0.7"/>
                <line x1="14" y1="27" x2="13" y2="46" stroke="rgba(147,210,255,0.32)" stroke-width="0.8"/>
                <line x1="17" y1="27" x2="18" y2="46" stroke="rgba(147,210,255,0.28)" stroke-width="0.7"/>
                <line x1="20" y1="27" x2="22" y2="46" stroke="rgba(147,210,255,0.32)" stroke-width="0.8"/>
                <line x1="23" y1="26" x2="26" y2="46" stroke="rgba(147,210,255,0.28)" stroke-width="0.7"/>
            </g>
        </svg>
        <svg class="hero-jelly" style="left:38%;top:6%;animation-duration:6s;animation-delay:2.2s;opacity:0.50;" width="22" height="30" viewBox="0 0 22 30">
            <ellipse cx="11" cy="9" rx="10" ry="8" fill="rgba(196,150,255,0.16)" stroke="rgba(196,150,255,0.38)" stroke-width="0.8"/>
            <g style="animation:jelly-tent 3.2s ease-in-out infinite">
                <line x1="5"  y1="17" x2="3"  y2="30" stroke="rgba(196,150,255,0.28)" stroke-width="0.6"/>
                <line x1="8"  y1="17" x2="7"  y2="30" stroke="rgba(196,150,255,0.26)" stroke-width="0.6"/>
                <line x1="11" y1="17" x2="11" y2="30" stroke="rgba(196,150,255,0.28)" stroke-width="0.6"/>
                <line x1="14" y1="17" x2="15" y2="30" stroke="rgba(196,150,255,0.26)" stroke-width="0.6"/>
                <line x1="17" y1="17" x2="19" y2="30" stroke="rgba(196,150,255,0.28)" stroke-width="0.6"/>
            </g>
        </svg>
        <svg class="hero-jelly" style="left:55%;top:55%;animation-duration:5.2s;animation-delay:1s;opacity:0.42;" width="18" height="24" viewBox="0 0 18 24">
            <ellipse cx="9" cy="7" rx="8" ry="6" fill="rgba(147,210,255,0.14)" stroke="rgba(147,210,255,0.32)" stroke-width="0.8"/>
            <g style="animation:jelly-tent 2.8s ease-in-out infinite">
                <line x1="4"  y1="13" x2="2"  y2="24" stroke="rgba(147,210,255,0.25)" stroke-width="0.5"/>
                <line x1="7"  y1="13" x2="6"  y2="24" stroke="rgba(147,210,255,0.22)" stroke-width="0.5"/>
                <line x1="10" y1="13" x2="10" y2="24" stroke="rgba(147,210,255,0.25)" stroke-width="0.5"/>
                <line x1="13" y1="13" x2="14" y2="24" stroke="rgba(147,210,255,0.22)" stroke-width="0.5"/>
            </g>
        </svg>

        <!-- ── Bubbles ── -->
        <div class="hero-bubble" style="width:7px;height:7px;left:14%;bottom:10px;animation-duration:6.2s;animation-delay:0s;"></div>
        <div class="hero-bubble" style="width:4px;height:4px;left:21%;bottom:6px;animation-duration:7.4s;animation-delay:1.1s;"></div>
        <div class="hero-bubble" style="width:9px;height:9px;left:44%;bottom:8px;animation-duration:8.1s;animation-delay:0.4s;"></div>
        <div class="hero-bubble" style="width:5px;height:5px;left:58%;bottom:12px;animation-duration:6.7s;animation-delay:2.2s;"></div>
        <div class="hero-bubble" style="width:3px;height:3px;left:72%;bottom:4px;animation-duration:5.8s;animation-delay:0.7s;"></div>
        <div class="hero-bubble" style="width:8px;height:8px;left:87%;bottom:9px;animation-duration:9.2s;animation-delay:1.6s;"></div>
        <div class="hero-bubble" style="width:4px;height:4px;left:31%;bottom:6px;animation-duration:7.8s;animation-delay:3.1s;"></div>
        <div class="hero-bubble" style="width:6px;height:6px;left:50%;bottom:14px;animation-duration:6s;animation-delay:2.6s;"></div>
        <div class="hero-bubble" style="width:3px;height:3px;left:8%;bottom:8px;animation-duration:7s;animation-delay:1.8s;"></div>

        <!-- ── Plankton particles ── -->
        <div class="hero-particle" style="width:3px;height:3px;top:28%;left:25%;animation-duration:3.2s;animation-delay:0s;"></div>
        <div class="hero-particle" style="width:2px;height:2px;top:48%;left:52%;animation-duration:4.8s;animation-delay:1.1s;"></div>
        <div class="hero-particle" style="width:3px;height:3px;top:18%;left:68%;animation-duration:3.9s;animation-delay:2s;"></div>
        <div class="hero-particle" style="width:2px;height:2px;top:62%;left:38%;animation-duration:5.2s;animation-delay:0.5s;"></div>
        <div class="hero-particle" style="width:2px;height:2px;top:35%;left:80%;animation-duration:4.2s;animation-delay:1.6s;"></div>

        <!-- ── Hero text ── -->
        <div class="hero-inner">
            <div class="hero-badge">
                <span class="dot"></span>
                Calibration Management System
            </div>
            <h1>Built for <span>Precision.</span><br>Designed for People.</h1>
            <p>Streamlining calibration tracking, inspection reports, and sample management — so your team can focus on what matters.</p>
        </div>

    </div><!-- /.about-hero -->


    <!-- ── Purpose ── -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
            </div>
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
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Dashboard overview of calibration schedules
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Calibration status reports
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Inspection tracking with schedule
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Sample &amp; standard sample management
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Sticker printing
                </li>
                <li>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Full audit history
                </li>
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
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.11-.21c1.21.49 2.53.76 3.88.76a1 1 0 011 1v3.5a1 1 0 01-1 1C10.07 21.5 2.5 13.93 2.5 4a1 1 0 011-1H7a1 1 0 011 1c0 1.35.26 2.67.76 3.88a1 1 0 01-.21 1.11l-2.2 2.2z"/></svg>
                    </div>
                    <div class="contact-meta">
                        <span class="contact-label">Phone</span>
                        <span class="contact-value">0916-577-4198</span>
                    </div>
                </a>

                <a class="contact-item" href="mailto:curatchiad@gmail.com" id="emailLink">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                    <div class="contact-meta">
                        <span class="contact-label">Email</span>
                        <span class="contact-value">curatchiad@gmail.com</span>
                    </div>
                </a>

                <a class="contact-item" href="https://www.facebook.com/dariuslanze.curatchia" target="_blank" rel="noopener">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-6.99H7.9v-2.89h2.54V9.8c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34v6.99C18.34 21.12 22 16.99 22 12z"/></svg>
                    </div>
                    <div class="contact-meta">
                        <span class="contact-label">Facebook</span>
                        <span class="contact-value">Darius Lanze Curatchia</span>
                    </div>
                </a>

            </div>
        </div>
    </div>

    <p class="footer-note">Made with ♥ by Darius Lanze Curatchia</p>

</div><!-- /.main-content -->

<script>
/* ═══════════════════════════════
   UTILITIES
═══════════════════════════════ */
function showToast(msg, emoji, duration) {
    emoji    = emoji    || '🎉';
    duration = duration || 3000;
    var t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent   = msg;
    document.getElementById('toastEmoji').textContent = emoji;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(function(){ t.classList.remove('show'); }, duration);
}

/* ═══════════════════════════════
   KONAMI CODE
═══════════════════════════════ */
var KONAMI = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
var konamiIdx = 0;
var hint = document.getElementById('konamiHint');

document.addEventListener('keydown', function (e) {
    if (e.key === KONAMI[konamiIdx]) {
        konamiIdx++;
        hint.classList.add('lit');
        clearTimeout(hint._t);
        hint._t = setTimeout(function(){ hint.classList.remove('lit'); }, 800);
        if (konamiIdx === KONAMI.length) {
            konamiIdx = 0;
            triggerKonami();
        }
    } else {
        konamiIdx = 0;
        hint.classList.remove('lit');
    }
});

function triggerKonami() {
    var overlay = document.getElementById('konamiOverlay');
    var rain    = document.getElementById('konamiRain');
    rain.innerHTML = '';
    var emojis = ['🐠','🐟','🐙','🦑','🐡','🦀','🐚','🌊','🐳','🐬'];
    for (var i = 0; i < 40; i++) {
        var drop = document.createElement('div');
        drop.className = 'konami-drop';
        drop.textContent = emojis[Math.floor(Math.random() * emojis.length)];
        drop.style.cssText =
            'left:' + (Math.random()*100) + '%;' +
            'animation-duration:' + (1.5 + Math.random()*2) + 's;' +
            'animation-delay:' + (Math.random()*1.5) + 's;' +
            'font-size:' + (18 + Math.random()*20) + 'px;';
        rain.appendChild(drop);
    }
    overlay.classList.add('show');
}

document.getElementById('konamiClose').addEventListener('click', function () {
    document.getElementById('konamiOverlay').classList.remove('show');
});

/* ═══════════════════════════════
   EMAIL: copy on click
═══════════════════════════════ */
document.getElementById('emailLink').addEventListener('click', function (e) {
    e.preventDefault();
    navigator.clipboard.writeText('curatchiad@gmail.com').then(function() {
        showToast('Email copied to clipboard!', '📋', 2500);
    }).catch(function() {
        window.location.href = 'mailto:curatchiad@gmail.com';
    });
});
</script>
</body>
</html>