<?php
// header.php
// Load current user's display_name and avatar from DB
if (!isset($pdo)) include_once __DIR__ . '/../db.php';

$_hUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $hStmt = $pdo->prepare("SELECT username, display_name, avatar FROM users WHERE id = ?");
        $hStmt->execute([$_SESSION['user_id']]);
        $_hUser = $hStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* columns may not exist yet */ }
}

$_hUsername    = $_hUser['username']     ?? ($_SESSION['username'] ?? 'User');
$_hDisplayName = !empty($_hUser['display_name']) ? $_hUser['display_name'] : $_hUsername;
$_hAvatar      = $_hUser['avatar']       ?? null;
$_hInitial     = strtoupper(substr($_hDisplayName, 0, 1));
$_hHasPhoto    = $_hAvatar && file_exists($_hAvatar);
$_hIsGuest     = ($_SESSION['role'] ?? '') === 'guest';
$_hRole        = $_SESSION['role'] ?? 'user';
$_hRoleLabel   = ucwords(str_replace('_', ' ', $_hRole));
?>

<div class="header" id="header">

    <!-- ── Left: logo + divider + system title + clock ── -->
    <div class="header-left">
        <img src="images/shindengen-logo.png" alt="Shindengen logo" class="header-logo">
        <div class="header-vline" aria-hidden="true"></div>
        <div class="header-identity">
            <span class="header-sys-title">Calibration Management System</span>
            <div class="header-clock-wrap" aria-label="Current date and time">
                <span class="header-clock-dot" aria-hidden="true"></span>
                <time id="headerClock" class="header-clock" aria-live="off"></time>
            </div>
        </div>
    </div>

    <!-- ── Right: role tag + user dropdown ── -->
    <div class="header-right">

        <!-- Session role indicator -->
        <div class="header-role-tag" data-role="<?= htmlspecialchars($_hRole) ?>" aria-label="Current role: <?= htmlspecialchars($_hRoleLabel) ?>">
            <span class="header-role-dot" aria-hidden="true"></span>
            <span class="header-role-label"><?= htmlspecialchars($_hRoleLabel) ?></span>
        </div>

        <!-- User trigger -->
        <div class="header-user"
             id="headerUserBtn"
             role="button"
             tabindex="0"
             aria-expanded="false"
             aria-haspopup="menu"
             aria-controls="headerDropdown">

            <div class="header-user-avatar" id="headerAvatar">
                <?php if ($_hHasPhoto): ?>
                    <img src="<?= htmlspecialchars($_hAvatar) ?>" alt="<?= htmlspecialchars($_hDisplayName) ?>'s avatar">
                <?php else: ?>
                    <?= htmlspecialchars($_hInitial) ?>
                <?php endif; ?>
                <span class="header-avatar-ring" aria-label="Online" title="Online"></span>
            </div>

            <div class="header-user-meta">
                <span class="header-user-label">Logged in as</span>
                <span class="header-user-name"><?= htmlspecialchars($_hDisplayName) ?></span>
            </div>

            <svg class="header-chevron" id="headerChevron" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>

        <!-- ── Dropdown menu ── -->
        <div class="header-dropdown" id="headerDropdown" role="menu" aria-labelledby="headerUserBtn">

            <!-- Profile summary -->
            <div class="hd-profile" role="presentation">
                <div class="hd-avatar" aria-hidden="true">
                    <?php if ($_hHasPhoto): ?>
                        <img src="<?= htmlspecialchars($_hAvatar) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($_hInitial) ?>
                    <?php endif; ?>
                </div>
                <div class="hd-profile-info">
                    <span class="hd-name"><?= htmlspecialchars($_hDisplayName) ?></span>
                    <span class="hd-username">@<?= htmlspecialchars($_hUsername) ?></span>
                    <span class="hd-role-badge <?= htmlspecialchars($_hRole) ?>"><?= htmlspecialchars($_hRoleLabel) ?></span>
                </div>
            </div>

            <div class="hd-divider" role="separator"></div>

            <?php if (!$_hIsGuest): ?>
            <a href="profile.php" class="hd-item" role="menuitem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Edit Profile
            </a>

            <a href="profile.php#change-password" class="hd-item" role="menuitem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Change Password
            </a>

            <div class="hd-divider" role="separator"></div>
            <?php endif; ?>

            <button type="button"
                    class="hd-item hd-item-danger"
                    role="menuitem"
                    id="headerLogoutBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Log Out
            </button>
        </div>
    </div>

</div>

<script>
(function () {
    /* ── Clock ──────────────────────────────────────────────────────────── */
    var clockEl = document.getElementById('headerClock');

    function tick() {
        if (!clockEl) return;
        var now  = new Date();
        var h    = now.getHours();
        var m    = String(now.getMinutes()).padStart(2, '0');
        var s    = String(now.getSeconds()).padStart(2, '0');
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var date = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var full = date + '  \u2009' + h + ':' + m + ':' + s + '\u202F' + ampm;
        clockEl.textContent = full;
        /* Update <time> datetime attribute for semantics */
        clockEl.setAttribute('datetime', now.toISOString());
    }
    tick();
    setInterval(tick, 1000);

    /* ── Dropdown ────────────────────────────────────────────────────────── */
    var btn      = document.getElementById('headerUserBtn');
    var dd       = document.getElementById('headerDropdown');
    var logoutBtn = document.getElementById('headerLogoutBtn');

    function openDropdown() {
        dd.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
        /* Focus first focusable item */
        var first = dd.querySelector('a.hd-item, button.hd-item');
        if (first) setTimeout(function () { first.focus(); }, 30);
    }

    function closeDropdown() {
        dd.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    }

    function isOpen() {
        return dd.classList.contains('open');
    }

    /* Click trigger */
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        isOpen() ? closeDropdown() : openDropdown();
    });

    /* Keyboard trigger (Enter / Space on the button div) */
    btn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            isOpen() ? closeDropdown() : openDropdown();
        }
        if (e.key === 'ArrowDown' && !isOpen()) {
            e.preventDefault();
            openDropdown();
        }
    });

    /* Arrow key navigation within dropdown */
    dd.addEventListener('keydown', function (e) {
        var items = Array.from(dd.querySelectorAll('a.hd-item, button.hd-item'));
        var idx   = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[(idx + 1) % items.length].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[(idx - 1 + items.length) % items.length].focus();
        } else if (e.key === 'Tab') {
            /* Trap focus inside */
            var first = items[0];
            var last  = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
            btn.focus();
        }
    });

    /* Close on outside click */
    document.addEventListener('click', function () {
        if (isOpen()) closeDropdown();
    });

    /* Prevent inside clicks from bubbling to document */
    dd.addEventListener('click', function (e) { e.stopPropagation(); });

    /* Logout — open the sidebar's logout modal */
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            closeDropdown();
            var modal  = document.getElementById('logoutModal');
            var cancel = document.getElementById('logoutCancel');
            if (modal) {
                modal.classList.add('open');
                if (cancel) setTimeout(function () { cancel.focus(); }, 50);
            }
        });
    }
})();
</script>

<?php
// ── Heartbeat: only for real logged-in users, not guests ─────────────────────
if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] !== 0 && ($_SESSION['role'] ?? '') !== 'guest'):
?>
<script src="assets/js/heartbeat.js"></script>
<?php endif; ?>