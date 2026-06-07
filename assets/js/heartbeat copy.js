/**
 * assets/js/heartbeat.js
 *
 * Three jobs:
 *   1. Ping server every 60 s — keeps last_seen fresh for stale-session detection
 *   2. Idle timeout — 30 min of no activity triggers auto-logout
 *   3. Warning modal — appears 5 min before logout with a live countdown
 *
 * Depends on:
 *   heartbeat.php   — action=ping, action=force_close
 *   auto_logout.php — action=auto_logout
 */
(function () {
    'use strict';

// ── Config ────────────────────────────────────────────────────────────────
const IDLE_TIMEOUT_MS  = 20 * 1000;   // 20 seconds total (normally 30 * 60 * 1000)
const WARN_BEFORE_MS   = 10 * 1000;   // warn 10 seconds before (normally 5 * 60 * 1000)
const WARN_AT_MS       = IDLE_TIMEOUT_MS - WARN_BEFORE_MS; // 10 seconds
    const PING_INTERVAL_MS = 60 * 1000;        // heartbeat ping every 60 s
    const ENDPOINT_HB      = 'heartbeat.php';
    const ENDPOINT_LOGOUT  = 'auto_logout.php';

    // ── State ─────────────────────────────────────────────────────────────────
    let idleStart      = Date.now();
    let warnShown      = false;
    let logoutFired    = false;
    let countdownTimer = null;

    // ── 1. Heartbeat ping ─────────────────────────────────────────────────────
    function sendPing() {
        const fd = new FormData();
        fd.append('action', 'ping');
        fetch(ENDPOINT_HB, { method: 'POST', body: fd, keepalive: true }).catch(() => {});
    }
    sendPing();
    const pingInterval = setInterval(sendPing, PING_INTERVAL_MS);

    // ── 2. Activity detection — resets the idle clock ─────────────────────────
    // mousemove included — if the cursor is moving, the user is at their desk.
    // 30 minutes is long enough that ghost events are not a concern.
    const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
    function onActivity() {
        if (logoutFired) return;
        idleStart = Date.now();
        if (warnShown) dismissWarning();
    }
    ACTIVITY_EVENTS.forEach(ev => document.addEventListener(ev, onActivity, { passive: true }));

    // ── Idle check loop (runs every second) ───────────────────────────────────
    const idleCheckInterval = setInterval(() => {
        if (logoutFired) return;
        const idle = Date.now() - idleStart;
        if (idle >= IDLE_TIMEOUT_MS) {
            doAutoLogout();
        } else if (idle >= WARN_AT_MS && !warnShown) {
            showWarning();
        }
    }, 1000);

    // ── 3. Warning modal ──────────────────────────────────────────────────────
    function buildModal() {
        if (document.getElementById('idleWarningOverlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'idleWarningOverlay';
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:99999',
            'background:rgba(5,48,79,0.55)',
            'backdrop-filter:blur(4px)',
            'display:flex', 'align-items:center', 'justify-content:center',
            'animation:idleFadeIn .25s ease both',
        ].join(';');

        overlay.innerHTML = `
            <style>
                @keyframes idleFadeIn  { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
                @keyframes idleFadeOut { from{opacity:1;transform:scale(1)}   to{opacity:0;transform:scale(.95)} }
                #idleWarningBox {
                    background:#fff;
                    border-radius:16px;
                    box-shadow:0 24px 64px rgba(5,48,79,0.28);
                    width:92%; max-width:400px;
                    padding:32px 28px 24px;
                    text-align:center;
                    font-family:'DM Sans',sans-serif;
                }
                #idleWarningBox .iw-icon { font-size:40px; margin-bottom:12px; }
                #idleWarningBox h3 {
                    font-size:17px; font-weight:700; color:#05304f; margin:0 0 8px;
                }
                #idleWarningBox p {
                    font-size:13px; color:#64788a; margin:0 0 6px; line-height:1.55;
                }
                #idleCountdown {
                    font-size:38px; font-weight:700; color:#c2410c;
                    font-family:'DM Mono',monospace;
                    margin:14px 0 22px; letter-spacing:2px;
                }
                #idleStayBtn {
                    display:inline-flex; align-items:center; justify-content:center; gap:8px;
                    width:100%; padding:11px 16px; border-radius:9px;
                    background:#05304f; color:#fff;
                    font-size:14px; font-weight:700; font-family:'DM Sans',sans-serif;
                    border:none; cursor:pointer;
                    transition:background .15s, transform .1s;
                }
                #idleStayBtn:hover  { background:#07406e; transform:translateY(-1px); }
                #idleStayBtn:active { transform:translateY(0); }
                #idleLogoutNowBtn {
                    display:inline-block; margin-top:12px;
                    font-size:12px; color:#94a3b8;
                    background:none; border:none; cursor:pointer;
                    font-family:'DM Sans',sans-serif; text-decoration:underline;
                }
                #idleLogoutNowBtn:hover { color:#64788a; }
            </style>
            <div id="idleWarningBox">
                <div class="iw-icon">⏱️</div>
                <h3>Still there?</h3>
                <p>You've been inactive for a while.<br>You'll be automatically logged out in:</p>
                <div id="idleCountdown">5:00</div>
                <button id="idleStayBtn">✓ Stay Logged In</button>
                <br>
                <button id="idleLogoutNowBtn">Log out now</button>
            </div>
        `;

        document.body.appendChild(overlay);
        document.getElementById('idleStayBtn').addEventListener('click', onActivity);
        document.getElementById('idleLogoutNowBtn').addEventListener('click', doAutoLogout);
    }

    function showWarning() {
        warnShown = true;
        buildModal();
        const overlay = document.getElementById('idleWarningOverlay');
        if (overlay) overlay.style.display = 'flex';

        // Live countdown ticker (updates every 500 ms for smoothness)
        countdownTimer = setInterval(() => {
            const remaining = Math.max(0, IDLE_TIMEOUT_MS - (Date.now() - idleStart));
            const mins = Math.floor(remaining / 60000);
            const secs = Math.floor((remaining % 60000) / 1000);
            const el   = document.getElementById('idleCountdown');
            if (el) el.textContent = `${mins}:${String(secs).padStart(2, '0')}`;
            if (remaining <= 0) clearInterval(countdownTimer);
        }, 500);
    }

    function dismissWarning() {
        warnShown = false;
        clearInterval(countdownTimer);
        const overlay = document.getElementById('idleWarningOverlay');
        if (!overlay) return;
        overlay.style.animation = 'idleFadeOut .2s ease both';
        setTimeout(() => { if (overlay.parentNode) overlay.remove(); }, 220);
    }

    // ── Auto-logout ───────────────────────────────────────────────────────────
    function doAutoLogout() {
        if (logoutFired) return;
        logoutFired = true;

        clearInterval(pingInterval);
        clearInterval(idleCheckInterval);
        clearInterval(countdownTimer);

        // Snap countdown to 0:00 so user sees final state before redirect
        const el = document.getElementById('idleCountdown');
        if (el) el.textContent = '0:00';

        const fd = new FormData();
        fd.append('action', 'auto_logout');

        // keepalive ensures the POST completes even as we navigate away
        fetch(ENDPOINT_LOGOUT, { method: 'POST', body: fd, keepalive: true })
            .finally(() => {
                window.location.href = 'login.php?reason=idle';
            });
    }

    // ── Force-close beacon (window closed without any logout) ─────────────────
    let isNormalNavigation = false;

    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (a && !a.getAttribute('href').startsWith('#')) isNormalNavigation = true;
    }, true);

    document.addEventListener('submit', function () {
        isNormalNavigation = true;
    }, true);

    window.addEventListener('pagehide', function (e) {
        if (!e.persisted) isNormalNavigation = true;
    });

    window.addEventListener('beforeunload', function () {
        clearInterval(pingInterval);
        if (isNormalNavigation || logoutFired) return;

        const fd = new FormData();
        fd.append('action', 'force_close');
        if (navigator.sendBeacon) {
            navigator.sendBeacon(ENDPOINT_HB, fd);
        } else {
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', ENDPOINT_HB, false);
                xhr.send(fd);
            } catch (err) {}
        }
    });

}());