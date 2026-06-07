<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

function formatFrequency($freq) {
    switch($freq) {
        case '6':  return '6 months';
        case '12': return '1 year';
        case '24': return '2 years';
        case '60': return '5 years';
        default:   return $freq;
    }
}
function formatDateExcel($date) {
    if (empty($date) || $date === '0000-00-00') return '';
    return date('d-M-y', strtotime($date));
}

$currentPageFromQuery = isset($_GET['page']) ? intval($_GET['page']) : 1;
$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_delete_password') {
    header('Content-Type: application/json');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    echo json_encode($hash && password_verify($password, $hash)
        ? ['success' => true]
        : ['success' => false, 'message' => 'Incorrect password.']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calibration Report — Calibration Management</title>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" defer></script>
<style>
:root {
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15);--accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7;--bg-card:#ffffff;--bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10);--border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d;--text-2:#4a6070;--text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px;--r-md:12px;--r-lg:16px;--r-xl:20px;
    --shadow-xs:0 1px 3px rgba(5,48,79,0.06);
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);
    --shadow-md:0 4px 20px rgba(5,48,79,0.10);
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
.btn-accent{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(26,144,217,0.25);}
.btn-accent:hover{background:#1480c5;}
.btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,0.25);}
.btn-success:hover{background:#15803d;}
.btn-cert{background:#7c3aed;color:#fff;box-shadow:0 2px 8px rgba(124,58,237,0.25);}
.btn-cert:hover{background:#6d28d9;}
.btn-danger{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.btn-danger:hover{background:rgba(220,53,53,0.16);}
.btn-danger-solid{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.25);}
.btn-danger-solid:hover{background:#b91c1c;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}
.filter-input,.filter-select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);box-sizing:border-box;}
.filter-input:focus,.filter-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.filter-input{min-width:200px;}
.date-range-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:11px 16px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:14px;}
.date-range-label{font-size:10.5px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;}
.date-range-inputs{display:flex;align-items:center;gap:6px;}
.date-range-input{height:32px;padding:0 10px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12px;font-family:var(--mono);color:var(--text);background:var(--bg-card);cursor:pointer;box-sizing:border-box;}
.date-range-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.date-range-sep{font-size:12px;color:var(--text-3);font-weight:600;}
.date-range-divider{width:1px;height:20px;background:var(--border);flex-shrink:0;}
/* ── Field toggle select ── */
.date-field-select{height:30px;padding:0 8px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:11.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);background:var(--bg-card);cursor:pointer;font-weight:600;}
.date-field-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.range-presets{display:flex;align-items:center;gap:5px;flex-wrap:wrap;}
.preset-chip{display:inline-flex;align-items:center;padding:4px 11px;border-radius:20px;border:1px solid var(--border);background:var(--bg-card);font-size:11.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text-2);cursor:pointer;white-space:nowrap;user-select:none;}
.preset-chip:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.preset-chip.active{background:var(--navy);border-color:var(--navy);color:#fff;}
.preset-chip[data-preset="overdue"].active{background:#dc2626;border-color:#dc2626;}
.preset-chip[data-preset="overdue"]:hover:not(.active){border-color:#dc2626;color:#dc2626;background:rgba(220,53,53,0.08);}


.range-result-badge{font-size:11px;font-family:var(--mono);font-weight:700;color:var(--accent);background:var(--accent-soft);border:1px solid rgba(26,144,217,0.2);border-radius:20px;padding:3px 10px;white-space:nowrap;}
.range-clear-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;border:1px solid rgba(220,53,53,0.20);background:rgba(220,53,53,0.08);font-size:11.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;color:#a81c1c;cursor:pointer;white-space:nowrap;}
.range-clear-btn:hover{background:rgba(220,53,53,0.14);}
.range-active-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-left:3px;vertical-align:middle;}
.bulk-bar{display:none;align-items:center;gap:10px;padding:10px 16px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:14px;flex-wrap:wrap;}
.bulk-bar.visible{display:flex;}
#bulkExportBar.visible{background:#f0fdf4;border-color:#86efac;border-left:3px solid #16a34a;}
.bulk-badge{display:inline-flex;align-items:center;background:var(--accent-soft);color:var(--accent);font-weight:700;font-size:11.5px;font-family:var(--mono);padding:4px 10px;border-radius:20px;border:1px solid rgba(26,144,217,0.2);}
.bulk-export-hint{font-size:11.5px;color:var(--text-3);font-style:italic;margin-left:auto;}
.table-legend{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;font-size:11.5px;color:var(--text-2);}
.legend-item{display:flex;align-items:center;gap:6px;}
.legend-chip{display:inline-block;width:28px;height:12px;border-radius:20px;border:1px solid transparent;flex-shrink:0;}
.lc-overdue{background:#fef0f0;border-color:rgba(220,53,53,0.25);}
.lc-thismonth{background:#e8f5ff;border-color:rgba(26,144,217,0.25);}
.lc-nextmonth{background:#f0faf6;border-color:rgba(22,163,74,0.25);}
.lc-external{background:#fffbea;border-color:rgba(202,138,4,0.25);}
.table-container{overflow-x:auto;overflow-y:visible;border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.calibration-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12.5px;}
.calibration-table[data-mode="full"]{min-width:1321px;}
.calibration-table[data-mode="guest"]{min-width:1217px;}
.calibration-table thead{position:sticky;top:0;z-index:2;}
.calibration-table th{background:var(--navy);color:rgba(255,255,255,0.80);padding:10px 12px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,0.07);}
.calibration-table th:last-child{border-right:none;}
.calibration-table td{padding:9px 12px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;color:var(--text);line-height:16px;}
.calibration-table td:last-child{border-right:none;}
.calibration-table tr:last-child td{border-bottom:none;}
.calibration-table tbody tr{cursor:pointer;}
.calibration-table tbody tr:nth-child(even){background:var(--bg-raised);}
.calibration-table tbody tr:hover{background:rgba(26,144,217,0.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.calibration-table td.bulkCol,.calibration-table td.printCol,.calibration-table td.exportCol{cursor:default;}
.calibration-table td.exportCol{text-align:center;}
.calibration-table td.exportCol input[type="checkbox"]{accent-color:#16a34a;}
.calibration-table th.exportColHeader{background:#14532d;text-align:center;}
.calibration-table th.exportColHeader input[type="checkbox"]{accent-color:#4ade80;}
.calibration-table tbody tr:has(.export-cb:checked){background:#f0fdf4!important;box-shadow:inset 3px 0 0 #16a34a;}
.no-results-row{cursor:default!important;}
.no-results-row:hover{background:transparent!important;box-shadow:none!important;}
.no-results-row td{text-align:center;padding:60px 20px!important;color:var(--text-3);font-size:13px;border-right:none!important;cursor:default;line-height:1.5!important;}
.no-results-icon{font-size:32px;display:block;margin-bottom:10px;opacity:0.45;}
.no-results-label{font-weight:700;color:var(--navy);display:block;margin-bottom:4px;font-size:14px;}
.date-cell{text-align:center;font-family:var(--mono);font-size:11.5px;color:var(--text-2);}
.calibration-table[data-mode="full"] th:nth-child(1),.calibration-table[data-mode="full"] td:nth-child(1){width:32px;padding:0 4px;}
.calibration-table[data-mode="full"] th:nth-child(2),.calibration-table[data-mode="full"] td:nth-child(2){width:32px;padding:0 4px;}
.calibration-table[data-mode="full"] th:nth-child(3),.calibration-table[data-mode="full"] td:nth-child(3){width:32px;padding:0 4px;}
.calibration-table[data-mode="full"] th:nth-child(4),.calibration-table[data-mode="full"] td:nth-child(4){width:40px;}
.calibration-table[data-mode="full"] th:nth-child(5),.calibration-table[data-mode="full"] td:nth-child(5){width:200px;}
.calibration-table[data-mode="full"] th:nth-child(6),.calibration-table[data-mode="full"] td:nth-child(6){width:130px;}
.calibration-table[data-mode="full"] th:nth-child(7),.calibration-table[data-mode="full"] td:nth-child(7){width:120px;}
.calibration-table[data-mode="full"] th:nth-child(8),.calibration-table[data-mode="full"] td:nth-child(8){width:130px;}
.calibration-table[data-mode="full"] th:nth-child(9),.calibration-table[data-mode="full"] td:nth-child(9){width:120px;}
.calibration-table[data-mode="full"] th:nth-child(10),.calibration-table[data-mode="full"] td:nth-child(10){width:100px;}
.calibration-table[data-mode="full"] th:nth-child(11),.calibration-table[data-mode="full"] td:nth-child(11){width:100px;}
.calibration-table[data-mode="full"] th:nth-child(12),.calibration-table[data-mode="full"] td:nth-child(12){width:85px;}
.calibration-table[data-mode="full"] th:nth-child(13),.calibration-table[data-mode="full"] td:nth-child(13){width:90px;}
.calibration-table[data-mode="full"] th:nth-child(14),.calibration-table[data-mode="full"] td:nth-child(14){width:110px;}
.calibration-table[data-mode="guest"] th:nth-child(1),.calibration-table[data-mode="guest"] td:nth-child(1){width:32px;padding:0 4px;}
.calibration-table[data-mode="guest"] th:nth-child(2),.calibration-table[data-mode="guest"] td:nth-child(2){width:200px;}
.calibration-table[data-mode="guest"] th:nth-child(3),.calibration-table[data-mode="guest"] td:nth-child(3){width:130px;}
.calibration-table[data-mode="guest"] th:nth-child(4),.calibration-table[data-mode="guest"] td:nth-child(4){width:120px;}
.calibration-table[data-mode="guest"] th:nth-child(5),.calibration-table[data-mode="guest"] td:nth-child(5){width:130px;}
.calibration-table[data-mode="guest"] th:nth-child(6),.calibration-table[data-mode="guest"] td:nth-child(6){width:120px;}
.calibration-table[data-mode="guest"] th:nth-child(7),.calibration-table[data-mode="guest"] td:nth-child(7){width:100px;}
.calibration-table[data-mode="guest"] th:nth-child(8),.calibration-table[data-mode="guest"] td:nth-child(8){width:100px;}
.calibration-table[data-mode="guest"] th:nth-child(9),.calibration-table[data-mode="guest"] td:nth-child(9){width:85px;}
.calibration-table[data-mode="guest"] th:nth-child(10),.calibration-table[data-mode="guest"] td:nth-child(10){width:90px;}
.calibration-table[data-mode="guest"] th:nth-child(11),.calibration-table[data-mode="guest"] td:nth-child(11){width:110px;}
.status-badge{display:inline-block;padding:0 7px;border-radius:3px;font-size:11px;font-weight:700;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;line-height:16px;height:16px;vertical-align:middle;box-sizing:border-box;}
.status-good{background:rgba(22,163,74,0.12);color:#126934;}
.status-not-good{background:rgba(220,53,53,0.10);color:#a81c1c;}
.status-disposed{background:rgba(100,116,139,0.10);color:#4a6070;}
.status-for-disposal{background:rgba(202,138,4,0.10);color:#8a4d00;}
.status-for-repair{background:rgba(202,138,4,0.10);color:#8a4d00;}
.status-for-replace{background:rgba(124,58,237,0.10);color:#5b21b6;}
.status-missing{background:rgba(220,53,53,0.10);color:#a81c1c;}
.status-not-in-use{background:rgba(100,116,139,0.10);color:#4a6070;}
.calibration-table input[type="checkbox"]{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;}
.calibrator-external{display:inline-block;padding:0 6px;border-radius:3px;background:rgba(202,138,4,0.10);color:#8a4d00;font-weight:700;font-size:11px;font-family:'Plus Jakarta Sans',sans-serif;line-height:16px;height:16px;vertical-align:middle;box-sizing:border-box;}
.cal-chip{display:inline-block;padding:0 6px;border-radius:3px;font-family:var(--mono);font-size:11px;font-weight:600;white-space:nowrap;line-height:16px;height:16px;vertical-align:middle;box-sizing:border-box;}
.cal-overdue{background:rgba(220,53,53,0.10);color:#a81c1c;}
.cal-this-month{background:rgba(26,144,217,0.10);color:#0b5c96;}
.cal-next-month{background:rgba(22,163,74,0.10);color:#126934;}
.cal-default{color:var(--text-2);font-family:var(--mono);font-size:11.5px;}
.detail-field-value.remarks-value{white-space:pre-wrap;word-break:break-word;font-size:12.5px;color:var(--text-2);font-style:italic;line-height:1.55;}
.detail-field-value.remarks-value.has-value{color:var(--text);font-style:normal;}
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);min-height:52px;}
.pagination button{padding:6px 16px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-raised);color:var(--navy);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;min-width:90px;text-align:center;box-sizing:border-box;}
.pagination button:hover:not(:disabled){background:var(--navy);color:#fff;border-color:var(--navy);}
.pagination button:disabled{opacity:0.4;cursor:not-allowed;}
.pagination-info{font-size:11.5px;color:var(--text-3);font-family:var(--mono);min-width:120px;text-align:center;display:inline-block;}
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,0.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;box-sizing:border-box;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px;color:var(--text-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;line-height:1;}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}
.detail-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);border:1px solid var(--border);}
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
.modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:360px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.modal-box-header{padding:18px 20px 16px;border-bottom:1px solid var(--border);}
.modal-box-header h3{font-size:14.5px;font-weight:700;color:var(--navy);margin:0;}
.modal-box-body{padding:18px 20px;display:flex;flex-direction:column;gap:10px;}
.sticker-btn{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-radius:var(--r-md);border:1px solid var(--border);background:var(--bg-raised);cursor:pointer;font-size:13.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}
.sticker-btn:hover{background:var(--accent-soft);border-color:var(--accent);}
.sticker-btn span.size-tag{font-size:11px;font-weight:500;color:var(--text-3);font-family:var(--mono);}
.modal-box-footer{padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-raised);display:flex;justify-content:flex-end;}
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:420px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.confirm-modal-icon{font-size:36px;padding:26px 0 6px;text-align:center;}
.confirm-modal-body{padding:0 28px 20px;text-align:center;}
.confirm-modal-body h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-body p{font-size:13px;color:var(--text-2);margin:0 0 18px;line-height:1.55;}
.confirm-pw-row{display:flex;flex-direction:column;gap:5px;text-align:left;margin-bottom:4px;}
.confirm-pw-row label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);}
.confirm-pw-wrap{position:relative;}
.confirm-pw-input{width:100%;box-sizing:border-box;padding:9px 42px 9px 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);}
.confirm-pw-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.confirm-pw-input.error-input{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,0.10);}
.confirm-pw-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.confirm-pw-eye:hover{color:var(--navy);}
.confirm-pw-eye svg{width:15px;height:15px;}
.confirm-modal-error{display:none;font-size:12px;color:#a81c1c;font-weight:600;background:rgba(220,53,53,0.08);border:1px solid rgba(220,53,53,0.20);border-radius:var(--r-sm);padding:8px 12px;margin-top:10px;text-align:left;}
.confirm-modal-error.show{display:block;}
.confirm-modal-footer{display:flex;gap:10px;justify-content:center;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}
.export-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:580px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.export-modal-header{padding:18px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.export-modal-header h3{font-size:14.5px;font-weight:700;color:var(--navy);margin:0;}
.export-modal-body{padding:20px 22px;display:flex;flex-direction:column;gap:16px;overflow-y:auto;flex:1;}
.export-format-row{display:flex;gap:10px;}
.export-btn{flex:1;display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:var(--r-md);border:1px solid var(--border);background:var(--bg-raised);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);text-align:left;}
.export-btn:hover{background:var(--accent-soft);border-color:var(--accent);}
.export-btn-icon{font-size:22px;line-height:1;flex-shrink:0;}
.export-btn-text strong{display:block;font-size:12.5px;font-weight:700;margin-bottom:2px;}
.export-btn-text span{font-size:11px;color:var(--text-3);font-family:var(--mono);}
.export-modal-footer{padding:12px 22px;border-top:1px solid var(--border);background:var(--bg-raised);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
.export-filter-note{font-size:11px;color:var(--text-3);font-style:italic;font-family:var(--mono);}
.export-signatories-section{border:1px solid var(--border);border-radius:var(--r-md);overflow:hidden;}
.export-signatories-label{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:var(--bg-raised);border-bottom:1px solid var(--border);font-size:12px;font-weight:700;color:var(--navy);}
.export-signatories-hint{font-size:10.5px;font-weight:500;color:var(--text-3);font-style:italic;}
.export-signatories-grid{display:grid;grid-template-columns:1fr 1fr;}
.signatory-field{padding:10px 12px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:5px;}
.signatory-field:nth-child(2n){border-right:none;}
.signatory-field:nth-last-child(-n+2){border-bottom:none;}
.sig-name,.sig-title{width:100%;border:1px solid var(--border-mid);border-radius:6px;padding:5px 8px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-card);box-sizing:border-box;}
.sig-name{font-size:12px;font-weight:700;}
.sig-title{font-size:11px;color:var(--text-2);}
.sig-name:focus,.sig-title:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.sig-name::placeholder,.sig-title::placeholder{color:var(--text-3);font-weight:400;}
.rev-history-table{width:100%;border-collapse:collapse;font-size:11.5px;}
.rev-history-header{display:grid;grid-template-columns:32px 70px 80px 1fr 100px 100px;background:var(--navy);}
.rev-history-header span{padding:7px 7px;font-size:10px;font-weight:700;color:rgba(255,255,255,0.80);text-transform:uppercase;letter-spacing:0.4px;border-right:1px solid rgba(255,255,255,0.08);}
.rev-history-header span:last-child{border-right:none;}
.rev-history-row{display:grid;grid-template-columns:32px 70px 80px 1fr 100px 100px;border-bottom:1px solid var(--border);}
.rev-history-row:last-child{border-bottom:none;}
.rev-history-row:nth-child(even){background:var(--bg-raised);}
.rev-input{width:100%;border:none;border-right:1px solid var(--border);padding:5px 7px;font-family:'Plus Jakarta Sans',sans-serif;font-size:11.5px;color:var(--text);background:transparent;box-sizing:border-box;}
.rev-input:last-child{border-right:none;}
.rev-input:focus{outline:none;background:var(--accent-soft);box-shadow:inset 0 0 0 2px rgba(26,144,217,0.25);position:relative;z-index:1;}
.rev-input::placeholder{color:var(--text-3);font-style:italic;}
.rev-code{text-align:center;font-family:var(--mono);font-weight:700;}
.rev-date{font-family:var(--mono);font-size:11px;}
.rev-desc{font-size:11px;}
.rev-appr1,.rev-appr2{color:var(--text-3);font-style:italic;font-size:11px;}
@keyframes cr-spin{to{transform:rotate(360deg);}}
.spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;animation:cr-spin 0.6s linear infinite;display:none;}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>
    <div class="card">
        <div class="card-header">
            <h2>Equipment / Instruments Calibration Status Report</h2>
            <p>Reference: PC2-9710 Calibration Control Procedure</p>
        </div>
        <div class="card-body">
            <div class="top-controls">
                <div class="controls-left">
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-primary" id="addNewBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New
                    </button>
                    <button class="btn btn-accent" id="togglePrintBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print Stickers
                    </button>
                    <button class="btn btn-cert" id="certBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                        Certificate
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-success" id="exportReportBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export Report
                    </button>
                </div>
                <div class="controls-right">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search…">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
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
                    <select class="filter-select" id="calibratorFilter">
                        <option value="">All Calibrators</option>
                        <?php
                        $stmt = $pdo->query("SELECT DISTINCT calibrator FROM calibration_report WHERE calibrator IS NOT NULL AND calibrator != '' ORDER BY calibrator ASC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='".htmlspecialchars($row['calibrator'])."'>".htmlspecialchars($row['calibrator'])."</option>";
                        }
                        ?>
                    </select>
                    <select class="filter-select" id="calTypeFilter">
                        <option value="">All Cal Types</option>
                        <option value="internal">Internal</option>
                        <option value="external">External</option>
                    </select>
                    <button class="btn btn-muted" id="toggleDateRangeBtn" title="Filter by date range">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Date Range
                        <span class="range-active-dot" id="rangeActiveDot" style="display:none;"></span>
                    </button>
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-danger" id="toggleDeleteBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                        Delete Multiple
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Date Range Bar ─────────────────────────────────────────── -->
            <div class="date-range-bar" id="dateRangeBar" style="display:none;">

                <!-- Field selector: which date column to filter -->
                <select class="date-field-select" id="dateRangeField" title="Choose which date to filter">
                    <option value="next_cal">Next Cal Date</option>
                    <option value="cal_date">Cal Date</option>
                </select>

                <div class="date-range-divider"></div>

                <!-- Manual date inputs -->
                <span class="date-range-label">Range</span>
                <div class="date-range-inputs">
                    <input type="date" class="date-range-input" id="dateFrom" title="From">
                    <span class="date-range-sep">→</span>
                    <input type="date" class="date-range-input" id="dateTo" title="To">
                </div>

                <div class="date-range-divider"></div>

                <!-- Preset chips -->
                <div class="range-presets">
                    <span class="preset-chip" data-preset="overdue">Overdue</span>
                    <span class="preset-chip" data-preset="thismonth">This Month</span>
                    <span class="preset-chip" data-preset="nextmonth">Next Month</span>
                    <span class="preset-chip" data-preset="lastmonth-cal">Last Month</span>
                </div>

                <div class="date-range-divider"></div>

                <span class="range-result-badge" id="rangeResultBadge" style="display:none;"></span>
                <button class="range-clear-btn" id="clearDateRange">✕ Clear</button>
            </div>

            <div class="bulk-bar" id="bulkDeleteBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Bulk Delete</span>
                <button class="btn btn-danger-solid" type="button" id="deleteSelectedBtn" disabled>Delete Selected</button>
                <button class="btn btn-muted" type="button" id="cancelDeleteBtn">Cancel</button>
                <span class="bulk-badge" id="selectedCountBadge">0 selected</span>
            </div>
            <div class="bulk-bar" id="bulkPrintBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Select items to print</span>
                <button class="btn btn-accent" type="button" id="continuePrintBtn">Continue →</button>
                <button class="btn btn-muted" type="button" id="cancelPrintBtn">Cancel</button>
                <span class="bulk-badge" id="printSelectedBadge">0 selected</span>
            </div>
            <div class="bulk-bar" id="bulkExportBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Select rows to export</span>
                <button class="btn btn-success" type="button" id="continueExportBtn">Continue →</button>
                <button class="btn btn-muted" type="button" id="cancelExportSelBtn">Cancel</button>
                <span class="bulk-badge" id="exportSelectedBadge">0 selected</span>
                <span class="bulk-export-hint">Leave all unchecked to export all filtered rows</span>
            </div>
            <div class="table-legend">
                <div class="legend-item"><span class="legend-chip lc-overdue"></span> Overdue</div>
                <div class="legend-item"><span class="legend-chip lc-thismonth"></span> This month</div>
                <div class="legend-item"><span class="legend-chip lc-nextmonth"></span> Next month</div>
                <div class="legend-item"><span class="legend-chip lc-external"></span> External calibrator</div>
            </div>
            <form method="POST" action="delete_multiple_calibration.php" id="calibrationForm">
                <input type="hidden" name="page" id="currentPageInput">
                <div id="deleteHiddenInputs"></div>
                <div class="table-container">
                    <table class="calibration-table" id="calibrationTable" data-mode="<?= $isGuest ? 'guest' : 'full' ?>">
                        <thead>
                            <tr>
                                <th class="exportColHeader" id="exportColHeader" style="display:none;"><input type="checkbox" id="selectAllExport"></th>
                                <?php if (!$isGuest): ?>
                                <th class="bulkCol" id="deleteColHeader" style="display:none;"><input type="checkbox" id="selectAllDelete"></th>
                                <th class="printColHeader" id="printColHeader" style="display:none;"><input type="checkbox" id="selectAllPrint"></th>
                                <th>No.</th>
                                <?php endif; ?>
                                <th>Description</th>
                                <th>Machine Code</th>
                                <th>Model / Maker</th>
                                <th>Serial Number</th>
                                <th>Location</th>
                                <th>Cal Date</th>
                                <th>Next Cal</th>
                                <th>Frequency</th>
                                <th>Calibrator</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM calibration_report ORDER BY machine_code ASC");
                        $count = 1;
                        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $statusClass = 'status-good';
                            switch($r['present_status']) {
                                case 'Not Good':        $statusClass = 'status-not-good';    break;
                                case 'Disposed':        $statusClass = 'status-disposed';     break;
                                case 'For Disposal':    $statusClass = 'status-for-disposal'; break;
                                case 'For Repair':      $statusClass = 'status-for-repair';   break;
                                case 'For Replacement': $statusClass = 'status-for-replace';  break;
                                case 'Missing':         $statusClass = 'status-missing';      break;
                                case 'Not In Use':      $statusClass = 'status-not-in-use';   break;
                            }
                            $nextCalRaw   = $r['next_calibration']   ?? '';
                            $calDateRaw   = $r['calibration_date']   ?? '';
                            $da  = " data-id='{$r['id']}'";
                            $da .= " data-description='".htmlspecialchars($r['description'],ENT_QUOTES)."'";
                            $da .= " data-machine-code='".htmlspecialchars($r['machine_code'],ENT_QUOTES)."'";
                            $da .= " data-model-maker='".htmlspecialchars($r['model_maker'],ENT_QUOTES)."'";
                            $da .= " data-model='".htmlspecialchars($r['model'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-maker='".htmlspecialchars($r['maker'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-serial-number='".htmlspecialchars($r['serial_number'],ENT_QUOTES)."'";
                            $da .= " data-location='".htmlspecialchars($r['location'],ENT_QUOTES)."'";
                            $da .= " data-section='".htmlspecialchars($r['section'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-manager='".htmlspecialchars($r['manager'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-reference='".htmlspecialchars($r['reference'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-cal-date='".formatDateExcel($r['calibration_date'])."'";
                            $da .= " data-cal-date-raw='".htmlspecialchars($calDateRaw, ENT_QUOTES)."'";
                            $da .= " data-next-cal='".formatDateExcel($r['next_calibration'])."'";
                            $da .= " data-next-cal-raw='".htmlspecialchars($nextCalRaw, ENT_QUOTES)."'";
                            $da .= " data-frequency='".formatFrequency($r['cal_frequency'])."'";
                            $da .= " data-calibrator='".htmlspecialchars($r['calibrator'],ENT_QUOTES)."'";
                            $da .= " data-cal-type='".htmlspecialchars($r['cal_type'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-env-type='".htmlspecialchars($r['env_type'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-remarks='".htmlspecialchars($r['remarks'] ?? '',ENT_QUOTES)."'";
                            $da .= " data-status='".htmlspecialchars($r['present_status'],ENT_QUOTES)."'";
                            $da .= " data-status-class='{$statusClass}'";
                        ?>
                            <tr class='data-row' <?= $da ?>>
                                <td class='exportCol' style='display:none;' onclick='event.stopPropagation()'><input type='checkbox' class='export-cb' value='<?= $r['id'] ?>'></td>
                                <?php if (!$isGuest): ?>
                                <td class='bulkCol' style='display:none;' onclick='event.stopPropagation()'><input type='checkbox' class='delete-cb' value='<?= $r['id'] ?>'></td>
                                <td class='printCol' style='display:none;' onclick='event.stopPropagation()'><input type='checkbox' class='print-cb' value='<?= $r['id'] ?>'></td>
                                <td style='font-family:"DM Mono",monospace;font-size:11px;color:var(--text-3);'><?= $count ?></td>
                                <?php endif; ?>
                                <td title='<?= htmlspecialchars($r['description']) ?>'><?= htmlspecialchars($r['description']) ?></td>
                                <td style="font-family:'DM Mono',monospace;font-size:11.5px;"><?= htmlspecialchars($r['machine_code']) ?></td>
                                <td><?= htmlspecialchars($r['model_maker']) ?></td>
                                <td style="font-family:'DM Mono',monospace;font-size:11px;"><?= htmlspecialchars($r['serial_number']) ?></td>
                                <td><?= htmlspecialchars($r['location']) ?></td>
                                <td class='date-cell'><?= formatDateExcel($r['calibration_date']) ?></td>
                                <td class='date-cell next-cal-cell' data-raw='<?= htmlspecialchars($nextCalRaw, ENT_QUOTES) ?>'><?= formatDateExcel($r['next_calibration']) ?></td>
                                <td style='font-size:12px;'><?= formatFrequency($r['cal_frequency']) ?></td>
                                <td class='calibrator-cell'><?= htmlspecialchars($r['calibrator']) ?></td>
                                <td><span class='status-badge <?= $statusClass ?>'><?= htmlspecialchars($r['present_status']) ?></span></td>
                            </tr>
                        <?php $count++; endwhile; ?>
                        <tr id="noResultsRow" class="no-results-row" style="display:none;">
                            <td colspan="20">
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

<!-- Export Modal -->
<div id="exportModal" class="modal-overlay">
    <div class="export-modal-box">
        <div class="export-modal-header"><h3>Export Report</h3><button class="modal-close-btn" id="closeExportModal">&times;</button></div>
        <div class="export-modal-body">
            <div class="export-format-row">
                <button class="export-btn" id="exportExcelBtn"><span class="export-btn-icon">📗</span><div class="export-btn-text"><strong>Excel (.xlsx)</strong><span>Spreadsheet — filtered rows only</span></div></button>
                <button class="export-btn" id="exportPdfBtn"><span class="export-btn-icon">📄</span><div class="export-btn-text"><strong>PDF (A4 Landscape)</strong><span>Print-ready — filtered rows only</span></div></button>
            </div>
            <div class="export-signatories-section">
                <div class="export-signatories-label"><span>Signatories</span><span class="export-signatories-hint">Used in PDF export</span></div>
                <div class="export-signatories-grid">
                    <div class="signatory-field"><input type="text" class="sig-name" id="sig1name" placeholder="Full Name" value="CARLO CAGAS"><input type="text" class="sig-title" id="sig1title" placeholder="Title / Position" value="Junior Engineer 4"></div>
                    <div class="signatory-field"><input type="text" class="sig-name" id="sig2name" placeholder="Full Name" value="MR. EDMUND NAVARRO"><input type="text" class="sig-title" id="sig2title" placeholder="Title / Position" value="Supervisor 7"></div>
                    <div class="signatory-field"><input type="text" class="sig-name" id="sig3name" placeholder="Full Name" value="MR. ARIEL LACAO"><input type="text" class="sig-title" id="sig3title" placeholder="Title / Position" value="PE Section Manager"></div>
                    <div class="signatory-field"><input type="text" class="sig-name" id="sig4name" placeholder="Full Name" value="MR. JOMAN ROSARIO"><input type="text" class="sig-title" id="sig4title" placeholder="Title / Position" value="FC Department Manager"></div>
                </div>
            </div>
            <div class="export-signatories-section">
                <div class="export-signatories-label"><span>Revision History</span><span class="export-signatories-hint">Used in PDF export</span></div>
                <div class="rev-history-table">
                    <div class="rev-history-header"><span>Rev.</span><span>Rev. Date</span><span>Revised By</span><span>Nature / Description of Revision</span><span>Appr. (Section Mgr.)</span><span>Appr. (Dept. Mgr.)</span></div>
                    <div class="rev-history-row"><input type="text" class="rev-input rev-code" id="rev1code" value="1"><input type="text" class="rev-input rev-date" id="rev1date" value="01-Feb-15"><input type="text" class="rev-input rev-by" id="rev1by" value="J. Garces"><input type="text" class="rev-input rev-desc" id="rev1desc" value="Included Reference Number"><input type="text" class="rev-input rev-appr1" id="rev1appr1" placeholder="(signature)"><input type="text" class="rev-input rev-appr2" id="rev1appr2" placeholder="(signature)"></div>
                    <div class="rev-history-row"><input type="text" class="rev-input rev-code" id="rev2code" value="2"><input type="text" class="rev-input rev-date" id="rev2date" value="02-Apr-18"><input type="text" class="rev-input rev-by" id="rev2by" value="E. Navarro"><input type="text" class="rev-input rev-desc" id="rev2desc" value="Revised logo to Shindengen Philippines Corp."><input type="text" class="rev-input rev-appr1" id="rev2appr1" placeholder="(signature)"><input type="text" class="rev-input rev-appr2" id="rev2appr2" placeholder="(signature)"></div>
                    <div class="rev-history-row"><input type="text" class="rev-input rev-code" id="rev3code" value="3"><input type="text" class="rev-input rev-date" id="rev3date" value="13-Sep-18"><input type="text" class="rev-input rev-by" id="rev3by" value="E. Navarro"><input type="text" class="rev-input rev-desc" id="rev3desc" value="Additional column for Calibration Frequency"><input type="text" class="rev-input rev-appr1" id="rev3appr1" placeholder="(signature)"><input type="text" class="rev-input rev-appr2" id="rev3appr2" value="QA Dept. Mngr."></div>
                </div>
            </div>
        </div>
        <div class="export-modal-footer"><span class="export-filter-note" id="exportFilterNote">Loading…</span><button class="btn btn-muted" id="cancelExportBtn">Cancel</button></div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="modal-overlay">
    <div class="detail-modal-box">
        <div class="detail-modal-header">
            <div class="detail-modal-header-text"><h3 id="modal-description">—</h3><p id="modal-machine-code">—</p></div>
            <button class="modal-close-btn" id="closeDetailModal">&times;</button>
        </div>
        <div class="detail-modal-body">
            <div class="detail-field"><div class="detail-field-label">Model / Maker</div><div class="detail-field-value" id="modal-model-maker">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Serial Number</div><div class="detail-field-value mono" id="modal-serial-number">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Model</div><div class="detail-field-value mono" id="modal-model">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Maker</div><div class="detail-field-value" id="modal-maker">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Location</div><div class="detail-field-value" id="modal-location">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Section</div><div class="detail-field-value" id="modal-section">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Manager</div><div class="detail-field-value" id="modal-manager">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Reference</div><div class="detail-field-value mono" id="modal-reference">—</div></div>
            <div class="detail-divider"></div>
            <div class="detail-field"><div class="detail-field-label">Calibration Date</div><div class="detail-field-value mono" id="modal-cal-date">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Next Calibration</div><div class="detail-field-value mono" id="modal-next-cal">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Frequency</div><div class="detail-field-value" id="modal-frequency">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Calibrator</div><div class="detail-field-value" id="modal-calibrator">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Type of Calibration</div><div class="detail-field-value" id="modal-cal-type">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Environment Type</div><div class="detail-field-value" id="modal-env-type">—</div></div>
            <div class="detail-divider"></div>
            <div class="detail-field full"><div class="detail-field-label">Remarks</div><div class="detail-field-value remarks-value" id="modal-remarks">—</div></div>
            <div class="detail-field full"><div class="detail-field-label">Present Status</div><div class="detail-field-value" id="modal-status">—</div></div>
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

<!-- Sticker Modal -->
<div id="stickerModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-box-header"><h3>Select Sticker Size</h3></div>
        <div class="modal-box-body">
            <button class="sticker-btn" id="smallStickerBtn">Small Sticker <span class="size-tag">compact</span></button>
            <button class="sticker-btn" id="bigStickerBtn">Big Sticker <span class="size-tag">full size</span></button>
        </div>
        <div class="modal-box-footer"><button class="btn btn-muted" id="closeStickerModal">Cancel</button></div>
    </div>
</div>

<!-- Password Verify Modal -->
<div id="passwordVerifyModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">🔒</div>
        <div class="confirm-modal-body">
            <h3>Verify Your Identity</h3>
            <p>Enter your account password to proceed with deletion.</p>
            <div class="confirm-pw-row">
                <label for="deletePasswordInput">Your Password <span style="color:#a81c1c;">*</span></label>
                <div class="confirm-pw-wrap">
                    <input type="password" id="deletePasswordInput" class="confirm-pw-input" placeholder="Enter your password…">
                    <button type="button" class="confirm-pw-eye" id="toggleDeletePwBtn">
                        <svg id="deletePwEyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="confirm-modal-error" id="passwordVerifyError"></div>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="confirmPasswordBtn"><span class="spinner" id="pwVerifySpinner"></span>Verify &amp; Continue</button>
            <button class="btn btn-muted" id="cancelPasswordBtn">Cancel</button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">🗑️</div>
        <div class="confirm-modal-body">
            <h3>Delete selected items?</h3>
            <p>You are about to delete <strong id="deleteCountSpan">0</strong> item(s).<br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="confirmDeleteYesBtn">Yes, Delete</button>
            <button class="btn btn-muted" id="confirmDeleteNoBtn">Cancel</button>
        </div>
    </div>
</div>

<script>
    const IS_GUEST          = <?= $isGuest ? 'true' : 'false' ?>;
    const CURRENT_PAGE_INIT = <?= $currentPageFromQuery ?>;
</script>
<script src="assets/js/calibration_report.js" defer></script>
</body>
</html>