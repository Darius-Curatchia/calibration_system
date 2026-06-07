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

/* ── Calibration: Overdue (Good only — Not Yet Calibrated is now Ongoing) ── */
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

/* ── Standard Samples: Overdue (Good only — Not Yet Calibrated is now Ongoing) ── */
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

/* ── Calibration: Not Yet Calibrated (Ongoing) ── */
$stmt = $pdo->prepare("SELECT * FROM calibration_report WHERE present_status = 'Not Yet Calibrated' ORDER BY next_calibration ASC");
$stmt->execute();
$rowsNotYetCal   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$notYetCalSDP    = [];
$notYetCalNonSDP = [];
foreach ($rowsNotYetCal as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $notYetCalSDP[] = $row;
    else $notYetCalNonSDP[] = $row;
}

/* ── Standard Samples: Not Yet Calibrated (Ongoing) ── */
$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE present_status = 'Not Yet Calibrated' ORDER BY next_calibration_date ASC");
$stmt->execute();
$rowsSamplesNotYetCal   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$samplesNotYetCalSDP    = [];
$samplesNotYetCalNonSDP = [];
foreach ($rowsSamplesNotYetCal as $row) {
    if (strtoupper($row['calibrator']) === 'SDP') $samplesNotYetCalSDP[] = $row;
    else $samplesNotYetCalNonSDP[] = $row;
}
$notYetCalCount = count($rowsNotYetCal) + count($rowsSamplesNotYetCal);

/* ── Inspection: Overdue (Good only — Not Yet Inspected is now Ongoing) ── */
$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE next_inspection < :monthStart AND inspection_result = 'Good' ORDER BY next_inspection ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->execute();
$rowsInspectionOverdue  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$overdueInspectionCount = count($rowsInspectionOverdue);

/* ── Inspection: This Month (Good only) ── */
$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE next_inspection BETWEEN :monthStart AND :monthEnd AND inspection_result = 'Good' ORDER BY next_inspection ASC");
$stmt->bindValue(':monthStart', $monthStart);
$stmt->bindValue(':monthEnd',   $monthEnd);
$stmt->execute();
$rowsInspectionMonth  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$inspectionMonthCount = count($rowsInspectionMonth);

/* ── Inspection: Not Yet Inspected (Ongoing) ── */
$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE inspection_result = 'Not Yet Inspected' ORDER BY next_inspection ASC");
$stmt->execute();
$rowsInspectionNotYet  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$inspectionNotYetCount = count($rowsInspectionNotYet);
$notYetCalCount       += $inspectionNotYetCount; /* add to the shared Ongoing stat */

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
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
<?php include 'includes/header.php'; ?>

<!-- ── STAT BAR ── -->
<div class="db-stat-bar">

    <div class="db-stat-card s-today" onclick="scrollToCard('sec-today', <?= $dueTodaySlide ?>)" title="Due Today">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                <circle cx="12" cy="16" r="2" fill="currentColor" stroke="none"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $dueTodayCount + $samplesTodayCount ?></div>
            <div class="db-stat-lbl">Due Today</div>
        </div>
    </div>

    <div class="db-stat-card s-over" onclick="scrollToCard('sec-overdue', <?= $overdueSlide ?>)" title="Overdue">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $overdueCount + $overdueSamplesCount ?></div>
            <div class="db-stat-lbl">Overdue</div>
        </div>
    </div>

    <div class="db-stat-card s-ongo" onclick="scrollToCard('sec-ongoing')" title="Ongoing (Not Yet Calibrated)">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $notYetCalCount ?></div>
            <div class="db-stat-lbl">Ongoing</div>
        </div>
    </div>

    <div class="db-stat-card s-month" onclick="scrollToCard('sec-month', <?= $thisMonthSlide ?>)" title="This Month">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $monthCalTotal + $monthSamplesTotal ?></div>
            <div class="db-stat-lbl">This Month</div>
        </div>
    </div>

    <div class="db-stat-card s-insp-ov" onclick="scrollToCard('sec-insp-over')" title="Inspection Overdue">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $overdueInspectionCount ?></div>
            <div class="db-stat-lbl">Insp. Overdue</div>
        </div>
    </div>

    <div class="db-stat-card s-insp" onclick="scrollToCard('sec-insp-month')" title="Inspections This Month">
        <div class="db-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h h11"/>
            </svg>
        </div>
        <div class="db-stat-body">
            <div class="db-stat-num"><?= $inspectionMonthCount ?></div>
            <div class="db-stat-lbl">Inspections</div>
        </div>
    </div>

</div><!-- /.db-stat-bar -->


<!-- ══════════════════════════════════════════════
     DUE TODAY
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-today">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-today">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    <circle cx="12" cy="16" r="2" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <h2>Due Today</h2>
        </div>
        <span class="db-badge badge-today"><?= $dueTodayCount + $samplesTodayCount ?> item<?= ($dueTodayCount + $samplesTodayCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <div data-carousel>
            <div class="db-tabs">
                <button class="db-tab active tabBtn">Calibration Report</button>
                <button class="db-tab tabBtn">Standard Samples</button>
            </div>

            <!-- Slide 1: Calibration -->
            <div class="db-slide active">
                <?php if ($dueTodayCount > 0): ?>
                <table class="db-table">
                    <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead>
                    <tbody>
                    <?php $i=1; foreach ($rowsDueToday as $row): ?>
                    <tr class="db-row-click row-today" data-cal-id="<?= $row['id'] ?>">
                        <td><?= $i++ ?></td>
                        <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= formatDateExcel($row['next_calibration']) ?></td>
                        <td><span class="db-cal-tag <?= strtoupper($row['calibrator']) === 'SDP' ? 'sdp' : 'ext' ?>"><?= htmlspecialchars($row['calibrator']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="db-empty">
                    <div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <p>No calibration due today</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Slide 2: Standard Samples -->
            <div class="db-slide">
                <?php if ($samplesTodayCount > 0): ?>
                <table class="db-table">
                    <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Due Date</th><th>Calibrator</th></tr></thead>
                    <tbody>
                    <?php $i=1; foreach ($rowsSamplesToday as $row): ?>
                    <tr class="db-row-click row-today" data-sample-id="<?= $row['id'] ?>">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                        <td><span class="db-cal-tag <?= strtoupper($row['calibrator']) === 'SDP' ? 'sdp' : 'ext' ?>"><?= htmlspecialchars($row['calibrator']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="db-empty">
                    <div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <p>No standard samples due today</p>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /data-carousel -->
    </div>
</div><!-- /#sec-today -->


<!-- ══════════════════════════════════════════════
     OVERDUE ITEMS
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-overdue">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-over">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <h2>Overdue Items</h2>
        </div>
        <span class="db-badge badge-over"><?= $overdueCount + $overdueSamplesCount ?> item<?= ($overdueCount + $overdueSamplesCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <div data-carousel>
            <div class="db-tabs">
                <button class="db-tab active tabBtn">Calibration</button>
                <button class="db-tab tabBtn">Standard Samples</button>
            </div>

            <!-- Slide 1 -->
            <div class="db-slide active">
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($overdueSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($overdueSDP as $row): ?>
                            <tr class="db-row-click row-over" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No internal overdue</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($overdueNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($overdueNonSDP as $row): ?>
                            <tr class="db-row-click row-over" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No external overdue</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Slide 2: Standard Samples -->
            <div class="db-slide">
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($overdueSamplesSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($overdueSamplesSDP as $row): ?>
                            <tr class="db-row-click row-over" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No internal overdue</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($overdueSamplesNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($overdueSamplesNonSDP as $row): ?>
                            <tr class="db-row-click row-over" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No external overdue</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     ONGOING (NOT YET CALIBRATED)
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-ongoing">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-ongo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <h2>Ongoing Calibrations</h2>
        </div>
        <span class="db-badge badge-ongo"><?= $notYetCalCount ?> item<?= $notYetCalCount !== 1 ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <div data-carousel>
            <div class="db-tabs">
                <button class="db-tab active tabBtn">Calibration Report <span style="font-family:'DM Mono',monospace;font-size:10px;opacity:0.7;">(<?= count($rowsNotYetCal) ?>)</span></button>
                <button class="db-tab tabBtn">Standard Samples <span style="font-family:'DM Mono',monospace;font-size:10px;opacity:0.7;">(<?= count($rowsSamplesNotYetCal) ?>)</span></button>
                <button class="db-tab tabBtn">Inspection <span style="font-family:'DM Mono',monospace;font-size:10px;opacity:0.7;">(<?= $inspectionNotYetCount ?>)</span></button>
            </div>

            <!-- Slide 1: Calibration Report ongoing -->
            <div class="db-slide active">
                <?php if (count($rowsNotYetCal) > 0): ?>
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($notYetCalSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Scheduled Date</th></tr></thead>
                            <tbody>
                            <?php $i = 1; foreach ($notYetCalSDP as $row): ?>
                            <tr class="db-row-click row-ongo" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>None</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($notYetCalNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Scheduled Date</th></tr></thead>
                            <tbody>
                            <?php $i = 1; foreach ($notYetCalNonSDP as $row): ?>
                            <tr class="db-row-click row-ongo" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>None</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No ongoing calibration reports</p></div>
                <?php endif; ?>
            </div>

            <!-- Slide 2: Standard Samples ongoing -->
            <div class="db-slide">
                <?php if (count($rowsSamplesNotYetCal) > 0): ?>
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($samplesNotYetCalSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Scheduled Date</th></tr></thead>
                            <tbody>
                            <?php $i = 1; foreach ($samplesNotYetCalSDP as $row): ?>
                            <tr class="db-row-click row-ongo" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>None</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($samplesNotYetCalNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Scheduled Date</th></tr></thead>
                            <tbody>
                            <?php $i = 1; foreach ($samplesNotYetCalNonSDP as $row): ?>
                            <tr class="db-row-click row-ongo" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>None</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No ongoing standard samples</p></div>
                <?php endif; ?>
            </div>

            <!-- Slide 3: Inspection ongoing -->
            <div class="db-slide">
                <?php if ($inspectionNotYetCount > 0): ?>
                <table class="db-table">
                    <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Scheduled Date</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($rowsInspectionNotYet as $row): ?>
                    <tr class="db-row-click row-ongo" data-insp-id="<?= $row['id'] ?>" data-overdue="0">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= formatMonthYear($row['next_inspection']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No ongoing inspections</p></div>
                <?php endif; ?>
            </div>

        </div><!-- /data-carousel -->
    </div>
</div>


<!-- ══════════════════════════════════════════════
     THIS MONTH'S SCHEDULE
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-month">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-month">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <h2>This Month's Schedule</h2>
        </div>
        <span class="db-badge badge-month"><?= $monthCalTotal + $monthSamplesTotal ?> item<?= (($monthCalTotal + $monthSamplesTotal) !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <div data-carousel>
            <div class="db-tabs">
                <button class="db-tab active tabBtn">Calibration <span style="font-family:'DM Mono',monospace;font-size:10px;opacity:0.7;">(<?= $monthCalTotal ?>)</span></button>
                <button class="db-tab tabBtn">Standard Samples <span style="font-family:'DM Mono',monospace;font-size:10px;opacity:0.7;">(<?= $monthSamplesTotal ?>)</span></button>
            </div>

            <!-- Slide 1 -->
            <div class="db-slide active">
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($monthSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($monthSDP as $row):
                                $dl = ceil((strtotime($row['next_calibration']) - strtotime($today)) / 86400);
                                $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warn' : 'row-ok');
                            ?>
                            <tr class="db-row-click <?= $rc ?>" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No internal calibrations</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($monthNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Machine Code</th><th>Location</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($monthNonSDP as $row):
                                $dl = ceil((strtotime($row['next_calibration']) - strtotime($today)) / 86400);
                                $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warn' : 'row-ok');
                            ?>
                            <tr class="db-row-click <?= $rc ?>" data-cal-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['machine_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td><?= formatDateExcel($row['next_calibration']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No external calibrations</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="db-legend">
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#f59e0b;"></span>&le; 7 days left</div>
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#86efac;"></span>&le; 14 days left</div>
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#bbf7d0;"></span>On track</div>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="db-slide">
                <div class="db-split">
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot internal"></span><span class="db-pane-label">Internal (SDP)</span></div>
                        <?php if (count($monthSamplesSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($monthSamplesSDP as $row):
                                $dl = ceil((strtotime($row['next_calibration_date']) - strtotime($today)) / 86400);
                                $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warn' : 'row-ok');
                            ?>
                            <tr class="db-row-click <?= $rc ?>" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No internal standard samples</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="db-split-pane">
                        <div class="db-split-pane-head"><span class="db-pane-dot external"></span><span class="db-pane-label">External</span></div>
                        <?php if (count($monthSamplesNonSDP) > 0): ?>
                        <table class="db-table">
                            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Due Date</th></tr></thead>
                            <tbody>
                            <?php $i=1; foreach ($monthSamplesNonSDP as $row):
                                $dl = ceil((strtotime($row['next_calibration_date']) - strtotime($today)) / 86400);
                                $rc = $dl <= 7 ? 'row-urgent' : ($dl <= 14 ? 'row-warn' : 'row-ok');
                            ?>
                            <tr class="db-row-click <?= $rc ?>" data-sample-id="<?= $row['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                                <td><?= formatDateExcel($row['next_calibration_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="db-empty"><div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><p>No external standard samples</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="db-legend">
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#f59e0b;"></span>&le; 7 days left</div>
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#86efac;"></span>&le; 14 days left</div>
                    <div class="db-legend-item"><span class="db-legend-dot" style="background:#bbf7d0;"></span>On track</div>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     OVERDUE INSPECTIONS
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-insp-over">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-insp-ov">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h2>Overdue Inspections</h2>
        </div>
        <span class="db-badge badge-insp-ov"><?= $overdueInspectionCount ?> item<?= ($overdueInspectionCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <?php if ($overdueInspectionCount > 0): ?>
        <table class="db-table">
            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Was Due</th><th>Result</th></tr></thead>
            <tbody>
            <?php $i=1; foreach ($rowsInspectionOverdue as $row): ?>
            <tr class="db-row-click row-over" data-insp-id="<?= $row['id'] ?>" data-overdue="1">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= formatMonthYear($row['next_inspection']) ?></td>
                <td><?= htmlspecialchars($row['inspection_result']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="db-empty">
            <div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <p>No overdue inspections</p>
        </div>
        <?php endif; ?>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     THIS MONTH'S INSPECTION
══════════════════════════════════════════════ -->
<div class="db-section" id="sec-insp-month">
    <div class="db-section-head">
        <div class="db-section-title">
            <div class="db-section-icon icon-insp">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
            </div>
            <h2>This Month's Inspection</h2>
        </div>
        <span class="db-badge badge-insp"><?= $inspectionMonthCount ?> item<?= ($inspectionMonthCount !== 1) ? 's' : '' ?></span>
    </div>
    <div class="db-section-body">
        <?php if ($inspectionMonthCount > 0): ?>
        <table class="db-table">
            <thead><tr><th>#</th><th>Description</th><th>Equipment Code</th><th>Location</th><th>Next Inspection</th><th>Result</th></tr></thead>
            <tbody>
            <?php $i=1; foreach ($rowsInspectionMonth as $row): ?>
            <tr class="db-row-click row-ok" data-insp-id="<?= $row['id'] ?>" data-overdue="0">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><span class="db-code"><?= htmlspecialchars($row['equipment_code']) ?></span></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= formatMonthYear($row['next_inspection']) ?></td>
                <td><?= htmlspecialchars($row['inspection_result']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="db-empty">
            <div class="db-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div>
            <p>No inspections scheduled this month</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.main-content -->


<!-- ══════════════════════════════════════════════
     CALIBRATION MODAL
══════════════════════════════════════════════ -->
<div id="calModal" class="db-overlay">
    <div class="db-modal">
        <div class="db-modal-head">
            <h2>Edit Calibration</h2>
            <button class="db-modal-close" id="calModalClose">&times;</button>
        </div>
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
                        <option value="Not Yet Calibrated">Not Yet Calibrated</option>
                    </select>
                </div>
                <div class="db-fg"><label>Completed Date</label><input type="date" id="cal_completed_date"></div>
                <div class="db-overdue-warn" id="cal_overdue_warn">⚠ This item is overdue. If calibration was done earlier, adjust the date.</div>
                <div id="cal_modal_err" class="db-modal-inline-err" style="display:none;grid-column:1/-1;"></div>
            </div>
        </div>
        <div class="db-modal-foot">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="calDoneBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Mark as Done
            </button>
            <button type="button" class="db-btn db-btn-ongo" id="calOngoingBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Ongoing
            </button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-ghost" id="calViewBtn">View Full Record</button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     EXCLUSION MODAL
══════════════════════════════════════════════ -->
<div id="exclusionModal" class="db-overlay" style="z-index:1010;">
    <div class="db-modal" style="max-width:460px;">
        <div class="db-modal-head">
            <h2>Equipment Status Changed</h2>
            <button class="db-modal-close" id="exclusionModalClose">&times;</button>
        </div>
        <div class="db-modal-body">
            <div class="excl-warn-box">
                This equipment's status has changed from <strong>Good</strong> to a non-Good status.
                It will be automatically added to the <strong>Exclusion Summary</strong>.
                Please fill in the details below.
            </div>
            <div class="db-form-grid">
                <div class="db-fg span2"><label>Equipment</label><input type="text" id="excl_equipment_display" readonly></div>
                <div class="db-fg span2"><label>New Status</label><input type="text" id="excl_status_display" readonly style="background:#fef0f0;color:var(--c-over-text);font-weight:600;"></div>
                <div class="db-fg span2">
                    <label>Remarks <span style="color:var(--c-over-text);">*</span></label>
                    <textarea id="excl_remarks" rows="3" placeholder="Reason for status change, condition details, etc." style="resize:vertical;"></textarea>
                </div>
                <div class="db-fg span2">
                    <label>Recorded By <span style="color:var(--c-over-text);">*</span></label>
                    <input type="text" id="excl_recorded_by" placeholder="Enter your name">
                </div>
                <div id="excl_error" style="display:none;grid-column:1/-1;background:var(--c-over-bg);border:1px solid #fca5a5;border-radius:var(--r-sm);padding:8px 12px;font-size:12.5px;color:var(--c-over-text);"></div>
            </div>
        </div>
        <div class="db-modal-foot" style="justify-content:flex-end;">
            <button type="button" class="db-btn db-btn-ghost" id="exclusionCancelBtn">Cancel</button>
            <button type="button" class="db-btn db-btn-primary" id="exclusionConfirmBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Confirm &amp; Save
            </button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     STANDARD SAMPLE MODAL
══════════════════════════════════════════════ -->
<div id="sampleModal" class="db-overlay">
    <div class="db-modal">
        <div class="db-modal-head">
            <h2>Edit Standard Sample</h2>
            <button class="db-modal-close" id="sampleModalClose">&times;</button>
        </div>
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
                        <option value="Not Yet Calibrated">Not Yet Calibrated</option>
                        <option value="For Disposal">For Disposal</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="db-fg"><label>Completed Date</label><input type="date" id="sample_completed_date"></div>
                <div class="db-overdue-warn" id="sample_overdue_warn">⚠ This item is overdue. If calibration was done earlier, adjust the date.</div>
                <div id="sample_modal_err" class="db-modal-inline-err" style="display:none;grid-column:1/-1;"></div>
            </div>
        </div>
        <div class="db-modal-foot">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="sampleDoneBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Mark as Done
            </button>
            <button type="button" class="db-btn db-btn-ongo" id="sampleOngoingBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Ongoing
            </button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-ghost" id="sampleViewBtn">View Full Record</button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     INSPECTION MODAL
══════════════════════════════════════════════ -->
<div id="inspModal" class="db-overlay">
    <div class="db-modal">
        <div class="db-modal-head">
            <h2>Edit Inspection</h2>
            <button class="db-modal-close" id="inspModalClose">&times;</button>
        </div>
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
                <div id="insp_modal_err" class="db-modal-inline-err" style="display:none;grid-column:1/-1;"></div>
            </div>
        </div>
        <div class="db-modal-foot">
            <?php if (!$isGuest): ?>
            <button type="button" class="db-btn db-btn-primary" id="inspDoneBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Mark as Done
            </button>
            <button type="button" class="db-btn db-btn-ongo" id="inspOngoingBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Ongoing
            </button>
            <?php endif; ?>
            <button type="button" class="db-btn db-btn-ghost" id="inspViewBtn">View Full Record</button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     CONFIRMATION MODAL
══════════════════════════════════════════════ -->
<div id="confirmModal" class="db-overlay" style="z-index:1020;">
    <div class="db-modal" style="max-width:460px;">
        <div class="db-modal-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="db-section-icon" id="cfm-icon"></div>
                <h2 id="cfm-title">Confirm Action</h2>
            </div>
            <button class="db-modal-close" id="confirmModalClose">&times;</button>
        </div>
        <div class="db-modal-body">
            <p id="cfm-desc-text" style="font-size:13px;color:var(--text-2);margin-bottom:16px;line-height:1.6;"></p>
            <div class="cfm-item-card">
                <div class="cfm-row">
                    <span class="cfm-label">Code</span>
                    <span id="cfm-code" class="db-code" style="font-size:12px;"></span>
                </div>
                <div class="cfm-row">
                    <span class="cfm-label">Description</span>
                    <span id="cfm-name" style="font-size:13px;color:var(--text);font-weight:500;"></span>
                </div>
                <div class="cfm-row">
                    <span class="cfm-label">Location</span>
                    <span id="cfm-location" style="font-size:13px;color:var(--text-2);"></span>
                </div>
                <div class="cfm-row" id="cfm-date-row">
                    <span class="cfm-label">Completed Date</span>
                    <span id="cfm-date" style="font-size:13px;color:var(--text);font-weight:600;"></span>
                </div>
                <div class="cfm-row" id="cfm-next-row">
                    <span class="cfm-label">Next Calibration</span>
                    <span id="cfm-next" style="font-size:13px;color:var(--c-month-text);font-weight:600;"></span>
                </div>
            </div>
            <div id="cfm-warn" class="cfm-warn-box" style="display:none;"></div>
        </div>
        <div class="db-modal-foot" style="justify-content:flex-end;">
            <button type="button" class="db-btn db-btn-ghost" id="confirmCancelBtn">Cancel</button>
            <button type="button" class="db-btn" id="confirmProceedBtn">Confirm</button>
        </div>
    </div>
</div>


<script>
const isGuest = <?= $isGuest ? 'true' : 'false' ?>;

/* ── Carousel registry ── */
const carouselMap = {};

function scrollToCard(id, slideIndex) {
    const el = document.getElementById(id);
    if (!el) return;
    if (slideIndex !== undefined && carouselMap[id]) {
        carouselMap[id].goTo(slideIndex);
    }
    const top = el.getBoundingClientRect().top + window.scrollY - 20;
    window.scrollTo({ top, behavior: 'smooth' });
    el.style.transition = 'box-shadow 0.3s';
    el.style.boxShadow  = '0 0 0 3px rgba(26,144,217,0.4), 0 8px 40px rgba(5,48,79,0.14)';
    setTimeout(() => { el.style.boxShadow = ''; }, 1400);
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
    const yearMatch  = s.match(/^(\d+)\s*year/);
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

/* Close on backdrop click */
document.querySelectorAll('.db-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

/* ═══════════════════════════
   CALIBRATION MODAL
═══════════════════════════ */
const calModal       = document.getElementById('calModal');
const calFreqSel     = document.getElementById('cal_frequency');
const calOngoingBtn  = document.getElementById('calOngoingBtn');
const exclusionModal = document.getElementById('exclusionModal');
let   pendingCalData = null;

document.getElementById('calModalClose').onclick = () => calModal.classList.remove('open');
document.getElementById('exclusionModalClose').onclick = () => exclusionModal.classList.remove('open');
document.getElementById('exclusionCancelBtn').onclick  = () => exclusionModal.classList.remove('open');

/* ── Open Calibration Modal ── */
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
            document.getElementById('cal_status').dataset.originalStatus = data.present_status || '';
            calModal.dataset.modelMaker   = data.model_maker      || '';
            calModal.dataset.serialNumber = data.serial_number    || '';
            calModal.dataset.calDate      = data.calibration_date || '';

            const freqMap = {'6 months':'6','1 year':'12','2 years':'24','5 years':'60'};
            calFreqSel.value    = freqMap[data.cal_frequency] ?? data.cal_frequency ?? '';
            calFreqSel.disabled = true;

            document.getElementById('cal_completed_date').value = todayISO();

            const warn = document.getElementById('cal_overdue_warn');
            warn.style.display = (data.next_calibration && data.next_calibration < todayISO()) ? 'block' : 'none';

            /* Show "Ongoing" button only when status is NOT already ongoing */
            const isAlreadyOngoing = (data.present_status || '').toLowerCase() === 'not yet calibrated';
            if (calOngoingBtn) {
                calOngoingBtn.style.display = isAlreadyOngoing ? 'none' : 'inline-flex';
            }

            calModal.classList.add('open');
        });
}

/* Bind ALL calibration rows (due today, overdue, ongoing, this month) */
document.querySelectorAll('.db-row-click[data-cal-id]').forEach(row => {
    row.addEventListener('click', () => openCalModal(row.dataset.calId));
});

/* ── Inline modal error helper ── */
function showModalErr(elId, msg) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent   = '⚠ ' + msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

/* ── Confirmation modal wiring ── */
const confirmModal    = document.getElementById('confirmModal');
const confirmProceed  = document.getElementById('confirmProceedBtn');
const confirmCancel   = document.getElementById('confirmCancelBtn');
document.getElementById('confirmModalClose').onclick = () => confirmModal.classList.remove('open');
confirmCancel.onclick = () => confirmModal.classList.remove('open');
confirmModal.addEventListener('click', e => { if (e.target === confirmModal) confirmModal.classList.remove('open'); });

let pendingConfirmFn = null; /* function to call when user clicks Confirm */

function formatDisplayDate(isoStr) {
    if (!isoStr) return '—';
    const [y, m, d] = isoStr.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${d}-${months[parseInt(m,10)-1]}-${String(y).slice(2)}`;
}

function openConfirmModal({ type, code, name, location, completedDate, nextDate, onConfirm }) {
    const isDone    = type === 'done';
    const iconEl    = document.getElementById('cfm-icon');
    const titleEl   = document.getElementById('cfm-title');
    const descEl    = document.getElementById('cfm-desc-text');
    const warnEl    = document.getElementById('cfm-warn');
    const dateRow   = document.getElementById('cfm-date-row');
    const nextRow   = document.getElementById('cfm-next-row');
    const proceedEl = document.getElementById('confirmProceedBtn');

    document.getElementById('cfm-code').textContent     = code     || '—';
    document.getElementById('cfm-name').textContent     = name     || '—';
    document.getElementById('cfm-location').textContent = location || '—';

    if (isDone) {
        iconEl.className    = 'db-section-icon icon-month';
        iconEl.innerHTML    = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
        titleEl.textContent = 'Confirm Mark as Done';
        descEl.textContent  = 'You are about to record this calibration as completed. Please verify the details below before confirming.';
        document.getElementById('cfm-date').textContent = formatDisplayDate(completedDate);
        document.getElementById('cfm-next').textContent = formatDisplayDate(nextDate);
        dateRow.style.display = '';
        nextRow.style.display = '';
        warnEl.style.display  = 'none';
        proceedEl.className   = 'db-btn db-btn-primary';
        proceedEl.innerHTML   = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Mark as Done`;
    } else {
        iconEl.className    = 'db-section-icon icon-ongo';
        iconEl.innerHTML    = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
        titleEl.textContent = 'Mark as Ongoing?';
        descEl.textContent  = 'This item will be marked as Not Yet Calibrated and moved to the Ongoing Calibrations card. The scheduled date will remain unchanged.';
        dateRow.style.display = 'none';
        nextRow.style.display = 'none';
        warnEl.style.display  = 'block';
        warnEl.textContent    = '⚠ This action does not update calibration dates. Only the status will change.';
        proceedEl.className   = 'db-btn db-btn-ongo';
        proceedEl.innerHTML   = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Confirm Ongoing`;
    }

    pendingConfirmFn = onConfirm;
    confirmModal.classList.add('open');
}

confirmProceed.addEventListener('click', () => {
    confirmModal.classList.remove('open');
    if (typeof pendingConfirmFn === 'function') {
        pendingConfirmFn();
        pendingConfirmFn = null;
    }
});

/* ── Mark as Done ── */
const calDoneBtn = document.getElementById('calDoneBtn');
if (calDoneBtn) {
    calDoneBtn.addEventListener('click', () => {
        const completedDate  = document.getElementById('cal_completed_date').value;
        const newStatus      = document.getElementById('cal_status').value;
        const originalStatus = document.getElementById('cal_status').dataset.originalStatus || 'Good';
        if (!completedDate) { showModalErr('cal_modal_err', 'Please set a completed date before proceeding.'); return; }

        calFreqSel.disabled = false;
        const freq = calFreqSel.value;
        calFreqSel.disabled = true;
        if (!freq) { showModalErr('cal_modal_err', 'Frequency is not set for this record.'); return; }

        const freqToLabel   = {'6':'6 months','12':'1 year','24':'2 years','60':'5 years'};
        const nextCalDate   = addMonths(completedDate, freq);
        const neutralStatuses = ['good', 'not yet calibrated'];
        const goingNonGood  = neutralStatuses.includes(originalStatus.toLowerCase()) && !neutralStatuses.includes(newStatus.toLowerCase());
        const savedCalStatus = goingNonGood ? newStatus : 'Good';

        pendingCalData = new FormData();
        pendingCalData.append('id',               document.getElementById('cal_id').value);
        pendingCalData.append('description',      document.getElementById('cal_description').value);
        pendingCalData.append('machine_code',     document.getElementById('cal_machine_code').value);
        pendingCalData.append('location',         document.getElementById('cal_location').value);
        pendingCalData.append('calibration_date', completedDate);
        pendingCalData.append('next_calibration', nextCalDate);
        pendingCalData.append('cal_frequency',    freqToLabel[freq] ?? freq);
        pendingCalData.append('calibrator',       document.getElementById('cal_calibrator').value);
        pendingCalData.append('present_status',   savedCalStatus);

        openConfirmModal({
            type:          'done',
            code:          document.getElementById('cal_machine_code').value,
            name:          document.getElementById('cal_description').value,
            location:      document.getElementById('cal_location').value,
            completedDate: completedDate,
            nextDate:      nextCalDate,
            onConfirm: () => {
                if (goingNonGood) {
                    document.getElementById('excl_equipment_display').value =
                        document.getElementById('cal_machine_code').value + ' — ' +
                        document.getElementById('cal_description').value;
                    document.getElementById('excl_status_display').value = newStatus;
                    document.getElementById('excl_remarks').value        = '';
                    document.getElementById('excl_recorded_by').value    = '';
                    document.getElementById('excl_error').style.display  = 'none';
                    exclusionModal.dataset.source = 'cal';
                    exclusionModal.classList.add('open');
                } else {
                    saveCalibrationOnly();
                }
            }
        });
    });
}

/* ── Ongoing button: mark as Not Yet Calibrated ── */
if (calOngoingBtn) {
    calOngoingBtn.addEventListener('click', () => {
        const id = document.getElementById('cal_id').value;
        if (!id) return;

        calFreqSel.disabled = false;
        const freq = calFreqSel.value;
        calFreqSel.disabled = true;
        const freqToLabel = {'6':'6 months','12':'1 year','24':'2 years','60':'5 years'};

        openConfirmModal({
            type:     'ongoing',
            code:     document.getElementById('cal_machine_code').value,
            name:     document.getElementById('cal_description').value,
            location: document.getElementById('cal_location').value,
            onConfirm: () => {
                const fd = new FormData();
                fd.append('id',               id);
                fd.append('description',      document.getElementById('cal_description').value);
                fd.append('machine_code',     document.getElementById('cal_machine_code').value);
                fd.append('location',         document.getElementById('cal_location').value);
                fd.append('calibration_date', document.getElementById('cal_calibration_date').value);
                fd.append('next_calibration', document.getElementById('cal_next_calibration').value);
                fd.append('cal_frequency',    freqToLabel[freq] ?? freq);
                fd.append('calibrator',       document.getElementById('cal_calibrator').value);
                fd.append('present_status',   'Not Yet Calibrated');

                fetch('update_calibration.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) { calModal.classList.remove('open'); location.reload(); }
                        else showModalErr('cal_modal_err', resp.message || 'Failed to update.');
                    });
            }
        });
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
    if (!remarks)    { errEl.textContent = 'Please enter a reason in the Remarks field.'; errEl.style.display = 'block'; document.getElementById('excl_remarks').focus(); return; }
    if (!recordedBy) { errEl.textContent = 'Please enter who is recording this.'; errEl.style.display = 'block'; document.getElementById('excl_recorded_by').focus(); return; }
    errEl.style.display = 'none';

    const source = exclusionModal.dataset.source || 'cal';
    let newStatus, updateEndpoint, pendingData;
    if (source === 'sample') {
        newStatus = document.getElementById('sample_status').value;
        updateEndpoint = 'update_standard_sample.php';
        pendingData = pendingSampleData;
    } else if (source === 'insp') {
        newStatus = document.getElementById('insp_result').value;
        updateEndpoint = 'update_inspection.php';
        pendingData = pendingInspData;
    } else {
        newStatus = document.getElementById('cal_status').value;
        updateEndpoint = 'update_calibration.php';
        pendingData = pendingCalData;
    }

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
        exclData.append('control_number', document.getElementById('insp_equipment_code').value);
        exclData.append('model_maker',    '');
        exclData.append('serial_number',  document.getElementById('insp_equipment_code').value);
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
        pendingCalData = null; pendingSampleData = null; pendingInspData = null;
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

document.getElementById('calViewBtn').onclick = () => {
    const id = document.getElementById('cal_id').value;
    if (id) window.location.href = 'edit_calibration.php?id=' + id + '&from=dashboard';
};

/* ═══════════════════════════
   STANDARD SAMPLE MODAL
═══════════════════════════ */
const sampleModal      = document.getElementById('sampleModal');
const sampleFreqSel    = document.getElementById('sample_frequency');
const sampleOngoingBtn = document.getElementById('sampleOngoingBtn');
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

            /* Show Ongoing button only when not already ongoing */
            const isAlreadyOngoing = (data.present_status || '').toLowerCase() === 'not yet calibrated';
            if (sampleOngoingBtn) sampleOngoingBtn.style.display = isAlreadyOngoing ? 'none' : 'inline-flex';

            sampleModal.classList.add('open');
        });
}

document.querySelectorAll('.db-row-click[data-sample-id]').forEach(row => {
    row.addEventListener('click', () => openSampleModal(row.dataset.sampleId));
});

let pendingSampleData = null;

const sampleDoneBtn = document.getElementById('sampleDoneBtn');
if (sampleDoneBtn) {
    sampleDoneBtn.addEventListener('click', () => {
        const completedDate  = document.getElementById('sample_completed_date').value;
        const newStatus      = document.getElementById('sample_status').value;
        const originalStatus = document.getElementById('sample_status').dataset.originalStatus || 'Good';
        if (!completedDate) { showModalErr('sample_modal_err', 'Please set a completed date before proceeding.'); return; }
        sampleFreqSel.disabled = false;
        const freq = sampleFreqSel.value;
        sampleFreqSel.disabled = true;
        if (!freq) { showModalErr('sample_modal_err', 'Frequency is not set for this record.'); return; }

        const nextCalDate  = addMonths(completedDate, freq);
        const neutralStatuses = ['good', 'not yet calibrated'];
        const goingNonGood = neutralStatuses.includes(originalStatus.toLowerCase()) && !neutralStatuses.includes(newStatus.toLowerCase());
        const savedSampleStatus = goingNonGood ? newStatus : 'Good';

        pendingSampleData = new FormData();
        pendingSampleData.append('id',                    document.getElementById('sample_id').value);
        pendingSampleData.append('calibration_date',      completedDate);
        pendingSampleData.append('next_calibration_date', nextCalDate);
        pendingSampleData.append('calibration_frequency', freq);
        pendingSampleData.append('present_status',        savedSampleStatus);
        pendingSampleData.append('description',           currentSampleData.description    || '');
        pendingSampleData.append('equipment_code',        currentSampleData.equipment_code || '');
        pendingSampleData.append('model_maker',           currentSampleData.model_maker    || '');
        pendingSampleData.append('serial_no',             currentSampleData.serial_no      || '');
        pendingSampleData.append('location',              currentSampleData.location       || '');
        pendingSampleData.append('calibrator',            currentSampleData.calibrator     || '');

        openConfirmModal({
            type:          'done',
            code:          currentSampleData.equipment_code || '',
            name:          currentSampleData.description    || '',
            location:      currentSampleData.location       || '',
            completedDate: completedDate,
            nextDate:      nextCalDate,
            onConfirm: () => {
                if (goingNonGood) {
                    document.getElementById('excl_equipment_display').value =
                        (currentSampleData.equipment_code || '') + ' — ' + (currentSampleData.description || '');
                    document.getElementById('excl_status_display').value = newStatus;
                    document.getElementById('excl_remarks').value        = '';
                    document.getElementById('excl_recorded_by').value    = '';
                    document.getElementById('excl_error').style.display  = 'none';
                    exclusionModal.dataset.source = 'sample';
                    exclusionModal.classList.add('open');
                } else {
                    saveSampleOnly();
                }
            }
        });
    });
}

/* ── Sample Ongoing button: mark as Not Yet Calibrated ── */
if (sampleOngoingBtn) {
    sampleOngoingBtn.addEventListener('click', () => {
        const id = document.getElementById('sample_id').value;
        if (!id) return;

        sampleFreqSel.disabled = false;
        const freq = sampleFreqSel.value;
        sampleFreqSel.disabled = true;

        openConfirmModal({
            type:     'ongoing',
            code:     currentSampleData.equipment_code || '',
            name:     currentSampleData.description    || '',
            location: currentSampleData.location       || '',
            onConfirm: () => {
                const fd = new FormData();
                fd.append('id',                    id);
                fd.append('calibration_date',      document.getElementById('sample_calibration_date').value);
                fd.append('next_calibration_date', document.getElementById('sample_next_calibration').value);
                fd.append('calibration_frequency', freq);
                fd.append('present_status',        'Not Yet Calibrated');
                fd.append('description',           currentSampleData.description    || '');
                fd.append('equipment_code',        currentSampleData.equipment_code || '');
                fd.append('model_maker',           currentSampleData.model_maker    || '');
                fd.append('serial_no',             currentSampleData.serial_no      || '');
                fd.append('location',              currentSampleData.location       || '');
                fd.append('calibrator',            currentSampleData.calibrator     || '');

                fetch('update_standard_sample.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) { sampleModal.classList.remove('open'); location.reload(); }
                        else showModalErr('sample_modal_err', resp.message || 'Failed to update.');
                    });
            }
        });
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

document.getElementById('sampleViewBtn').onclick = () => {
    const id = document.getElementById('sample_id').value;
    if (id) window.location.href = 'edit_standard_sample.php?id=' + id + '&from=dashboard';
};

/* ═══════════════════════════
   INSPECTION MODAL
═══════════════════════════ */
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
            afterInspModalOpen(data.inspection_result);
            inspModal.classList.add('open');
        });
}

document.querySelectorAll('.db-row-click[data-insp-id]').forEach(row => {
    const isOverdue = row.dataset.overdue === '1';
    row.addEventListener('click', () => openInspModal(row.dataset.inspId, isOverdue));
});

const inspOngoingBtn = document.getElementById('inspOngoingBtn');

/* ── Show/hide Ongoing button based on current result ── */
function afterInspModalOpen(presentResult) {
    const isAlreadyOngoing = (presentResult || '').toLowerCase() === 'not yet inspected';
    if (inspOngoingBtn) inspOngoingBtn.style.display = isAlreadyOngoing ? 'none' : 'inline-flex';
}

/* ── Mark as Done ── */
const inspDoneBtn = document.getElementById('inspDoneBtn');
if (inspDoneBtn) {
    inspDoneBtn.addEventListener('click', () => {
        const completedDate  = inspCompDate.value;
        const newResult      = document.getElementById('insp_result').value;
        const originalResult = document.getElementById('insp_result').dataset.originalResult || 'Good';
        if (!completedDate) { showModalErr('insp_modal_err', 'Please set a completed date before proceeding.'); return; }
        const freq = inspFreqHidden.value;
        if (!freq) { showModalErr('insp_modal_err', 'Frequency is not set for this record.'); return; }
        const freqMonths = normalizeFreqMonths(freq);
        if (!freqMonths) { showModalErr('insp_modal_err', 'Could not parse frequency: ' + freq); return; }

        const nextInspDate   = addMonthsToFirst(completedDate, freqMonths);
        const neutralResults = ['good', 'not yet inspected'];
        const goingNonGood   = neutralResults.includes(originalResult.toLowerCase()) && !neutralResults.includes(newResult.toLowerCase());
        const savedInspResult = goingNonGood ? newResult : 'Good';

        pendingInspData = new FormData();
        pendingInspData.append('id',                   document.getElementById('insp_id').value);
        pendingInspData.append('description',          document.getElementById('insp_description').value);
        pendingInspData.append('equipment_code',       document.getElementById('insp_equipment_code').value);
        pendingInspData.append('location',             document.getElementById('insp_location').value);
        pendingInspData.append('inspection_date',      firstOfMonth(completedDate));
        pendingInspData.append('next_inspection',      nextInspDate);
        pendingInspData.append('inspection_frequency', freq);
        pendingInspData.append('inspection_result',    savedInspResult);

        openConfirmModal({
            type:          'done',
            code:          document.getElementById('insp_equipment_code').value,
            name:          document.getElementById('insp_description').value,
            location:      document.getElementById('insp_location').value,
            completedDate: completedDate,
            nextDate:      nextInspDate,
            onConfirm: () => {
                if (goingNonGood) {
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
            }
        });
    });
}

/* ── Ongoing button: mark as Not Yet Inspected ── */
if (inspOngoingBtn) {
    inspOngoingBtn.addEventListener('click', () => {
        const id = document.getElementById('insp_id').value;
        if (!id) return;
        openConfirmModal({
            type:     'ongoing',
            code:     document.getElementById('insp_equipment_code').value,
            name:     document.getElementById('insp_description').value,
            location: document.getElementById('insp_location').value,
            onConfirm: () => {
                const fd = new FormData();
                fd.append('id',                   id);
                fd.append('description',          document.getElementById('insp_description').value);
                fd.append('equipment_code',       document.getElementById('insp_equipment_code').value);
                fd.append('location',             document.getElementById('insp_location').value);
                fd.append('inspection_date',      inspModal.dataset.inspDate || '');
                fd.append('next_inspection',      inspNextDate.value);
                fd.append('inspection_frequency', inspFreqHidden.value);
                fd.append('inspection_result',    'Not Yet Inspected');
                fetch('update_inspection.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) { inspModal.classList.remove('open'); location.reload(); }
                        else showModalErr('insp_modal_err', resp.message || 'Failed to update.');
                    });
            }
        });
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
                showModalErr('insp_modal_err', resp.message || 'Failed to update.');
            }
        });
}

document.getElementById('inspViewBtn').onclick = () => {
    const id = document.getElementById('insp_id').value;
    if (id) window.location.href = 'edit_inspection.php?id=' + id + '&from=dashboard';
};

/* ═══════════════════════════
   TAB-BASED CAROUSEL
═══════════════════════════ */
document.querySelectorAll('[data-carousel]').forEach(container => {
    const slides  = container.querySelectorAll('.db-slide');
    const tabs    = container.querySelectorAll('.tabBtn');
    let   current = 0;

    function showSlide(i) {
        slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
        tabs.forEach((t, idx)   => t.classList.toggle('active', idx === i));
        current = i;
    }

    tabs.forEach((tab, i) => tab.addEventListener('click', () => showSlide(i)));

    const card = container.closest('.db-section');
    if (card && card.id) carouselMap[card.id] = { goTo: showSlide };
});

/* ═══════════════════════════
   AUTO-REFRESH
═══════════════════════════ */
const startDate = new Date().toDateString();
let   knownHash = null;

fetch('dashboard_poll.php').then(r => r.json()).then(d => { knownHash = d.hash; }).catch(() => {});

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
}, 30000);
</script>
</body>
</html>