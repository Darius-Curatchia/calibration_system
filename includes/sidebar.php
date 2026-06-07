<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">

    <!-- ── Header ── -->
    <div class="sidebar-header">
        <div class="sidebar-logo-wrap">
            <div class="sidebar-logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-brand-name">Calibration</span>
                <span class="sidebar-brand-sub">Management</span>
            </div>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarToggle" aria-label="Collapse Sidebar" title="Collapse">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M15 6l-6 6 6 6"/>
            </svg>
        </button>
    </div>

    <!-- ── Section label ── -->
    <div class="sidebar-section-label">
        <span>Navigation</span>
    </div>

    <!-- ── Main nav ── -->
    <nav class="sidebar-nav" aria-label="Main navigation">

        <a href="dashboard.php"
           class="sidebar-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>"
           data-label="Dashboard"
           <?= ($current_page == 'dashboard.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M3 13h8V3H3v10zm10 8h8v-6h-8v6zm0-8h8V3h-8v10zm-10 8h8v-6H3v6z"/>
                </svg>
            </span>
            <span class="sidebar-label">Dashboard</span>
        </a>

        <a href="calibration_report.php"
           class="sidebar-link <?= ($current_page == 'calibration_report.php') ? 'active' : '' ?>"
           data-label="Calibration Status Report"
           <?= ($current_page == 'calibration_report.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                </svg>
            </span>
            <span class="sidebar-label">Calibration Status Report</span>
        </a>

        <a href="inspection.php"
           class="sidebar-link <?= ($current_page == 'inspection.php') ? 'active' : '' ?>"
           data-label="Inspection"
           <?= ($current_page == 'inspection.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19z"/>
                </svg>
            </span>
            <span class="sidebar-label">Inspection</span>
        </a>

        <a href="samples.php"
           class="sidebar-link <?= ($current_page == 'samples.php') ? 'active' : '' ?>"
           data-label="List of Samples"
           <?= ($current_page == 'samples.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/>
                </svg>
            </span>
            <span class="sidebar-label">List of Samples</span>
        </a>

        <a href="standard_samples.php"
           class="sidebar-link <?= ($current_page == 'standard_samples.php') ? 'active' : '' ?>"
           data-label="List of Standard Samples"
           <?= ($current_page == 'standard_samples.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/>
                </svg>
            </span>
            <span class="sidebar-label">List of Standard Samples</span>
        </a>

        <a href="exclusion_summary.php"
           class="sidebar-link <?= ($current_page == 'exclusion_summary.php') ? 'active' : '' ?>"
           data-label="Exclusion-Inclusion Summary"
           <?= ($current_page == 'exclusion_summary.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>
                </svg>
            </span>
            <span class="sidebar-label">Exclusion-Inclusion Summary</span>
        </a>

        <a href="temp_humidity.php"
           class="sidebar-link <?= ($current_page == 'temp_humidity.php') ? 'active' : '' ?>"
           data-label="Temp &amp; Humidity"
           <?= ($current_page == 'temp_humidity.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>
                </svg>
            </span>
            <span class="sidebar-label">Temp &amp; Humidity</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>

        <!-- ── Super-Admin section label ── -->
        <div class="sidebar-section-label sidebar-section-label--admin">
            <span>Admin</span>
        </div>

        <a href="account_monitoring.php"
           class="sidebar-link <?= ($current_page == 'account_monitoring.php') ? 'active' : '' ?>"
           data-label="Account Monitoring"
           <?= ($current_page == 'account_monitoring.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
            </span>
            <span class="sidebar-label">Account Monitoring</span>
        </a>

        <a href="audit_trail.php"
           class="sidebar-link <?= ($current_page == 'audit_trail.php') ? 'active' : '' ?>"
           data-label="Audit Trail"
           <?= ($current_page == 'audit_trail.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                </svg>
            </span>
            <span class="sidebar-label">Audit Trail</span>
        </a>

        <?php endif; ?>

    </nav>

    <!-- ── Footer ── -->
    <div class="sidebar-footer">
        <div class="sidebar-footer-divider"></div>

        <a href="about.php"
           class="sidebar-link sidebar-link--about <?= ($current_page == 'about.php') ? 'active' : '' ?>"
           data-label="About"
           <?= ($current_page == 'about.php') ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z"/>
                </svg>
            </span>
            <span class="sidebar-label">About</span>
        </a>

        <button class="sidebar-link sidebar-link--logout"
                id="logoutTrigger"
                data-label="Logout"
                type="button"
                aria-haspopup="dialog">
            <span class="sidebar-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3H8c-1.1 0-2 .9-2 2v4h2V5h12v14H8v-4H6v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                </svg>
            </span>
            <span class="sidebar-label">Logout</span>
        </button>

    </div>

</div>

<!-- ── Logout Modal ── -->
<div id="logoutModal" class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
    <div class="logout-modal-card">
        <div class="logout-modal-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3H8c-1.1 0-2 .9-2 2v4h2V5h12v14H8v-4H6v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
            </svg>
        </div>
        <h2 id="logoutModalTitle">Sign Out?</h2>
        <p>You're about to leave the Calibration Management System. Do you want to continue?</p>
        <div class="logout-modal-actions">
            <button class="logout-btn-cancel" id="logoutCancel">Stay</button>
            <a href="logout.php" class="logout-btn-confirm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3H8c-1.1 0-2 .9-2 2v4h2V5h12v14H8v-4H6v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                </svg>
                Yes, Sign Out
            </a>
        </div>
    </div>
</div>

<script>
(function () {
    /* ── Flicker-free collapse ──────────────────────────────────────────
       State lives on body[data-sidebar] — one attribute, one CSS cascade.
       Restored synchronously before first paint to prevent flash.
    ──────────────────────────────────────────────────────────────────── */
    const collapsed = localStorage.getItem('sb-state') === '1';
    document.body.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';

    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');

        /* ── Toggle collapse ── */
        toggle.addEventListener('click', function () {
            const next = document.body.dataset.sidebar === 'collapsed' ? 'expanded' : 'collapsed';
            document.body.dataset.sidebar = next;
            localStorage.setItem('sb-state', next === 'collapsed' ? '1' : '0');
            this.title = next === 'collapsed' ? 'Expand' : 'Collapse';
            this.setAttribute('aria-label', next === 'collapsed' ? 'Expand Sidebar' : 'Collapse Sidebar');
        });

        /* ── Tooltip vertical tracking ──────────────────────────────────
           Set --tt-y CSS custom property on each link to the midpoint of
           that link's bounding rect, so the tooltip appears beside the
           hovered item rather than at a fixed top offset.
        ──────────────────────────────────────────────────────────────── */
        sidebar.querySelectorAll('.sidebar-link[data-label]').forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                const rect = this.getBoundingClientRect();
                const midY = Math.round(rect.top + rect.height / 2);
                this.style.setProperty('--tt-y', midY + 'px');
            });
        });

        /* ── Logout modal ── */
        const modal   = document.getElementById('logoutModal');
        const trigger = document.getElementById('logoutTrigger');
        const cancel  = document.getElementById('logoutCancel');

        /* Track last focused element to restore focus on close */
        let lastFocus = null;

        function openModal() {
            lastFocus = document.activeElement;
            modal.classList.add('open');
            setTimeout(function () { cancel.focus(); }, 50);
        }

        function closeModal() {
            modal.classList.remove('open');
            if (lastFocus) lastFocus.focus();
        }

        trigger.addEventListener('click', openModal);
        cancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });

        /* ── Focus trap inside modal ──────────────────────────────────── */
        modal.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !modal.classList.contains('open')) return;
            const focusable = modal.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])');
            const first = focusable[0];
            const last  = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    });
})();
</script>