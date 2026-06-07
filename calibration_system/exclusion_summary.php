<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

$currentYear = (new DateTime())->format('Y');

$periodStmt = $pdo->query("
    SELECT DISTINCT strftime('%Y', date_added) AS yr
    FROM exclusion_summary
    WHERE date_added IS NOT NULL AND date_added != '0000-00-00'
    ORDER BY yr DESC
");
$allYears = $periodStmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array($currentYear, $allYears)) {
    array_unshift($allYears, $currentYear);
}

$selectedYear = isset($_GET['period']) ? $_GET['period'] : $currentYear;

if ($selectedYear !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM exclusion_summary
        WHERE strftime('%Y', date_added) = :yr
        ORDER BY id ASC
    ");
    $stmt->execute([':yr' => $selectedYear]);
} else {
    $stmt = $pdo->query("SELECT * FROM exclusion_summary ORDER BY id ASC");
}
$allRows = $stmt->fetchAll();

$flashMessage = $_SESSION['exclusion_message'] ?? null;
unset($_SESSION['exclusion_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exclusion-Inclusion Summary — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico?v=2">
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
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:20px 24px;}
.top-controls{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.controls-left,.controls-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-danger{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.btn-danger:hover{background:rgba(220,53,53,0.16);}
.btn-danger-solid{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.25);}
.btn-danger-solid:hover{background:#b91c1c;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}
.filter-input,.filter-select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);box-sizing:border-box;}
.filter-input:focus,.filter-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.filter-input{min-width:200px;}
.bulk-bar{display:none;align-items:center;gap:10px;padding:10px 16px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:14px;flex-wrap:wrap;}
.bulk-bar.visible{display:flex;}
.bulk-badge{display:inline-flex;align-items:center;background:var(--accent-soft);color:var(--accent);font-weight:700;font-size:11.5px;font-family:var(--mono);padding:4px 10px;border-radius:20px;border:1px solid rgba(26,144,217,0.2);}
.flash-message{display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;margin-bottom:14px;background:rgba(22,163,74,0.10);border:1px solid rgba(22,163,74,0.30);color:#126934;}
.table-container{overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 300px);border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.data-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.data-table thead{position:sticky;top:0;z-index:2;}
.data-table th{background:var(--navy);color:rgba(255,255,255,0.80);padding:10px 12px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,0.07);}
.data-table th:last-child{border-right:none;}
.data-table td{padding:9px 12px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);vertical-align:middle;color:var(--text);white-space:normal;word-break:break-word;}
.data-table td:last-child{border-right:none;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tbody tr{cursor:pointer;}
.data-table tbody tr:nth-child(even){background:var(--bg-raised);}
.data-table tbody tr:hover{background:rgba(26,144,217,0.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.date-cell{text-align:center;font-family:var(--mono);font-size:11.5px;color:var(--text-2);}
.data-table input[type="checkbox"]{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;}
.bulk-col{display:none;width:36px;padding:0 8px!important;cursor:default!important;}
.bulk-col-header{display:none;width:36px;padding:0 8px!important;}
.status-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;}
.status-good{background:rgba(22,163,74,0.12);color:#126934;}
.status-not-good{background:rgba(220,53,53,0.10);color:#a81c1c;}
.status-disposed{background:rgba(100,116,139,0.10);color:#4a6070;}
.status-for-disposal{background:rgba(202,138,4,0.10);color:#8a4d00;}
.status-for-repair{background:rgba(202,138,4,0.10);color:#8a4d00;}
.status-for-replace{background:rgba(124,58,237,0.10);color:#5b21b6;}
.status-missing{background:rgba(220,53,53,0.10);color:#a81c1c;}
.status-not-in-use{background:rgba(100,116,139,0.10);color:#4a6070;}
.status-excluded{background:rgba(202,138,4,0.10);color:#8a4d00;}
.status-included{background:rgba(22,163,74,0.12);color:#126934;}
.no-results-row{cursor:default!important;}
.no-results-row:hover{background:transparent!important;box-shadow:none!important;}
.no-results-row td{text-align:center;padding:60px 20px!important;color:var(--text-3);font-size:13px;border-right:none!important;cursor:default;}
.no-results-icon{font-size:32px;display:block;margin-bottom:10px;opacity:0.45;}
.no-results-label{font-weight:700;color:var(--navy);display:block;margin-bottom:4px;font-size:14px;}
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);min-height:52px;}
.pagination button{padding:6px 16px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-raised);color:var(--navy);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;min-width:90px;text-align:center;box-sizing:border-box;}
.pagination button:hover:not(:disabled){background:var(--navy);color:#fff;border-color:var(--navy);}
.pagination button:disabled{opacity:0.4;cursor:not-allowed;}
.pagination-info{font-size:11.5px;color:var(--text-3);font-family:var(--mono);min-width:120px;text-align:center;display:inline-block;}
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,0.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;box-sizing:border-box;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px;color:var(--text-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;line-height:1;}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}
.detail-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);border:1px solid var(--border);}
.detail-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:18px 22px 16px;border-bottom:1px solid var(--border);}
.detail-modal-header-text h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 3px;}
.detail-modal-header-text p{font-size:11.5px;color:var(--text-3);margin:0;font-family:var(--mono);}
.detail-modal-body{padding:20px 22px;display:grid;grid-template-columns:1fr 1fr;gap:14px 20px;}
.detail-field-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);margin-bottom:4px;}
.detail-field-value{font-size:13px;font-weight:500;color:var(--text);word-break:break-word;}
.detail-field-value.mono{font-family:var(--mono);font-size:12px;}
.detail-field.full{grid-column:1/-1;}
.detail-divider{grid-column:1/-1;height:1px;background:var(--border);margin:2px 0;}
.detail-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--border);}
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:400px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;text-align:center;}
.confirm-modal-icon{font-size:36px;padding:26px 0 6px;}
.confirm-modal-body{padding:0 28px 20px;}
.confirm-modal-body h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-body p{font-size:13px;color:var(--text-2);margin:0;line-height:1.55;}
.confirm-modal-footer{display:flex;gap:10px;justify-content:center;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>
    <div class="card">
        <div class="card-header">
            <h2>Exclusion-Inclusion Summary</h2>
            <p>Records of excluded and included equipment / instruments</p>
        </div>
        <div class="card-body">

            <?php if ($flashMessage): ?>
            <div class="flash-message">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <?= htmlspecialchars($flashMessage) ?>
            </div>
            <?php endif; ?>

            <div class="top-controls">
                <div class="controls-left">
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-primary" onclick="window.location.href='add_exclusion.php'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New Record
                    </button>
                    <?php endif; ?>
                </div>
                <div class="controls-right">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search…">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Excluded">Excluded</option>
                        <option value="Included">Included</option>
                    </select>
                    <select class="filter-select" id="yearFilter"
                            onchange="window.location.href='exclusion_summary.php?period='+encodeURIComponent(this.value)"
                            style="min-width:110px;">
                        <option value="" <?= $selectedYear === '' ? 'selected' : '' ?>>All Years</option>
                        <?php foreach ($allYears as $y): ?>
                        <option value="<?= htmlspecialchars($y) ?>" <?= $selectedYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-danger" id="toggleDeleteBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                        Delete Multiple
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bulk-bar" id="bulkDeleteBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Bulk Delete</span>
                <button class="btn btn-danger-solid" type="button" id="deleteSelectedBtn" disabled>Delete Selected</button>
                <button class="btn btn-muted" type="button" id="cancelDeleteBtn">Cancel</button>
                <span class="bulk-badge" id="selectedCountBadge">0 selected</span>
            </div>

            <form method="POST" action="delete_multiple_exclusion.php" id="exclusionForm">
                <div id="deleteHiddenInputs"></div>
                <div class="table-container">
                    <table class="data-table" id="dataTable">
                        <thead>
                            <tr>
                                <th class="bulk-col-header" id="bulkColHeader"><input type="checkbox" id="selectAllDelete"></th>
                                <th>#</th>
                                <th>Date Added</th>
                                <th>Description</th>
                                <th>Control No.</th>
                                <th>Model / Maker</th>
                                <th>Serial No.</th>
                                <th>Location</th>
                                <th>Calib / Insp Date</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $counter = 1;
                        foreach ($allRows as $row):
                            $status = ucfirst($row['present_status']);
                            $statusClass = 'status-good';
                            switch(strtolower($row['present_status'])) {
                                case 'not good':        $statusClass = 'status-not-good';    break;
                                case 'disposed':        $statusClass = 'status-disposed';     break;
                                case 'for disposal':    $statusClass = 'status-for-disposal'; break;
                                case 'for repair':      $statusClass = 'status-for-repair';   break;
                                case 'for replacement': $statusClass = 'status-for-replace';  break;
                                case 'missing':         $statusClass = 'status-missing';      break;
                                case 'not in use':      $statusClass = 'status-not-in-use';   break;
                                case 'excluded':        $statusClass = 'status-excluded';     break;
                                case 'included':        $statusClass = 'status-included';     break;
                            }
                            $calDate   = (!empty($row['calibration_inspection_date']) && $row['calibration_inspection_date'] !== '0000-00-00')
                                ? date('d-M-y', strtotime($row['calibration_inspection_date'])) : '—';
                            $dateAdded = (!empty($row['date_added']) && $row['date_added'] !== '0000-00-00')
                                ? date('d-M-y', strtotime($row['date_added'])) : '—';
                            $da  = " data-id='{$row['id']}'";
                            $da .= " data-description='".htmlspecialchars($row['description'], ENT_QUOTES)."'";
                            $da .= " data-control-number='".htmlspecialchars($row['control_number'], ENT_QUOTES)."'";
                            $da .= " data-model-maker='".htmlspecialchars($row['model_maker'], ENT_QUOTES)."'";
                            $da .= " data-serial-number='".htmlspecialchars($row['serial_number'], ENT_QUOTES)."'";
                            $da .= " data-location='".htmlspecialchars($row['location'], ENT_QUOTES)."'";
                            $da .= " data-date-added='".htmlspecialchars($dateAdded, ENT_QUOTES)."'";
                            $da .= " data-cal-date='".htmlspecialchars($calDate, ENT_QUOTES)."'";
                            $da .= " data-status='".htmlspecialchars($status, ENT_QUOTES)."'";
                            $da .= " data-status-class='{$statusClass}'";
                            $da .= " data-remarks='".htmlspecialchars($row['remarks'], ENT_QUOTES)."'";
                            $da .= " data-recorded-by='".htmlspecialchars($row['recorded_by'], ENT_QUOTES)."'";
                        ?>
                        <tr class="data-row" <?= $da ?>>
                            <td class="bulk-col" onclick="event.stopPropagation()"><input type="checkbox" class="delete-cb" value="<?= $row['id'] ?>"></td>
                            <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-3);"><?= $counter++ ?></td>
                            <td class="date-cell"><?= htmlspecialchars($dateAdded) ?></td>
                            <td title="<?= htmlspecialchars($row['description']) ?>"><?= htmlspecialchars($row['description']) ?></td>
                            <td style="font-family:'DM Mono',monospace;font-size:11.5px;"><?= htmlspecialchars($row['control_number']) ?></td>
                            <td><?= htmlspecialchars($row['model_maker']) ?></td>
                            <td style="font-family:'DM Mono',monospace;font-size:11.5px;"><?= htmlspecialchars($row['serial_number']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td class="date-cell"><?= $calDate ?></td>
                            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td title="<?= htmlspecialchars($row['remarks']) ?>"><?= htmlspecialchars($row['remarks']) ?></td>
                            <td><?= htmlspecialchars($row['recorded_by']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="noResultsRow" class="no-results-row" style="display:none;">
                            <td colspan="100">
                                <span class="no-results-icon">🔍</span>
                                <span class="no-results-label">No results found</span>
                                Try adjusting your search or filter.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </form>

        </div>
    </div>
</div>

<!-- DETAIL MODAL -->
<div id="detailModal" class="modal-overlay">
    <div class="detail-modal-box">
        <div class="detail-modal-header">
            <div class="detail-modal-header-text"><h3 id="modal-description">—</h3><p id="modal-control-number">—</p></div>
            <button class="modal-close-btn" id="closeDetailModal">&times;</button>
        </div>
        <div class="detail-modal-body">
            <div class="detail-field"><div class="detail-field-label">Model / Maker</div><div class="detail-field-value" id="modal-model-maker">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Serial Number</div><div class="detail-field-value mono" id="modal-serial-number">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Location</div><div class="detail-field-value" id="modal-location">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Recorded By</div><div class="detail-field-value" id="modal-recorded-by">—</div></div>
            <div class="detail-divider"></div>
            <div class="detail-field"><div class="detail-field-label">Date Added</div><div class="detail-field-value mono" id="modal-date-added">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Calib / Insp Date</div><div class="detail-field-value mono" id="modal-cal-date">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Present Status</div><div class="detail-field-value" id="modal-status">—</div></div>
            <div class="detail-field full"><div class="detail-field-label">Remarks</div><div class="detail-field-value" id="modal-remarks">—</div></div>
        </div>
        <div class="detail-modal-footer">
            <button class="btn btn-muted" id="closeDetailModalBtn">Cancel</button>
            <?php if (!$isGuest): ?>
            <a class="btn btn-primary" id="editRecordBtn" href="#">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Record
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">🗑️</div>
        <div class="confirm-modal-body">
            <h3>Delete selected records?</h3>
            <p>You are about to delete <strong id="deleteCountSpan">0</strong> record(s).<br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="confirmDeleteYesBtn">Yes, Delete</button>
            <button class="btn btn-muted" id="confirmDeleteNoBtn">Cancel</button>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tableBody           = document.querySelector('#dataTable tbody');
    const noResultsRow        = document.getElementById('noResultsRow');
    const searchInput         = document.getElementById('searchInput');
    const statusFilter        = document.getElementById('statusFilter');
    const detailModal         = document.getElementById('detailModal');
    const closeDetailModal    = document.getElementById('closeDetailModal');
    const closeDetailModalBtn = document.getElementById('closeDetailModalBtn');
    const editRecordBtn       = document.getElementById('editRecordBtn');
    const toggleDeleteBtn     = document.getElementById('toggleDeleteBtn');
    const bulkDeleteBar       = document.getElementById('bulkDeleteBar');
    const deleteSelectedBtn   = document.getElementById('deleteSelectedBtn');
    const cancelDeleteBtn     = document.getElementById('cancelDeleteBtn');
    const selectAllDelete     = document.getElementById('selectAllDelete');
    const bulkColHeader       = document.getElementById('bulkColHeader');
    const selectedBadge       = document.getElementById('selectedCountBadge');
    const deleteHiddenInputs  = document.getElementById('deleteHiddenInputs');
    const exclusionForm       = document.getElementById('exclusionForm');
    const deleteConfirmModal  = document.getElementById('deleteConfirmModal');
    const deleteCountSpan     = document.getElementById('deleteCountSpan');
    const confirmDeleteYesBtn = document.getElementById('confirmDeleteYesBtn');
    const confirmDeleteNoBtn  = document.getElementById('confirmDeleteNoBtn');

    let filteredRows = [];
    let currentPage  = 1;
    const rowsPerPage = 15;
    const statusColIndex = 9;

    function showBulkCols() {
        document.querySelectorAll('.bulk-col').forEach(c => c.style.display = 'table-cell');
        if (bulkColHeader) bulkColHeader.style.display = 'table-cell';
    }
    function hideBulkCols() {
        document.querySelectorAll('.bulk-col').forEach(c => c.style.display = 'none');
        if (bulkColHeader) bulkColHeader.style.display = 'none';
    }
    function uncheckAll() {
        document.querySelectorAll('.delete-cb').forEach(cb => cb.checked = false);
        if (selectAllDelete) selectAllDelete.checked = false;
    }
    function updateDeleteBadge() {
        const count = document.querySelectorAll('.delete-cb:checked').length;
        if (selectedBadge) selectedBadge.textContent = `${count} selected`;
        if (deleteSelectedBtn) deleteSelectedBtn.disabled = count === 0;
    }

    if (toggleDeleteBtn) toggleDeleteBtn.addEventListener('click', () => { bulkDeleteBar.classList.add('visible'); showBulkCols(); updateDeleteBadge(); });
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => { bulkDeleteBar.classList.remove('visible'); hideBulkCols(); uncheckAll(); updateDeleteBadge(); });
    if (selectAllDelete) selectAllDelete.addEventListener('change', function () {
        filteredRows.slice((currentPage-1)*rowsPerPage, currentPage*rowsPerPage).forEach(row => { const cb = row.querySelector('.delete-cb'); if (cb) cb.checked = this.checked; });
        updateDeleteBadge();
    });
    tableBody.addEventListener('change', e => { if (e.target && e.target.matches('.delete-cb')) updateDeleteBadge(); });

    if (deleteSelectedBtn) deleteSelectedBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.delete-cb:checked');
        if (checked.length === 0) { alert('Please select at least one record to delete.'); return; }
        deleteCountSpan.textContent = checked.length;
        deleteConfirmModal.classList.add('open');
    });
    if (confirmDeleteYesBtn) confirmDeleteYesBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.delete-cb:checked');
        deleteHiddenInputs.innerHTML = '';
        checked.forEach(cb => { const input = document.createElement('input'); input.type='hidden'; input.name='selected_ids[]'; input.value=cb.value; deleteHiddenInputs.appendChild(input); });
        exclusionForm.submit();
    });
    if (confirmDeleteNoBtn) confirmDeleteNoBtn.addEventListener('click', () => deleteConfirmModal.classList.remove('open'));
    deleteConfirmModal.addEventListener('click', e => { if (e.target === deleteConfirmModal) deleteConfirmModal.classList.remove('open'); });

    tableBody.addEventListener('click', function (e) {
        if (e.target.closest('.bulk-col')) return;
        const row = e.target.closest('tr.data-row');
        if (!row) return;
        const d = row.dataset;
        document.getElementById('modal-description').textContent    = d.description   || '—';
        document.getElementById('modal-control-number').textContent = d.controlNumber || '—';
        document.getElementById('modal-model-maker').textContent    = d.modelMaker    || '—';
        document.getElementById('modal-serial-number').textContent  = d.serialNumber  || '—';
        document.getElementById('modal-location').textContent       = d.location      || '—';
        document.getElementById('modal-recorded-by').textContent    = d.recordedBy    || '—';
        document.getElementById('modal-date-added').textContent     = d.dateAdded     || '—';
        document.getElementById('modal-cal-date').textContent       = d.calDate       || '—';
        document.getElementById('modal-remarks').textContent        = d.remarks       || '—';
        document.getElementById('modal-status').innerHTML = `<span class="status-badge ${d.statusClass}">${d.status}</span>`;
        if (editRecordBtn) editRecordBtn.href = `edit_exclusion.php?id=${d.id}`;
        detailModal.classList.add('open');
    });
    function closeDetail() { detailModal.classList.remove('open'); }
    closeDetailModal.addEventListener('click', closeDetail);
    closeDetailModalBtn.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetail(); });

    function filterRows(resetPage = false) {
        const search = searchInput.value.toLowerCase();
        const status = statusFilter.value.toLowerCase();
        filteredRows = Array.from(tableBody.querySelectorAll('tr.data-row')).filter(row => {
            const text = row.innerText.toLowerCase();
            const st   = row.cells[statusColIndex]?.innerText.toLowerCase() || '';
            return text.includes(search) && (status === '' || st.includes(status));
        });
        if (resetPage) currentPage = 1;
        if (selectAllDelete) selectAllDelete.checked = false;
        showPage();
    }

    function showPage() {
        const start = (currentPage - 1) * rowsPerPage;
        const end   = start + rowsPerPage;
        tableBody.querySelectorAll('tr.data-row').forEach(r => r.style.display = 'none');
        filteredRows.slice(start, end).forEach(r => r.style.display = '');
        if (noResultsRow) noResultsRow.style.display = filteredRows.length === 0 ? '' : 'none';
        renderPagination();
        updateDeleteBadge();
    }

    function renderPagination() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
        const p = document.getElementById('pagination');
        p.innerHTML = '';
        const prev = document.createElement('button'); prev.textContent = '← Prev'; prev.disabled = currentPage === 1; prev.onclick = () => { currentPage--; showPage(); };
        const info = document.createElement('span'); info.className = 'pagination-info'; info.textContent = filteredRows.length === 0 ? 'No results' : `Page ${currentPage} of ${totalPages}`;
        const next = document.createElement('button'); next.textContent = 'Next →'; next.disabled = currentPage === totalPages; next.onclick = () => { currentPage++; showPage(); };
        p.appendChild(prev); p.appendChild(info); p.appendChild(next);
    }

    searchInput.addEventListener('input',   () => filterRows(true));
    statusFilter.addEventListener('change', () => filterRows(true));

    hideBulkCols();
    filterRows();
});
</script>
</body>
</html>