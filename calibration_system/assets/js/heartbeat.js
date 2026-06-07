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
    const IDLE_TIMEOUT_MS  = 30 * 60 * 1000;  // 30 minutes total idle time
    const WARN_BEFORE_MS   =  5 * 60 * 1000;  // show warning 5 min before logout
    const WARN_AT_MS       = IDLE_TIMEOUT_MS - WARN_BEFORE_MS; // 25 minutes
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
            'display:flex', 'align-items:center', 'justify-content:center',
        ].join(';');

        overlay.innerHTML = `
            <style>
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
                    transition:background .15s;
                }
                #idleStayBtn:hover { background:#07406e; }
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
        if (overlay && overlay.parentNode) overlay.remove();
    }

    // ── Auto-logout ───────────────────────────────────────────────────────────
    function doAutoLogout() {
        if (logoutFired) return;
        logoutFired = true;

        clearInterval(pingInterval);
        clearInterval(idleCheckInterval);
        clearInterval(countdownTimer);

        const el = document.getElementById('idleCountdown');
        if (el) el.textContent = '0:00';

        const fd = new FormData();
        fd.append('action', 'auto_logout');

        fetch(ENDPOINT_LOGOUT, { method: 'POST', body: fd, keepalive: true })
            .finally(() => {
                window.location.href = 'login.php?reason=idle';
            });
    }

    // ── Force-close beacon ────────────────────────────────────────────────────
    //
    // The fundamental problem: beforeunload fires for BOTH tab close AND reload/navigation.
    // No browser API tells you synchronously which one is happening.
    //
    // Solution — sessionStorage handshake:
    //   - beforeunload always writes sessionStorage._unloading = timestamp
    //   - On the NEXT page load (DOMContentLoaded already fired, we're in an IIFE),
    //     check if _unloading exists:
    //       • If YES → the previous unload was a reload/navigation (page came back)
    //         → the force_close beacon should NOT have been sent (or we can ignore it
    //         server-side with the 5s dedup already in heartbeat.php)
    //       • If NO  → this is a fresh session start, no previous unload
    //   - In beforeunload:
    //       1. If logoutFired or recent tracked navigation → skip beacon
    //       2. Write sessionStorage._unloading = Date.now()
    //       3. Send beacon — server's 5s dedup prevents double-logging anyway
    //       4. On next load, if sessionStorage._unloading exists → it was a
    //          reload/nav (not a close), so cancel via a "cancel" ping
    //
    // But sending a cancel is complex. Simpler:
    //   - Only skip the beacon if we KNOW it's a reload/navigation via tracked events.
    //   - Accept that the ↻ button in browsers is undetectable without the handshake.
    //   - Use the handshake: set a flag in beforeunload; if the page reloads, the
    //     flag is still in sessionStorage on the next load → send a "cancel" request.
    //
    // PRACTICAL APPROACH (what actually works):
    //   On page load: if sessionStorage._pendingClose exists, this is a reload/nav
    //   (the page survived) → send a cancel to heartbeat.php to undo the force_close.
    //   Then clear the flag. If the tab was truly closed, sessionStorage is gone.

    let lastNavTimestamp = 0;

    function markNavigation() {
        lastNavTimestamp = Date.now();
    }

    // Check on load: if a pending close flag exists from last unload,
    // this page reloaded/navigated — send a cancel to undo any force_close logged.
    (function checkPendingClose() {
        try {
            if (sessionStorage.getItem('_pendingClose')) {
                sessionStorage.removeItem('_pendingClose');
                // Cancel the force_close that was sent on the previous unload
                const fd = new FormData();
                fd.append('action', 'cancel_force_close');
                fetch(ENDPOINT_HB, { method: 'POST', body: fd, keepalive: true }).catch(() => {});
            }
        } catch(e) {}
    })();

    // Keyboard reloads
    document.addEventListener('keydown', function (e) {
        if (
            e.key === 'F5' ||
            (e.ctrlKey && (e.key === 'r' || e.key === 'R')) ||
            (e.ctrlKey && e.shiftKey && (e.key === 'r' || e.key === 'R'))
        ) {
            markNavigation();
        }
        if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            markNavigation();
        }
    }, true);

    // Link clicks
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (a) {
            const href   = a.getAttribute('href') || '';
            const target = a.getAttribute('target') || '';
            if (href && !href.startsWith('#') && target !== '_blank') {
                markNavigation();
            }
        }
    }, true);

    // Form submissions
    document.addEventListener('submit', function () {
        markNavigation();
    }, true);

    window.addEventListener('beforeunload', function () {
        clearInterval(pingInterval);

        if (logoutFired) return;

        // Skip immediately if a tracked navigation/reload action happened recently
        if ((Date.now() - lastNavTimestamp) < 2000) return;

        // Set the handshake flag — if the page comes back (reload/nav),
        // the next load will find this and send a cancel.
        try { sessionStorage.setItem('_pendingClose', '1'); } catch(e) {}

        // Send the force_close beacon
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