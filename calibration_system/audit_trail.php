<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] !== 'super_admin') { header("Location: dashboard.php"); exit(); }
include 'db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT NULL, username TEXT DEFAULT NULL, action TEXT NOT NULL, target_table TEXT DEFAULT NULL, record_id INTEGER DEFAULT NULL, details TEXT DEFAULT NULL, ip_address TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL"); } catch(Exception $e){}

define('TZ_OFFSET', 8 * 3600);
function pht(string $raw, string $fmt): string { return $raw ? date($fmt, strtotime($raw) + TZ_OFFSET) : '—'; }

// ─── AUTO-PURGE: silently remove logs older than retention window ─────────────
$retentionDays = 90; // change this value to adjust how long logs are kept
$pdo->prepare("DELETE FROM audit_log WHERE created_at < datetime('now', '-{$retentionDays} days')")->execute();

// ─── WARN THRESHOLD ───────────────────────────────────────────────────────────
$warnThreshold = 5000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Delete a single row ──────────────────────────────────────────────────
    if (isset($_POST['delete_id'])) {
        $delId = intval($_POST['delete_id']);
        $pdo->prepare("DELETE FROM audit_log WHERE id = ?")->execute([$delId]);
        header("Location: audit_trail.php?" . http_build_query(array_filter(['action'=>$_POST['prev_action']??'','table'=>$_POST['prev_table']??'','user'=>$_POST['prev_user']??'','date'=>$_POST['prev_date']??'','machine'=>$_POST['prev_machine']??'','page'=>$_POST['prev_page']??1])));
        exit();
    }

    // ── Archive & Clear All ──────────────────────────────────────────────────
    if (isset($_POST['clear_all'])) {
        $rows = $pdo->query("SELECT * FROM audit_log ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
        $archiveDir = __DIR__ . '/audit_archives/';
        if (!is_dir($archiveDir)) mkdir($archiveDir, 0755, true);
        $filename = $archiveDir . 'audit_' . date('Y-m-d_His') . '.csv';
        $fp = fopen($filename, 'w');
        if (!empty($rows)) {
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($fp, $row);
        }
        fclose($fp);
        $pdo->exec("DELETE FROM audit_log");
        header("Location: audit_trail.php?archived=1");
        exit();
    }

    // ── Purge logs before a chosen date ─────────────────────────────────────
    if (isset($_POST['purge_before']) && !empty($_POST['purge_before_date'])) {
        $purgeDate = $_POST['purge_before_date'];
        // Validate date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $purgeDate)) {
            $pdo->prepare("DELETE FROM audit_log WHERE created_at < ?")->execute([$purgeDate . ' 00:00:00']);
        }
        header("Location: audit_trail.php?purged=1");
        exit();
    }
}

$userMap = [];
try {
    $uRows = $pdo->query("SELECT username, display_name, avatar FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($uRows as $u) { $userMap[strtolower($u['username'])] = $u; }
} catch(Exception $e){}

$filterAction = $_GET['action'] ?? '';
$filterTable  = $_GET['table']  ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterDate   = $_GET['date']   ?? '';
$filterUser   = $filterSearch;
$filterMachine= '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where = []; $params = [];
if ($filterAction) { $where[] = "action = ?";       $params[] = $filterAction; }
if ($filterTable)  { $where[] = "target_table = ?"; $params[] = $filterTable; }
if ($filterSearch) { $where[] = "(username LIKE ? OR ip_address LIKE ? OR details LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }
if ($filterDate)   { $where[] = "created_at >= ? AND created_at < ?"; $params[] = date('Y-m-d H:i:s', strtotime($filterDate) - TZ_OFFSET); $params[] = date('Y-m-d H:i:s', strtotime($filterDate) + 86400 - TZ_OFFSET); }
$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log $whereSQL"); $countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT * FROM audit_log $whereSQL ORDER BY created_at DESC"); $stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$tables  = $pdo->query("SELECT DISTINCT target_table FROM audit_log WHERE target_table IS NOT NULL ORDER BY target_table")->fetchAll(PDO::FETCH_COLUMN);

$summary = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN action IN ('ADD','INSERT') THEN 1 ELSE 0 END) AS inserts, SUM(CASE WHEN action IN ('EDIT','UPDATE') THEN 1 ELSE 0 END) AS updates, SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) AS deletes FROM audit_log")->fetch(PDO::FETCH_ASSOC);

// ── Oldest log date for UI hint ───────────────────────────────────────────────
$oldestLog = $pdo->query("SELECT MIN(created_at) FROM audit_log")->fetchColumn();
$oldestPHT = $oldestLog ? date('M d, Y', strtotime($oldestLog) + TZ_OFFSET) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Trail — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function(){var c=localStorage.getItem('sb-state')==='1';document.documentElement.dataset.sidebar=c?'collapsed':'expanded';if(document.body)document.body.dataset.sidebar=c?'collapsed':'expanded';document.addEventListener('DOMContentLoaded',function(){document.body.dataset.sidebar=document.documentElement.dataset.sidebar;});})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
:root{
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15);--accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7;--bg-card:#ffffff;--bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10);--border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d;--text-2:#4a6070;--text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px;--r-md:12px;--r-xl:20px;
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);--shadow-lg:0 8px 40px rgba(5,48,79,0.14);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg-page);color:var(--text);-webkit-font-smoothing:antialiased;}
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#fff 100%);}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:20px 24px;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;}
.stat-card{background:var(--bg-raised);border-radius:var(--r-md);border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:12px;}
.stat-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon svg{width:18px;height:18px;}
.stat-icon.total{background:rgba(26,144,217,.12);}.stat-icon.total svg{fill:#1a90d9;}
.stat-icon.insert{background:rgba(16,185,129,.12);}.stat-icon.insert svg{fill:#059669;}
.stat-icon.update{background:rgba(245,158,11,.12);}.stat-icon.update svg{fill:#d97706;}
.stat-icon.delete{background:rgba(239,68,68,.12);}.stat-icon.delete svg{fill:#dc2626;}
.stat-value{font-size:22px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:11px;color:var(--text-3);margin-top:2px;font-weight:500;}
.filter-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-md);}
.filter-toolbar select,.filter-toolbar input[type="text"],.filter-toolbar input[type="date"]{height:34px;padding:0 10px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-family:'Plus Jakarta Sans',sans-serif;font-size:12.5px;color:var(--text);background:var(--bg-card);outline:none;box-sizing:border-box;}
.filter-toolbar select{min-width:130px;}
.filter-toolbar input[type="text"]{width:240px;}
.filter-toolbar input[type="date"]{width:145px;}
.filter-toolbar select:focus,.filter-toolbar input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);}
.filter-toolbar input.has-value{border-color:var(--accent);background:rgba(26,144,217,.04);}
.filter-divider{width:1px;height:24px;background:var(--border);flex-shrink:0;}
.filter-count{font-size:12px;color:var(--text-3);font-family:var(--mono);margin-left:auto;white-space:nowrap;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:34px;padding:0 14px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn-primary{background:var(--navy);color:#fff;}
.btn-primary:hover{background:var(--navy-mid);}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}
.btn-danger{background:#dc2626;color:#fff;}
.btn-danger:hover{background:#b91c1c;}
.btn-danger-soft{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.btn-danger-soft:hover{background:rgba(220,53,53,0.18);}
.btn-warning-soft{background:rgba(245,158,11,0.10);color:#78350f;border:1px solid rgba(245,158,11,0.25);}
.btn-warning-soft:hover{background:rgba(245,158,11,0.20);}
.table-container{overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 440px);border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.audit-table{width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed;}
.audit-table thead{position:sticky;top:0;z-index:2;}
.audit-table th{background:var(--navy);color:rgba(255,255,255,.80);padding:10px 12px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,.07);overflow:hidden;}
.audit-table th:last-child{border-right:none;}
.audit-table td{padding:10px 12px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);vertical-align:middle;overflow:hidden;}
.audit-table td:last-child{border-right:none;}
.audit-table tr:last-child td{border-bottom:none;}
.audit-table tbody tr{cursor:pointer;}
.audit-table tbody tr:nth-child(even){background:var(--bg-raised);}
.audit-table tbody tr:hover{background:rgba(26,144,217,.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.audit-table th:nth-child(1),.audit-table td:nth-child(1){width:44px;}
.audit-table th:nth-child(2),.audit-table td:nth-child(2){width:160px;}
.audit-table th:nth-child(3),.audit-table td:nth-child(3){width:110px;}
.audit-table th:nth-child(4),.audit-table td:nth-child(4){width:130px;}
.audit-table th:nth-child(5),.audit-table td:nth-child(5){width:70px;}
.audit-table th:nth-child(6),.audit-table td:nth-child(6){width:auto;}
.audit-table th:nth-child(7),.audit-table td:nth-child(7){width:130px;}
.audit-table th:nth-child(8),.audit-table td:nth-child(8){width:155px;}
.audit-table th:nth-child(9),.audit-table td:nth-child(9){width:60px;text-align:center;}
.user-cell{display:flex;align-items:center;gap:9px;min-width:0;}
.user-avatar-sm{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
.user-avatar-sm img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;}
.user-cell-info{display:flex;flex-direction:column;gap:1px;min-width:0;}
.user-cell-name{font-weight:700;font-size:13px;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.user-cell-display{font-size:11px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.action-badge{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;}
.action-badge .dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.action-badge.ADD,.action-badge.INSERT{background:rgba(16,185,129,.10);color:#065f46;}.action-badge.ADD .dot,.action-badge.INSERT .dot{background:#059669;}
.action-badge.EDIT,.action-badge.UPDATE{background:rgba(245,158,11,.10);color:#78350f;}.action-badge.EDIT .dot,.action-badge.UPDATE .dot{background:#d97706;}
.action-badge.DELETE{background:rgba(239,68,68,.10);color:#a81c1c;}.action-badge.DELETE .dot{background:#dc2626;}
.action-badge.LOGIN{background:rgba(26,144,217,.10);color:#0b5c96;}.action-badge.LOGIN .dot{background:#1a90d9;}
.action-badge.LOGOUT{background:rgba(100,120,138,.10);color:#4a6070;}.action-badge.LOGOUT .dot{background:#94a3b8;}
.action-badge.FORCE_CLOSE{background:rgba(234,88,12,.10);color:#c2410c;}.action-badge.FORCE_CLOSE .dot{background:#c2410c;}
.action-badge.OTHER{background:rgba(124,58,237,.10);color:#5b21b6;}.action-badge.OTHER .dot{background:#7c3aed;}
.table-chip{background:var(--bg-raised);color:var(--text-2);font-size:11.5px;font-weight:600;font-family:var(--mono);padding:2px 7px;border-radius:5px;border:1px solid var(--border);white-space:nowrap;display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;}
.date-cell{font-family:var(--mono);font-size:11.5px;color:var(--text-3);white-space:nowrap;line-height:1.6;}
.date-part{color:var(--text);font-weight:600;font-size:12px;display:block;}
.time-part{color:var(--text-3);font-size:11px;display:block;}
.details-cell{font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:100%;}
.details-td{max-width:0;}
.machine-cell{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-family:var(--mono);background:var(--bg-raised);color:var(--text-2);padding:2px 8px;border-radius:5px;border:1px solid var(--border);white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;}
.machine-cell svg{width:11px;height:11px;fill:var(--text-3);flex-shrink:0;}
.delete-row-btn{width:28px;height:28px;border-radius:6px;border:1px solid rgba(220,53,53,0.20);background:rgba(220,53,53,0.10);color:#a81c1c;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;}
.delete-row-btn:hover{background:rgba(220,53,53,0.20);}
.delete-row-btn svg{width:13px;height:13px;fill:currentColor;pointer-events:none;}
.empty-state{text-align:center;padding:56px 20px;}
.empty-state svg{width:44px;height:44px;fill:var(--text-3);margin:0 auto 12px;display:block;}
.empty-state p{color:var(--text-3);font-size:14px;}
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);min-height:52px;}
.pagination button{padding:6px 16px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-raised);color:var(--navy);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;min-width:90px;text-align:center;}
.pagination button:hover:not(:disabled){background:var(--navy);color:#fff;border-color:var(--navy);}
.pagination button:disabled{opacity:.4;cursor:not-allowed;}
.pagination-info{font-size:11.5px;color:var(--text-3);font-family:var(--mono);min-width:120px;text-align:center;display:inline-block;}
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;box-sizing:border-box;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px;color:var(--text-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;line-height:1;}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}
.event-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:94%;max-width:520px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.event-modal-header{padding:18px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;}
.event-modal-header-left{display:flex;align-items:center;gap:12px;min-width:0;}
.event-modal-avatar{width:36px;height:36px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;overflow:hidden;}
.event-modal-avatar img{width:100%;height:100%;object-fit:cover;}
.event-modal-title{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 2px;}
.event-modal-sub{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.event-modal-body{padding:20px 22px;display:flex;flex-direction:column;gap:14px;}
.event-modal-row{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px;}
.event-modal-field{display:flex;flex-direction:column;gap:4px;}
.event-modal-field.full{grid-column:1/-1;}
.field-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-3);}
.field-value{font-size:13px;font-weight:500;color:var(--text);word-break:break-word;}
.field-value.mono{font-family:var(--mono);font-size:12px;}
.details-box{background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--text);line-height:1.6;word-break:break-word;white-space:pre-wrap;max-height:160px;overflow-y:auto;}
.details-box::-webkit-scrollbar{width:4px;}
.details-box::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:2px;}
.event-modal-divider{height:1px;background:var(--border);}
.event-modal-footer{padding:14px 22px 18px;border-top:1px solid var(--border);background:var(--bg-raised);display:flex;justify-content:space-between;align-items:center;gap:10px;}
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:92%;max-width:420px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;text-align:center;padding:28px 30px;}
.confirm-modal-box .icon{font-size:36px;margin-bottom:10px;}
.confirm-modal-box h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-box p{font-size:13px;color:var(--text-2);margin:0 0 22px;line-height:1.5;}
.confirm-modal-actions{display:flex;gap:10px;justify-content:center;}
/* ── Alert banners ─────────────────────────────────────────────────────────── */
.alert-banner{display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:var(--r-md);margin-bottom:16px;font-size:13px;line-height:1.5;}
.alert-banner svg{width:17px;height:17px;flex-shrink:0;margin-top:1px;}
.alert-warn{background:rgba(245,158,11,0.10);border:1px solid rgba(245,158,11,0.30);color:#78350f;}
.alert-warn svg{fill:#d97706;}
.alert-success{background:rgba(16,185,129,0.10);border:1px solid rgba(16,185,129,0.30);color:#065f46;}
.alert-success svg{fill:#059669;}
.alert-banner strong{font-weight:700;}
.alert-banner a{color:inherit;text-decoration:underline;cursor:pointer;background:none;border:none;font:inherit;padding:0;}
/* ── Purge toolbar row ─────────────────────────────────────────────────────── */
.purge-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;padding:10px 14px;background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.20);border-radius:var(--r-md);}
.purge-toolbar label{font-size:12.5px;font-weight:600;color:#78350f;white-space:nowrap;}
.purge-toolbar input[type="date"]{height:34px;padding:0 10px;border-radius:var(--r-sm);border:1px solid rgba(245,158,11,0.30);font-family:'Plus Jakarta Sans',sans-serif;font-size:12.5px;color:var(--text);background:var(--bg-card);outline:none;}
.purge-toolbar input[type="date"]:focus{border-color:#d97706;box-shadow:0 0 0 3px rgba(245,158,11,0.15);}
.purge-toolbar-hint{font-size:11.5px;color:#92400e;font-family:var(--mono);}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <?php /* ── Flash banners ─────────────────────────────────────────────── */ ?>
    <?php if (isset($_GET['archived'])): ?>
    <div class="alert-banner alert-success">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
        <div><strong>Logs archived and cleared.</strong> A CSV backup was saved to <code>audit_archives/</code> on the server before deletion.</div>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['purged'])): ?>
    <div class="alert-banner alert-success">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
        <div><strong>Old logs purged successfully.</strong></div>
    </div>
    <?php endif; ?>
    <?php if ($totalRows >= $warnThreshold): ?>
    <div class="alert-banner alert-warn">
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        <div>
            <strong><?= number_format($totalRows) ?> log entries detected.</strong>
            The table is getting large<?= $oldestPHT ? ' — oldest record is from <strong>' . $oldestPHT . '</strong>' : '' ?>.
            Consider using <a onclick="document.getElementById('purgeToolbar').style.display='flex'" href="#">Purge Old Logs</a>
            or <a onclick="document.getElementById('clearAllModal').classList.add('open')" href="#">Archive &amp; Clear All</a> to keep performance optimal.
            Logs older than <strong><?= $retentionDays ?> days</strong> are removed automatically.
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon total"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div><div class="stat-value"><?= number_format($summary['total']??0) ?></div><div class="stat-label">Total Events</div></div></div>
        <div class="stat-card"><div class="stat-icon insert"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L21 8l-9 9z"/></svg></div><div><div class="stat-value"><?= number_format($summary['inserts']??0) ?></div><div class="stat-label">Adds</div></div></div>
        <div class="stat-card"><div class="stat-icon update"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></div><div><div class="stat-value"><?= number_format($summary['updates']??0) ?></div><div class="stat-label">Edits</div></div></div>
        <div class="stat-card"><div class="stat-icon delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></div><div><div class="stat-value"><?= number_format($summary['deletes']??0) ?></div><div class="stat-label">Deletes</div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Audit Trail</h2>
            <p>Click any row to view full event details &nbsp;·&nbsp; All times shown in Philippine Time (UTC+8) &nbsp;·&nbsp; Auto-purge: logs older than <?= $retentionDays ?> days are removed automatically</p>
        </div>
        <div class="card-body">

            <?php /* ── Purge by date toolbar (hidden by default) ──────────── */ ?>
            <form method="POST" id="purgeForm">
                <div class="purge-toolbar" id="purgeToolbar" style="display:none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="#d97706"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                    <label for="purge_before_date">Delete all logs before:</label>
                    <input type="date" name="purge_before_date" id="purge_before_date" max="<?= date('Y-m-d') ?>">
                    <button type="submit" name="purge_before" value="1" class="btn btn-warning-soft"
                        onclick="return confirm('This will permanently delete all log entries before the selected date. Continue?')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                        Purge Old Logs
                    </button>
                    <span class="purge-toolbar-hint"><?= $oldestPHT ? 'Oldest record: ' . $oldestPHT : 'No logs yet' ?></span>
                    <button type="button" class="btn btn-muted" style="margin-left:auto;" onclick="document.getElementById('purgeToolbar').style.display='none'">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Hide
                    </button>
                </div>
            </form>

            <form method="GET" action="audit_trail.php" id="filterForm">
                <div class="filter-toolbar">
                    <select name="action" id="f_action">
                        <option value="">All Actions</option>
                        <?php $commonActions=['ADD','EDIT','DELETE','LOGIN','LOGOUT','FORCE_CLOSE','INSERT','UPDATE']; foreach($commonActions as $a): if(!in_array($a,$actions)) continue; ?>
                        <option value="<?= $a ?>" <?= $filterAction===$a?'selected':'' ?>><?= $a ?></option>
                        <?php endforeach; foreach($actions as $a): if(in_array($a,$commonActions)) continue; ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="table" id="f_table">
                        <option value="">All Tables</option>
                        <?php foreach($tables as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $filterTable===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" id="f_search" placeholder="Search user, machine, details…" value="<?= htmlspecialchars($filterSearch) ?>" class="<?= $filterSearch?'has-value':'' ?>">
                    <input type="date" name="date" id="f_date" value="<?= htmlspecialchars($filterDate) ?>" class="<?= $filterDate?'has-value':'' ?>">
                    <div class="filter-divider"></div>
                    <a href="audit_trail.php" class="btn btn-muted">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Clear
                    </a>
                    <div class="filter-divider"></div>
                    <button type="button" class="btn btn-warning-soft" onclick="document.getElementById('purgeToolbar').style.display='flex'">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        Purge Old Logs
                    </button>
                    <button type="button" class="btn btn-danger-soft" onclick="document.getElementById('clearAllModal').classList.add('open')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                        Archive &amp; Clear All
                    </button>
                    <span class="filter-count"><?= number_format($totalRows) ?> event<?= $totalRows!==1?'s':'' ?><?= ($filterAction||$filterTable||$filterSearch||$filterDate)?' (filtered)':'' ?></span>
                </div>
            </form>

            <div class="table-container">
                <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    <p><?= ($filterAction||$filterTable||$filterSearch||$filterDate)?'No events match your filters.':'No audit events recorded yet.' ?></p>
                </div>
                <?php else: ?>
                <table class="audit-table" id="auditTable">
                    <thead>
                        <tr><th>No.</th><th>User</th><th>Action</th><th>Table</th><th>Rec. ID</th><th>Details</th><th>Machine</th><th>Date &amp; Time (PHT)</th><th style="text-align:center;">Del</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $avatarColors = ['#1a90d9','#7c3aed','#059669','#d97706','#e11d48','#0891b2'];
                    $badgeMap = ['ADD'=>'ADD','INSERT'=>'INSERT','EDIT'=>'EDIT','UPDATE'=>'UPDATE','DELETE'=>'DELETE','LOGIN'=>'LOGIN','LOGOUT'=>'LOGOUT','FORCE_CLOSE'=>'FORCE_CLOSE'];
                    foreach ($logs as $i => $row):
                        $action = strtoupper($row['action']); $badgeClass = $badgeMap[$action] ?? 'OTHER';
                        $username = $row['username'] ?? 'System';
                        $colorIdx = abs(crc32($username)) % count($avatarColors); $color = $avatarColors[$colorIdx];
                        $machine = $row['ip_address'] ?? ''; $details = $row['details'] ?? '';
                        $datePart = pht($row['created_at'],'M d, Y'); $timePart = pht($row['created_at'],'h:i A');
                        $fullDt = pht($row['created_at'],'F d, Y — h:i:s A') . ' PHT';
                        $uData = $userMap[strtolower($username)] ?? null;
                        $dispName = !empty($uData['display_name']) ? $uData['display_name'] : null;
                        $uAvatar  = !empty($uData['avatar']) && file_exists($uData['avatar']) ? $uData['avatar'] : null;
                        $initial  = strtoupper(substr($dispName ?? $username, 0, 1));
                        $rowData  = htmlspecialchars(json_encode(['id'=>$row['id'],'no'=>$i+1,'username'=>$username,'dispName'=>$dispName,'avatar'=>$uAvatar,'color'=>$color,'initial'=>$initial,'action'=>$row['action'],'badge'=>$badgeClass,'table'=>$row['target_table']??'','recordId'=>$row['record_id']??'','details'=>$details,'machine'=>$machine,'datetime'=>$fullDt]), ENT_QUOTES);
                    ?>
                    <tr data-event='<?= $rowData ?>'>
                        <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-3);"><?= $i+1 ?></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-sm" style="background:<?= $color ?>;">
                                    <?php if($uAvatar): ?><img src="<?= htmlspecialchars($uAvatar) ?>" alt=""><?php else: ?><?= htmlspecialchars($initial) ?><?php endif; ?>
                                </div>
                                <div class="user-cell-info">
                                    <span class="user-cell-name"><?= htmlspecialchars($username) ?></span>
                                    <?php if($dispName && $dispName !== $username): ?><span class="user-cell-display"><?= htmlspecialchars($dispName) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="action-badge <?= $badgeClass ?>"><span class="dot"></span><?= htmlspecialchars($row['action']) ?></span></td>
                        <td><?php if(!empty($row['target_table'])): ?><span class="table-chip"><?= htmlspecialchars($row['target_table']) ?></span><?php else: ?><span style="color:var(--text-3);">—</span><?php endif; ?></td>
                        <td style="font-size:12px;color:var(--text-3);font-family:'DM Mono',monospace;"><?= $row['record_id'] ?? '—' ?></td>
                        <td class="details-td"><?php if($details): ?><span class="details-cell" title="<?= htmlspecialchars($details) ?>"><?= htmlspecialchars($details) ?></span><?php else: ?><span style="color:var(--text-3);">—</span><?php endif; ?></td>
                        <td><?php if($machine): ?><span class="machine-cell"><svg viewBox="0 0 24 24"><path d="M21 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H3V4h18v12z"/></svg><?= htmlspecialchars($machine) ?></span><?php else: ?><span style="color:var(--text-3);font-size:12px;">—</span><?php endif; ?></td>
                        <td class="date-cell"><span class="date-part"><?= $datePart ?></span><span class="time-part"><?= $timePart ?></span></td>
                        <td style="text-align:center;" onclick="event.stopPropagation()">
                            <button class="delete-row-btn" title="Delete this log entry" onclick="confirmDeleteRow(<?= $row['id'] ?>,'<?= htmlspecialchars(addslashes($row['action']),ENT_QUOTES) ?>')">
                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</div>

<!-- EVENT DETAIL MODAL -->
<div id="eventModal" class="modal-overlay">
    <div class="event-modal-box">
        <div class="event-modal-header">
            <div class="event-modal-header-left">
                <div class="event-modal-avatar" id="modal-avatar" style="background:#1a90d9;"></div>
                <div><div class="event-modal-title" id="modal-username">—</div><div class="event-modal-sub" id="modal-datetime">—</div></div>
            </div>
            <button class="modal-close-btn" id="closeEventModal">&times;</button>
        </div>
        <div class="event-modal-body">
            <div class="event-modal-row">
                <div class="event-modal-field"><div class="field-label">Action</div><div class="field-value" id="modal-action">—</div></div>
                <div class="event-modal-field"><div class="field-label">Table</div><div class="field-value mono" id="modal-table">—</div></div>
                <div class="event-modal-field"><div class="field-label">Record ID</div><div class="field-value mono" id="modal-record-id">—</div></div>
                <div class="event-modal-field"><div class="field-label">Machine / IP</div><div class="field-value mono" id="modal-machine">—</div></div>
            </div>
            <div class="event-modal-divider"></div>
            <div class="event-modal-field full"><div class="field-label">Details</div><div class="details-box" id="modal-details">—</div></div>
        </div>
        <div class="event-modal-footer">
            <button class="btn btn-danger-soft" id="modal-delete-btn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Delete This Entry
            </button>
            <button class="btn btn-muted" id="closeEventModalBtn">Close</button>
        </div>
    </div>
</div>

<!-- SINGLE ROW DELETE CONFIRM MODAL -->
<div id="deleteRowModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="icon">🗑️</div>
        <h3>Delete this log entry?</h3>
        <p id="deleteRowDesc">This action cannot be undone.</p>
        <div class="confirm-modal-actions">
            <form method="POST" id="deleteRowForm">
                <input type="hidden" name="delete_id" id="deleteRowId">
                <input type="hidden" name="prev_action"  value="<?= htmlspecialchars($filterAction) ?>">
                <input type="hidden" name="prev_table"   value="<?= htmlspecialchars($filterTable) ?>">
                <input type="hidden" name="prev_user"    value="<?= htmlspecialchars($filterUser) ?>">
                <input type="hidden" name="prev_date"    value="<?= htmlspecialchars($filterDate) ?>">
                <input type="hidden" name="prev_machine" value="<?= htmlspecialchars($filterMachine) ?>">
                <input type="hidden" name="prev_page"    value="<?= $page ?>">
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    <button type="button" class="btn btn-muted" onclick="document.getElementById('deleteRowModal').classList.remove('open')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ARCHIVE & CLEAR ALL CONFIRM MODAL -->
<div id="clearAllModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="icon">📦</div>
        <h3>Archive &amp; Clear all audit logs?</h3>
        <p>All <strong><?= number_format($totalRows) ?> log entries</strong> will be exported to a CSV backup in <code>audit_archives/</code> on the server, then permanently deleted. This cannot be undone.</p>
        <div class="confirm-modal-actions">
            <form method="POST">
                <input type="hidden" name="clear_all" value="1">
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-danger">Yes, Archive &amp; Clear</button>
                    <button type="button" class="btn btn-muted" onclick="document.getElementById('clearAllModal').classList.remove('open')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const filterForm    = document.getElementById('filterForm');
const searchInput   = document.getElementById('f_search');
const auditTableBody = document.querySelector('#auditTable tbody');
const ROWS_PER_PAGE = <?= $perPage ?>;
let currentPage = <?= $page ?>;
let allRows = auditTableBody ? Array.from(auditTableBody.querySelectorAll('tr[data-event]')) : [];
let visibleRows = [...allRows];

function showPage() {
    const total = Math.ceil(visibleRows.length / ROWS_PER_PAGE) || 1;
    if (currentPage > total) currentPage = total;
    const start = (currentPage - 1) * ROWS_PER_PAGE, end = start + ROWS_PER_PAGE;
    allRows.forEach(r => r.style.display = 'none');
    visibleRows.slice(start, end).forEach(r => r.style.display = '');
    renderPagination();
}
function renderPagination() {
    const pEl = document.getElementById('pagination'); if (!pEl) return;
    const total = Math.ceil(visibleRows.length / ROWS_PER_PAGE) || 1;
    pEl.innerHTML = '';
    const prev = document.createElement('button'); prev.textContent = '← Prev'; prev.disabled = currentPage === 1; prev.onclick = () => { currentPage--; showPage(); };
    const info = document.createElement('span'); info.className = 'pagination-info'; info.textContent = `Page ${currentPage} of ${total}`;
    const next = document.createElement('button'); next.textContent = 'Next →'; next.disabled = currentPage === total; next.onclick = () => { currentPage++; showPage(); };
    pEl.appendChild(prev); pEl.appendChild(info); pEl.appendChild(next);
}
function submitFilter() { filterForm.submit(); }
document.getElementById('f_action')?.addEventListener('change', submitFilter);
document.getElementById('f_table')?.addEventListener('change', submitFilter);
document.getElementById('f_date')?.addEventListener('change', submitFilter);
if (searchInput && auditTableBody) {
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        this.classList.toggle('has-value', q.length > 0);
        visibleRows = q ? allRows.filter(row => row.innerText.toLowerCase().includes(q)) : [...allRows];
        currentPage = 1; showPage();
    });
}
showPage();

const modal = document.getElementById('eventModal');
const closeBtn = document.getElementById('closeEventModal');
const closeBtnFooter = document.getElementById('closeEventModalBtn');
const modalDeleteBtn = document.getElementById('modal-delete-btn');
let currentEventId = null;

const badgeColors = {
    ADD:{bg:'rgba(16,185,129,.10)',color:'#065f46'},INSERT:{bg:'rgba(16,185,129,.10)',color:'#065f46'},
    EDIT:{bg:'rgba(245,158,11,.10)',color:'#78350f'},UPDATE:{bg:'rgba(245,158,11,.10)',color:'#78350f'},
    DELETE:{bg:'rgba(239,68,68,.10)',color:'#a81c1c'},LOGIN:{bg:'rgba(26,144,217,.10)',color:'#0b5c96'},
    LOGOUT:{bg:'rgba(100,120,138,.10)',color:'#4a6070'},FORCE_CLOSE:{bg:'rgba(234,88,12,.10)',color:'#c2410c'},
};

const auditTable = document.getElementById('auditTable');
if (auditTable) {
    auditTable.addEventListener('click', function(e) {
        if (e.target.closest('.delete-row-btn')) return;
        const row = e.target.closest('tr[data-event]'); if (!row) return;
        let d; try { d = JSON.parse(row.getAttribute('data-event')); } catch(err) { return; }
        currentEventId = d.id;
        const avatarEl = document.getElementById('modal-avatar');
        avatarEl.style.background = d.color;
        avatarEl.innerHTML = d.avatar ? `<img src="${d.avatar}" alt="">` : d.initial;
        let nameHtml = `<strong>${escHtml(d.username)}</strong>`;
        if (d.dispName && d.dispName !== d.username) nameHtml += ` <span style="font-size:12px;font-weight:400;color:var(--text-3);">(${escHtml(d.dispName)})</span>`;
        document.getElementById('modal-username').innerHTML = nameHtml;
        document.getElementById('modal-datetime').textContent = d.datetime || '—';
        const bc = badgeColors[d.badge] || {bg:'rgba(124,58,237,.10)',color:'#5b21b6'};
        document.getElementById('modal-action').innerHTML = `<span style="display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;background:${bc.bg};color:${bc.color};"><span style="width:6px;height:6px;border-radius:50%;background:${bc.color};flex-shrink:0;display:inline-block;"></span>${escHtml(d.action)}</span>`;
        document.getElementById('modal-table').textContent     = d.table     || '—';
        document.getElementById('modal-record-id').textContent = d.recordId !== '' ? d.recordId : '—';
        document.getElementById('modal-machine').textContent   = d.machine   || '—';
        document.getElementById('modal-details').textContent   = d.details   || '(no details)';
        modal.classList.add('open');
    });
}
modalDeleteBtn.addEventListener('click', function() { if (!currentEventId) return; closeModal(); confirmDeleteRow(currentEventId,''); });
function closeModal() { modal.classList.remove('open'); }
closeBtn.addEventListener('click', closeModal);
closeBtnFooter.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.getElementById('deleteRowModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
document.getElementById('clearAllModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); document.getElementById('deleteRowModal').classList.remove('open'); document.getElementById('clearAllModal').classList.remove('open'); } });

function confirmDeleteRow(id, action) {
    document.getElementById('deleteRowId').value = id;
    document.getElementById('deleteRowDesc').innerHTML = action ? `Delete the <strong>${escHtml(action)}</strong> log entry #${id}? This cannot be undone.` : `Delete log entry #${id}? This cannot be undone.`;
    document.getElementById('deleteRowModal').classList.add('open');
}
function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
</script>
</body>
</html>
