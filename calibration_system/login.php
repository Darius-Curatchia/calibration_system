<?php
session_start();
$error = '';
$info  = '';

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Show friendly message when redirected by idle auto-logout
if (isset($_GET['reason']) && $_GET['reason'] === 'idle') {
    $info = 'You were automatically logged out after 30 minutes of inactivity.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calibration System — Login</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --navy:         #05304f;
        --navy-mid:     #073c64;
        --navy-light:   #0a4a7a;
        --accent:       #1a90d9;
        --accent-dim:   rgba(26,144,217,0.15);
        --accent-glow:  rgba(26,144,217,0.30);
        --border:       #dde4ec;
        --text:         #1c2b3a;
        --text-muted:   #64788a;
        --bg:           #f0f4f8;
        --green:        #16a34a;
        --green-soft:   #dcfce7;
        --green-border: #bbf7d0;
        --red:          #b91c1c;
        --red-soft:     #fde8e8;
        --red-border:   #fca5a5;
        --amber:        #92400e;
        --amber-soft:   #fef3c7;
        --amber-border: #fde68a;
        --ease:         cubic-bezier(0.4, 0, 0.2, 1);
    }

    html, body { height: 100%; font-family: 'DM Sans', sans-serif; }

    body {
        display: flex;
        align-items: stretch;
        min-height: 100vh;
        background: var(--navy);
        overflow: hidden;
    }

    /* ═══════════════════════════════════════════
       LEFT PANEL — Brand / Identity
       ═══════════════════════════════════════════ */
    .brand-panel {
        flex: 0 0 46%;
        background: var(--navy);
        background-image:
            radial-gradient(ellipse at 20% 15%, rgba(26,144,217,0.20) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 85%, rgba(7,60,100,0.55) 0%, transparent 50%),
            radial-gradient(ellipse at 50% 50%, rgba(5,48,79,0.30) 0%, transparent 80%);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 48px 44px;
        position: relative;
        overflow: hidden;
        animation: panelIn 0.7s var(--ease) both;
    }

    @keyframes panelIn {
        from { opacity: 0; transform: translateX(-20px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .brand-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
        background-size: 44px 44px;
        pointer-events: none;
    }

    .brand-panel::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -1px;
        width: 3px;
        height: calc(100% + 120px);
        background: linear-gradient(
            to bottom,
            transparent 0%,
            var(--accent) 25%,
            var(--accent) 75%,
            transparent 100%
        );
        opacity: 0.6;
    }

    .brand-top {
        position: relative;
        z-index: 1;
        animation: brandIn 0.65s var(--ease) both 0.15s;
    }

    @keyframes brandIn {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .brand-icon { position: relative; width: 44px; height: 44px; margin-bottom: 22px; }
    .brand-icon::before {
        content: ''; position: absolute; inset: 0;
        background: var(--accent); border-radius: 12px;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.12), 0 6px 20px rgba(26,144,217,0.45);
    }
    .brand-icon::after {
        content: ''; position: absolute; inset: 7px;
        border: 2px solid rgba(255,255,255,0.45); border-radius: 6px;
    }
    .brand-icon svg {
        position: relative; z-index: 1;
        width: 20px; height: 20px;
        fill: none; stroke: #fff; stroke-width: 2;
        stroke-linecap: round; stroke-linejoin: round;
        display: block; margin: 12px auto 0;
    }

    .brand-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 2px; color: var(--accent); margin-bottom: 10px;
        font-family: 'DM Mono', monospace;
    }
    .brand-title {
        font-size: 26px; font-weight: 700; color: #fff;
        line-height: 1.25; letter-spacing: -0.3px; margin-bottom: 14px;
    }
    .brand-desc {
        font-size: 13px; font-weight: 400;
        color: rgba(255,255,255,0.48); line-height: 1.65; max-width: 280px;
    }

    .brand-features {
        position: relative; z-index: 1;
        display: flex; flex-direction: column; gap: 12px;
        margin-top: 36px; animation: brandIn 0.65s var(--ease) both 0.28s;
    }
    .brand-feature { display: flex; align-items: center; gap: 11px; }
    .brand-feature-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: var(--accent); flex-shrink: 0; opacity: 0.75;
    }
    .brand-feature span {
        font-size: 12.5px; color: rgba(255,255,255,0.55);
        font-weight: 500; letter-spacing: 0.1px;
    }

    .brand-bottom {
        position: relative; z-index: 1;
        animation: brandIn 0.65s var(--ease) both 0.38s;
    }
    .brand-logo-img {
        max-width: 130px; height: auto; display: block;
        opacity: 0.70; filter: brightness(0) invert(1); margin-bottom: 10px;
    }
    .brand-footer-text {
        font-size: 10.5px; color: rgba(255,255,255,0.28);
        font-family: 'DM Mono', monospace; letter-spacing: 0.3px;
    }

    /* ═══════════════════════════════════════════
       RIGHT PANEL — Form
       ═══════════════════════════════════════════ */
    .form-panel {
        flex: 1; background: #f0f4f8;
        display: flex; align-items: center; justify-content: center;
        padding: 40px 32px;
        animation: formIn 0.65s var(--ease) both 0.1s;
        position: relative;
    }

    @keyframes formIn {
        from { opacity: 0; transform: translateX(20px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .form-panel::before {
        content: ''; position: absolute;
        left: 0; top: 0; bottom: 0; width: 30px;
        background: linear-gradient(to right, rgba(5,48,79,0.06), transparent);
        pointer-events: none;
    }

    .form-card {
        width: 100%; max-width: 380px;
        animation: cardIn 0.55s cubic-bezier(0.34,1.1,0.64,1) both 0.22s;
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(16px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .form-header { margin-bottom: 24px; }
    .form-eyebrow {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1.8px; color: var(--accent);
        font-family: 'DM Mono', monospace; margin-bottom: 7px;
    }
    .form-title {
        font-size: 22px; font-weight: 700; color: var(--navy);
        letter-spacing: -0.2px; line-height: 1.2; margin-bottom: 6px;
    }
    .form-subtitle {
        font-size: 13px; color: var(--text-muted);
        font-weight: 400; line-height: 1.5;
    }

    /* ── Banners (shared base + variants) ── */
    .error-banner,
    .info-banner {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        border-radius: 9px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 22px;
        line-height: 1.5;
    }
    .error-banner {
        background: var(--red-soft);
        border: 1px solid var(--red-border);
        color: var(--red);
        animation: shake 0.4s ease;
    }
    /* ── Amber banner for idle timeout message ── */
    .info-banner {
        background: var(--amber-soft);
        border: 1px solid var(--amber-border);
        color: var(--amber);
    }
    .error-banner svg,
    .info-banner svg { flex-shrink: 0; margin-top: 1px; }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%       { transform: translateX(-5px); }
        40%       { transform: translateX(5px); }
        60%       { transform: translateX(-3px); }
        80%       { transform: translateX(3px); }
    }

    /* ── Input group ── */
    .input-group { position: relative; margin-bottom: 16px; }
    .input-label {
        display: block; font-size: 10.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.7px;
        color: var(--text-muted); margin-bottom: 6px;
        font-family: 'DM Mono', monospace;
    }
    .input-wrap { position: relative; }
    .input-wrap input {
        width: 100%; padding: 11px 14px 11px 42px;
        border: 1.5px solid var(--border); border-radius: 9px;
        font-size: 13.5px; font-family: 'DM Sans', sans-serif;
        color: var(--text); background: #fff; outline: none;
        transition: border-color 0.16s var(--ease), box-shadow 0.16s var(--ease);
    }
    /* Remove browser-native password reveal eye button */
    .input-wrap input[type="password"]::-ms-reveal,
    .input-wrap input[type="password"]::-ms-clear,
    .input-wrap input[type="password"]::-webkit-credentials-auto-fill-button,
    .input-wrap input[type="password"]::-webkit-textfield-decoration-container { display: none; }
    /* Extra right padding so typed text doesn't slide under the SHOW/HIDE label */
    #password { padding-right: 58px; }
    .input-wrap input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(26,144,217,0.12);
    }
    .input-wrap input::placeholder { color: #b0bcc8; font-size: 13px; }
    .input-icon {
        position: absolute; left: 13px; top: 50%;
        transform: translateY(-50%); width: 17px; height: 17px;
        fill: #b0bcc8; pointer-events: none;
        transition: fill 0.15s var(--ease);
    }
    .input-wrap:focus-within .input-icon { fill: var(--accent); }
    .toggle-pw {
        position: absolute; right: 13px; top: 50%;
        transform: translateY(-50%); font-size: 11px; font-weight: 700;
        font-family: 'DM Mono', monospace; color: var(--accent);
        cursor: pointer; user-select: none; letter-spacing: 0.3px;
        transition: color 0.14s ease, opacity 0.14s ease; opacity: 0.8;
    }
    .toggle-pw:hover { opacity: 1; }

    /* ── Divider ── */
    .form-divider { display: flex; align-items: center; gap: 10px; margin: 20px 0; }
    .form-divider::before,
    .form-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .form-divider span {
        font-size: 10.5px; font-weight: 700; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.8px;
        font-family: 'DM Mono', monospace;
    }

    /* ── Buttons ── */
    .btn {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 11.5px 16px; border-radius: 9px;
        font-size: 13.5px; font-weight: 700; font-family: 'DM Sans', sans-serif;
        cursor: pointer; border: none; letter-spacing: 0.15px;
        transition: background 0.16s var(--ease), box-shadow 0.16s var(--ease), transform 0.10s var(--ease);
    }
    .btn:hover  { transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn-login {
        background: var(--navy); color: #fff;
        box-shadow: 0 3px 14px rgba(5,48,79,0.28);
    }
    .btn-login:hover { background: var(--navy-light); box-shadow: 0 6px 20px rgba(5,48,79,0.36); }
    .btn-guest {
        background: #fff; color: var(--green);
        border: 1.5px solid var(--green-border);
        box-shadow: 0 2px 8px rgba(22,163,74,0.08);
    }
    .btn-guest:hover { background: var(--green-soft); box-shadow: 0 4px 12px rgba(22,163,74,0.16); }

    .form-foot {
        margin-top: 28px; text-align: center; font-size: 11px;
        color: var(--text-muted); font-family: 'DM Mono', monospace; letter-spacing: 0.2px;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        body { flex-direction: column; overflow: auto; }
        .brand-panel { flex: 0 0 auto; padding: 36px 28px 32px; min-height: auto; }
        .brand-panel::after { display: none; }
        .brand-features { display: none; }
        .brand-title    { font-size: 20px; }
        .brand-desc     { display: none; }
        .brand-bottom   { display: none; }
        .form-panel     { flex: 1; padding: 32px 20px 40px; }
        .form-panel::before { display: none; }
    }
    @media (max-width: 420px) {
        .brand-panel { padding: 28px 20px 24px; }
        .form-card   { max-width: 100%; }
    }
    </style>
</head>
<body>

<!-- ══ LEFT: Brand panel ══ -->
<div class="brand-panel">

    <div class="brand-top">
        <div class="brand-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        </div>

        <div class="brand-label">Production Engineering Section</div>
        <h1 class="brand-title">Calibration<br>Management<br>System</h1>
        <p class="brand-desc">Centralized tracking and reporting for all equipment calibration records and statuses.</p>

        <div class="brand-features">
            <div class="brand-feature">
                <div class="brand-feature-dot"></div>
                <span>Calibration Status Reports</span>
            </div>
            <div class="brand-feature">
                <div class="brand-feature-dot"></div>
                <span>Inspection &amp; Sample Tracking</span>
            </div>
            <div class="brand-feature">
                <div class="brand-feature-dot"></div>
                <span>Exclusion-Inclusion Summaries</span>
            </div>
            <div class="brand-feature">
                <div class="brand-feature-dot"></div>
                <span>Sticker Printing &amp; Records</span>
            </div>
        </div>
    </div>

    <div class="brand-bottom">
        <img src="images/shindengen-logo2.png" alt="Company Logo" class="brand-logo-img">
        <div class="brand-footer-text">&copy; <?= date('Y') ?> Shindengen Philippines</div>
    </div>

</div>

<!-- ══ RIGHT: Form panel ══ -->
<div class="form-panel">
    <div class="form-card">

        <div class="form-header">
            <div class="form-eyebrow">Secure Access</div>
            <h2 class="form-title">Sign in to continue</h2>
            <p class="form-subtitle">Enter your credentials to access the system.</p>
        </div>

        <!-- Idle timeout info banner — only shown when redirected with ?reason=idle -->
        <?php if ($info): ?>
        <div class="info-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#92400e">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <?= htmlspecialchars($info) ?>
        </div>
        <?php endif; ?>

        <!-- Error banner -->
        <?php if ($error): ?>
        <div class="error-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#b91c1c">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" action="login_process.php">

            <div class="input-group">
                <label class="input-label" for="username">Username</label>
                <div class="input-wrap">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
                    </svg>
                    <input type="text"
                           name="username"
                           id="username"
                           placeholder="Enter your username"
                           required
                           autocomplete="off">
                </div>
            </div>

            <div class="input-group">
                <label class="input-label" for="password">Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
                    </svg>
                    <input type="password"
                           name="password"
                           id="password"
                           placeholder="Enter your password"
                           required
                           autocomplete="current-password">
                    <span class="toggle-pw" id="toggleLabel" onclick="togglePassword()">SHOW</span>
                </div>
            </div>

            <button type="submit" class="btn btn-login" style="margin-top: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>
                </svg>
                Login
            </button>
        </form>

        <!-- Divider -->
        <div class="form-divider"><span>or</span></div>

        <!-- Guest form -->
        <form method="POST" action="login_process.php">
            <input type="hidden" name="guest_login" value="1">
            <button type="submit" class="btn btn-guest">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
                Continue as Guest
            </button>
        </form>

        <div class="form-foot">&copy; <?= date('Y') ?> Calibration Management System</div>

    </div>
</div>

<script>
function togglePassword() {
    var f = document.getElementById('password');
    var l = document.getElementById('toggleLabel');
    if (f.type === 'password') {
        f.type = 'text';
        l.textContent = 'HIDE';
    } else {
        f.type = 'password';
        l.textContent = 'SHOW';
    }
}
</script>

</body>
</html>