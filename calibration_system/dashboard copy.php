<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "db.php";

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

function formatMonthYear($date) {
    if (empty($date) || $date === '0000-00-00') return '';
    return date('M-y', strtotime($date));
}

function formatDateExcel($date) {
    if (empty($date) || $date === '0000-00-00') return '';
    return date('d-M-y', strtotime($date));
}

$today      = date("Y-m-d");
$monthStart = date("Y-m-01");
$monthEnd   = date("Y-m-t");

/* ── Calibration: Due Today ── */
$stmt = $pdo->prepare("SELECT * FROM calibration_report WHERE next_calibration = :today AND present_status = 'Good'");
$stmt->bindValue(':today', $today);
$stmt->execute();
$rowsDueToday  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dueTodayCount = count($rowsDueToday);

/* ── Standard Samples: Due Today ── */
$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE next_calibration_date = :today AND present_status = 'Good' ORDER BY next_calibration_date ASC");
$stmt->bindValue(':today', $today);
$stmt->execute();
$rowsSamplesToday  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$samplesTodayCount = count($rowsSamplesToday);

/* ── Calibration: This Month (excludes overdue) ── */
$stmt = $pdo->prepare("SELECT * FROM calibration_report WHERE next_calibration >= :monthStart AND next_calibration <= :monthEnd AND next_calibration > :today AND present_status = 'Good' ORDER BY next_calibration ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->bindValue(':monthEnd',   $monthEnd);
$stmt->bindValue(':today',      $today);
$stmt->execute();
$rowsMonth   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthSDP    = [];
$monthNonSDP = [];
foreach ($rowsMonth as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $monthSDP[] = $row;
    else $monthNonSDP[] = $row;
}

/* ── Standard Samples: This Month ── */
$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE next_calibration_date >= :monthStart AND next_calibration_date <= :monthEnd AND next_calibration_date > :today AND present_status = 'Good' ORDER BY next_calibration_date ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->bindValue(':monthEnd',   $monthEnd);
$stmt->bindValue(':today',      $today);
$stmt->execute();
$rowsSamplesMonth   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthSamplesSDP    = [];
$monthSamplesNonSDP = [];
foreach ($rowsSamplesMonth as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $monthSamplesSDP[] = $row;
    else $monthSamplesNonSDP[] = $row;
}
$monthCalTotal     = count($monthSDP) + count($monthNonSDP);
$monthSamplesTotal = count($monthSamplesSDP) + count($monthSamplesNonSDP);

/* ── Calibration: Overdue ── */
$stmt = $pdo->prepare("SELECT * FROM calibration_report WHERE next_calibration < :today AND present_status = 'Good' ORDER BY next_calibration ASC");
$stmt->bindValue(':today', $today);
$stmt->execute();
$rowsOverdue   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$overdueCount  = count($rowsOverdue);
$overdueSDP    = [];
$overdueNonSDP = [];
foreach ($rowsOverdue as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $overdueSDP[] = $row;
    else $overdueNonSDP[] = $row;
}

/* ── Standard Samples: Overdue ── */
$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE next_calibration_date < :today AND present_status = 'Good' ORDER BY next_calibration_date ASC");
$stmt->bindValue(':today', $today);
$stmt->execute();
$rowsSamplesOverdue   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$overdueSamplesSDP    = [];
$overdueSamplesNonSDP = [];
foreach ($rowsSamplesOverdue as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $overdueSamplesSDP[] = $row;
    else $overdueSamplesNonSDP[] = $row;
}
$overdueSamplesCount = count($overdueSamplesSDP) + count($overdueSamplesNonSDP);

/* ── Inspection: Overdue — only Good result ── */
$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE next_inspection < :monthStart AND inspection_result = 'Good' ORDER BY next_inspection ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->execute();
$rowsInspectionOverdue  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$overdueInspectionCount = count($rowsInspectionOverdue);

/* ── Inspection: This Month — only Good result ── */
$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE next_inspection BETWEEN :monthStart AND :monthEnd AND inspection_result = 'Good' ORDER BY next_inspection ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->bindValue(':monthEnd',   $monthEnd);
$stmt->execute();
$rowsInspectionMonth  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$inspectionMonthCount = count($rowsInspectionMonth);

$dueTodaySlide  = ($dueTodayCount === 0 && $samplesTodayCount  > 0) ? 1 : 0;
$overdueSlide   = ($overdueCount  === 0 && $overdueSamplesCount > 0) ? 1 : 0;
$thisMonthSlide = ($monthCalTotal  === 0 && $monthSamplesTotal  > 0) ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">

<!-- Sidebar anti-flicker: must run before any CSS is parsed -->
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
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
:root {
    --db-bg:          #f0f4f8;
    --db-card-bg:     #ffffff;
    --db-navy:        #05304f;
    --db-accent:      #1a90d9;
    --db-accent-soft: rgba(26,144,217,0.1);
    --db-border:      #dde4ec;
    --db-text:        #1c2b3a;
    --db-text-muted:  #64788a;
    --db-radius:      12px;
    --db-shadow:      0 2px 12px rgba(5,48,79,0.08);
    --db-shadow-lg:   0 8px 32px rgba(5,48,79,0.12);
    --row-today:      #dbeeff;
    --row-overdue:    #fde8e8;
    --row-urgent:     #fff3cd;
    --row-warning:    #e3ffbf;
    --row-normal:     #f0faf0;
}
body          { font-family: 'DM Sans', sans-serif; background: var(--db-bg); color: var(--db-text); }
.main-content { padding: 92px 28px 36px; min-height: 100vh; }
.db-stat-bar  { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; margin-bottom: 24px; }
.db-stat-card {
    background: var(--db-card-bg); border-radius: var(--db-radius);
    padding: 18px 20px; display: flex; align-items: center; gap: 14px;
    box-shadow: var(--db-shadow); border: 1px solid var(--db-border);
    transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
    cursor: pointer; user-select: none;
}
.db-stat-card:hover {
    box-shadow: var(--db-shadow-lg);
    transform: translateY(-2px);
    border-color: var(--db-accent);
}
.db-stat-card:hover .db-stat-value { color: var(--db-accent); }
.db-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.db-stat-icon.si-today   { background: #dbeeff; }
.db-stat-icon.si-overdue { background: #fde8e8; }
.db-stat-icon.si-month   { background: #f0faf0; }
.db-stat-icon.si-insp-ov { background: #fde8e8; }
.db-stat-icon.si-insp    { background: #fef3dc; }
.db-stat-value { font-size: 26px; font-weight: 700; line-height: 1; color: var(--db-navy); font-variant-numeric: tabular-nums; transition: color 0.2s; }
.db-stat-label { font-size: 11.5px; font-weight: 500; color: var(--db-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }
.db-card { background: var(--db-card-bg); border-radius: var(--db-radius); box-shadow: var(--db-shadow); border: 1px solid var(--db-border); margin-bottom: 20px; overflow: hidden; }
.db-card-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 22px; border-bottom: 1px solid var(--db-border); background: linear-gradient(135deg, #f8fafc 0%, #fff 100%); }
.db-card-title  { display: flex; align-items: center; gap: 10px; font-size: 14.5px; font-weight: 700; color: var(--db-navy); }
.db-card-title-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; }
.db-card-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; font-family: 'DM Mono', monospace; }
.cb-today   { background: #dbeeff; color: #0b5c96; }
.cb-overdue { background: #fde8e8; color: #b91c1c; }
.cb-month   { background: #f0faf0; color: #166534; }
.cb-insp-ov { background: #fde8e8; color: #b91c1c; }
.cb-insp    { background: #fef3dc; color: #92400e; }
.db-card-body { padding: 18px 22px; }
.db-sub-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--db-text-muted); margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--db-border); }
.db-split     { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.db-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.db-table thead tr { background: var(--db-navy); }
.db-table th { padding: 9px 12px; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.85); text-align: left; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
.db-table th:first-child { border-radius: 6px 0 0 0; }
.db-table th:last-child  { border-radius: 0 6px 0 0; }
.db-table td { padding: 9px 12px; border-bottom: 1px solid var(--db-border); vertical-align: middle; color: var(--db-text); font-size: 13px; }
.db-table tr:last-child td { border-bottom: none; }
.db-table td:first-child { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--db-text-muted); width: 32px; }
.db-clickable { cursor: pointer; transition: background 0.15s, box-shadow 0.15s; }
.db-clickable:hover { background: rgba(26,144,217,0.08) !important; box-shadow: inset 3px 0 0 var(--db-accent); }
.row-today    { background: var(--row-today); }
.row-overdue  { background: var(--row-overdue); }
.row-urgent   { background: var(--row-urgent); }
.row-warning  { background: var(--row-warning); }
.row-normal   { background: var(--row-normal); }
.db-empty { text-align: center; padding: 28px 16px; color: var(--db-text-muted); font-size: 13px; }
.db-empty span { display: block; font-size: 28px; margin-bottom: 6px; }
.db-legend { display: flex; gap: 14px; margin-top: 14px; flex-wrap: wrap; }
.db-legend span { font-size: 11px; color: var(--db-text-muted); display: flex; align-items: center; gap: 5px; }
.db-legend-dot  { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.db-slide { display: none; }
.db-slide.active { display: block; }
.db-carousel-controls { display: flex; align-items: center; justify-content: space-between; padding: 14px 0 0; }
.db-carousel-indicator { font-size: 12px; color: var(--db-text-muted); font-family: 'DM Mono', monospace; }
.db-carousel-btns { display: flex; gap: 8px; }
.db-carousel-btn { background: var(--db-navy); color: #fff; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: background 0.18s; padding: 0; }
.db-carousel-btn:hover { background: var(--db-accent); }
.db-overlay { position: fixed; inset: 0; background: rgba(5,48,79,0.55); display: none; justify-content: center; align-items: center; z-index: 1000; }
.db-overlay.open { display: flex; }
.db-modal-box { background: #ffffff; color: var(--db-text); border-radius: 16px; width: 92%; max-width: 620px; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 64px rgba(5,48,79,0.24); }
.db-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid var(--db-border); background: linear-gradient(135deg, #f8fafc, #fff); }
.db-modal-header h2 { font-size: 15px; font-weight: 700; color: var(--db-navy); margin: 0; }
.db-modal-close { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--db-border); background: transparent; cursor: pointer; font-size: 18px; color: var(--db-text-muted); display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; font-family: 'DM Sans', sans-serif; transition: background 0.15s, color 0.15s; }
.db-modal-close:hover { background: #fde8e8; color: #b91c1c; border-color: #fca5a5; }
.db-modal-body { padding: 20px 22px; background: #ffffff; }
.db-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.db-form-grid .span2 { grid-column: 1 / -1; }
.db-fg { display: flex; flex-direction: column; gap: 5px; }
.db-fg label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--db-text-muted); }
.db-fg input, .db-fg select { padding: 9px 12px; border-radius: 8px; border: 1px solid var(--db-border); font-size: 13.5px; font-family: 'DM Sans', sans-serif; color: var(--db-text); background: #f8fafc; transition: border-color 0.15s, box-shadow 0.15s; }
.db-fg input:focus, .db-fg select:focus { outline: none; border-color: var(--db-accent); box-shadow: 0 0 0 3px rgba(26,144,217,0.12); background: #fff; color: var(--db-text); }
.db-fg input[readonly] { background: #f0f4f8; color: var(--db-text-muted); cursor: not-allowed; }
.db-fg select:disabled  { background: #f0f4f8; color: var(--db-text-muted); cursor: not-allowed; }
.db-overdue-warn { grid-column: 1 / -1; display: none; background: #fef3dc; border: 1px solid #f59e0b; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; color: #92400e; line-height: 1.5; }
.db-modal-footer { display: flex; gap: 10px; padding: 16px 22px; border-top: 1px solid var(--db-border); background: #f8fafc; border-radius: 0 0 16px 16px; }
.db-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; border: none; transition: background 0.18s; }
.db-btn-primary   { background: var(--db-navy); color: #ffffff; box-shadow: 0 2px 8px rgba(5,48,79,0.2); }
.db-btn-primary:hover { background: #07406e; color: #ffffff; }
.db-btn-secondary { background: var(--db-accent-soft); color: var(--db-accent); border: 1px solid rgba(26,144,217,0.25); }
.db-btn-secondary:hover { background: rgba(26,144,217,0.16); color: var(--db-accent); }
@media (max-width: 900px) { .db-split { grid-template-columns: 1fr; } .db-stat-bar { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 540px) { .db-stat-bar { grid-template-columns: repeat(2,1fr); } .main-content { padding: 80px 14px 28px; } }
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
<?php include 'includes/header.php'; ?>

<div class="db-stat-bar">
    <div class="db-stat-card"
         onclick="scrollToCard('section-due-today', <?= $dueTodaySlide ?>)"
         title="Jump to Due Today">
        <div class="db-stat-icon si-today">📅</div>
        <div><div class="db-stat-value"><?= $dueTodayCount + $samplesTodayCount ?></div><div class="db-stat-label">Due Today</div></div>
    </div>
    <div class="db-stat-card"
         onclick="scrollToCard('section-overdue', <?= $overdueSlide ?>)"
         title="Jump to Overdue Items">
        <div class="db-stat-icon si-overdue">⚠️</div>
        <div><div class="db-stat-value"><?= $overdueCount + $overdueSamplesCount ?></div><div class="db-stat-label">Overdue</div></div>
    </div>
    <div class="db-stat-card"
         onclick="scrollToCard('section-this-month', <?= $thisMonthSlide ?>)"
         title="Jump to This Month's Schedule">
        <div class="db-stat-icon si-month">📆</div>
        <div><div class="db-stat-value"><?= $monthCalTotal + $monthSamplesTotal ?></div><div class="db-stat-label">This Month</div></div>
    </div>
    <div class="db-stat-card"
         onclick="scrollToCard('section-insp-overdue')"
         title="Jump to Overdue Inspections">
        <div class="db-stat-icon si-insp-ov">🔴</div>
        <div><div class="db-stat-value"><?= $overdueInspectionCount ?></div><div class="db-stat-label">Insp. Overdue</div></div>
    </div>
    <div class="db-stat-card"
         onclick="scrollToCard('section-insp-month')"
         title="Jump to This Month's Inspection">
        <div class="db-stat-icon si-insp">📋</div>
        <div><div class="db-stat-value"><?= $inspectionMonthCount ?></div><div class="db-stat-label">Inspections</div></div>
    </div>
</div>

<!-- DUE TODAY -->
<div class="db-card" id="section-due-today">
    <div class="db-card-header">
        <div class="db-card-title"><div class="db-card-title-icon cb-today">📅</div>Due Today</div>
        <span class="db-card-badge cb-today"><?= $dueTodayCount + $samplesTodayCount ?> item<?= ($dueTodayCount + $samplesTodayCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-card-body">
        <div data-carousel>
            <div class="db-slide active">
                <p class="db-sub-label">Calibration Report</p>
                <?php if ($dueTodayCount > 0): ?>
                <table class="db-table"><thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                <?php $i = 1; foreach ($rowsDueToday as $row): ?>
                <tr class="db-clickable row-today" data-cal-id="<?= $row['id'] ?>">
                    <td><?= $i++ ?></td><td><?= htmlspecialchars($row['machine_code']) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= formatDateExcel($row['next_calibration']) ?></td>
                    <td><?= htmlspecialchars($row['calibrator']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="db-empty"><span>😊</span>No calibration due today</div><?php endif; ?>
            </div>
            <div class="db-slide">
                <p class="db-sub-label">Standard Samples</p>
                <?php if ($samplesTodayCount > 0): ?>
                <table class="db-table"><thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                <?php $i = 1; foreach ($rowsSamplesToday as $row): ?>
                <tr class="db-clickable row-today" data-sample-id="<?= $row['id'] ?>">
                    <td><?= $i++ ?></td><td><?= htmlspecialchars($row['description']) ?></td>
                    <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                    <td><?= htmlspecialchars($row['calibrator']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="db-empty"><span>😊</span>No standard samples due today</div><?php endif; ?>
            </div>
            <div class="db-carousel-controls">
                <span class="db-carousel-indicator">Slide <span class="cur">1</span> / 2</span>
                <div class="db-carousel-btns"><button class="db-carousel-btn prevSlideBtn">&#8592;</button><button class="db-carousel-btn nextSlideBtn">&#8594;</button></div>
            </div>
        </div>
    </div>
</div>

<!-- OVERDUE ITEMS -->
<div class="db-card" id="section-overdue">
    <div class="db-card-header">
        <div class="db-card-title"><div class="db-card-title-icon cb-overdue">⚠️</div>Overdue Items</div>
        <span class="db-card-badge cb-overdue"><?= $overdueCount + $overdueSamplesCount ?> item<?= ($overdueCount + $overdueSamplesCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-card-body">
        <div data-carousel>
            <div class="db-slide active">
                <p class="db-sub-label">Overdue Calibration</p>
                <div class="db-split">
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">Internal (SDP)</p>
                        <?php if (count($overdueSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($overdueSDP as $row): ?>
                        <tr class="db-clickable row-overdue" data-cal-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['machine_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No internal overdue</div><?php endif; ?>
                    </div>
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">External</p>
                        <?php if (count($overdueNonSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($overdueNonSDP as $row): ?>
                        <tr class="db-clickable row-overdue" data-cal-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['machine_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No external overdue</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="db-slide">
                <p class="db-sub-label">Overdue Standard Samples</p>
                <div class="db-split">
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">Internal (SDP)</p>
                        <?php if (count($overdueSamplesSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($overdueSamplesSDP as $row): ?>
                        <tr class="db-clickable row-overdue" data-sample-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No internal overdue</div><?php endif; ?>
                    </div>
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">External</p>
                        <?php if (count($overdueSamplesNonSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($overdueSamplesNonSDP as $row): ?>
                        <tr class="db-clickable row-overdue" data-sample-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No external overdue</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="db-carousel-controls">
                <span class="db-carousel-indicator">Slide <span class="cur">1</span> / 2</span>
                <div class="db-carousel-btns"><button class="db-carousel-btn prevSlideBtn">&#8592;</button><button class="db-carousel-btn nextSlideBtn">&#8594;</button></div>
            </div>
        </div>
    </div>
</div>

<!-- THIS MONTH'S SCHEDULE -->
<div class="db-card" id="section-this-month">
    <div class="db-card-header">
        <div class="db-card-title"><div class="db-card-title-icon cb-month">📆</div>This Month's Schedule</div>
        <span class="db-card-badge cb-month"><?= $monthCalTotal + $monthSamplesTotal ?> item<?= (($monthCalTotal + $monthSamplesTotal) !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-card-body">
        <div data-carousel>
            <div class="db-slide active">
                <p class="db-sub-label">Calibration Report <span style="font-size:10px;font-weight:500;color:var(--db-text-muted);margin-left:6px;font-family:'DM Mono',monospace;"><?= $monthCalTotal ?> item<?= $monthCalTotal !== 1 ? 's' : '' ?></span></p>
                <div class="db-split">
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">Internal (SDP)</p>
                        <?php if (count($monthSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($monthSDP as $row):
                            $dl = ceil((strtotime($row['next_calibration']) - strtotime($today)) / 86400);
                            $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warning' : 'row-normal');
                        ?>
                        <tr class="db-clickable <?= $rc ?>" data-cal-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['machine_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No internal calibrations this month</div><?php endif; ?>
                    </div>
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">External</p>
                        <?php if (count($monthNonSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($monthNonSDP as $row):
                            $dl = ceil((strtotime($row['next_calibration']) - strtotime($today)) / 86400);
                            $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warning' : 'row-normal');
                        ?>
                        <tr class="db-clickable <?= $rc ?>" data-cal-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['machine_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No external calibrations this month</div><?php endif; ?>
                    </div>
                </div>
                <div class="db-legend">
                    <span><span class="db-legend-dot" style="background:#fff3cd;border:1px solid #e6ac00;"></span>&le; 7 days</span>
                    <span><span class="db-legend-dot" style="background:var(--row-warning)"></span>&le; 14 days</span>
                    <span><span class="db-legend-dot" style="background:var(--row-normal)"></span>On track</span>
                </div>
            </div>
            <div class="db-slide">
                <p class="db-sub-label">Standard Samples <span style="font-size:10px;font-weight:500;color:var(--db-text-muted);margin-left:6px;font-family:'DM Mono',monospace;"><?= $monthSamplesTotal ?> item<?= $monthSamplesTotal !== 1 ? 's' : '' ?></span></p>
                <div class="db-split">
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">Internal (SDP)</p>
                        <?php if (count($monthSamplesSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($monthSamplesSDP as $row):
                            $dl = ceil((strtotime($row['next_calibration_date']) - strtotime($today)) / 86400);
                            $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warning' : 'row-normal');
                        ?>
                        <tr class="db-clickable <?= $rc ?>" data-sample-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No internal standard samples this month</div><?php endif; ?>
                    </div>
                    <div>
                        <p class="db-sub-label" style="font-size:10px;">External</p>
                        <?php if (count($monthSamplesNonSDP) > 0): ?>
                        <table class="db-table"><thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead><tbody>
                        <?php $i = 1; foreach ($monthSamplesNonSDP as $row):
                            $dl = ceil((strtotime($row['next_calibration_date']) - strtotime($today)) / 86400);
                            $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warning' : 'row-normal');
                        ?>
                        <tr class="db-clickable <?= $rc ?>" data-sample-id="<?= $row['id'] ?>">
                            <td><?= $i++ ?></td><td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            <td><?= htmlspecialchars($row['calibrator']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php else: ?><div class="db-empty"><span>😊</span>No external standard samples this month</div><?php endif; ?>
                    </div>
                </div>
                <div class="db-legend">
                    <span><span class="db-legend-dot" style="background:#fff3cd;border:1px solid #e6ac00;"></span>&le; 7 days</span>
                    <span><span class="db-legend-dot" style="background:var(--row-warning)"></span>&le; 14 days</span>
                    <span><span class="db-legend-dot" style="background:var(--row-normal)"></span>On track</span>
                </div>
            </div>
            <div class="db-carousel-controls">
                <span class="db-carousel-indicator">Slide <span class="cur">1</span> / 2</span>
                <div class="db-carousel-btns"><button class="db-carousel-btn prevSlideBtn">&#8592;</button><button class="db-carousel-btn nextSlideBtn">&#8594;</button></div>
            </div>
        </div>
    </div>
</div>

<!-- OVERDUE INSPECTIONS -->
<div class="db-card" id="section-insp-overdue">
    <div class="db-card-header">
        <div class="db-card-title"><div class="db-card-title-icon cb-insp-ov">🔴</div>Overdue Inspections</div>
        <span class="db-card-badge cb-insp-ov"><?= $overdueInspectionCount ?> item<?= ($overdueInspectionCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-card-body">
        <?php if ($overdueInspectionCount > 0): ?>
        <table class="db-table">
            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Was Due</th><th>Result</th></tr></thead>
            <tbody>
            <?php $i = 1; foreach ($rowsInspectionOverdue as $row): ?>
            <tr class="db-clickable row-overdue" data-insp-id="<?= $row['id'] ?>" data-overdue="1">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= formatMonthYear($row['next_inspection']) ?></td>
                <td><?= htmlspecialchars($row['inspection_result']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="db-empty"><span>😊</span>No overdue inspections</div>
        <?php endif; ?>
    </div>
</div>

<!-- THIS MONTH'S INSPECTION -->
<div class="db-card" id="section-insp-month">
    <div class="db-card-header">
        <div class="db-card-title"><div class="db-card-title-icon cb-insp">📋</div>This Month's Inspection</div>
        <span class="db-card-badge cb-insp"><?= $inspectionMonthCount ?> item<?= ($inspectionMonthCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-card-body">
        <?php if ($inspectionMonthCount > 0): ?>
        <table class="db-table">
            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Next Inspection</th><th>Result</th></tr></thead>
            <tbody>
            <?php $i = 1; foreach ($rowsInspectionMonth as $row): ?>
            <tr class="db-clickable" data-insp-id="<?= $row['id'] ?>" data-overdue="0">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= htmlspecialchars($row['equipment_code']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= formatMonthYear($row['next_inspection']) ?></td>
                <td><?= htmlspecialchars($row['inspection_result']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="db-empty"><span>📋</span>No inspections scheduled this month</div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.main-content -->

<!-- CALIBRATION MODAL -->
<div id="calModal" class="db-overlay">
    <div class="db-modal-box">
        <div class="db-modal-header"><h2>Edit Calibration</h2><button class="db-modal-close" id="calModalClose">&times;</button></div>
        <div class="db-modal-body">
            <input type="hidden" id="cal_id">
            <div class="db-form-grid">
                <div class="db-fg span2"><label>Description</label><input type="text" id="cal_description" readonly></div>
                <div class="db-fg"><label>Machine Code</label><input type="text" id="cal_machine_code" readonly></div>
                <div class="db-fg"><label>Location</label><input type="text" id="cal_location" readonly></div>
                <div class="db-fg"><label>Calibration Date</label><input type="date" id="cal_calibration_date" readonly></div>
                <div class="db-fg"><label>Next Calibration</label><input type="date" id="cal_next_calibration" readonly></div>
                <div class="db-fg"><label>Frequency</label>
                    <select id="cal_frequency">
                        <option value="">Select frequency</option>
                        <option value="6">6 months</option>
                        <option value="12">1 year</option>
                        <option value="24">2 years</option>
                        <option value="60">5 years</option>
                    </select>
                </div>
                <div class="db-fg"><label>Calibrator</label><input type="text" id="cal_calibrator" readonly></div>
                <div class="db-fg"><label>Status</label>
                    <select id="cal_status">
                        <option value="Good">Good</option>
                        <option value="Not Good">Not Good</option>
                        <option value="Disposed">Disposed</option>
                        <option value="For Disposal">For Disposal</option>
                        <option value="For Repair">For Repair</option>
                        <option value="For Replacement">For Replacement</option>
                        <option value="Missing">Missing</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="db-fg"><label>Completed Date</label><input type="date" id="cal_completed_date"></div>
                <div class="db-overdue-warn" id="cal_overdue_warn">⚠ This item is overdue. If calibration was done earlier, adjust the date.</div>
            </div>
        </div>
        <div class="db-modal-footer">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="calDoneBtn">&#10003; Mark as Done</button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-secondary" id="calViewBtn">View Full Record</button>
        </div>
    </div>
</div>

<!-- EXCLUSION CONFIRM MODAL -->
<div id="exclusionModal" class="db-overlay" style="z-index:1010;">
    <div class="db-modal-box" style="max-width:460px;">
        <div class="db-modal-header">
            <h2>⚠ Equipment Status Changed</h2>
            <button class="db-modal-close" id="exclusionModalClose">&times;</button>
        </div>
        <div class="db-modal-body">
            <div style="background:#fef3dc;border:1px solid #f59e0b;border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#92400e;line-height:1.55;">
                This equipment's status has changed from <strong>Good</strong> to a non-Good status.
                It will be automatically added to the <strong>Exclusion Summary</strong>.
                Please fill in the details below.
            </div>
            <div class="db-form-grid">
                <div class="db-fg span2">
                    <label>Equipment</label>
                    <input type="text" id="excl_equipment_display" readonly style="background:#f0f4f8;color:var(--db-text-muted);">
                </div>
                <div class="db-fg span2">
                    <label>New Status</label>
                    <input type="text" id="excl_status_display" readonly style="background:#fde8e8;color:#b91c1c;font-weight:600;">
                </div>
                <div class="db-fg span2">
                    <label>Remarks <span style="color:#b91c1c;">*</span></label>
                    <textarea id="excl_remarks" rows="3"
                        placeholder="Reason for status change, condition details, etc."
                        style="padding:9px 12px;border-radius:8px;border:1px solid var(--db-border);
                               font-size:13.5px;font-family:'DM Sans',sans-serif;
                               color:var(--db-text);background:#f8fafc;resize:vertical;
                               transition:border-color .15s,box-shadow .15s;width:100%;box-sizing:border-box;"></textarea>
                </div>
                <div class="db-fg span2">
                    <label>Recorded By <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="excl_recorded_by" placeholder="Enter your name">
                </div>
                <div id="excl_error" style="display:none;grid-column:1/-1;background:#fde8e8;border:1px solid #fca5a5;border-radius:7px;padding:8px 12px;font-size:12.5px;color:#b91c1c;"></div>
            </div>
        </div>
        <div class="db-modal-footer" style="justify-content:flex-end;">
            <button type="button" class="db-btn db-btn-secondary" id="exclusionCancelBtn">Cancel</button>
            <button type="button" class="db-btn db-btn-primary"   id="exclusionConfirmBtn">✓ Confirm &amp; Save</button>
        </div>
    </div>
</div>

<!-- STANDARD SAMPLE MODAL -->
<div id="sampleModal" class="db-overlay">
    <div class="db-modal-box">
        <div class="db-modal-header"><h2>Edit Standard Sample</h2><button class="db-modal-close" id="sampleModalClose">&times;</button></div>
        <div class="db-modal-body">
            <input type="hidden" id="sample_id">
            <div class="db-form-grid">
                <div class="db-fg span2"><label>Description</label><input type="text" id="sample_description" readonly></div>
                <div class="db-fg"><label>Equipment Code</label><input type="text" id="sample_equipment_code" readonly></div>
                <div class="db-fg"><label>Location</label><input type="text" id="sample_location" readonly></div>
                <div class="db-fg"><label>Calibration Date</label><input type="date" id="sample_calibration_date" readonly></div>
                <div class="db-fg"><label>Next Calibration</label><input type="date" id="sample_next_calibration" readonly></div>
                <div class="db-fg"><label>Frequency</label>
                    <select id="sample_frequency">
                        <option value="6">6 months</option>
                        <option value="12">1 year</option>
                        <option value="24">2 years</option>
                        <option value="60">5 years</option>
                    </select>
                </div>
                <div class="db-fg"><label>Calibrator</label><input type="text" id="sample_calibrator" readonly></div>
                <div class="db-fg"><label>Status</label>
                    <select id="sample_status">
                        <option value="Good">Good</option>
                        <option value="Not Good">Not Good</option>
                        <option value="For Disposal">For Disposal</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="db-fg"><label>Completed Date</label><input type="date" id="sample_completed_date"></div>
                <div class="db-overdue-warn" id="sample_overdue_warn">⚠ This item is overdue. If calibration was done earlier, adjust the date.</div>
            </div>
        </div>
        <div class="db-modal-footer">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="sampleDoneBtn">&#10003; Mark as Done</button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-secondary" id="sampleViewBtn">View Full Record</button>
        </div>
    </div>
</div>

<!-- INSPECTION MODAL -->
<div id="inspModal" class="db-overlay">
    <div class="db-modal-box">
        <div class="db-modal-header"><h2>Edit Inspection</h2><button class="db-modal-close" id="inspModalClose">&times;</button></div>
        <div class="db-modal-body">
            <input type="hidden" id="insp_id">
            <input type="hidden" id="insp_frequency">
            <div class="db-form-grid">
                <div class="db-fg span2"><label>Description</label><input type="text" id="insp_description" readonly></div>
                <div class="db-fg"><label>Equipment Code</label><input type="text" id="insp_equipment_code" readonly></div>
                <div class="db-fg"><label>Location</label><input type="text" id="insp_location" readonly></div>
                <div class="db-fg"><label>Frequency</label><input type="text" id="insp_frequency_display" readonly></div>
                <div class="db-fg"><label>Result</label>
                    <select id="insp_result">
                        <option value="Good">Good</option>
                        <option value="No Good">No Good</option>
                        <option value="For Disposal">For Disposal</option>
                        <option value="Missing">Missing</option>
                        <option value="Safekeep">Safekeep</option>
                        <option value="Not Yet Inspected">Not Yet Inspected</option>
                    </select>
                </div>
                <div class="db-fg"><label>Completed Date</label><input type="date" id="insp_completed_date"></div>
                <div class="db-fg"><label>Next Inspection</label><input type="date" id="insp_next_inspection" readonly></div>
                <div class="db-overdue-warn" id="insp_overdue_warn">⚠ This inspection is overdue. The next inspection will be calculated from the 1st of this completion month.</div>
            </div>
        </div>
        <div class="db-modal-footer">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="inspDoneBtn">&#10003; Mark as Done</button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-secondary" id="inspViewBtn">View Full Record</button>
        </div>
    </div>
</div>

<script>
const isGuest = <?= $isGuest ? 'true' : 'false' ?>;

// ── Carousel registry ─────────────────────────────────────────────────────────
const carouselMap = {};

// ── Scroll + optional carousel slide jump ────────────────────────────────────
function scrollToCard(id, slideIndex) {
    const el = document.getElementById(id);
    if (!el) return;
    if (slideIndex !== undefined && carouselMap[id]) {
        carouselMap[id].goTo(slideIndex);
    }
    const top = el.getBoundingClientRect().top + window.scrollY - 24;
    window.scrollTo({ top, behavior: 'smooth' });
    el.style.transition = 'box-shadow 0.3s';
    el.style.boxShadow  = '0 0 0 3px var(--db-accent), 0 8px 32px rgba(5,48,79,0.14)';
    setTimeout(() => { el.style.boxShadow = ''; }, 1200);
}

function todayISO() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function firstOfMonth(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`;
}
function addMonthsToFirst(dateStr, months) {
    if (!dateStr || !months) return '';
    const base = new Date(firstOfMonth(dateStr) + 'T00:00:00');
    base.setMonth(base.getMonth() + parseInt(months, 10));
    return `${base.getFullYear()}-${String(base.getMonth()+1).padStart(2,'0')}-01`;
}
function addMonths(dateStr, months) {
    if (!dateStr || !months) return '';
    const base = new Date(dateStr + 'T00:00:00');
    base.setMonth(base.getMonth() + parseInt(months, 10));
    return `${base.getFullYear()}-${String(base.getMonth()+1).padStart(2,'0')}-${String(base.getDate()).padStart(2,'0')}`;
}
function normalizeFreqMonths(freq) {
    if (freq === null || freq === undefined || freq === '') return null;
    const s = String(freq).trim().toLowerCase();
    if (/^\d+$/.test(s)) return parseInt(s, 10);
    const yearMatch = s.match(/^(\d+)\s*year/);
    if (yearMatch) return parseInt(yearMatch[1], 10) * 12;
    const monthMatch = s.match(/^(\d+)\s*month/);
    if (monthMatch) return parseInt(monthMatch[1], 10);
    if (s === 'monthly') return 1;
    const leading = parseInt(s, 10);
    return isNaN(leading) ? null : leading;
}
function calcNextInspection(completedDateStr, freqRaw) {
    if (!completedDateStr || !freqRaw) return '';
    const freqMonths = normalizeFreqMonths(freqRaw);
    if (!freqMonths) return '';
    return addMonthsToFirst(completedDateStr, freqMonths);
}

document.querySelectorAll('.db-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

/* ── Calibration Modal ── */
const calModal       = document.getElementById('calModal');
const calFreqSel     = document.getElementById('cal_frequency');
const exclusionModal = document.getElementById('exclusionModal');
let   pendingCalData = null;

document.getElementById('calModalClose').onclick = () => calModal.classList.remove('open');
document.getElementById('exclusionModalClose').onclick = () => exclusionModal.classList.remove('open');
document.getElementById('exclusionCancelBtn').onclick  = () => exclusionModal.classList.remove('open');

function openCalModal(id) {
    fetch('fetch_calibration.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('cal_id').value               = data.id;
            document.getElementById('cal_description').value      = data.description      || '';
            document.getElementById('cal_machine_code').value     = data.machine_code     || '';
            document.getElementById('cal_location').value         = data.location         || '';
            document.getElementById('cal_calibration_date').value = data.calibration_date || '';
            document.getElementById('cal_next_calibration').value = data.next_calibration || '';
            document.getElementById('cal_calibrator').value       = data.calibrator       || '';
            document.getElementById('cal_status').value           = data.present_status   || 'Good';
            document.getElementById('cal_status').dataset.originalStatus = data.present_status || 'Good';
            calModal.dataset.modelMaker   = data.model_maker      || '';
            calModal.dataset.serialNumber = data.serial_number    || '';
            calModal.dataset.calDate      = data.calibration_date || '';
            const freqMap = {'6 months':'6','1 year':'12','2 years':'24','5 years':'60'};
            calFreqSel.value    = freqMap[data.cal_frequency] ?? data.cal_frequency ?? '';
            calFreqSel.disabled = true;
            document.getElementById('cal_completed_date').value = todayISO();
            const warn = document.getElementById('cal_overdue_warn');
            warn.style.display = (data.next_calibration && data.next_calibration < todayISO()) ? 'block' : 'none';
            calModal.classList.add('open');
        });
}

document.querySelectorAll('.db-clickable[data-cal-id]').forEach(row => {
    row.addEventListener('click', () => openCalModal(row.dataset.calId));
});

const calDoneBtn = document.getElementById('calDoneBtn');
if (calDoneBtn) {
    calDoneBtn.addEventListener('click', () => {
        const completedDate  = document.getElementById('cal_completed_date').value;
        const newStatus      = document.getElementById('cal_status').value;
        const originalStatus = document.getElementById('cal_status').dataset.originalStatus || 'Good';

        if (!completedDate) { alert('Please set a completed date.'); return; }
        calFreqSel.disabled = false;
        const freq = calFreqSel.value;
        calFreqSel.disabled = true;
        if (!freq) { alert('Frequency is not set for this record.'); return; }

        pendingCalData = new FormData();
        pendingCalData.append('id',               document.getElementById('cal_id').value);
        pendingCalData.append('description',      document.getElementById('cal_description').value);
        pendingCalData.append('machine_code',     document.getElementById('cal_machine_code').value);
        pendingCalData.append('location',         document.getElementById('cal_location').value);
        pendingCalData.append('calibration_date', completedDate);
        pendingCalData.append('next_calibration', addMonths(completedDate, freq));
        const freqToLabel = {'6':'6 months','12':'1 year','24':'2 years','60':'5 years'};
        pendingCalData.append('cal_frequency', freqToLabel[freq] ?? freq);
        pendingCalData.append('calibrator',       document.getElementById('cal_calibrator').value);
        pendingCalData.append('present_status',   newStatus);

        const wasGood      = originalStatus.toLowerCase() === 'good';
        const isNowNotGood = newStatus.toLowerCase() !== 'good';

        if (wasGood && isNowNotGood) {
            document.getElementById('excl_equipment_display').value =
                document.getElementById('cal_machine_code').value + ' — ' +
                document.getElementById('cal_description').value;
            document.getElementById('excl_status_display').value = newStatus;
            document.getElementById('excl_remarks').value        = '';
            document.getElementById('excl_recorded_by').value    = '';
            document.getElementById('excl_error').style.display  = 'none';
            exclusionModal.classList.add('open');
        } else {
            saveCalibrationOnly();
        }
    });
}

function saveCalibrationOnly() {
    if (!pendingCalData) return;
    fetch('update_calibration.php', { method: 'POST', body: pendingCalData })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                calModal.classList.remove('open');
                exclusionModal.classList.remove('open');
                pendingCalData = null;
                location.reload();
            } else {
                alert(resp.message || 'Failed to update.');
            }
        });
}

let pendingInspData = null;

document.getElementById('exclusionConfirmBtn').addEventListener('click', async () => {
    const remarks    = document.getElementById('excl_remarks').value.trim();
    const recordedBy = document.getElementById('excl_recorded_by').value.trim();
    const errEl      = document.getElementById('excl_error');

    if (!remarks) {
        errEl.textContent = 'Please enter a reason in the Remarks field.';
        errEl.style.display = 'block';
        document.getElementById('excl_remarks').focus();
        return;
    }
    if (!recordedBy) {
        errEl.textContent = 'Please enter who is recording this.';
        errEl.style.display = 'block';
        document.getElementById('excl_recorded_by').focus();
        return;
    }
    errEl.style.display = 'none';

    const source = exclusionModal.dataset.source || 'cal';

    let newStatus, updateEndpoint, pendingData;

    if (source === 'sample') {
        newStatus        = document.getElementById('sample_status').value;
        updateEndpoint   = 'update_standard_sample.php';
        pendingData      = pendingSampleData;
    } else if (source === 'insp') {
        newStatus        = document.getElementById('insp_result').value;
        updateEndpoint   = 'update_inspection.php';
        pendingData      = pendingInspData;
    } else {
        newStatus        = document.getElementById('cal_status').value;
        updateEndpoint   = 'update_calibration.php';
        pendingData      = pendingCalData;
    }

    const equipCode = document.getElementById('insp_equipment_code').value;

    const exclData = new FormData();
    if (source === 'sample') {
        exclData.append('description',    currentSampleData.description    || '');
        exclData.append('control_number', currentSampleData.equipment_code || '');
        exclData.append('model_maker',    currentSampleData.model_maker    || '');
        exclData.append('serial_number',  currentSampleData.serial_no      || '');
        exclData.append('location',       currentSampleData.location       || '');
        exclData.append('cal_date',       currentSampleData.calibration_date || '');
    } else if (source === 'insp') {
        exclData.append('description',    document.getElementById('insp_description').value);
        exclData.append('control_number', equipCode);
        exclData.append('model_maker',    '');
        exclData.append('serial_number',  equipCode);
        exclData.append('location',       document.getElementById('insp_location').value);
        exclData.append('cal_date',       inspModal.dataset.inspDate || '');
    } else {
        exclData.append('description',    document.getElementById('cal_description').value);
        exclData.append('control_number', document.getElementById('cal_machine_code').value);
        exclData.append('model_maker',    calModal.dataset.modelMaker   || '');
        exclData.append('serial_number',  calModal.dataset.serialNumber || '');
        exclData.append('location',       document.getElementById('cal_location').value);
        exclData.append('cal_date',       calModal.dataset.calDate      || '');
    }
    exclData.append('present_status', newStatus);
    exclData.append('remarks',        remarks);
    exclData.append('recorded_by',    recordedBy);

    const confirmBtn = document.getElementById('exclusionConfirmBtn');
    confirmBtn.disabled    = true;
    confirmBtn.textContent = 'Saving…';

    try {
        const [updateResp, exclResp] = await Promise.all([
            fetch(updateEndpoint,                      { method: 'POST', body: pendingData }).then(r => r.json()),
            fetch('save_exclusion_from_dashboard.php', { method: 'POST', body: exclData   }).then(r => r.json()),
        ]);

        if (!updateResp.success) throw new Error(updateResp.message || `Failed to update ${source}.`);
        if (!exclResp.success)   throw new Error(exclResp.message   || 'Failed to save exclusion record.');

        calModal.classList.remove('open');
        sampleModal.classList.remove('open');
        inspModal.classList.remove('open');
        exclusionModal.classList.remove('open');
        pendingCalData    = null;
        pendingSampleData = null;
        pendingInspData   = null;
        exclusionModal.dataset.source = 'cal';
        location.reload();

    } catch (err) {
        errEl.textContent   = err.message;
        errEl.style.display = 'block';
    } finally {
        confirmBtn.disabled    = false;
        confirmBtn.textContent = '✓ Confirm & Save';
    }
});

const calViewBtn = document.getElementById('calViewBtn');
if (calViewBtn) calViewBtn.onclick = () => {
    const id = document.getElementById('cal_id').value;
    if (id) window.location.href = 'edit_calibration.php?id=' + id + '&from=dashboard';
};

/* ── Standard Sample Modal ── */
const sampleModal   = document.getElementById('sampleModal');
const sampleFreqSel = document.getElementById('sample_frequency');
let   currentSampleData = {};
document.getElementById('sampleModalClose').onclick = () => sampleModal.classList.remove('open');

function openSampleModal(id) {
    fetch('fetch_standard_sample.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            currentSampleData = data;
            document.getElementById('sample_id').value               = data.id;
            document.getElementById('sample_description').value      = data.description     || '';
            document.getElementById('sample_equipment_code').value   = data.equipment_code  || '';
            document.getElementById('sample_location').value         = data.location        || '';
            document.getElementById('sample_calibration_date').value = data.calibration_date || '';
            document.getElementById('sample_next_calibration').value = data.next_calibration_date || '';
            document.getElementById('sample_calibrator').value       = data.calibrator      || '';
            document.getElementById('sample_status').value           = data.present_status  || 'Good';
            document.getElementById('sample_status').dataset.originalStatus = data.present_status || 'Good';
            sampleFreqSel.value    = data.calibration_frequency || '';
            sampleFreqSel.disabled = true;
            document.getElementById('sample_completed_date').value = todayISO();
            const warn = document.getElementById('sample_overdue_warn');
            warn.style.display = (data.next_calibration_date && data.next_calibration_date < todayISO()) ? 'block' : 'none';
            sampleModal.classList.add('open');
        });
}

document.querySelectorAll('.db-clickable[data-sample-id]').forEach(row => {
    row.addEventListener('click', () => openSampleModal(row.dataset.sampleId));
});

let pendingSampleData = null;

const sampleDoneBtn = document.getElementById('sampleDoneBtn');
if (sampleDoneBtn) {
    sampleDoneBtn.addEventListener('click', () => {
        const completedDate  = document.getElementById('sample_completed_date').value;
        const newStatus      = document.getElementById('sample_status').value;
        const originalStatus = (document.getElementById('sample_status').dataset.originalStatus || 'Good');

        if (!completedDate) { alert('Please set a completed date.'); return; }
        sampleFreqSel.disabled = false;
        const freq = sampleFreqSel.value;
        sampleFreqSel.disabled = true;
        if (!freq) { alert('Frequency is not set for this record.'); return; }

        pendingSampleData = new FormData();
        pendingSampleData.append('id',                    document.getElementById('sample_id').value);
        pendingSampleData.append('calibration_date',      completedDate);
        pendingSampleData.append('next_calibration_date', addMonths(completedDate, freq));
        pendingSampleData.append('calibration_frequency', freq);
        pendingSampleData.append('present_status',        newStatus);
        pendingSampleData.append('description',           currentSampleData.description    || '');
        pendingSampleData.append('equipment_code',        currentSampleData.equipment_code || '');
        pendingSampleData.append('model_maker',           currentSampleData.model_maker    || '');
        pendingSampleData.append('serial_no',             currentSampleData.serial_no      || '');
        pendingSampleData.append('location',              currentSampleData.location       || '');
        pendingSampleData.append('calibrator',            currentSampleData.calibrator     || '');

        const wasGood      = originalStatus.toLowerCase() === 'good';
        const isNowNotGood = newStatus.toLowerCase() !== 'good';

        if (wasGood && isNowNotGood) {
            document.getElementById('excl_equipment_display').value =
                (currentSampleData.equipment_code || '') + ' — ' +
                (currentSampleData.description    || '');
            document.getElementById('excl_status_display').value = newStatus;
            document.getElementById('excl_remarks').value        = '';
            document.getElementById('excl_recorded_by').value    = '';
            document.getElementById('excl_error').style.display  = 'none';
            exclusionModal.dataset.source = 'sample';
            exclusionModal.classList.add('open');
        } else {
            saveSampleOnly();
        }
    });
}

function saveSampleOnly() {
    if (!pendingSampleData) return;
    fetch('update_standard_sample.php', { method: 'POST', body: pendingSampleData })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                sampleModal.classList.remove('open');
                exclusionModal.classList.remove('open');
                pendingSampleData = null;
                location.reload();
            } else {
                alert(resp.message || 'Failed to update.');
            }
        });
}

const sampleViewBtn = document.getElementById('sampleViewBtn');
if (sampleViewBtn) sampleViewBtn.onclick = () => {
    const id = document.getElementById('sample_id').value;
    if (id) window.location.href = 'edit_standard_sample.php?id=' + id + '&from=dashboard';
};

/* ── Inspection Modal ── */
const inspModal      = document.getElementById('inspModal');
const inspCompDate   = document.getElementById('insp_completed_date');
const inspNextDate   = document.getElementById('insp_next_inspection');
const inspFreqHidden = document.getElementById('insp_frequency');
document.getElementById('inspModalClose').onclick = () => inspModal.classList.remove('open');

inspCompDate.addEventListener('change', function () {
    const freq = inspFreqHidden.value;
    if (freq && this.value) inspNextDate.value = calcNextInspection(this.value, freq);
});

function openInspModal(id, isOverdue) {
    fetch('fetch_inspection.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('insp_id').value                = data.id;
            document.getElementById('insp_description').value       = data.description       || '';
            document.getElementById('insp_equipment_code').value    = data.equipment_code    || '';
            document.getElementById('insp_location').value          = data.location          || '';
            document.getElementById('insp_frequency_display').value = data.inspection_frequency || '';
            inspFreqHidden.value                                     = data.inspection_frequency || '';
            document.getElementById('insp_result').value            = data.inspection_result  || 'Good';
            document.getElementById('insp_result').dataset.originalResult = data.inspection_result || 'Good';
            inspModal.dataset.inspDate = data.inspection_date || '';
            const defaultDate  = todayISO();
            inspCompDate.value = defaultDate;
            inspNextDate.value = calcNextInspection(defaultDate, data.inspection_frequency);
            document.getElementById('insp_overdue_warn').style.display = isOverdue ? 'block' : 'none';
            inspModal.classList.add('open');
        });
}

document.querySelectorAll('.db-clickable[data-insp-id]').forEach(row => {
    const isOverdue = row.dataset.overdue === '1';
    row.addEventListener('click', () => openInspModal(row.dataset.inspId, isOverdue));
});

const inspDoneBtn = document.getElementById('inspDoneBtn');
if (inspDoneBtn) {
    inspDoneBtn.addEventListener('click', () => {
        const completedDate  = inspCompDate.value;
        const newResult      = document.getElementById('insp_result').value;
        const originalResult = document.getElementById('insp_result').dataset.originalResult || 'Good';

        if (!completedDate) { alert('Please set a completed date.'); return; }
        const freq = inspFreqHidden.value;
        if (!freq) { alert('Frequency is not set for this record.'); return; }
        const freqMonths = normalizeFreqMonths(freq);
        if (!freqMonths) { alert('Could not parse frequency: ' + freq); return; }

        pendingInspData = new FormData();
        pendingInspData.append('id',                   document.getElementById('insp_id').value);
        pendingInspData.append('description',          document.getElementById('insp_description').value);
        pendingInspData.append('equipment_code',       document.getElementById('insp_equipment_code').value);
        pendingInspData.append('location',             document.getElementById('insp_location').value);
        pendingInspData.append('inspection_date',      firstOfMonth(completedDate));
        pendingInspData.append('next_inspection',      addMonthsToFirst(completedDate, freqMonths));
        pendingInspData.append('inspection_frequency', freq);
        pendingInspData.append('inspection_result',    newResult);

        const wasGood      = originalResult.toLowerCase() === 'good';
        const isNowNotGood = newResult.toLowerCase() !== 'good';

        if (wasGood && isNowNotGood) {
            document.getElementById('excl_equipment_display').value =
                document.getElementById('insp_equipment_code').value + ' — ' +
                document.getElementById('insp_description').value;
            document.getElementById('excl_status_display').value = newResult;
            document.getElementById('excl_remarks').value        = '';
            document.getElementById('excl_recorded_by').value    = '';
            document.getElementById('excl_error').style.display  = 'none';
            exclusionModal.dataset.source = 'insp';
            exclusionModal.classList.add('open');
        } else {
            saveInspectionOnly();
        }
    });
}

function saveInspectionOnly() {
    if (!pendingInspData) return;
    fetch('update_inspection.php', { method: 'POST', body: pendingInspData })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                inspModal.classList.remove('open');
                exclusionModal.classList.remove('open');
                pendingInspData = null;
                location.reload();
            } else {
                alert(resp.message || 'Failed to update.');
            }
        });
}

const inspViewBtn = document.getElementById('inspViewBtn');
if (inspViewBtn) inspViewBtn.onclick = () => {
    const id = document.getElementById('insp_id').value;
    if (id) window.location.href = 'edit_inspection.php?id=' + id + '&from=dashboard';
};

/* ── Carousel ── */
document.querySelectorAll('[data-carousel]').forEach(container => {
    const slides  = container.querySelectorAll('.db-slide');
    const cur     = container.querySelector('.cur');
    let   current = 0;

    function showSlide(i) {
        slides.forEach(s => s.classList.remove('active'));
        slides[i].classList.add('active');
        if (cur) cur.textContent = i + 1;
        current = i;
    }

    const nextBtn = container.querySelector('.nextSlideBtn');
    const prevBtn = container.querySelector('.prevSlideBtn');
    if (nextBtn) nextBtn.onclick = () => showSlide((current + 1) % slides.length);
    if (prevBtn) prevBtn.onclick = () => showSlide((current - 1 + slides.length) % slides.length);

    const card = container.closest('.db-card');
    if (card && card.id) {
        carouselMap[card.id] = { goTo: showSlide };
    }
});

/* ── Auto-refresh ── */
const startDate = new Date().toDateString();
let   knownHash = null;

fetch('dashboard_poll.php')
    .then(r => r.json())
    .then(d => { knownHash = d.hash; })
    .catch(() => {});

setInterval(() => {
    if (new Date().toDateString() !== startDate) { location.reload(); return; }
    if (document.querySelector('.db-overlay.open')) return;
    fetch('dashboard_poll.php')
        .then(r => r.json())
        .then(d => {
            if (knownHash === null) { knownHash = d.hash; return; }
            if (d.hash !== knownHash) location.reload();
        })
        .catch(() => {});
}, 30 * 1000);
</script>
</body>
</html>