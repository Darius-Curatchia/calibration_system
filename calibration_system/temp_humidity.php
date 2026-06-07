<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

$rooms = [
    'cal'     => 'Calibration Room',
    'insp'    => 'Dimensional Calibration Room',
    'cal_ext' => 'Calibration Extension Room',
];

$roomOffset = ['cal' => 1, 'insp' => 2, 'cal_ext' => 3];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Temp &amp; Humidity Monitoring — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function () {
    var s = localStorage.getItem('sb-state');
    var collapsed = (s === '1'); /* match sidebar.php: '1' = collapsed, else expanded */
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" defer></script>
<style>
:root {
    --navy:        #05304f;
    --navy-mid:    #0a4570;
    --accent:      #1a90d9;
    --accent-glow: rgba(26,144,217,0.15);
    --accent-soft: rgba(26,144,217,0.08);
    --bg-page:     #eef2f7;
    --bg-card:     #ffffff;
    --bg-raised:   #f8fafc;
    --border:      rgba(5,48,79,0.10);
    --border-mid:  rgba(5,48,79,0.16);
    --text:        #0d1f2d;
    --text-2:      #4a6070;
    --text-3:      #8fa3b1;
    --mono:        'DM Mono', monospace;
    --r-sm: 8px; --r-md: 12px; --r-xl: 20px;
    --shadow-sm: 0 2px 8px rgba(5,48,79,0.08);
    --shadow-lg: 0 8px 40px rgba(5,48,79,0.14);
    --row-nw-bg: #d4d4d4;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    background: var(--bg-page); color: var(--text);
    -webkit-font-smoothing: antialiased;
}
.card {
    background: var(--bg-card); border-radius: var(--r-xl);
    box-shadow: var(--shadow-sm); border: 1px solid var(--border);
    margin-bottom: 20px; overflow: hidden;
}
.card-header {
    padding: 16px 24px 0;
    background: linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);
    border-bottom: 1px solid var(--border);
}
.card-header-top {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 16px;
    flex-wrap: wrap; padding-bottom: 14px;
}
.card-header-top h2 { font-size: 14.5px; font-weight: 700; color: var(--navy); margin: 0 0 3px; letter-spacing: -0.1px; }
.card-header-top p  { font-size: 12px; color: var(--text-3); margin: 0; font-family: var(--mono); }
.card-body { padding: 20px 24px; }

.sheet-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.sheet-meta-item { display: flex; align-items: center; gap: 6px; }
.sheet-meta-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); white-space: nowrap; }
.meta-select { height: 30px; padding: 0 8px; border-radius: var(--r-sm); border: 1px solid var(--border-mid); font-size: 12.5px; font-family: var(--mono); font-weight: 700; color: var(--navy); background: var(--bg-raised); cursor: pointer; }
.meta-select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.active-sheet-label { font-size: 12px; font-weight: 700; color: var(--navy); padding: 4px 12px; background: var(--accent-soft); border: 1px solid rgba(26,144,217,0.18); border-radius: 20px; white-space: nowrap; }

.room-tabs { display: flex; gap: 2px; }
.room-tab {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: var(--r-md) var(--r-md) 0 0;
    font-size: 12.5px; font-weight: 600; color: var(--text-3);
    cursor: pointer; border: 1px solid transparent; border-bottom: none;
    background: transparent; font-family: 'Plus Jakarta Sans', sans-serif;
    transition: color 0.15s, background 0.15s; white-space: nowrap;
    position: relative; bottom: -1px;
}
.room-tab:hover:not(.active) { color: var(--text); background: rgba(5,48,79,0.04); }
.room-tab.active { color: var(--navy); background: var(--bg-card); border-color: var(--border); border-bottom-color: var(--bg-card); font-weight: 700; }
.tab-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--text-3); flex-shrink: 0; transition: background 0.15s; }
.room-tab.active .tab-dot { background: var(--accent); }

.tab-panel { display: none; }
.tab-panel.active { display: block; }

.tolerance-bar {
    display: flex; align-items: flex-start; gap: 24px; flex-wrap: wrap;
    padding: 11px 16px; background: var(--bg-raised);
    border: 1px solid var(--border); border-radius: var(--r-md); margin-bottom: 16px;
}
.tol-group { display: flex; flex-direction: column; gap: 4px; }
.tol-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.tol-values { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tol-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; font-family: var(--mono); white-space: nowrap; }
.tol-chip.temp      { background: rgba(234,88,12,0.10);  color: #b84e00; border: 1px solid rgba(234,88,12,0.18); }
.tol-chip.humid     { background: rgba(6,182,212,0.10);   color: #0a6b7c; border: 1px solid rgba(6,182,212,0.18); }
.tol-chip.temp-dim  { background: rgba(124,58,237,0.09);  color: #5b21b6; border: 1px solid rgba(124,58,237,0.18); }
.tol-chip.humid-dim { background: rgba(16,185,129,0.10);  color: #065f46; border: 1px solid rgba(16,185,129,0.18); }
.tol-divider { width: 1px; height: 36px; background: var(--border); flex-shrink: 0; }

.table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.toolbar-left, .toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.legend-row { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 7px; font-size: 11.5px; font-weight: 600; color: var(--text-2); }
.legend-swatch { width: 18px; height: 14px; border-radius: 3px; border: 1px solid var(--border-mid); flex-shrink: 0; }
.legend-swatch.nw { background: var(--row-nw-bg); }
.nw-hint { font-size: 11px; color: var(--text-3); font-style: italic; margin-left: auto; }

.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 14px; height: 32px; border-radius: var(--r-sm);
    font-size: 12px; font-weight: 600; font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; border: none; white-space: nowrap;
    text-decoration: none; box-sizing: border-box; transition: background 0.14s;
}
.btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.btn-primary { background: var(--navy);   color: #fff; box-shadow: 0 2px 8px rgba(5,48,79,0.20); }
.btn-primary:hover { background: var(--navy-mid); }
.btn-success { background: #16a34a; color: #fff; box-shadow: 0 2px 8px rgba(22,163,74,0.25); }
.btn-success:hover { background: #15803d; }
.btn-muted   { background: var(--bg-raised); color: var(--text-2); border: 1px solid var(--border); }
.btn-muted:hover   { background: var(--bg-page); color: var(--text); }
.btn-danger  { background: rgba(220,53,53,0.09); color: #a81c1c; border: 1px solid rgba(220,53,53,0.18); }
.btn-danger:hover  { background: rgba(220,53,53,0.16); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.save-status { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; opacity: 0; transition: opacity 0.3s; white-space: nowrap; }
.save-status.visible { opacity: 1; }
.save-status.ok    { color: #16a34a; }
.save-status.error { color: #dc2626; }
.save-status.busy  { color: var(--text-3); }

.panel-loading { display: none; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: var(--text-3); font-size: 13px; font-weight: 600; }
.panel-loading.visible { display: flex; }
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-container { overflow-x: auto; border-radius: var(--r-md); border: 1px solid var(--border); }
.table-container::-webkit-scrollbar { width: 6px; height: 6px; }
.table-container::-webkit-scrollbar-track { background: var(--bg-raised); }
.table-container::-webkit-scrollbar-thumb { background: var(--border-mid); border-radius: 3px; }
.table-container::-webkit-scrollbar-thumb:hover { background: var(--accent); }

.mon-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12.5px; min-width: 800px; }
.mon-table colgroup col.col-day     { width: 48px; }
.mon-table colgroup col.col-time    { width: 90px; }
.mon-table colgroup col.col-t-max   { width: 72px; }
.mon-table colgroup col.col-t-min   { width: 72px; }
.mon-table colgroup col.col-t-actual{ width: 72px; }
.mon-table colgroup col.col-h-max   { width: 72px; }
.mon-table colgroup col.col-h-min   { width: 72px; }
.mon-table colgroup col.col-h-actual{ width: 72px; }
.mon-table colgroup col.col-remarks { width: auto; }
.mon-table colgroup col.col-checked { width: 120px; }
.mon-table thead tr.thead-group th {
    background: var(--navy); color: rgba(255,255,255,0.85);
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    padding: 9px 10px; text-align: center;
    border-right: 1px solid rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.10); white-space: nowrap;
}
.mon-table thead tr.thead-group th:last-child { border-right: none; }
.mon-table thead tr.thead-sub th {
    background: #0a3d61; color: rgba(255,255,255,0.72);
    font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
    padding: 6px 10px; text-align: center; border-right: 1px solid rgba(255,255,255,0.07);
}
.mon-table thead tr.thead-sub th:last-child { border-right: none; }
.mon-table thead th.th-left { text-align: left; padding-left: 10px; }
.mon-table tbody tr { transition: background 0.1s; }
.mon-table tbody tr:nth-child(odd)  { background: #ffffff; }
.mon-table tbody tr:nth-child(even) { background: #ffffff; }
.mon-table tbody tr.nw-row          { background: var(--row-nw-bg) !important; }
.mon-table tbody tr.nw-row td       { color: #888; }
.mon-table tbody tr.today-row td:first-child { border-left: 3px solid var(--accent); }

/* Clickable rows */
.mon-table tbody tr.data-row { cursor: pointer; }
.mon-table tbody tr.data-row:not(.nw-row):hover {
    background: rgba(26,144,217,0.07) !important;
    outline: 1px solid rgba(26,144,217,0.25);
    outline-offset: -1px;
}
.mon-table tbody tr.nw-row { cursor: <?= $isGuest ? 'default' : 'pointer' ?>; }

.mon-table td { padding: 0; border-bottom: 1px solid var(--border); border-right: 1px solid rgba(5,48,79,0.05); vertical-align: middle; height: 36px; }
.mon-table td:last-child { border-right: none; }
.mon-table tbody tr:last-child td { border-bottom: none; }

.day-cell {
    text-align: center; font-family: var(--mono); font-size: 12px; font-weight: 700; color: var(--navy);
    padding: 0 6px; border-right: 1px solid var(--border-mid) !important;
    user-select: none; position: relative;
}
.nw-row .day-cell { color: #888; }

/* Read-only display cell */
.display-cell {
    padding: 0 10px; font-family: var(--mono); font-size: 12px;
    color: var(--text); display: flex; align-items: center;
    justify-content: center; height: 36px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap;
}
.display-cell.text-cell { justify-content: flex-start; font-family: 'Plus Jakarta Sans', sans-serif; }
.display-cell.empty { color: var(--text-3); }
.nw-row .display-cell { color: #aaa; }

/* ══════════════════════════════════════════════════
   ROW DETAIL MODAL
══════════════════════════════════════════════════ */
.row-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(5,48,79,0.55);
    display: none; justify-content: center; align-items: center;
    z-index: 900; padding: 16px; box-sizing: border-box;
}
.row-modal-overlay.open { display: flex; }
.row-modal-box {
    background: var(--bg-card); border-radius: var(--r-xl);
    width: 100%; max-width: 520px;
    box-shadow: var(--shadow-lg); border: 1px solid var(--border);
    display: flex; flex-direction: column; overflow: hidden;
}

.row-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px 14px; border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, #f6fafd 0%, #ffffff 100%);
}
.row-modal-title { display: flex; align-items: center; gap: 10px; }
.row-modal-day-badge {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--navy); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; font-family: var(--mono); flex-shrink: 0;
}
.row-modal-day-badge.is-nw    { background: #aaa; }
.row-modal-day-badge.is-today { background: var(--accent); }
.row-modal-heading { font-size: 14px; font-weight: 700; color: var(--navy); }
.row-modal-sub { font-size: 11px; color: var(--text-3); font-family: var(--mono); margin-top: 1px; }
.row-modal-close {
    width: 28px; height: 28px; border-radius: 7px;
    border: 1px solid var(--border); background: transparent;
    cursor: pointer; font-size: 18px; color: var(--text-3);
    display: flex; align-items: center; justify-content: center;
    line-height: 1; padding: 0; flex-shrink: 0;
    transition: background 0.13s, color 0.13s;
}
.row-modal-close:hover { background: rgba(220,53,53,0.10); color: #a81c1c; border-color: rgba(220,53,53,0.25); }

.row-modal-body {
    padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;
    overflow-y: auto; max-height: 70vh;
}

/* NW notice */
.nw-notice {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: rgba(212,212,212,0.4);
    border: 1px solid rgba(0,0,0,0.10); border-radius: var(--r-md);
    font-size: 12px; font-weight: 600; color: #666;
}

/* Field group */
.modal-section-label {
    font-size: 9.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--text-3); margin-bottom: 8px;
}
.modal-field-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.modal-field-grid.two-col { grid-template-columns: 1fr 1fr; }
.modal-field-grid.one-col { grid-template-columns: 1fr; }
.modal-field { display: flex; flex-direction: column; gap: 4px; }
.modal-field label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.6px; color: var(--text-3);
}
.modal-field input[type="text"],
.modal-field input[type="number"] {
    height: 34px; padding: 0 10px;
    border: 1px solid var(--border-mid); border-radius: var(--r-sm);
    font-size: 13px; font-family: var(--mono); color: var(--text);
    background: var(--bg-raised); width: 100%; box-sizing: border-box;
    transition: border-color 0.13s, box-shadow 0.13s;
}
.modal-field input:focus {
    outline: none; border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow); background: #fff;
}
.modal-field input:read-only { background: var(--bg-raised); color: var(--text-3); cursor: default; }

/* NW toggle inside modal */
.nw-toggle-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: var(--bg-raised);
    border: 1px solid var(--border); border-radius: var(--r-md);
}
.nw-toggle-row label { font-size: 12.5px; font-weight: 600; color: var(--text-2); cursor: pointer; user-select: none; flex: 1; }
.nw-toggle {
    width: 38px; height: 22px; border-radius: 11px;
    background: #ccd5de; border: none; cursor: pointer;
    position: relative; flex-shrink: 0; transition: background 0.18s;
    appearance: none; -webkit-appearance: none;
}
.nw-toggle::after {
    content: ''; position: absolute;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff; top: 3px; left: 3px;
    transition: transform 0.18s; box-shadow: 0 1px 3px rgba(0,0,0,0.20);
}
.nw-toggle:checked { background: var(--accent); }
.nw-toggle:checked::after { transform: translateX(16px); }

.row-modal-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; border-top: 1px solid var(--border);
    background: var(--bg-raised); gap: 8px;
}
.row-modal-footer-right { display: flex; gap: 8px; }

/* ── Export Modal ──────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(5,48,79,0.55); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 16px; box-sizing: border-box; }
.modal-overlay.open { display: flex; }
.modal-close-btn { width: 28px; height: 28px; border-radius: 7px; border: 1px solid var(--border); background: transparent; cursor: pointer; font-size: 16px; color: var(--text-2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; line-height: 1; }
.modal-close-btn:hover { background: rgba(220,53,53,0.10); color: #a81c1c; border-color: rgba(220,53,53,0.25); }
.export-modal-box { background: var(--bg-card); border-radius: var(--r-xl); width: 100%; max-width: 580px; max-height: 92vh; display: flex; flex-direction: column; box-shadow: var(--shadow-lg); border: 1px solid var(--border); overflow: hidden; }
.export-modal-header { padding: 18px 22px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.export-modal-header h3 { font-size: 14.5px; font-weight: 700; color: var(--navy); margin: 0; }
.export-modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; flex: 1; }
.export-modal-footer { padding: 12px 22px; border-top: 1px solid var(--border); background: var(--bg-raised); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.export-filter-note { font-size: 11px; color: var(--text-3); font-style: italic; font-family: var(--mono); }

.export-section-label { display: flex; align-items: center; justify-content: space-between; padding: 9px 14px; background: var(--bg-raised); border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 700; color: var(--navy); }
.export-section-hint { font-size: 10.5px; font-weight: 500; color: var(--text-3); font-style: italic; }
.export-section-box { border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden; }

.rev-history-header { display: grid; grid-template-columns: 28px 60px 70px 1fr 90px 90px; background: var(--navy); }
.rev-history-header span { padding: 7px 6px; font-size: 9.5px; font-weight: 700; color: rgba(255,255,255,0.80); text-transform: uppercase; letter-spacing: 0.4px; border-right: 1px solid rgba(255,255,255,0.08); }
.rev-history-header span:last-child { border-right: none; }
.rev-history-row { display: grid; grid-template-columns: 28px 60px 70px 1fr 90px 90px; border-bottom: 1px solid var(--border); }
.rev-history-row:last-child { border-bottom: none; }
.rev-history-row:nth-child(even) { background: var(--bg-raised); }
.rev-input { width: 100%; border: none; border-right: 1px solid var(--border); padding: 5px 6px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 11px; color: var(--text); background: transparent; box-sizing: border-box; }
.rev-input:last-child { border-right: none; }
.rev-input:focus { outline: none; background: var(--accent-soft); box-shadow: inset 0 0 0 2px rgba(26,144,217,0.25); position: relative; z-index: 1; }
.rev-input::placeholder { color: var(--text-3); font-style: italic; }
.rev-code { text-align: center; font-family: var(--mono); font-weight: 700; }
.rev-date { font-family: var(--mono); font-size: 10.5px; }

@media (max-width: 768px) {
    .card-header-top { flex-direction: column; }
    .room-tabs { overflow-x: auto; }
    .room-tab  { padding: 8px 12px; font-size: 11.5px; }
    .tolerance-bar { gap: 12px; }
    .table-toolbar { flex-direction: column; align-items: flex-start; }
    .nw-hint { display: none; }
    .modal-field-grid { grid-template-columns: 1fr 1fr; }
    .modal-field-grid.two-col { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-header-top">
                <div>
                    <h2>Calibration Room Temperature &amp; Humidity Monitoring Sheet</h2>
                    <p>Reference: PC2-9710 Calibration Control Procedure &nbsp;·&nbsp; PES.CAL08055.R4</p>
                </div>
                <div class="sheet-meta">
                    <div class="sheet-meta-item">
                        <span class="sheet-meta-label">Month</span>
                        <select class="meta-select" id="monthSelect">
                            <?php
                            $months = ['January','February','March','April','May','June',
                                       'July','August','September','October','November','December'];
                            $curM = (int)date('n');
                            foreach ($months as $i => $mn) {
                                $sel = ($i + 1 === $curM) ? ' selected' : '';
                                echo "<option value=\"".($i+1)."\"$sel>$mn</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="sheet-meta-item">
                        <span class="sheet-meta-label">Year</span>
                        <select class="meta-select" id="yearSelect">
                            <?php
                            $curY = (int)date('Y');
                            for ($y = $curY - 2; $y <= $curY + 2; $y++) {
                                $sel = ($y === $curY) ? ' selected' : '';
                                echo "<option value=\"$y\"$sel>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <span class="active-sheet-label" id="activeSheetLabel"></span>
                </div>
            </div>
            <div class="room-tabs" role="tablist">
                <?php $first = true; foreach ($rooms as $rk => $rn): ?>
                <button class="room-tab <?= $first ? 'active' : '' ?>"
                        role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>"
                        data-room="<?= $rk ?>" aria-controls="panel-<?= $rk ?>">
                    <span class="tab-dot"></span>
                    <?= htmlspecialchars($rk === 'cal_ext' ? 'Cal. Extension Room' : $rn) ?>
                </button>
                <?php $first = false; endforeach; ?>
            </div>
        </div>

        <div class="card-body">
            <div class="tolerance-bar">
                <div class="tol-group">
                    <span class="tol-label">General Tolerance</span>
                    <div class="tol-values">
                        <span class="tol-chip temp">🌡 Temperature: 23 °C ± 5 °C</span>
                        <span class="tol-chip humid">💧 Humidity: 50% ± 20%</span>
                    </div>
                </div>
                <div class="tol-divider"></div>
                <div class="tol-group">
                    <span class="tol-label">Note — During Dimensional Calibration</span>
                    <div class="tol-values">
                        <span class="tol-chip temp-dim">🌡 Temperature: 20 °C ± 2 °C</span>
                        <span class="tol-chip humid-dim">💧 Humidity: 55% ± 15%</span>
                    </div>
                </div>
            </div>

            <?php $first = true; foreach ($rooms as $rk => $rn): ?>
            <div class="tab-panel <?= $first ? 'active' : '' ?>" id="panel-<?= $rk ?>" role="tabpanel">
                <div class="table-toolbar">
                    <div class="toolbar-left"></div>
                    <div class="toolbar-right">
                        <span class="save-status busy" id="saveStatus-<?= $rk ?>">
                            <span class="spinner"></span> Loading…
                        </span>
                        <button class="btn btn-success" id="exportPdfBtn-<?= $rk ?>" data-room="<?= $rk ?>">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Export PDF
                        </button>
                    </div>
                </div>
                <div class="legend-row">
                    <div class="legend-item">
                        <span class="legend-swatch nw"></span>
                        Non-working day / Holiday
                    </div>
                    <?php if (!$isGuest): ?>
                    <span class="nw-hint">💡 Click any row to view or edit its data</span>
                    <?php else: ?>
                    <span class="nw-hint">👁 View-only mode — click a row to inspect</span>
                    <?php endif; ?>
                </div>
                <div class="panel-loading" id="loading-<?= $rk ?>">
                    <div class="spinner"></div> Loading sheet data…
                </div>
                <div class="table-container" id="tableWrap-<?= $rk ?>">
                    <table class="mon-table" id="table-<?= $rk ?>">
                        <colgroup>
                            <col class="col-day"><col class="col-time">
                            <col class="col-t-max"><col class="col-t-min"><col class="col-t-actual">
                            <col class="col-h-max"><col class="col-h-min"><col class="col-h-actual">
                            <col class="col-remarks"><col class="col-checked">
                        </colgroup>
                        <thead>
                            <tr class="thead-group">
                                <th rowspan="2" style="text-align:center;">Day</th>
                                <th rowspan="2" class="th-left">Time</th>
                                <th colspan="3">Temperature (°C)</th>
                                <th colspan="3">Humidity (%)</th>
                                <th rowspan="2" class="th-left">Remarks</th>
                                <th rowspan="2" class="th-left">Checked By</th>
                            </tr>
                            <tr class="thead-sub">
                                <th>Max</th><th>Min</th><th>Actual</th>
                                <th>Max</th><th>Min</th><th>Actual</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-<?= $rk ?>">
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <tr class="data-row" data-day="<?= $d ?>" data-room="<?= $rk ?>">
                                <td class="day-cell"><?= $d ?></td>
                                <?php
                                $fields = ['time_recorded','t_max','t_min','t_actual','h_max','h_min','h_actual','remarks','checked_by'];
                                foreach ($fields as $f):
                                    $isText = in_array($f, ['time_recorded','remarks','checked_by']);
                                    $cls = $isText ? 'text-cell' : '';
                                ?>
                                <td>
                                    <div class="display-cell <?= $cls ?> empty"
                                         data-display="<?= $f ?>"
                                         data-room="<?= $rk ?>"
                                         data-day="<?= $d ?>">—</div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     ROW DETAIL / EDIT MODAL
══════════════════════════════════════════════════════════ -->
<div id="rowModal" class="row-modal-overlay" role="dialog" aria-modal="true">
    <div class="row-modal-box">
        <div class="row-modal-header">
            <div class="row-modal-title">
                <div class="row-modal-day-badge" id="modalDayBadge">1</div>
                <div>
                    <div class="row-modal-heading" id="modalHeading">Day 1</div>
                    <div class="row-modal-sub" id="modalSub">Calibration Room</div>
                </div>
            </div>
            <button class="row-modal-close" id="rowModalClose">&times;</button>
        </div>
        <div class="row-modal-body">
            <?php if (!$isGuest): ?>
            <div class="nw-toggle-row">
                <label for="modalNwToggle">Mark as Non-Working Day / Holiday</label>
                <input type="checkbox" class="nw-toggle" id="modalNwToggle">
            </div>
            <?php endif; ?>
            <div id="modalNwNotice" class="nw-notice" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                This is marked as a non-working day. Data fields are disabled.
            </div>
            <div>
                <div class="modal-section-label">Time Recorded</div>
                <div class="modal-field-grid two-col">
                    <div class="modal-field">
                        <label>Time</label>
                        <input type="text" id="mf_time_recorded" placeholder="e.g. 08:00" maxlength="20">
                    </div>
                </div>
            </div>
            <div>
                <div class="modal-section-label">Temperature (°C)</div>
                <div class="modal-field-grid">
                    <div class="modal-field"><label>Max</label><input type="number" id="mf_t_max" step="0.1" placeholder="—"></div>
                    <div class="modal-field"><label>Min</label><input type="number" id="mf_t_min" step="0.1" placeholder="—"></div>
                    <div class="modal-field"><label>Actual</label><input type="number" id="mf_t_actual" step="0.1" placeholder="—"></div>
                </div>
            </div>
            <div>
                <div class="modal-section-label">Humidity (%)</div>
                <div class="modal-field-grid">
                    <div class="modal-field"><label>Max</label><input type="number" id="mf_h_max" step="0.1" placeholder="—"></div>
                    <div class="modal-field"><label>Min</label><input type="number" id="mf_h_min" step="0.1" placeholder="—"></div>
                    <div class="modal-field"><label>Actual</label><input type="number" id="mf_h_actual" step="0.1" placeholder="—"></div>
                </div>
            </div>
            <div>
                <div class="modal-section-label">Notes</div>
                <div class="modal-field-grid two-col">
                    <div class="modal-field"><label>Remarks</label><input type="text" id="mf_remarks" placeholder="Optional" maxlength="200"></div>
                    <div class="modal-field"><label>Checked By</label><input type="text" id="mf_checked_by" placeholder="Name" maxlength="100"></div>
                </div>
            </div>
        </div>
        <div class="row-modal-footer">
            <?php if (!$isGuest): ?>
            <button class="btn btn-danger" id="modalDeleteBtn">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Clear Row
            </button>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
            <div class="row-modal-footer-right">
                <button class="btn btn-muted" id="modalCancelBtn">Cancel</button>
                <?php if (!$isGuest): ?>
                <button class="btn btn-success" id="modalSaveBtn">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Save
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
══════════════════════════════════════════════════════════ -->
<div id="confirmModal" class="row-modal-overlay" role="dialog" aria-modal="true">
    <div class="row-modal-box" style="max-width:380px;">
        <div class="row-modal-header">
            <div class="row-modal-title">
                <div class="row-modal-day-badge" style="background:#dc2626;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                </div>
                <div>
                    <div class="row-modal-heading">Clear Row Data</div>
                    <div class="row-modal-sub">This action cannot be undone</div>
                </div>
            </div>
            <button class="row-modal-close" id="confirmModalClose">&times;</button>
        </div>
        <div class="row-modal-body" style="padding:18px 20px;">
            <p style="font-size:13px;color:var(--text-2);line-height:1.6;">
                Are you sure you want to clear all data for this row? The non-working day status and all entered values will be removed across all 3 rooms.
            </p>
        </div>
        <div class="row-modal-footer">
            <span></span>
            <div class="row-modal-footer-right">
                <button class="btn btn-muted" id="confirmCancelBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmOkBtn">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Yes, Clear Row
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EXPORT MODAL
══════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay">
    <div class="export-modal-box">
        <div class="export-modal-header">
            <h3>Export Monitoring Sheet — PDF</h3>
            <button class="modal-close-btn" id="closeExportModal">&times;</button>
        </div>
        <div class="export-modal-body">
            <div style="padding:10px 14px;background:var(--accent-soft);border:1px solid rgba(26,144,217,0.18);border-radius:var(--r-md);font-size:12px;color:var(--navy);display:flex;gap:20px;flex-wrap:wrap;">
                <span><strong>Room:</strong> <span id="exportRoomName">—</span></span>
                <span><strong>Month/Year:</strong> <span id="exportMonthYear">—</span></span>
                <span><strong>Sheet No.:</strong> <span id="exportSheetNo">—</span></span>
            </div>
            <div class="export-section-box">
                <div class="export-section-label">
                    <span>Revision History</span>
                    <span class="export-section-hint">Printed at bottom of PDF</span>
                </div>
                <div>
                    <div class="rev-history-header">
                        <span>Rev.</span><span>Rev. Date</span><span>Revised By</span>
                        <span>Nature / Description of Revision</span>
                        <span>Appr. (Section Mgr.)</span><span>Appr. (Dept. Mgr.)</span>
                    </div>
                    <div class="rev-history-row">
                        <input type="text" class="rev-input rev-code" id="rev1code" value="2">
                        <input type="text" class="rev-input rev-date" id="rev1date" value="01-Feb-15">
                        <input type="text" class="rev-input" id="rev1by" value="J. Garces">
                        <input type="text" class="rev-input" id="rev1desc" value="Included Reference Number and Tolerance">
                        <input type="text" class="rev-input" id="rev1appr1" placeholder="(signature)">
                        <input type="text" class="rev-input" id="rev1appr2" placeholder="(signature)">
                    </div>
                    <div class="rev-history-row">
                        <input type="text" class="rev-input rev-code" id="rev2code" value="3">
                        <input type="text" class="rev-input rev-date" id="rev2date" value="01-Jun-18">
                        <input type="text" class="rev-input" id="rev2by" value="E. Navarro">
                        <input type="text" class="rev-input" id="rev2desc" value="Revised logo to Shindengen Philippines Corp.">
                        <input type="text" class="rev-input" id="rev2appr1" placeholder="(signature)">
                        <input type="text" class="rev-input" id="rev2appr2" placeholder="(signature)">
                    </div>
                    <div class="rev-history-row">
                        <input type="text" class="rev-input rev-code" id="rev3code" value="4">
                        <input type="text" class="rev-input rev-date" id="rev3date" value="21-Jul-20">
                        <input type="text" class="rev-input" id="rev3by" value="E. Navarro">
                        <input type="text" class="rev-input" id="rev3desc" value="Added note for dimensional calibration temperature tolerance">
                        <input type="text" class="rev-input" id="rev3appr1" placeholder="(signature)">
                        <input type="text" class="rev-input" id="rev3appr2" value="QA Section Mgr. / Dept. Mgr.">
                    </div>
                </div>
            </div>
        </div>
        <div class="export-modal-footer">
            <span class="export-filter-note" id="exportFilterNote"></span>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-muted" id="cancelExportBtn">Cancel</button>
                <button class="btn btn-success" id="doExportPdfBtn">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const IS_GUEST   = <?= $isGuest ? 'true' : 'false' ?>;
const ROOMS      = ['cal', 'insp', 'cal_ext'];
const ROOM_NAMES = { cal: 'Calibration Room', insp: 'Dimensional Calibration Room', cal_ext: 'Calibration Extension Room' };
const ROOM_OFFSETS = { cal: 1, insp: 2, cal_ext: 3 };
const FIELDS = ['time_recorded','t_max','t_min','t_actual','h_max','h_min','h_actual','remarks','checked_by'];

function calcSheetNo(room, year, month) {
    const yy  = String(year).slice(-2);
    const seq = (month - 1) * 3 + ROOM_OFFSETS[room];
    return `${yy}-${String(seq).padStart(2, '0')}`;
}
function getMonthYear() {
    return {
        month: parseInt(document.getElementById('monthSelect').value, 10),
        year:  parseInt(document.getElementById('yearSelect').value,  10)
    };
}
function daysInMonth(y, m) { return new Date(y, m, 0).getDate(); }

function currentTimeString() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    return `${h}:${m}`;
}

function showStatus(room, type, msg) {
    const el = document.getElementById(`saveStatus-${room}`);
    el.className = `save-status ${type} visible`;
    el.innerHTML = type === 'busy'
        ? `<span class="spinner"></span> ${msg}`
        : (type === 'ok'
            ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ${msg}`
            : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msg}`);
    if (type !== 'busy') setTimeout(() => el.classList.remove('visible'), 3000);
}

/* ══════════════════════════════════════════════════════════
   ROW MODAL
══════════════════════════════════════════════════════════ */
const rowModal      = document.getElementById('rowModal');
const modalDayBadge = document.getElementById('modalDayBadge');
const modalHeading  = document.getElementById('modalHeading');
const modalSub      = document.getElementById('modalSub');
const modalNwNotice = document.getElementById('modalNwNotice');
const modalNwToggle = document.getElementById('modalNwToggle');
const modalSaveBtn  = document.getElementById('modalSaveBtn');
const modalDeleteBtn= document.getElementById('modalDeleteBtn');
const modalCancelBtn= document.getElementById('modalCancelBtn');

let activeModalRow = null;

const MODAL_FIELD_IDS = {
    time_recorded: 'mf_time_recorded',
    t_max:         'mf_t_max',
    t_min:         'mf_t_min',
    t_actual:      'mf_t_actual',
    h_max:         'mf_h_max',
    h_min:         'mf_h_min',
    h_actual:      'mf_h_actual',
    remarks:       'mf_remarks',
    checked_by:    'mf_checked_by',
};

function openRowModal(row) {
    activeModalRow = row;
    const day   = parseInt(row.dataset.day, 10);
    const room  = row.dataset.room;
    const isNW  = row.classList.contains('nw-row');

    const { month, year } = getMonthYear();
    const now = new Date();
    const isToday = (now.getFullYear() === year && (now.getMonth()+1) === month && now.getDate() === day);

    modalDayBadge.textContent = day;
    modalDayBadge.className = 'row-modal-day-badge' + (isNW ? ' is-nw' : (isToday ? ' is-today' : ''));

    const monthName = new Date(year, month-1, 1).toLocaleString('en-US', { month: 'long' });
    modalHeading.textContent = `Day ${day}  —  ${monthName} ${year}`;
    modalSub.textContent = ROOM_NAMES[room];

    if (modalNwToggle) modalNwToggle.checked = isNW;
    modalNwNotice.style.display = isNW ? '' : 'none';

    FIELDS.forEach(f => {
        const disp = row.querySelector(`[data-display="${f}"]`);
        const inp  = document.getElementById(MODAL_FIELD_IDS[f]);
        if (!inp) return;
        const val = (disp && !disp.classList.contains('empty')) ? disp.textContent.trim() : '';
        inp.value = val;
        inp.disabled = isNW;
    });

    // Auto-fill time if empty (any row, not just today)
    const timeInp = document.getElementById('mf_time_recorded');
    if (timeInp && !timeInp.value && !IS_GUEST && !isNW) {
        timeInp.value = currentTimeString();
    }

    setModalFieldsDisabled(isNW || IS_GUEST);
    if (IS_GUEST && modalNwToggle) modalNwToggle.disabled = true;

    rowModal.classList.add('open');

    if (!IS_GUEST && !isNW) {
        setTimeout(() => document.getElementById('mf_time_recorded')?.focus(), 60);
    }
}

function setModalFieldsDisabled(disabled) {
    FIELDS.forEach(f => {
        const inp = document.getElementById(MODAL_FIELD_IDS[f]);
        if (inp) inp.disabled = disabled;
    });
}

function closeRowModal() {
    rowModal.classList.remove('open');
    activeModalRow = null;
}

if (modalNwToggle) {
    modalNwToggle.addEventListener('change', () => {
        const isNW = modalNwToggle.checked;
        modalNwNotice.style.display = isNW ? '' : 'none';
        setModalFieldsDisabled(isNW);
        if (activeModalRow) {
            modalDayBadge.className = 'row-modal-day-badge' + (isNW ? ' is-nw' : '');
        }
    });
}

document.getElementById('rowModalClose').addEventListener('click', closeRowModal);
modalCancelBtn.addEventListener('click', closeRowModal);
rowModal.addEventListener('click', e => { if (e.target === rowModal) closeRowModal(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && rowModal.classList.contains('open')) closeRowModal();
});

/* ── Apply modal values back to the display row ── */
function applyModalToRow(row, isNW) {
    row.classList.toggle('nw-row', isNW);
    FIELDS.forEach(f => {
        const disp = row.querySelector(`[data-display="${f}"]`);
        const inp  = document.getElementById(MODAL_FIELD_IDS[f]);
        if (!disp) return;
        const val = inp ? inp.value.trim() : '';
        if (val && !isNW) {
            disp.textContent = val;
            disp.classList.remove('empty');
        } else {
            disp.textContent = '—';
            disp.classList.add('empty');
        }
    });
}

/* ── Save ── */
if (modalSaveBtn) {
    modalSaveBtn.addEventListener('click', async () => {
        if (!activeModalRow) return;
        const row   = activeModalRow;
        const room  = row.dataset.room;
        const day   = parseInt(row.dataset.day, 10);
        const isNW  = modalNwToggle ? modalNwToggle.checked : row.classList.contains('nw-row');
        const { month, year } = getMonthYear();

        const basePayload = { year, month, day, is_nonworking: isNW,
            time_recorded:null,t_max:null,t_min:null,t_actual:null,
            h_max:null,h_min:null,h_actual:null,remarks:null,checked_by:null };

        if (!isNW) {
            FIELDS.forEach(f => {
                const inp = document.getElementById(MODAL_FIELD_IDS[f]);
                if (inp) basePayload[f] = inp.value !== '' ? inp.value : null;
            });
        }

        modalSaveBtn.disabled = true;
        showStatus(room, 'busy', 'Saving…');
        try {
            if (isNW) {
                await Promise.all(ROOMS.map(r =>
                    fetch('th_save.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...basePayload, room: r })
                    }).then(res => res.json()).then(json => {
                        if (!json.ok) throw new Error(json.error || `Save failed for ${r}`);
                        const otherRow = document.querySelector(`#tbody-${r} tr[data-day="${day}"]`);
                        if (otherRow) applyModalToRow(otherRow, true);
                    })
                ));
            } else {
                const res  = await fetch('th_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...basePayload, room })
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Save failed');
                applyModalToRow(row, false);
            }

            showStatus(room, 'ok', 'Saved');
            closeRowModal();
        } catch(err) {
            showStatus(room, 'error', 'Save error: ' + err.message);
        } finally {
            modalSaveBtn.disabled = false;
        }
    });
}

/* ── Delete (clear) ── */
if (modalDeleteBtn) {
    const confirmModal      = document.getElementById('confirmModal');
    const confirmOkBtn      = document.getElementById('confirmOkBtn');
    const confirmCancelBtn  = document.getElementById('confirmCancelBtn');
    const confirmModalClose = document.getElementById('confirmModalClose');

    function closeConfirmModal() { confirmModal.classList.remove('open'); }
    confirmCancelBtn.addEventListener('click', closeConfirmModal);
    confirmModalClose.addEventListener('click', closeConfirmModal);
    confirmModal.addEventListener('click', e => { if (e.target === confirmModal) closeConfirmModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && confirmModal.classList.contains('open')) closeConfirmModal();
    });

    modalDeleteBtn.addEventListener('click', () => {
        if (!activeModalRow) return;
        confirmModal.classList.add('open');
    });

    confirmOkBtn.addEventListener('click', async () => {
        closeConfirmModal();
        if (!activeModalRow) return;

        const row  = activeModalRow;
        const room = row.dataset.room;
        const day  = parseInt(row.dataset.day, 10);
        const { month, year } = getMonthYear();

        const payload = { room, year, month, day, is_nonworking: false,
            time_recorded:null,t_max:null,t_min:null,t_actual:null,
            h_max:null,h_min:null,h_actual:null,remarks:null,checked_by:null };

        modalDeleteBtn.disabled = true;
        showStatus(room, 'busy', 'Clearing…');
        try {
            await Promise.all(ROOMS.map(r =>
                fetch('th_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...payload, room: r })
                }).then(res => res.json()).then(json => {
                    if (!json.ok) throw new Error(json.error || `Clear failed for ${r}`);
                })
            ));

            ROOMS.forEach(r => {
                const targetRow = document.querySelector(`#tbody-${r} tr[data-day="${day}"]`);
                if (targetRow) {
                    targetRow.classList.remove('nw-row');
                    FIELDS.forEach(f => {
                        const disp = targetRow.querySelector(`[data-display="${f}"]`);
                        if (disp) { disp.textContent = '—'; disp.classList.add('empty'); }
                    });
                }
            });
            showStatus(room, 'ok', 'Row cleared');
            closeRowModal();
        } catch(err) {
            showStatus(room, 'error', 'Error: ' + err.message);
        } finally {
            modalDeleteBtn.disabled = false;
        }
    });
}

/* ── Row click → open modal ── */
document.addEventListener('click', function(e) {
    const row = e.target.closest('tr.data-row');
    if (!row) return;
    if (e.target.closest('button, a, select, input')) return;
    openRowModal(row);
});

/* ══════════════════════════════════════════════════════════
   LOAD SHEET FROM DB
══════════════════════════════════════════════════════════ */
async function loadSheet(room) {
    const { month, year } = getMonthYear();
    const loading = document.getElementById(`loading-${room}`);
    const wrap    = document.getElementById(`tableWrap-${room}`);
    loading.classList.add('visible'); wrap.style.display = 'none';
    showStatus(room, 'busy', 'Loading…');
    try {
        const res  = await fetch(`th_load.php?room=${room}&year=${year}&month=${month}`);
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'Load failed');
        applySheetData(room, json.data, year, month);
        showStatus(room, 'ok', 'Loaded');
    } catch (err) {
        showStatus(room, 'error', 'Load error: ' + err.message);
    } finally {
        loading.classList.remove('visible'); wrap.style.display = '';
    }
}

function applySheetData(room, data, year, month) {
    const days  = daysInMonth(year, month);
    const tbody = document.getElementById(`tbody-${room}`);
    const now   = new Date();
    const todayDay = (now.getFullYear() === year && (now.getMonth()+1) === month) ? now.getDate() : -1;

    tbody.querySelectorAll('tr.data-row').forEach(row => {
        const d = parseInt(row.dataset.day, 10);
        if (d > days) { row.style.display = 'none'; return; }
        row.style.display = '';

        row.classList.toggle('today-row', d === todayDay);

        const rowData = data[d] || {};
        const isNW    = rowData.is_nonworking === true;
        row.classList.toggle('nw-row', isNW);

        FIELDS.forEach(f => {
            const disp = row.querySelector(`[data-display="${f}"]`);
            const raw  = (rowData[f] !== null && rowData[f] !== undefined) ? String(rowData[f]) : '';
            if (disp) {
                if (raw) { disp.textContent = raw; disp.classList.remove('empty'); }
                else     { disp.textContent = '—'; disp.classList.add('empty'); }
            }
        });
    });
}

/* ══════════════════════════════════════════════════════════
   TABS & MONTH/YEAR SELECTS
══════════════════════════════════════════════════════════ */
let activeTab = 'cal';
const loadedTabs = new Set();

function updateSheetLabel() {
    const { month, year } = getMonthYear();
    const monthName = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long' });
    document.getElementById('activeSheetLabel').textContent =
        ROOM_NAMES[activeTab] + '  ·  ' + monthName + ' ' + year;
}

document.querySelectorAll('.room-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.room-tab').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active'); btn.setAttribute('aria-selected','true');
        activeTab = btn.dataset.room;
        document.getElementById(`panel-${activeTab}`).classList.add('active');
        updateSheetLabel();
        if (!loadedTabs.has(activeTab)) { loadedTabs.add(activeTab); loadSheet(activeTab); }
    });
});

['monthSelect','yearSelect'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        closeRowModal();
        loadedTabs.clear(); updateSheetLabel();
        loadedTabs.add(activeTab); loadSheet(activeTab);
    });
});

/* ══════════════════════════════════════════════════════════
   EXPORT PDF
══════════════════════════════════════════════════════════ */
const exportModal      = document.getElementById('exportModal');
const closeExportModal = document.getElementById('closeExportModal');
const cancelExportBtn  = document.getElementById('cancelExportBtn');
const doExportPdfBtn   = document.getElementById('doExportPdfBtn');
let exportRoom = null;

function openExportModal(room) {
    exportRoom = room;
    const { month, year } = getMonthYear();
    const monthName = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long' });
    document.getElementById('exportRoomName').textContent  = ROOM_NAMES[room];
    document.getElementById('exportMonthYear').textContent = `${monthName} ${year}`;
    document.getElementById('exportSheetNo').textContent   = calcSheetNo(room, year, month);
    document.getElementById('exportFilterNote').textContent =
        `Sheet No.: ${calcSheetNo(room, year, month)}  ·  ${monthName} ${year}`;
    exportModal.classList.add('open');
}
function closeExport() { exportModal.classList.remove('open'); }
closeExportModal.addEventListener('click', closeExport);
cancelExportBtn.addEventListener('click', closeExport);
exportModal.addEventListener('click', e => { if (e.target === exportModal) closeExport(); });
ROOMS.forEach(room => {
    const btn = document.getElementById(`exportPdfBtn-${room}`);
    if (btn) btn.addEventListener('click', () => openExportModal(room));
});

/* Persist revision inputs */
const REV_KEY = 'thRevFields';
const revRowIds = [
    ['rev1code','rev1date','rev1by','rev1desc','rev1appr1','rev1appr2'],
    ['rev2code','rev2date','rev2by','rev2desc','rev2appr1','rev2appr2'],
    ['rev3code','rev3date','rev3by','rev3desc','rev3appr1','rev3appr2'],
];
function saveRev() {
    try { sessionStorage.setItem(REV_KEY, JSON.stringify(revRowIds.map(row => row.map(id => { const el=document.getElementById(id); return el?el.value:''; })))); } catch(e) {}
}
function loadRev() {
    try {
        const rev = JSON.parse(sessionStorage.getItem(REV_KEY));
        if (Array.isArray(rev)) rev.forEach((row,ri) => row.forEach((v,ci) => { const el=document.getElementById(revRowIds[ri][ci]); if(el) el.value=v; }));
    } catch(e) {}
}
revRowIds.flat().forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('input', saveRev); });
loadRev();

/* ── PDF generation ── */
doExportPdfBtn.addEventListener('click', async () => {
    if (typeof window.jspdf === 'undefined') { alert('PDF library is still loading, please try again.'); return; }

    const { jsPDF } = window.jspdf;
    const { month, year } = getMonthYear();
    const room      = exportRoom;
    const monthName = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long' });
    const sheetNo   = calcSheetNo(room, year, month);
    const roomName  = ROOM_NAMES[room];
    const days      = daysInMonth(year, month);

    const tbody     = document.getElementById(`tbody-${room}`);
    const tableRows = [];
    for (let d = 1; d <= days; d++) {
        const row = tbody.querySelector(`tr[data-day="${d}"]`);
        if (!row || row.style.display === 'none') continue;
        const isNW = row.classList.contains('nw-row');
        const gv = field => {
            const el = row.querySelector(`[data-display="${field}"]`);
            if (!el) return '';
            return el.classList.contains('empty') ? '' : el.textContent.trim();
        };
        tableRows.push({
            day: String(d), time: gv('time_recorded'),
            t_max: gv('t_max'), t_min: gv('t_min'), t_actual: gv('t_actual'),
            h_max: gv('h_max'), h_min: gv('h_min'), h_actual: gv('h_actual'),
            remarks: gv('remarks'), checked_by: gv('checked_by'),
            is_nonworking: isNW,
        });
    }

    const getRev = id => { const el=document.getElementById(id); return el?el.value.trim():''; };
    const revisions = revRowIds.map(row => ({
        code: getRev(row[0]), date: getRev(row[1]), by: getRev(row[2]),
        desc: getRev(row[3]), appr1: getRev(row[4]), appr2: getRev(row[5]),
    }));

    const doc   = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pageW = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    const mL = 12, mR = 12, mT = 10;
    const usableW = pageW - mL - mR;

    const loadImg = src => new Promise((res, rej) => {
        const img = new Image(); img.crossOrigin = 'anonymous';
        img.onload = () => {
            const c = document.createElement('canvas');
            c.width = img.naturalWidth; c.height = img.naturalHeight;
            c.getContext('2d').drawImage(img, 0, 0);
            res({ dataUrl: c.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight });
        };
        img.onerror = rej; img.src = src;
    });

    try {
        const logo = await loadImg('images/shindengen-logo.png');
        const lh = 10, lw = lh * (logo.w / logo.h);
        doc.addImage(logo.dataUrl, 'PNG', mL, mT, lw, lh);
    } catch(e) {
        doc.setFontSize(9); doc.setFont('helvetica','bold'); doc.setTextColor(5,48,79);
        doc.text('Shindengen Philippines Corp.', mL, mT + 5);
    }
    doc.setFontSize(7); doc.setFont('helvetica','normal'); doc.setTextColor(80,100,120);
    doc.text('Production Engineering Section', mL, mT + 13);

    const metaX = pageW - mR - 65, metaY = mT;
    const metaW = 65, metaRowH = 6.5;
    const metaRows = [
        { label: 'MONTH / YEAR :', value: `${monthName} ${year}` },
        { label: 'SHEET NO.    :', value: sheetNo },
        { label: 'LOCATION     :', value: roomName },
    ];
    doc.setDrawColor(80,100,120); doc.setLineWidth(0.3);
    metaRows.forEach((r, i) => {
        const ry = metaY + i * metaRowH;
        doc.rect(metaX, ry, metaW, metaRowH);
        doc.setFont('helvetica','bold'); doc.setFontSize(7.5); doc.setTextColor(5,48,79);
        doc.text(r.label, metaX + 2, ry + metaRowH * 0.68);
        doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(5,48,79);
        doc.text(r.value, metaX + metaW - 2, ry + metaRowH * 0.68, { align: 'right' });
    });

    const titleY = mT + 26;
    doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(5,48,79);
    doc.text('CALIBRATION ROOM TEMPERATURE AND HUMIDITY MONITORING SHEET', pageW / 2, titleY, { align: 'center' });
    doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(60,80,100);
    doc.text('Reference: PC2-9710 Calibration Control Procedure', pageW / 2, titleY + 5.5, { align: 'center' });

    const tableStartY = titleY + 10;
    const NW_FILL = [212, 212, 212];

    doc.autoTable({
        head: [
            [
                { content: 'Day',              rowSpan: 2, styles: { halign:'center', valign:'middle' } },
                { content: 'Time',             rowSpan: 2, styles: { halign:'center', valign:'middle' } },
                { content: 'Temperature (°C)', colSpan: 3, styles: { halign:'center' } },
                { content: 'Humidity (%)',     colSpan: 3, styles: { halign:'center' } },
                { content: 'REMARKS',          rowSpan: 2, styles: { halign:'center', valign:'middle' } },
                { content: 'CHECKED BY',       rowSpan: 2, styles: { halign:'center', valign:'middle' } },
            ],
            [
                { content:'Max', styles:{halign:'center'} }, { content:'Min', styles:{halign:'center'} }, { content:'Actual', styles:{halign:'center'} },
                { content:'Max', styles:{halign:'center'} }, { content:'Min', styles:{halign:'center'} }, { content:'Actual', styles:{halign:'center'} },
            ],
        ],
        body: tableRows.map(r => [r.day, r.time, r.t_max, r.t_min, r.t_actual, r.h_max, r.h_min, r.h_actual, r.remarks, r.checked_by]),
        startY: tableStartY,
        margin: { left: mL, right: mR, bottom: 55 },
        theme: 'grid', rowPageBreak: 'avoid',
        tableLineColor: [100,120,140], tableLineWidth: 0.25,
        styles: { fontSize:7, cellPadding:{top:1.2,bottom:1.2,left:1.5,right:1.5}, font:'helvetica', textColor:[28,43,58], lineColor:[180,195,210], lineWidth:0.2, overflow:'linebreak' },
        headStyles: { fillColor:[5,48,79], textColor:[255,255,255], fontStyle:'bold', fontSize:7, lineColor:[100,140,180], lineWidth:0.25, valign:'middle', halign:'center' },
        columnStyles: {
            0: { cellWidth:10,  halign:'center', fontStyle:'bold' },
            1: { cellWidth:18,  halign:'center' },
            2: { cellWidth:16,  halign:'center' },
            3: { cellWidth:16,  halign:'center' },
            4: { cellWidth:16,  halign:'center' },
            5: { cellWidth:16,  halign:'center' },
            6: { cellWidth:16,  halign:'center' },
            7: { cellWidth:16,  halign:'center' },
            8: { cellWidth:'auto' },
            9: { cellWidth:28,  halign:'center' },
        },
        didParseCell: function(data) {
            if (data.section === 'body' && tableRows[data.row.index]?.is_nonworking) {
                data.cell.styles.fillColor = NW_FILL;
                data.cell.styles.textColor = [130,130,130];
            }
        },
        didDrawPage: function() {
            const pn = doc.internal.getCurrentPageInfo().pageNumber;
            doc.setFontSize(6.5); doc.setTextColor(150);
            doc.text(`Page ${pn}`, pageW / 2, pageH - 5, { align: 'center' });
        },
    });

    const afterTableY = doc.lastAutoTable.finalY + 3;
    doc.setFontSize(6.5); doc.setFont('helvetica','italic'); doc.setTextColor(120);
    doc.text('PES.CAL08055.R4', pageW - mR, afterTableY + 3, { align: 'right' });

    doc.setFont('helvetica','bold'); doc.setFontSize(7.5); doc.setTextColor(28,43,58);
    doc.text('Tolerance:', mL, afterTableY + 3);
    doc.setFont('helvetica','normal');
    doc.text('Temperature ( °C ):  23 °C ± 5 °C', mL + 22, afterTableY + 3);
    doc.text('Humidity (%):  50 % ± 20%', mL + 22, afterTableY + 8);
    doc.setFont('helvetica','bold');
    doc.text('Note:', mL, afterTableY + 14);
    doc.setFont('helvetica','normal');
    doc.text('During Dimensional Calibration', mL + 22, afterTableY + 14);
    doc.text('Temperature ( °C ):  20 °C ± 2 °C', mL + 22, afterTableY + 19);
    doc.text('Humidity (%):  55 % ± 15%', mL + 22, afterTableY + 24);

    doc.setPage(doc.internal.getNumberOfPages());
    const FOOTER_TOTAL_H = 38;
    const footerTop = pageH - FOOTER_TOTAL_H - 6;
    const GAP    = 4;
    const revW   = usableW * 0.70;
    const deptW  = usableW - revW - GAP;
    const deptX  = mL + revW + GAP;
    const secApprW = 28;
    const revDescW = revW - 10 - 18 - 22 - secApprW;
    const revCols  = [10, 18, 22, revDescW, secApprW];
    const revHeadH = 6.5;
    const revRowH  = 5.2;
    const nRevRows = revisions.length;
    const revBodyH = revHeadH + revRowH * nRevRows;

    doc.setFillColor(5,48,79);
    doc.rect(mL, footerTop, revW, revHeadH, 'F');
    doc.setFont('helvetica','bold'); doc.setFontSize(5.5); doc.setTextColor(255,255,255);
    const revColHeaders = ['Rev. Code', 'Rev. Date', 'Revised By', 'Nature/Description of Revision', 'Approved by:\n(Section Manager)'];
    let rx = mL;
    revColHeaders.forEach((h, i) => {
        const cw = revCols[i];
        const lines = h.split('\n');
        const startY = footerTop + (lines.length > 1 ? 1.8 : 2.6);
        lines.forEach((line, li) => doc.text(line, rx + cw/2, startY + li * 2.7, { align:'center', maxWidth: cw - 1 }));
        if (i < revColHeaders.length - 1) {
            doc.setDrawColor(255,255,255); doc.setLineWidth(0.15);
            doc.line(rx + cw, footerTop, rx + cw, footerTop + revHeadH);
        }
        rx += cw;
    });

    revisions.forEach((rev, ri) => {
        const ry = footerTop + revHeadH + ri * revRowH;
        if (ri % 2 === 1) { doc.setFillColor(248,250,252); doc.rect(mL, ry, revW - secApprW, revRowH, 'F'); }
        doc.setDrawColor(200,210,220); doc.setLineWidth(0.15);
        doc.line(mL, ry + revRowH, mL + revW, ry + revRowH);
        const rowVals = [rev.code, rev.date, rev.by, rev.desc];
        let rrx = mL;
        doc.setFont('helvetica','normal'); doc.setFontSize(6); doc.setTextColor(28,43,58);
        rowVals.forEach((v, i) => {
            const cw = revCols[i];
            const align = i === 0 ? 'center' : 'left';
            const tx    = i === 0 ? rrx + cw/2 : rrx + 2;
            doc.text(v || '', tx, ry + revRowH * 0.68, { align, maxWidth: cw - 3 });
            doc.setDrawColor(200,210,220); doc.setLineWidth(0.15);
            doc.line(rrx + cw, ry, rrx + cw, ry + revRowH);
            rrx += cw;
        });
    });

    doc.setDrawColor(120,140,160); doc.setLineWidth(0.3);
    doc.rect(mL, footerTop, revW, revBodyH);

    const secApprX    = mL + revCols[0] + revCols[1] + revCols[2] + revCols[3];
    const secApprBodyH = revRowH * nRevRows;
    const secApprBodyY = footerTop + revHeadH;
    doc.setFillColor(255,255,255);
    doc.rect(secApprX, secApprBodyY, secApprW, secApprBodyH, 'F');
    try {
        const sig1 = await loadImg('images/Signature-1.png');
        const maxW = secApprW - 4, maxH = secApprBodyH - 3;
        const ratio = Math.min(maxW / (sig1.w / 3.7795), maxH / (sig1.h / 3.7795));
        const sw = (sig1.w / 3.7795) * ratio, sh = (sig1.h / 3.7795) * ratio;
        doc.addImage(sig1.dataUrl, 'PNG', secApprX + (secApprW - sw) / 2, secApprBodyY + (secApprBodyH - sh) / 2, sw, sh);
    } catch(e) {}

    doc.setFillColor(5,48,79);
    doc.rect(deptX, footerTop, deptW, revHeadH, 'F');
    doc.setFont('helvetica','bold'); doc.setFontSize(5.5); doc.setTextColor(255,255,255);
    doc.text('Approved by:', deptX + deptW/2, footerTop + 2.2, { align:'center' });
    doc.text('QA Section Mgr. / Dept. Mgr.', deptX + deptW/2, footerTop + 5, { align:'center', maxWidth: deptW - 2 });

    doc.setFillColor(255,255,255);
    doc.rect(deptX, footerTop + revHeadH, deptW, revBodyH - revHeadH, 'F');
    const deptBodyY = footerTop + revHeadH;
    const deptBodyH = revBodyH - revHeadH;
    try {
        const sig2 = await loadImg('images/Signature-2.png');
        const maxW = deptW * 1.4, maxH = deptBodyH * 1.4;
        const ratio = Math.min(maxW / (sig2.w / 3.7795), maxH / (sig2.h / 3.7795));
        const sw = (sig2.w / 3.7795) * ratio, sh = (sig2.h / 3.7795) * ratio;
        doc.addImage(sig2.dataUrl, 'PNG', deptX + (deptW - sw) / 2, deptBodyY + (deptBodyH - sh) / 2, sw, sh);
    } catch(e) {}

    doc.setFont('helvetica','bold'); doc.setFontSize(5.5); doc.setTextColor(80,100,120);
    doc.text('QA Section Mgr. / Dept. Mgr.', deptX + deptW / 2, footerTop + revBodyH - 1.5, { align:'center', maxWidth: deptW - 2 });

    doc.setDrawColor(120,140,160); doc.setLineWidth(0.3);
    doc.rect(deptX, footerTop, deptW, revBodyH);

    const fileName = `TH_Monitoring_${sheetNo}_${monthName}${year}.pdf`;
    doc.save(fileName);
    closeExport();
});

document.addEventListener('DOMContentLoaded', () => {
    updateSheetLabel();
    loadedTabs.add('cal');
    loadSheet('cal');
});
</script>

</body>
</html>