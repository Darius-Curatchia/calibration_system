<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

include 'db.php';

$periodStmt = $pdo->query("SELECT DISTINCT period FROM samples WHERE period IS NOT NULL AND period != '' ORDER BY period DESC");
$allPeriods = $periodStmt->fetchAll(PDO::FETCH_COLUMN);

$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : (new DateTime())->format('F Y');
$currentMonth   = (new DateTime())->format('F Y');
if (!in_array($currentMonth, $allPeriods)) {
    array_unshift($allPeriods, $currentMonth);
}

if ($selectedPeriod !== '') {
    $stmt = $pdo->prepare("SELECT * FROM samples WHERE period = :period ORDER BY id ASC");
    $stmt->execute([':period' => $selectedPeriod]);
} else {
    $stmt = $pdo->query("SELECT * FROM samples ORDER BY id ASC");
}
$rows = $stmt->fetchAll();

$currentPageFromQuery = isset($_GET['page']) ? intval($_GET['page']) : 1;

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
<title>List of Samples — Calibration Management</title>
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
.btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,0.25);}
.btn-success:hover{background:#15803d;}
.btn-danger{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.btn-danger:hover{background:rgba(220,53,53,0.16);}
.btn-danger-solid{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.25);}
.btn-danger-solid:hover{background:#b91c1c;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}
.filter-input,.filter-select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);box-sizing:border-box;min-width:200px;}
.filter-input:focus,.filter-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.bulk-bar{display:none;align-items:center;gap:10px;padding:10px 16px;border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:14px;flex-wrap:wrap;background:var(--bg-raised);}
.bulk-bar.visible{display:flex;}
#bulkExportBar.visible{background:#f0fdf4;border-color:#86efac;border-left:3px solid #16a34a;}
.bulk-badge{display:inline-flex;align-items:center;background:var(--accent-soft);color:var(--accent);font-weight:700;font-size:11.5px;font-family:var(--mono);padding:4px 10px;border-radius:20px;border:1px solid rgba(26,144,217,0.2);}
.bulk-export-hint{font-size:11.5px;color:var(--text-3);font-style:italic;margin-left:auto;}
.table-container{overflow-x:auto;overflow-y:visible;border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.samples-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.samples-table thead{position:sticky;top:0;z-index:2;}
.samples-table th{background:var(--navy);color:rgba(255,255,255,0.80);padding:10px 12px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,0.07);}
.samples-table th:last-child{border-right:none;}
.samples-table td{padding:9px 12px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;color:var(--text);}
.samples-table td:last-child{border-right:none;}
.samples-table tr:last-child td{border-bottom:none;}
.samples-table tbody tr{cursor:pointer;}
.samples-table tbody tr:nth-child(even){background:var(--bg-raised);}
.samples-table tbody tr:hover{background:rgba(26,144,217,0.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.samples-table td.exportCol{cursor:default;text-align:center;width:32px;padding:0 4px;}
.samples-table td.exportCol input[type="checkbox"]{accent-color:#16a34a;}
.samples-table th.exportColHeader{background:#14532d;text-align:center;width:32px;padding:0 4px;}
.samples-table th.exportColHeader input[type="checkbox"]{accent-color:#4ade80;}
.samples-table tbody tr:has(.export-cb:checked){background:#f0fdf4!important;box-shadow:inset 3px 0 0 #16a34a;}
.samples-table td.bulkCol{cursor:default;text-align:center;width:32px;padding:0 4px;}
.samples-table th.bulkColHeader{background:var(--navy);text-align:center;width:32px;padding:0 4px;}
.samples-table td.bulkCol input[type="checkbox"]{accent-color:var(--accent);}
.condition-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;}
.cond-good{background:rgba(22,163,74,0.12);color:#126934;}
.cond-fair{background:rgba(202,138,4,0.10);color:#8a4d00;}
.cond-poor{background:rgba(220,53,53,0.10);color:#a81c1c;}
.cond-expired{background:rgba(100,116,139,0.10);color:#4a6070;}
.cond-default{background:var(--bg-raised);color:var(--text-2);}
.no-results-row{cursor:default!important;}
.no-results-row:hover{background:transparent!important;box-shadow:none!important;}
.no-results-row td{text-align:center;padding:60px 20px!important;color:var(--text-3);font-size:13px;border-right:none!important;}
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
.detail-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:620px;box-shadow:var(--shadow-lg);border:1px solid var(--border);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;}
.detail-modal-header{padding:18px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-shrink:0;}
.detail-modal-header-text h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 3px;}
.detail-modal-header-text p{font-size:11.5px;color:var(--text-3);margin:0;font-family:var(--mono);}
.detail-modal-body{padding:20px 22px;overflow-y:auto;display:grid;grid-template-columns:1fr 1fr;gap:14px 20px;}
.detail-field-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);margin-bottom:4px;}
.detail-field-value{font-size:13px;font-weight:500;color:var(--text);word-break:break-word;}
.detail-field-value.mono{font-family:var(--mono);font-size:12px;}
.detail-field.full{grid-column:1/-1;}
.detail-divider{grid-column:1/-1;height:1px;background:var(--border);margin:2px 0;}
.detail-section-title{grid-column:1/-1;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--accent);padding-bottom:6px;border-bottom:1px solid var(--accent-soft);margin-top:4px;}
.detail-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--border);flex-shrink:0;}
.detail-empty{color:var(--text-3);font-style:italic;font-size:13px;}
.export-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
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
@keyframes spin{to{transform:rotate(360deg);}}
.spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite;display:none;}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>
    <div class="card">
        <div class="card-header">
            <h2>List of Samples</h2>
            <p>Reference: PC2-9710 Calibration Control Procedure</p>
        </div>
        <div class="card-body">
            <div class="top-controls">
                <div class="controls-left">
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-primary" id="addSampleBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New
                    </button>
                    <?php endif; ?>
                    <!-- Export Report is available to all users including guests -->
                    <button class="btn btn-success" id="exportReportBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export Report
                    </button>
                </div>
                <div class="controls-right">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search…">
                    <select class="filter-select" id="periodFilter" onchange="window.location.href='samples.php?period='+encodeURIComponent(this.value)" style="min-width:160px;">
                        <option value="" <?= $selectedPeriod === '' ? 'selected' : '' ?>>All Periods</option>
                        <?php foreach ($allPeriods as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $selectedPeriod === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
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
            <div class="bulk-bar" id="bulkExportBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Select rows to export</span>
                <button class="btn btn-success" type="button" id="continueExportBtn">Continue →</button>
                <button class="btn btn-muted" type="button" id="cancelExportSelBtn">Cancel</button>
                <span class="bulk-badge" id="exportSelectedBadge">0 selected</span>
                <span class="bulk-export-hint">Leave all unchecked to export all filtered rows</span>
            </div>
            <form method="POST" action="delete_multiple_samples.php" id="samplesForm">
                <input type="hidden" name="page" id="currentPageInput">
                <div id="deleteHiddenInputs"></div>
                <div class="table-container">
                    <table class="samples-table" id="samplesTable">
                        <thead>
                            <tr>
                                <th class="exportColHeader" id="exportColHeader" style="display:none;"><input type="checkbox" id="selectAllExport"></th>
                                <?php if (!$isGuest): ?>
                                <th class="bulkColHeader" id="deleteColHeader" style="display:none;"><input type="checkbox" id="selectAllDelete"></th>
                                <?php endif; ?>
                                <th>No.</th>
                                <th>Description</th>
                                <th>Batch / Lot</th>
                                <th>Model / Maker</th>
                                <th>Expiry Date</th>
                                <th>Qty</th>
                                <th>Condition</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $i = 1;
                        foreach ($rows as $row):
                            $condClass = 'cond-default';
                            $cond = strtolower(trim($row['sample_condition'] ?? ''));
                            if ($cond === 'good')        $condClass = 'cond-good';
                            elseif ($cond === 'fair')    $condClass = 'cond-fair';
                            elseif ($cond === 'poor')    $condClass = 'cond-poor';
                            elseif ($cond === 'expired') $condClass = 'cond-expired';
                            $da  = " data-id='{$row['id']}'";
                            $da .= " data-description='".htmlspecialchars($row['description'], ENT_QUOTES)."'";
                            $da .= " data-batch-lot='".htmlspecialchars($row['qc_batch_lot'], ENT_QUOTES)."'";
                            $da .= " data-model-maker='".htmlspecialchars($row['model_maker'], ENT_QUOTES)."'";
                            $da .= " data-expiry='".htmlspecialchars($row['expiry_date'], ENT_QUOTES)."'";
                            $da .= " data-quantity='".htmlspecialchars($row['quantity'], ENT_QUOTES)."'";
                            $da .= " data-condition='".htmlspecialchars($row['sample_condition'], ENT_QUOTES)."'";
                            $da .= " data-condition-class='{$condClass}'";
                            $da .= " data-remarks='".htmlspecialchars($row['remarks'], ENT_QUOTES)."'";
                            $da .= " data-serial='".htmlspecialchars($row['serial_number'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-section='".htmlspecialchars($row['section'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-section-manager='".htmlspecialchars($row['section_manager'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-model='".htmlspecialchars($row['model'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-maker='".htmlspecialchars($row['maker'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-calibration-date='".htmlspecialchars($row['calibration_date'] ?? '', ENT_QUOTES)."'";
                            $da .= " data-period='".htmlspecialchars($row['period'] ?? '', ENT_QUOTES)."'";
                        ?>
                        <tr class="data-row" <?= $da ?>>
                            <td class="exportCol" style="display:none;" onclick="event.stopPropagation()"><input type="checkbox" class="export-cb" value="<?= $row['id'] ?>"></td>
                            <?php if (!$isGuest): ?>
                            <td class="bulkCol" style="display:none;" onclick="event.stopPropagation()"><input type="checkbox" class="delete-cb" value="<?= $row['id'] ?>"></td>
                            <?php endif; ?>
                            <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-3);"><?= $i++ ?></td>
                            <td title="<?= htmlspecialchars($row['description']) ?>"><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['qc_batch_lot']) ?></td>
                            <td><?= htmlspecialchars($row['model_maker']) ?></td>
                            <td class="expiry-cell" data-expiry="<?= htmlspecialchars($row['expiry_date'], ENT_QUOTES) ?>" style="font-family:'DM Mono',monospace;font-size:11.5px;color:var(--text-2);text-align:center;"><?= htmlspecialchars($row['expiry_date']) ?></td>
                            <td style="text-align:center;font-family:'DM Mono',monospace;font-size:11.5px;"><?= htmlspecialchars($row['quantity']) ?></td>
                            <td class="condition-cell"><span class="condition-badge <?= $condClass ?>"><?= htmlspecialchars($row['sample_condition']) ?></span></td>
                            <td title="<?= htmlspecialchars($row['remarks']) ?>"><?= htmlspecialchars($row['remarks']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="noResultsRow" class="no-results-row" style="display:none;">
                            <td colspan="9">
                                <span class="no-results-icon">🔍</span>
                                <span class="no-results-label">No results found</span>
                                Try adjusting your search.
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

<!-- EXPORT MODAL -->
<div id="exportModal" class="modal-overlay">
    <div class="export-modal-box">
        <div class="export-modal-header"><h3>Export Samples Report</h3><button class="modal-close-btn" id="closeExportModal">&times;</button></div>
        <div class="export-modal-body">
            <div class="export-format-row">
                <button class="export-btn" id="exportExcelBtn"><span class="export-btn-icon">📗</span><div class="export-btn-text"><strong>Excel (.xlsx)</strong><span>Spreadsheet — selected rows only</span></div></button>
                <button class="export-btn" id="exportPdfBtn"><span class="export-btn-icon">📄</span><div class="export-btn-text"><strong>PDF (A4 Landscape)</strong><span>Print-ready — selected rows only</span></div></button>
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

<!-- DETAIL MODAL -->
<div id="detailModal" class="modal-overlay">
    <div class="detail-modal-box">
        <div class="detail-modal-header">
            <div class="detail-modal-header-text"><h3 id="modal-description">—</h3><p id="modal-batch-lot">—</p></div>
            <button class="modal-close-btn" id="closeDetailModal">&times;</button>
        </div>
        <div class="detail-modal-body">
            <div class="detail-section-title">Identification</div>
            <div class="detail-field"><div class="detail-field-label">Serial Number</div><div class="detail-field-value mono" id="modal-serial">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Quantity</div><div class="detail-field-value mono" id="modal-quantity">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Model</div><div class="detail-field-value" id="modal-model">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Maker</div><div class="detail-field-value" id="modal-maker">—</div></div>
            <div class="detail-field full"><div class="detail-field-label">Model / Maker (combined)</div><div class="detail-field-value mono" id="modal-model-maker">—</div></div>
            <div class="detail-divider"></div>
            <div class="detail-section-title">Section</div>
            <div class="detail-field"><div class="detail-field-label">Section</div><div class="detail-field-value" id="modal-section">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Section Manager</div><div class="detail-field-value" id="modal-section-manager">—</div></div>
            <div class="detail-divider"></div>
            <div class="detail-section-title">Dates & Condition</div>
            <div class="detail-field"><div class="detail-field-label">Period</div><div class="detail-field-value mono" id="modal-period">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Expiry Date</div><div class="detail-field-value mono" id="modal-expiry">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Calibration Date</div><div class="detail-field-value mono" id="modal-calibration-date">—</div></div>
            <div class="detail-field"><div class="detail-field-label">Condition</div><div class="detail-field-value" id="modal-condition">—</div></div>
            <div class="detail-divider"></div>
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

<!-- PASSWORD VERIFY MODAL -->
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

<!-- DELETE CONFIRM MODAL -->
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
window.addEventListener('DOMContentLoaded', () => {
    const tableBody           = document.querySelector('#samplesTable tbody');
    const noResultsRow        = document.getElementById('noResultsRow');
    const searchInput         = document.getElementById('searchInput');
    const detailModal         = document.getElementById('detailModal');
    const closeDetailModal    = document.getElementById('closeDetailModal');
    const closeDetailModalBtn = document.getElementById('closeDetailModalBtn');
    const editRecordBtn       = document.getElementById('editRecordBtn');
    const addSampleBtn        = document.getElementById('addSampleBtn');
    const exportReportBtn     = document.getElementById('exportReportBtn');
    const exportModal         = document.getElementById('exportModal');
    const closeExportModal    = document.getElementById('closeExportModal');
    const cancelExportBtn     = document.getElementById('cancelExportBtn');
    const exportExcelBtn      = document.getElementById('exportExcelBtn');
    const exportPdfBtn        = document.getElementById('exportPdfBtn');
    const exportFilterNote    = document.getElementById('exportFilterNote');
    const bulkExportBar       = document.getElementById('bulkExportBar');
    const continueExportBtn   = document.getElementById('continueExportBtn');
    const cancelExportSelBtn  = document.getElementById('cancelExportSelBtn');
    const exportSelectedBadge = document.getElementById('exportSelectedBadge');
    const selectAllExport     = document.getElementById('selectAllExport');
    const exportColHeader     = document.getElementById('exportColHeader');
    const IS_GUEST            = <?= $isGuest ? 'true' : 'false' ?>;

    let rowsPerPage  = 15;
    let currentPage  = <?= $currentPageFromQuery ?>;
    let filteredRows = Array.from(tableBody.querySelectorAll('tr.data-row'));

    if (addSampleBtn) addSampleBtn.addEventListener('click', () => { window.location.href = `add_sample.php?page=${currentPage}`; });

    const todayStr = (() => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; })();
    tableBody.querySelectorAll('tr.data-row').forEach(row => {
        const expiry = row.dataset.expiry;
        if (!expiry || expiry === '0000-00-00') return;
        if (expiry < todayStr) {
            const condCell = row.querySelector('.condition-cell');
            if (condCell) condCell.innerHTML = '<span class="condition-badge cond-expired">Expired</span>';
            row.dataset.condition      = 'Expired';
            row.dataset.conditionClass = 'cond-expired';
        }
    });

    function setField(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (value && value.trim() !== '') { el.textContent = value; el.classList.remove('detail-empty'); }
        else { el.innerHTML = '<span class="detail-empty">—</span>'; }
    }

    tableBody.addEventListener('click', function (e) {
        if (e.target.closest('.exportCol') || e.target.closest('.bulkCol')) return;
        const row = e.target.closest('tr.data-row');
        if (!row) return;
        const d = row.dataset;
        document.getElementById('modal-description').textContent = d.description || '—';
        document.getElementById('modal-batch-lot').textContent   = d.batchLot    || '—';
        setField('modal-serial',           d.serial);
        setField('modal-quantity',         d.quantity);
        setField('modal-model',            d.model);
        setField('modal-maker',            d.maker);
        setField('modal-model-maker',      d.modelMaker);
        setField('modal-section',          d.section);
        setField('modal-section-manager',  d.sectionManager);
        setField('modal-expiry',           d.expiry);
        setField('modal-calibration-date', d.calibrationDate);
        setField('modal-period',           d.period);
        setField('modal-remarks',          d.remarks);
        document.getElementById('modal-condition').innerHTML = `<span class="condition-badge ${d.conditionClass}">${d.condition}</span>`;
        if (editRecordBtn) editRecordBtn.href = `edit_sample.php?id=${d.id}&page=${currentPage}`;
        detailModal.classList.add('open');
    });

    function closeDetail() { detailModal.classList.remove('open'); }
    closeDetailModal.addEventListener('click', closeDetail);
    closeDetailModalBtn.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetail(); });

    function filterRows(resetPage = false) {
        const searchTerm = searchInput.value.toLowerCase();
        filteredRows = Array.from(tableBody.querySelectorAll('tr.data-row')).filter(row =>
            row.innerText.toLowerCase().includes(searchTerm)
        );
        if (resetPage) currentPage = 1;
        showPage();
    }

    function showPage() {
        const start = (currentPage - 1) * rowsPerPage;
        const end   = start + rowsPerPage;
        tableBody.querySelectorAll('tr.data-row').forEach(r => r.style.display = 'none');
        if (filteredRows.length === 0) { noResultsRow.style.display = ''; }
        else { noResultsRow.style.display = 'none'; filteredRows.slice(start, end).forEach(r => r.style.display = ''); }
        renderPagination();
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

    searchInput.addEventListener('input', () => filterRows(true));

    function showExportCols() { document.querySelectorAll('.exportCol').forEach(c => c.style.display = ''); if (exportColHeader) exportColHeader.style.display = ''; }
    function hideExportCols() { document.querySelectorAll('.exportCol').forEach(c => c.style.display = 'none'); if (exportColHeader) exportColHeader.style.display = 'none'; }
    function uncheckExport()  { document.querySelectorAll('.export-cb').forEach(cb => cb.checked = false); if (selectAllExport) selectAllExport.checked = false; }
    function updateExportBadge() { if (exportSelectedBadge) exportSelectedBadge.textContent = `${document.querySelectorAll('.export-cb:checked').length} selected`; }
    tableBody.addEventListener('change', e => { if (e.target.matches('.export-cb')) updateExportBadge(); });

    if (exportReportBtn) exportReportBtn.addEventListener('click', () => { uncheckExport(); bulkExportBar.classList.add('visible'); showExportCols(); updateExportBadge(); });
    if (selectAllExport) selectAllExport.addEventListener('change', function () { filteredRows.forEach(row => { const cb = row.querySelector('.export-cb'); if (cb) cb.checked = this.checked; }); updateExportBadge(); });
    if (cancelExportSelBtn) cancelExportSelBtn.addEventListener('click', () => { bulkExportBar.classList.remove('visible'); hideExportCols(); uncheckExport(); updateExportBadge(); });
    if (continueExportBtn) continueExportBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.export-cb:checked');
        window.exportRowIds = checked.length === 0 ? null : Array.from(checked).map(cb => cb.value);
        bulkExportBar.classList.remove('visible'); hideExportCols(); uncheckExport();
        exportFilterNote.textContent = buildFilterNote(); exportModal.classList.add('open');
    });

    function closeExport() { exportModal.classList.remove('open'); }
    closeExportModal.addEventListener('click', closeExport);
    cancelExportBtn.addEventListener('click', closeExport);
    exportModal.addEventListener('click', e => { if (e.target === exportModal) closeExport(); });

    const sigFields = [
        { name: document.getElementById('sig1name'), title: document.getElementById('sig1title') },
        { name: document.getElementById('sig2name'), title: document.getElementById('sig2title') },
        { name: document.getElementById('sig3name'), title: document.getElementById('sig3title') },
        { name: document.getElementById('sig4name'), title: document.getElementById('sig4title') },
    ];
    const SIG_KEY = 'samplesSignatories';
    function saveSig() { try { sessionStorage.setItem(SIG_KEY, JSON.stringify(sigFields.map(f => ({ name: f.name ? f.name.value : '', title: f.title ? f.title.value : '' })))); } catch(e) {} }
    function loadSig() { try { const data = JSON.parse(sessionStorage.getItem(SIG_KEY)); if (!Array.isArray(data)) return; data.forEach((d, i) => { if (!sigFields[i]) return; if (sigFields[i].name && d.name !== undefined) sigFields[i].name.value = d.name; if (sigFields[i].title && d.title !== undefined) sigFields[i].title.value = d.title; }); } catch(e) {} }
    sigFields.forEach(f => { if (f.name) f.name.addEventListener('input', saveSig); if (f.title) f.title.addEventListener('input', saveSig); });
    loadSig();

    const revFields = [
        { code: document.getElementById('rev1code'), date: document.getElementById('rev1date'), by: document.getElementById('rev1by'), desc: document.getElementById('rev1desc'), appr1: document.getElementById('rev1appr1'), appr2: document.getElementById('rev1appr2') },
        { code: document.getElementById('rev2code'), date: document.getElementById('rev2date'), by: document.getElementById('rev2by'), desc: document.getElementById('rev2desc'), appr1: document.getElementById('rev2appr1'), appr2: document.getElementById('rev2appr2') },
        { code: document.getElementById('rev3code'), date: document.getElementById('rev3date'), by: document.getElementById('rev3by'), desc: document.getElementById('rev3desc'), appr1: document.getElementById('rev3appr1'), appr2: document.getElementById('rev3appr2') },
    ];
    const REV_KEY = 'samplesRevisions';
    function saveRev() { try { sessionStorage.setItem(REV_KEY, JSON.stringify(revFields.map(f => ({ code: f.code?f.code.value:'', date: f.date?f.date.value:'', by: f.by?f.by.value:'', desc: f.desc?f.desc.value:'', appr1: f.appr1?f.appr1.value:'', appr2: f.appr2?f.appr2.value:'' })))); } catch(e) {} }
    function loadRev() { try { const data = JSON.parse(sessionStorage.getItem(REV_KEY)); if (!Array.isArray(data)) return; data.forEach((d, i) => { if (!revFields[i]) return; const f = revFields[i]; if (f.code && d.code !== undefined) f.code.value = d.code; if (f.date && d.date !== undefined) f.date.value = d.date; if (f.by && d.by !== undefined) f.by.value = d.by; if (f.desc && d.desc !== undefined) f.desc.value = d.desc; if (f.appr1 && d.appr1 !== undefined) f.appr1.value = d.appr1; if (f.appr2 && d.appr2 !== undefined) f.appr2.value = d.appr2; }); } catch(e) {} }
    revFields.forEach(f => { ['code','date','by','desc','appr1','appr2'].forEach(k => { if (f[k]) f[k].addEventListener('input', saveRev); }); });
    loadRev();

    function getSignatories() { return sigFields.map(f => ({ name: f.name ? f.name.value.trim() || '—' : '—', title: f.title ? f.title.value.trim() || '—' : '—' })); }
    function getRevisions()   { return revFields.map(f => ({ code: f.code?f.code.value.trim():'', date: f.date?f.date.value.trim():'', by: f.by?f.by.value.trim():'', desc: f.desc?f.desc.value.trim():'', appr1: f.appr1?f.appr1.value.trim():'', appr2: f.appr2?f.appr2.value.trim():'' })); }

    const EXPORT_COLUMNS = [
        { label: 'No.',              key: null              },
        { label: 'Description',      key: 'description'     },
        { label: 'Serial No.',       key: 'serial'          },
        { label: 'Batch / Lot',      key: 'batchLot'        },
        { label: 'Model / Maker',    key: 'modelMaker'      },
        { label: 'Section',          key: 'section'         },
        { label: 'Section Manager',  key: 'sectionManager'  },
        { label: 'Expiry Date',      key: 'expiry'          },
        { label: 'Calibration Date', key: 'calibrationDate' },
        { label: 'Quantity',         key: 'quantity'        },
        { label: 'Condition',        key: 'condition'       },
        { label: 'Remarks',          key: 'remarks'         },
    ];

    function getExportData() {
        const rows = window.exportRowIds ? filteredRows.filter(r => window.exportRowIds.includes(r.dataset.id)) : filteredRows;
        return rows.map((row, i) => { const d = row.dataset; return EXPORT_COLUMNS.map(col => col.key === null ? (i + 1) : (d[col.key] || '')); });
    }
    function buildFilterNote() {
        const parts = [];
        if (searchInput.value.trim()) parts.push(`Search: "${searchInput.value.trim()}"`);
        const rows = window.exportRowIds ? filteredRows.filter(r => window.exportRowIds.includes(r.dataset.id)) : filteredRows;
        const n = `${rows.length} row${rows.length !== 1 ? 's' : ''}`;
        return parts.length ? `${n} · Filters: ${parts.join(', ')}` : `${n} · No active filters`;
    }

    exportExcelBtn.addEventListener('click', () => {
        if (typeof XLSX === 'undefined') { alert('Excel library still loading, please try again.'); return; }
        const ws = XLSX.utils.aoa_to_sheet([EXPORT_COLUMNS.map(c => c.label), ...getExportData()]);
        ws['!cols'] = [{wch:5},{wch:30},{wch:16},{wch:16},{wch:24},{wch:20},{wch:22},{wch:14},{wch:16},{wch:10},{wch:12},{wch:30}];
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Samples Report');
        XLSX.writeFile(wb, `samples_report_${new Date().toISOString().slice(0,10)}.xlsx`);
        closeExport();
    });

    exportPdfBtn.addEventListener('click', async () => {
        if (typeof window.jspdf === 'undefined') { alert('PDF library still loading, please try again.'); return; }
        const { jsPDF } = window.jspdf;
        const doc     = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageW   = doc.internal.pageSize.getWidth();
        const pageH   = doc.internal.pageSize.getHeight();
        const dateStr = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        const HDR_H = 28; const LOGO_X = 6; const LOGO_MW = 20; const TEXT_X = LOGO_X + LOGO_MW + 5;
        doc.setFillColor(5, 48, 79); doc.rect(0, 0, pageW, HDR_H, 'F');
        try {
            const logoImg = await new Promise((res, rej) => { const img = new Image(); img.crossOrigin = 'anonymous'; img.onload = () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img, 0, 0); res({ dataUrl: c.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight }); }; img.onerror = rej; img.src = 'images/shindengen-logo2.png'; });
            const lw = LOGO_MW, lh = LOGO_MW * (logoImg.h / logoImg.w);
            doc.addImage(logoImg.dataUrl, 'PNG', LOGO_X, (HDR_H - lh) / 2, lw, lh);
        } catch(e) {}
        doc.setTextColor(255,255,255); doc.setFontSize(15); doc.setFont('helvetica','bold');
        doc.text('List of Samples', TEXT_X, 10);
        doc.setFontSize(9.5); doc.setFont('helvetica','normal'); doc.setTextColor(180,210,235);
        doc.text('Reference: PC2-9710 Calibration Control Procedure', TEXT_X, 18);
        doc.setFontSize(9); doc.setTextColor(200,225,245);
        doc.text(`Generated: ${dateStr}`, pageW - 8, 18, { align:'right' });
        doc.autoTable({
            head: [EXPORT_COLUMNS.map(c => c.label)], body: getExportData(), startY: 32,
            margin: { left: 8, right: 8, bottom: 10 }, theme: 'grid', rowPageBreak: 'avoid',
            tableLineColor: [180,195,210], tableLineWidth: 0.25,
            styles: { fontSize: 6.5, cellPadding: { top:1, bottom:1, left:2.5, right:2.5 }, overflow: 'linebreak', font: 'helvetica', textColor: [28,43,58], lineColor: [180,195,210], lineWidth: 0.25 },
            headStyles: { fillColor: [26,144,217], textColor: [255,255,255], fontStyle: 'bold', fontSize: 6.2, lineColor: [100,160,210], lineWidth: 0.25 },
            alternateRowStyles: { fillColor: [248,250,252] },
            columnStyles: { 0:{cellWidth:8,halign:'center'}, 1:{cellWidth:50}, 2:{cellWidth:22}, 3:{cellWidth:22}, 4:{cellWidth:32}, 5:{cellWidth:22}, 6:{cellWidth:24}, 7:{cellWidth:16}, 8:{cellWidth:18}, 9:{cellWidth:12}, 10:{cellWidth:16,halign:'center'}, 11:{cellWidth:39} },
            didParseCell: function(data) {
                if (data.section === 'body' && data.column.index === 10) {
                    const v = (data.cell.raw || '').toString().toLowerCase();
                    if      (v === 'good')    { data.cell.styles.textColor=[22,101,52];   data.cell.styles.fillColor=[220,252,231]; data.cell.styles.fontStyle='bold'; }
                    else if (v === 'fair')    { data.cell.styles.textColor=[146,64,14];   data.cell.styles.fillColor=[254,243,220]; data.cell.styles.fontStyle='bold'; }
                    else if (v === 'poor')    { data.cell.styles.textColor=[185,28,28];   data.cell.styles.fillColor=[253,232,232]; data.cell.styles.fontStyle='bold'; }
                    else if (v === 'expired') { data.cell.styles.textColor=[107,114,128]; data.cell.styles.fillColor=[243,244,246]; data.cell.styles.fontStyle='bold'; }
                }
            },
            didDrawPage: function() { const pn = doc.internal.getCurrentPageInfo().pageNumber; doc.setFontSize(7); doc.setTextColor(150); doc.text(`Page ${pn}`, pageW / 2, pageH - 5, { align:'center' }); },
        });
        const signatories = getSignatories(); const revisions = getRevisions();
        const marginL = 8; const usableW = pageW - marginL * 2;
        const SIG_H = 18; const HEAD_H = 7; const ROW_H = 5; const nRows = revisions.length;
        const bodyH = HEAD_H + ROW_H * nRows; const TOTAL_H = SIG_H + bodyH + 6;
        if (doc.lastAutoTable.finalY + TOTAL_H > pageH - 10) doc.addPage();
        doc.setPage(doc.internal.getNumberOfPages());
        const sigTop = pageH - TOTAL_H - 8; const colW = usableW / 4; const lh = 2.8;
        doc.setDrawColor(200,210,220); doc.setLineWidth(0.3); doc.line(marginL, sigTop-3, pageW-marginL, sigTop-3);
        doc.setFontSize(6.5); doc.setFont('helvetica','bold'); doc.setTextColor(5,48,79);
        doc.text('Prepared / Noted / Approved by:', marginL, sigTop+2.5);
        signatories.forEach((sig, i) => {
            const cx = marginL + colW * i + colW / 2; const nameY = sigTop + 12; const lineY = sigTop + 13; const titY = sigTop + 17;
            doc.setDrawColor(80,100,120); doc.setLineWidth(0.35); doc.line(cx - colW*0.38, lineY, cx + colW*0.38, lineY);
            doc.setFont('helvetica','bold'); doc.setFontSize(6.5); doc.setTextColor(28,43,58); doc.text(sig.name, cx, nameY, { align:'center', maxWidth: colW-4 });
            doc.setFont('helvetica','normal'); doc.setFontSize(5.5); doc.setTextColor(100,120,138); doc.text(sig.title, cx, titY, { align:'center', maxWidth: colW-4 });
        });
        const revTop = sigTop + SIG_H + 2; const wDept = 28; const leftW = usableW - wDept;
        const wRev = 10; const wDate = 18; const wBy = 26; const wSec = 28; const wDesc = leftW - wRev - wDate - wBy - wSec;
        const xRev = marginL; const xDate = xRev+wRev; const xBy = xDate+wDate; const xDesc = xBy+wBy; const xSec = xDesc+wDesc; const xDept = marginL+leftW;
        doc.setDrawColor(180,195,210); doc.setLineWidth(0.3); doc.rect(xRev, revTop, leftW, bodyH);
        doc.setFillColor(26,144,217); doc.rect(xRev, revTop, leftW, HEAD_H, 'F');
        const hCols = [{ label:'Rev.',x:xRev,w:wRev },{ label:'Rev. Date',x:xDate,w:wDate },{ label:'Revised By',x:xBy,w:wBy },{ label:'Nature / Description of Revision',x:xDesc,w:wDesc },{ label:'Approved by\n(Section Mgr.)',x:xSec,w:wSec }];
        doc.setFont('helvetica','bold'); doc.setFontSize(5); doc.setTextColor(255,255,255);
        hCols.forEach((col, ci) => { if (ci > 0) { doc.setDrawColor(255,255,255); doc.setLineWidth(0.2); doc.line(col.x, revTop, col.x, revTop+bodyH); } const lines = col.label.split('\n'); const startY = revTop+2.5+(lines.length>1?0:lh*0.5); lines.forEach((line, li) => doc.text(line, col.x+col.w/2, startY+li*lh, { align:'center', maxWidth: col.w-2 })); });
        revisions.forEach((rev, ri) => {
            const rowY = revTop+HEAD_H+ri*ROW_H; const textY = rowY+ROW_H*0.65;
            if (ri%2===1) { doc.setFillColor(248,250,252); doc.rect(xRev, rowY, wRev+wDate+wBy+wDesc, ROW_H, 'F'); }
            doc.setDrawColor(220,228,236); doc.setLineWidth(0.2); doc.line(xRev, rowY+ROW_H, xSec, rowY+ROW_H);
            doc.setFont('helvetica','normal'); doc.setFontSize(5.5); doc.setTextColor(28,43,58);
            doc.text(String(rev.code||''), xRev+wRev/2, textY, { align:'center' }); doc.text(rev.date||'', xDate+2, textY, { maxWidth: wDate-4 }); doc.text(rev.by||'', xBy+2, textY, { maxWidth: wBy-4 }); doc.text(rev.desc||'', xDesc+2, textY, { maxWidth: wDesc-4 });
            [xDate,xBy,xDesc].forEach(lx => { doc.setDrawColor(200,210,220); doc.setLineWidth(0.2); doc.line(lx, rowY, lx, rowY+ROW_H); });
        });
        doc.setFillColor(255,255,255); doc.rect(xSec, revTop+HEAD_H, wSec, ROW_H*nRows, 'F');
        try { const sig1Img = await new Promise((resolve, reject) => { const img = new Image(); img.crossOrigin='anonymous'; img.onload=()=>{ const canvas=document.createElement('canvas'); canvas.width=img.naturalWidth; canvas.height=img.naturalHeight; canvas.getContext('2d').drawImage(img,0,0); resolve({dataUrl:canvas.toDataURL('image/png'),w:img.naturalWidth,h:img.naturalHeight}); }; img.onerror=reject; img.src='images/Signature-1.png'; }); const s1mw=(wSec-6)*0.6,s1mh=(ROW_H*nRows-4)*0.6,s1r=Math.min(s1mw/(sig1Img.w/3.7795),s1mh/(sig1Img.h/3.7795)),s1w=(sig1Img.w/3.7795)*s1r,s1h=(sig1Img.h/3.7795)*s1r; doc.addImage(sig1Img.dataUrl,'PNG',xSec+(wSec-s1w)/2,revTop+HEAD_H+(ROW_H*nRows-s1h)/2,s1w,s1h); } catch(e) {}
        doc.setDrawColor(180,195,210); doc.setLineWidth(0.3); doc.rect(xDept, revTop, wDept, bodyH);
        doc.setFillColor(26,144,217); doc.rect(xDept, revTop, wDept, HEAD_H, 'F');
        doc.setFont('helvetica','bold'); doc.setFontSize(5); doc.setTextColor(255,255,255);
        'Approved by\n(Dept. Mgr.)'.split('\n').forEach((line, li) => doc.text(line, xDept+wDept/2, revTop+2.5+li*lh, { align:'center', maxWidth: wDept-2 }));
        const deptMergeH = ROW_H*(nRows-1); doc.setFillColor(255,255,255); doc.rect(xDept, revTop+HEAD_H, wDept, deptMergeH, 'F');
        try { const sig2Img = await new Promise((resolve, reject) => { const img = new Image(); img.crossOrigin='anonymous'; img.onload=()=>{ const canvas=document.createElement('canvas'); canvas.width=img.naturalWidth; canvas.height=img.naturalHeight; canvas.getContext('2d').drawImage(img,0,0); resolve({dataUrl:canvas.toDataURL('image/png'),w:img.naturalWidth,h:img.naturalHeight}); }; img.onerror=reject; img.src='images/Signature-2.png'; }); const s2mw=wDept-4,s2mh=deptMergeH-2,s2r=Math.min(s2mw/(sig2Img.w/3.7795),s2mh/(sig2Img.h/3.7795)),s2w=(sig2Img.w/3.7795)*s2r,s2h=(sig2Img.h/3.7795)*s2r; doc.addImage(sig2Img.dataUrl,'PNG',xDept+(wDept-s2w)/2,revTop+HEAD_H+(deptMergeH-s2h)/2,s2w,s2h); } catch(e) {}
        doc.setDrawColor(200,210,220); doc.setLineWidth(0.2); doc.line(xDept, revTop+HEAD_H+deptMergeH, xDept+wDept, revTop+HEAD_H+deptMergeH);
        const row3Y = revTop+HEAD_H+ROW_H*(nRows-1); doc.setFillColor(248,250,252); doc.rect(xDept, row3Y, wDept, ROW_H, 'F');
        doc.setFont('helvetica','bold'); doc.setFontSize(4.5); doc.setTextColor(80,100,120); doc.text('QA Dept. Mngr.', xDept+wDept/2, row3Y+ROW_H*0.65, { align:'center', maxWidth: wDept-4 });
        doc.save(`samples_report_${new Date().toISOString().slice(0,10)}.pdf`);
        closeExport();
    });

    // ── Bulk Delete ──────────────────────────────────────────────────────────
    if (!IS_GUEST) {
        const toggleDeleteBtn     = document.getElementById('toggleDeleteBtn');
        const bulkDeleteBar       = document.getElementById('bulkDeleteBar');
        const deleteSelectedBtn   = document.getElementById('deleteSelectedBtn');
        const cancelDeleteBtn     = document.getElementById('cancelDeleteBtn');
        const selectAllDelete     = document.getElementById('selectAllDelete');
        const deleteColHeader     = document.getElementById('deleteColHeader');
        const selectedBadge       = document.getElementById('selectedCountBadge');
        const passwordVerifyModal = document.getElementById('passwordVerifyModal');
        const deletePasswordInput = document.getElementById('deletePasswordInput');
        const passwordVerifyError = document.getElementById('passwordVerifyError');
        const confirmPasswordBtn  = document.getElementById('confirmPasswordBtn');
        const cancelPasswordBtn   = document.getElementById('cancelPasswordBtn');
        const toggleDeletePwBtn   = document.getElementById('toggleDeletePwBtn');
        const deletePwEyeIcon     = document.getElementById('deletePwEyeIcon');
        const pwVerifySpinner     = document.getElementById('pwVerifySpinner');
        const deleteConfirmModal  = document.getElementById('deleteConfirmModal');
        const deleteCountSpan     = document.getElementById('deleteCountSpan');
        const confirmDeleteYesBtn = document.getElementById('confirmDeleteYesBtn');
        const confirmDeleteNoBtn  = document.getElementById('confirmDeleteNoBtn');
        const deleteHiddenInputs  = document.getElementById('deleteHiddenInputs');
        const samplesForm         = document.getElementById('samplesForm');
        const currentPageInput    = document.getElementById('currentPageInput');

        function getFilteredRows() { return Array.from(tableBody.querySelectorAll('tr.data-row')).filter(r => r.style.display !== 'none'); }
        function showDeleteCols() { document.querySelectorAll('.bulkCol').forEach(c => c.style.display = ''); if (deleteColHeader) deleteColHeader.style.display = ''; document.querySelectorAll('.exportCol').forEach(c => c.style.display = 'none'); if (exportColHeader) exportColHeader.style.display = 'none'; bulkExportBar.classList.remove('visible'); }
        function hideDeleteCols() { document.querySelectorAll('.bulkCol').forEach(c => c.style.display = 'none'); if (deleteColHeader) deleteColHeader.style.display = 'none'; }
        function uncheckAllDelete() { document.querySelectorAll('.delete-cb').forEach(cb => cb.checked = false); if (selectAllDelete) selectAllDelete.checked = false; }
        function updateDeleteBadge() { const count = document.querySelectorAll('.delete-cb:checked').length; selectedBadge.textContent = `${count} selected`; deleteSelectedBtn.disabled = count === 0; }
        tableBody.addEventListener('change', e => { if (e.target.matches('.delete-cb')) updateDeleteBadge(); });
        toggleDeleteBtn.addEventListener('click', () => { uncheckAllDelete(); bulkDeleteBar.classList.add('visible'); showDeleteCols(); updateDeleteBadge(); });
        cancelDeleteBtn.addEventListener('click', () => { bulkDeleteBar.classList.remove('visible'); hideDeleteCols(); uncheckAllDelete(); updateDeleteBadge(); });
        selectAllDelete.addEventListener('change', function () { getFilteredRows().forEach(row => { const cb = row.querySelector('.delete-cb'); if (cb) cb.checked = this.checked; }); updateDeleteBadge(); });
        deleteSelectedBtn.addEventListener('click', () => { if (document.querySelectorAll('.delete-cb:checked').length === 0) { alert('Please select at least one item to delete.'); return; } openPasswordModal(); });

        function openPasswordModal() { deletePasswordInput.value = ''; deletePasswordInput.classList.remove('error-input'); passwordVerifyError.classList.remove('show'); passwordVerifyError.textContent = ''; passwordVerifyModal.classList.add('open'); setTimeout(() => deletePasswordInput.focus(), 100); }
        function closePasswordModal() { passwordVerifyModal.classList.remove('open'); deletePasswordInput.value = ''; deletePasswordInput.classList.remove('error-input'); passwordVerifyError.classList.remove('show'); }
        toggleDeletePwBtn.addEventListener('click', () => { if (deletePasswordInput.type === 'password') { deletePasswordInput.type = 'text'; deletePwEyeIcon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`; } else { deletePasswordInput.type = 'password'; deletePwEyeIcon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`; } });
        async function verifyPasswordAndProceed() {
            const password = deletePasswordInput.value.trim();
            passwordVerifyError.classList.remove('show'); deletePasswordInput.classList.remove('error-input');
            if (!password) { passwordVerifyError.textContent = 'Please enter your password.'; passwordVerifyError.classList.add('show'); deletePasswordInput.classList.add('error-input'); deletePasswordInput.focus(); return; }
            confirmPasswordBtn.disabled = true; pwVerifySpinner.style.display = 'inline-block';
            try {
                const fd = new FormData(); fd.append('action','verify_delete_password'); fd.append('password', password);
                const res = await fetch('samples.php', { method: 'POST', body: fd }); const json = await res.json();
                if (json.success) { closePasswordModal(); deleteCountSpan.textContent = document.querySelectorAll('.delete-cb:checked').length; deleteConfirmModal.classList.add('open'); }
                else { passwordVerifyError.textContent = '✗ ' + (json.message || 'Incorrect password.'); passwordVerifyError.classList.add('show'); deletePasswordInput.classList.add('error-input'); deletePasswordInput.value = ''; deletePasswordInput.focus(); }
            } catch(e) { passwordVerifyError.textContent = 'Network error. Please try again.'; passwordVerifyError.classList.add('show'); }
            finally { confirmPasswordBtn.disabled = false; pwVerifySpinner.style.display = 'none'; }
        }
        confirmPasswordBtn.addEventListener('click', verifyPasswordAndProceed);
        cancelPasswordBtn.addEventListener('click', closePasswordModal);
        passwordVerifyModal.addEventListener('click', e => { if (e.target === passwordVerifyModal) closePasswordModal(); });
        deletePasswordInput.addEventListener('keydown', e => { if (e.key === 'Enter') verifyPasswordAndProceed(); });
        confirmDeleteYesBtn.addEventListener('click', () => { const checked = document.querySelectorAll('.delete-cb:checked'); deleteHiddenInputs.innerHTML = ''; checked.forEach(cb => { const inp = document.createElement('input'); inp.type='hidden'; inp.name='selected_ids[]'; inp.value=cb.value; deleteHiddenInputs.appendChild(inp); }); if (currentPageInput) currentPageInput.value = 1; samplesForm.submit(); });
        confirmDeleteNoBtn.addEventListener('click', () => deleteConfirmModal.classList.remove('open'));
        deleteConfirmModal.addEventListener('click', e => { if (e.target === deleteConfirmModal) deleteConfirmModal.classList.remove('open'); });
    }

    hideExportCols();
    filterRows(false);
});
</script>
</body>
</html>