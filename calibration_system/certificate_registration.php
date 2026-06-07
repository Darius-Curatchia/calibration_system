<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
require_once 'audit_helper.php';

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

function fmtDate($d) {
    if (empty($d) || $d === '0000-00-00') return '';
    return date('d-M-y', strtotime($d));
}

function fmtDateLong($d) {
    if (empty($d) || $d === '0000-00-00') return '';
    return date('F j, Y', strtotime($d));
}

// ── Build year list from existing records + current year ──────────────────────
$yearStmt   = $pdo->query("
    SELECT DISTINCT strftime('%Y', date) AS yr
    FROM certificate_registration
    WHERE date IS NOT NULL AND date != '0000-00-00'
    ORDER BY yr DESC
");
$allYears   = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
$currentYear= (int)date('Y');
if (!in_array($currentYear, $allYears)) array_unshift($allYears, $currentYear);

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// ── AJAX: verify password before bulk delete ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_delete_password') {
    header('Content-Type: application/json');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    if ($hash && password_verify($password, $hash)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    }
    exit();
}

// ── AJAX: GET ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'search_machine') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $pdo->prepare("
            SELECT machine_code, calibration_date, reference
            FROM calibration_report
            WHERE machine_code LIKE ?
            ORDER BY machine_code ASC LIMIT 15
        ");
        $stmt->execute([$q]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    if ($_GET['action'] === 'search_equipment') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $pdo->prepare("
            SELECT equipment_code AS machine_code, calibration_date, reference
            FROM standard_samples
            WHERE equipment_code LIKE ?
            ORDER BY equipment_code ASC LIMIT 15
        ");
        $stmt->execute([$q]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    if ($_GET['action'] === 'search_unified') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        // Search calibration_report
        $stmt1 = $pdo->prepare("
            SELECT machine_code, calibration_date, reference, 'calibration_report' AS source
            FROM calibration_report
            WHERE machine_code LIKE ?
            ORDER BY machine_code ASC LIMIT 10
        ");
        $stmt1->execute([$q]);
        $rows1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        // Search standard_samples
        $stmt2 = $pdo->prepare("
            SELECT equipment_code AS machine_code, calibration_date, reference, 'standard_samples' AS source
            FROM standard_samples
            WHERE equipment_code LIKE ?
            ORDER BY equipment_code ASC LIMIT 10
        ");
        $stmt2->execute([$q]);
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        // Merge, deduplicate by machine_code (calibration_report takes priority)
        $seen   = [];
        $merged = [];
        foreach (array_merge($rows1, $rows2) as $row) {
            if (!isset($seen[$row['machine_code']])) {
                $seen[$row['machine_code']] = true;
                $merged[] = $row;
            }
        }
        // Sort merged by machine_code, limit 15
        usort($merged, fn($a, $b) => strcmp($a['machine_code'], $b['machine_code']));
        echo json_encode(array_slice($merged, 0, 15));
        exit();
    }

    if ($_GET['action'] === 'get_cert_number') {
        $year   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $prefix = substr((string)$year, 2);
        $stmt   = $pdo->prepare("
            SELECT certificate_number FROM certificate_registration
            WHERE certificate_number LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$prefix . ' %']);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode(' ', $last);
            $next  = isset($parts[1]) ? ((int)$parts[1]) + 1 : 1;
        } else {
            $next = 1;
        }
        echo json_encode(['certificate_number' => $prefix . ' ' . str_pad($next, 3, '0', STR_PAD_LEFT)]);
        exit();
    }

    if ($_GET['action'] === 'get_machine_info') {
        $machine_code = trim($_GET['machine_code'] ?? '');
        $cert_id      = isset($_GET['cert_id']) ? (int)$_GET['cert_id'] : 0;

        if (!$machine_code) { echo json_encode(['success' => false]); exit(); }

        $stmt = $pdo->prepare("
            SELECT machine_code, description, model_maker, serial_number, location,
                   calibration_date, next_calibration, cal_frequency, calibrator,
                   present_status, model, maker, section, manager, reference,
                   cal_type, env_type
            FROM calibration_report
            WHERE machine_code = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$machine_code]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── Fallback: check standard_samples if not found in calibration_report ─
        $dataSource = 'calibration_report';
        if (!$report) {
            $stmtSS = $pdo->prepare("
                SELECT
                    equipment_code        AS machine_code,
                    description,
                    model_maker,
                    serial_no             AS serial_number,
                    location,
                    calibration_date,
                    next_calibration_date AS next_calibration,
                    calibration_frequency AS cal_frequency,
                    calibrator,
                    present_status,
                    model,
                    maker,
                    section,
                    manager,
                    reference,
                    cal_type,
                    env_type
                FROM standard_samples
                WHERE equipment_code = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmtSS->execute([$machine_code]);
            $report     = $stmtSS->fetch(PDO::FETCH_ASSOC);
            $dataSource = 'standard_samples';
        }

        if (!$report) { echo json_encode(['success' => false, 'message' => 'Machine not found in calibration records or standard samples']); exit(); }

        if ($cert_id > 0) {
            $stmt2 = $pdo->prepare("
                SELECT instrument_date_received, calibration_location, certificate_number,
                       date, date_of_calibration, calibrated_by, reference, remarks
                FROM certificate_registration
                WHERE id = ?
            ");
            $stmt2->execute([$cert_id]);
        } else {
            $stmt2 = $pdo->prepare("
                SELECT instrument_date_received, calibration_location, certificate_number,
                       date, date_of_calibration, calibrated_by, reference, remarks
                FROM certificate_registration
                WHERE machine_code = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt2->execute([$machine_code]);
        }
        $cert = $stmt2->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'report' => $report, 'cert' => $cert ?: [], 'data_source' => $dataSource]);
        exit();
    }

    exit();
}

// ── AJAX: POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'save_certificate' && !$isGuest) {
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE certificate_registration SET
                        date = ?, certificate_number = ?, machine_code = ?,
                        date_of_calibration = ?, calibrated_by = ?,
                        instrument_date_received = ?, calibration_location = ?,
                        reference = ?, remarks = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['date']                     ?? date('Y-m-d'),
                    $_POST['certificate_number']       ?? '',
                    $_POST['machine_code']             ?? '',
                    $_POST['date_of_calibration']      ?: null,
                    $_POST['calibrated_by']            ?? '',
                    $_POST['instrument_date_received'] ?: null,
                    $_POST['calibration_location']     ?? '',
                    $_POST['reference']                ?? '',
                    $_POST['remarks']                  ?? '',
                    $id,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO certificate_registration
                        (date, certificate_number, machine_code, date_of_calibration,
                         calibrated_by, instrument_date_received, calibration_location,
                         reference, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['date']                     ?? date('Y-m-d'),
                    $_POST['certificate_number']       ?? '',
                    $_POST['machine_code']             ?? '',
                    $_POST['date_of_calibration']      ?: null,
                    $_POST['calibrated_by']            ?? '',
                    $_POST['instrument_date_received'] ?: null,
                    $_POST['calibration_location']     ?? '',
                    $_POST['reference']                ?? '',
                    $_POST['remarks']                  ?? '',
                ]);
                $id = (int)$pdo->lastInsertId();
            }
            $row = $pdo->prepare("SELECT * FROM certificate_registration WHERE id = ?");
            $row->execute([$id]);
            $saved = $row->fetch(PDO::FETCH_ASSOC);

            // ── Audit log ─────────────────────────────────────────────
            $certNo  = $saved['certificate_number'] ?? '';
            $machine = $saved['machine_code']       ?? '';
            $calBy   = $saved['calibrated_by']      ?? '';
            if ($id > 0 && isset($_POST['id']) && (int)$_POST['id'] > 0) {
                $action  = 'EDIT';
                $details = "Edited Cert No: {$certNo} | Machine: {$machine} | Calibrated By: {$calBy}";
            } else {
                $action  = 'ADD';
                $details = "Added Cert No: {$certNo} | Machine: {$machine} | Calibrated By: {$calBy}";
            }
            log_audit($pdo, $action, 'certificate_registration', $id, $details);

            echo json_encode(['success' => true, 'row' => $saved]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['action'] === 'delete_certificate' && !$isGuest) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Fetch before deleting so we can log meaningful details
            $pre = $pdo->prepare("SELECT certificate_number, machine_code FROM certificate_registration WHERE id = ?");
            $pre->execute([$id]);
            $rec = $pre->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM certificate_registration WHERE id = ?")->execute([$id]);

            $certNo  = $rec['certificate_number'] ?? '';
            $machine = $rec['machine_code']       ?? '';
            log_audit($pdo, 'DELETE', 'certificate_registration', $id,
                "Deleted Cert No: {$certNo} | Machine: {$machine}");

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    exit();
}

// ── Load records filtered by year ─────────────────────────────────────────────
if ($selectedYear > 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM certificate_registration
        WHERE strftime('%Y', date) = ?
        ORDER BY id DESC
    ");
    $stmt->execute([(string)$selectedYear]);
} else {
    $stmt = $pdo->query("SELECT * FROM certificate_registration ORDER BY id DESC");
}
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate Registration — Calibration Management</title>
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
<style>
/* ── Design Tokens ── */
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

/* ── Card ── */
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:20px 24px;}

/* ── Year badge ── */
.year-badge{display:inline-flex;align-items:center;gap:6px;background:#e0f2fe;color:#075985;border:1px solid #bae6fd;border-radius:20px;padding:4px 13px;font-size:12px;font-weight:700;font-family:var(--mono);white-space:nowrap;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn svg{flex-shrink:0;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-accent{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(26,144,217,0.25);}
.btn-accent:hover{background:#1480c5;}
.btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,0.25);}
.btn-success:hover{background:#15803d;}
.btn-danger{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.btn-danger:hover{background:rgba(220,53,53,0.16);}
.btn-danger-solid{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.25);}
.btn-danger-solid:hover{background:#b91c1c;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

/* ── Top controls ── */
.top-controls{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.controls-left,.controls-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}

/* ── Filter inputs ── */
.filter-input,.year-select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);box-sizing:border-box;}
.filter-input:focus,.year-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.filter-input{min-width:200px;}
.year-select{min-width:130px;cursor:pointer;}

/* ── Bulk bars ── */
.bulk-bar{display:none;align-items:center;gap:10px;padding:10px 16px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:12px;flex-wrap:wrap;}
.bulk-bar.visible{display:flex;}
#bulkDeleteBar.visible{background:#fff5f5;border-color:#fca5a5;border-left:3px solid #dc2626;}
.bulk-badge{display:inline-flex;align-items:center;background:var(--accent-soft);color:var(--accent);font-weight:700;font-size:11.5px;font-family:var(--mono);padding:4px 10px;border-radius:20px;border:1px solid rgba(26,144,217,0.2);}

/* ── Table ── */
.table-container{overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 310px);border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}

.cert-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12.5px;min-width:900px;}
.cert-table thead{position:sticky;top:0;z-index:2;}
.cert-table th{background:var(--navy);color:rgba(255,255,255,0.80);padding:10px 12px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,0.07);}
.cert-table th:last-child{border-right:none;}
.cert-table td{padding:9px 12px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;color:var(--text);}
.cert-table td:last-child{border-right:none;}
.cert-table tr:last-child td{border-bottom:none;}
.cert-table tbody tr.data-row{cursor:pointer;}
.cert-table tbody tr.data-row:nth-child(even){background:var(--bg-raised);}
.cert-table tbody tr.data-row:hover{background:rgba(26,144,217,0.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.cert-table tbody tr.row-new{background:rgba(26,144,217,0.12);}

/* fixed column widths */
.cert-table th:nth-child(1),.cert-table td:nth-child(1){width:44px;}
.cert-table th:nth-child(2),.cert-table td:nth-child(2){width:88px;}
.cert-table th:nth-child(3),.cert-table td:nth-child(3){width:108px;}
.cert-table th:nth-child(4),.cert-table td:nth-child(4){width:140px;}
.cert-table th:nth-child(5),.cert-table td:nth-child(5){width:100px;}
.cert-table th:nth-child(6),.cert-table td:nth-child(6){width:130px;}
.cert-table th:nth-child(7),.cert-table td:nth-child(7){width:105px;}
.cert-table th:nth-child(8),.cert-table td:nth-child(8){width:130px;}
.cert-table th:nth-child(9),.cert-table td:nth-child(9){width:118px;}
.cert-table th:nth-child(10),.cert-table td:nth-child(10){width:auto;}

.date-cell{text-align:center;font-family:var(--mono);font-size:11.5px;color:var(--text-2);}
.certnum{color:var(--navy);font-weight:700;font-size:12px;font-family:var(--mono);}
.num-cell{font-size:11px;color:var(--text-3);font-family:var(--mono);}

/* delete column */
.deleteCol,.deleteColHeader{width:36px;text-align:center;padding:0 6px!important;}
.deleteCol input[type="checkbox"],.deleteColHeader input[type="checkbox"]{width:15px;height:15px;accent-color:#dc2626;cursor:pointer;}
.cert-table thead th.deleteColHeader{background:#7f1d1d;}
.cert-table tbody tr:has(.delete-cb:checked){background:#fff5f5!important;box-shadow:inset 3px 0 0 #dc2626;}

/* gen column */
.genCol,.genColHeader{width:36px;text-align:center;padding:0 6px!important;}
.gen-cb{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;}

/* ── Empty state ── */
.cert-empty{display:flex;flex-direction:column;align-items:center;padding:60px 20px;gap:6px;text-align:center;}
.cert-empty svg{opacity:0.20;color:var(--navy);}
.cert-empty-label{font-size:14px;font-weight:700;color:var(--navy);opacity:0.45;}
.cert-empty-sub{font-size:12px;color:var(--text-3);}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}
.pagination button{padding:6px 16px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-raised);color:var(--navy);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;min-width:90px;}
.pagination button:hover:not(:disabled){background:var(--navy);color:#fff;border-color:var(--navy);}
.pagination button:disabled{opacity:0.4;cursor:not-allowed;}
.pagination-info{font-size:11.5px;color:var(--text-3);font-family:var(--mono);min-width:120px;text-align:center;}

/* ── Modal overlay ── */
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,0.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;color:var(--text-2);}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}

/* ── Form box (add/edit + detail) ── */
.form-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:640px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;}
.form-header{padding:18px 22px 16px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#fff 100%);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.form-header h3{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 2px;}
.form-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.form-body{padding:20px 22px;overflow-y:auto;flex:1;}
.form-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border);background:var(--bg-raised);flex-shrink:0;}
.form-error{font-size:12px;color:#be123c;font-weight:600;background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:5px 10px;margin-right:auto;display:none;}
.form-spinner{width:13px;height:13px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:4px;}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Form grid ── */
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px 16px;}
.form-field{display:flex;flex-direction:column;gap:6px;}
.form-field.wide{grid-column:span 2;}
.form-field.full{grid-column:1/-1;}
.form-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);}
.req{color:#e53e3e;}

/* ── Form inputs ── */
.form-input{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-input::placeholder{color:var(--text-3);font-weight:400;}
.form-input[readonly]{background:#f0f4f8;color:var(--text-3);cursor:default;border-color:var(--border);}
.form-input.mono{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}
.form-textarea{height:auto;padding:9px 12px;resize:vertical;min-height:58px;font-family:'Plus Jakarta Sans',sans-serif;}
input[type="date"].form-input{font-family:var(--mono);font-size:12px;}

/* ── Detail value display ── */
.detail-val{font-size:13px;font-weight:500;color:var(--text);padding:8px 10px;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--r-sm);min-height:34px;word-break:break-word;line-height:1.5;}
.detail-val.mono{font-family:var(--mono);font-size:12px;}
.detail-val.bold{font-weight:700;}
.detail-val.empty-val{color:var(--text-3);font-style:italic;}

/* ── Autocomplete ── */
.mc-wrap{position:relative;}
.mc-dropdown{display:none;position:absolute;top:calc(100% + 3px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border-mid);border-radius:var(--r-sm);box-shadow:var(--shadow-lg);z-index:500;max-height:200px;overflow-y:auto;}
.mc-option{padding:8px 12px;font-size:12.5px;color:var(--text);cursor:pointer;border-bottom:1px solid var(--border);font-weight:500;display:flex;align-items:center;justify-content:space-between;gap:8px;}
.mc-option:last-child{border-bottom:none;}
.mc-option:hover,.mc-option.active{background:var(--accent-soft);color:var(--accent);}
.mc-option-code{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-source-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;white-space:nowrap;flex-shrink:0;}
.mc-source-cr{background:rgba(26,144,217,0.10);color:#0b5c96;border:1px solid rgba(26,144,217,0.20);}
.mc-source-ss{background:rgba(124,58,237,0.10);color:#5b21b6;border:1px solid rgba(124,58,237,0.20);}
/* ── Data source pill in Gen Cert info preview ── */
.gc-data-source{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;margin-bottom:8px;}
.gc-data-source.from-cr{background:rgba(26,144,217,0.10);color:#0b5c96;border:1px solid rgba(26,144,217,0.20);}
.gc-data-source.from-ss{background:rgba(124,58,237,0.10);color:#5b21b6;border:1px solid rgba(124,58,237,0.20);}

/* ── Confirm modals ── */
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:400px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;text-align:center;}
.confirm-modal-icon{padding:26px 0 6px;display:flex;justify-content:center;}
.confirm-modal-icon svg{color:#dc2626;}
.confirm-modal-body{padding:0 28px 20px;}
.confirm-modal-body h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-body p{font-size:13px;color:var(--text-2);margin:0;line-height:1.55;}
.confirm-modal-footer{display:flex;gap:10px;justify-content:center;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}

/* Password modal extras */
.confirm-pw-row{display:flex;flex-direction:column;gap:5px;text-align:left;margin:12px 0 4px;}
.confirm-pw-row label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);}
.confirm-pw-wrap{position:relative;}
.confirm-pw-input{width:100%;height:34px;padding:0 40px 0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);}
.confirm-pw-input:focus{outline:none;border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,0.10);background:var(--bg-card);}
.confirm-pw-input.error-input{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,0.12);}
.confirm-pw-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.confirm-pw-eye:hover{color:#dc2626;}
.confirm-pw-eye svg{width:14px;height:14px;}
.confirm-modal-error{display:none;font-size:12px;color:#be123c;font-weight:600;background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:8px 12px;margin:10px 28px 0;text-align:left;}
.confirm-modal-error.show{display:block;}
.pw-spinner{width:13px;height:13px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:4px;}

/* ── Generate Certificate modal ── */
.gen-cert-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:640px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;}
.gen-cert-header{padding:18px 22px 16px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#fff 100%);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.gen-cert-header-left{display:flex;align-items:center;gap:12px;}
.gen-cert-icon{width:36px;height:36px;border-radius:var(--r-md);background:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.gen-cert-icon svg{color:#fff;}
.gen-cert-header h3{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 2px;}
.gen-cert-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.gen-cert-body{padding:20px 22px;overflow-y:auto;flex:1;}
.gen-cert-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border);background:var(--bg-raised);flex-shrink:0;}

.gen-section-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--accent);display:flex;align-items:center;gap:8px;margin:18px 0 12px;}
.gen-section-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* Selected record banner */
.selected-record-banner{background:#f0f9ff;border:1px solid #bae6fd;border-radius:var(--r-md);padding:12px 16px;margin-bottom:16px;}
.srb-inner{display:flex;align-items:center;gap:12px;}
.srb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--accent-soft);border:1px solid var(--accent-glow);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.srb-icon svg{color:var(--accent);}
.srb-info{flex:1;min-width:0;}
.srb-machine{display:block;font-size:14px;font-weight:700;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.srb-meta{display:block;font-size:11.5px;color:var(--text-3);margin-top:2px;font-family:var(--mono);}
.srb-change{font-size:11.5px;font-weight:600;color:var(--accent);cursor:pointer;white-space:nowrap;padding:4px 10px;border-radius:var(--r-sm);border:1px solid rgba(26,144,217,0.3);background:var(--accent-soft);flex-shrink:0;}
.srb-change:hover{background:rgba(26,144,217,0.14);}

/* Not-found / loading */
.not-found-msg{font-size:12.5px;font-weight:600;border-radius:var(--r-sm);padding:9px 12px;margin-top:10px;display:none;}
#gcNotFound{background:#fff1f2;border:1px solid #fda4af;color:#be123c;}
#gcLoading{background:var(--accent-soft);border:1px solid var(--accent-glow);color:var(--accent);}

/* Info preview */
.info-preview{background:#f0f7ff;border:1px solid #bfdbfe;border-radius:var(--r-md);padding:14px 16px;margin-top:12px;display:none;}
.info-preview.show{display:block;}
.info-preview-header{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--accent);margin-bottom:10px;}
.info-preview-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;}
.info-preview-item{display:flex;flex-direction:column;gap:2px;}
.info-preview-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--accent);}
.info-preview-value{font-size:12.5px;font-weight:500;color:var(--navy);word-break:break-word;}
.info-preview-value.empty{color:var(--text-3);font-style:italic;}

/* Result of calibration */
.result-row{display:flex;gap:20px;margin-top:6px;}
.result-option{display:flex;align-items:center;gap:8px;cursor:pointer;}
.result-option input[type=radio]{display:none;}
.result-box{width:18px;height:18px;border:2px solid var(--border-mid);border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.result-option input[type=radio]:checked + .result-box{border-color:#16a34a;background:#16a34a;}
.result-option input[type=radio]:checked + .result-box::after{content:'✓';color:#fff;font-size:11px;font-weight:700;line-height:1;}
.result-option.fail input[type=radio]:checked + .result-box{border-color:#dc2626;background:#dc2626;}
.result-label{font-size:13px;font-weight:600;color:var(--text-2);}
.result-option input[type=radio]:checked ~ .result-label{color:#16a34a;}
.result-option.fail input[type=radio]:checked ~ .result-label{color:#dc2626;}

/* Signatories */
.sig-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;}
.sig-field{display:flex;flex-direction:column;gap:5px;}
.sig-sub-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);}
.sig-name-input,.sig-title-input{height:34px;padding:0 10px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.sig-name-input{font-weight:600;}
.sig-name-input:focus,.sig-title-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.sig-name-input[readonly]{background:#f0f4f8;color:var(--text-3);cursor:default;border-color:var(--border);}
.sig-persist-hint{font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px;margin-bottom:10px;}
.sig-persist-hint svg{flex-shrink:0;opacity:0.55;}

/* Revision history */
.rev-history-table{border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden;}
.rev-history-header{display:grid;grid-template-columns:42px 80px 90px 1fr 110px;background:var(--navy);}
.rev-history-header span{padding:7px 7px;font-size:10px;font-weight:700;color:rgba(255,255,255,0.80);text-transform:uppercase;letter-spacing:0.4px;border-right:1px solid rgba(255,255,255,0.08);}
.rev-history-header span:last-child{border-right:none;}
.rev-history-row{display:grid;grid-template-columns:42px 80px 90px 1fr 110px;border-top:1px solid var(--border);}
.rev-history-row:nth-child(even){background:var(--bg-raised);}
.rev-input{padding:6px 7px;border:none;border-right:1px solid var(--border);font-size:11.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:transparent;width:100%;height:100%;}
.rev-input:last-child{border-right:none;}
.rev-input:focus{outline:none;background:var(--accent-soft);}
.rev-input::placeholder{color:var(--text-3);font-style:italic;}

.form-error-inline{display:none;font-size:12px;color:#be123c;font-weight:600;background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:8px 12px;margin-top:10px;}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Certificate Registration</h2>
                <p>Calibration certificates issued after completion of calibration</p>
            </div>
            <?php if ($selectedYear > 0): ?>
            <span class="year-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= $selectedYear ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">

            <div class="top-controls">
                <div class="controls-left">
                    <a href="calibration_report.php" class="btn btn-muted">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Back
                    </a>
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-primary" id="addBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New
                    </button>
                    <button class="btn btn-success" id="genCertBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Generate Certificate
                    </button>
                    <?php endif; ?>
                </div>
                <div class="controls-right">
                    <select class="year-select" id="yearFilter"
                            onchange="window.location.href='certificate_registration.php?year='+encodeURIComponent(this.value)">
                        <option value="0" <?= $selectedYear === 0 ? 'selected' : '' ?>>All Years</option>
                        <?php foreach ($allYears as $yr): ?>
                        <option value="<?= $yr ?>" <?= $selectedYear === (int)$yr ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search…">
                    <?php if (!$isGuest): ?>
                    <button class="btn btn-danger" id="toggleDeleteBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                        Delete Multiple
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Bulk Delete Bar ── -->
            <?php if (!$isGuest): ?>
            <div class="bulk-bar" id="bulkDeleteBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Bulk Delete</span>
                <button class="btn btn-danger-solid" type="button" id="deleteSelectedBtn" disabled>Delete Selected</button>
                <button class="btn btn-muted" type="button" id="cancelDeleteBtn">Cancel</button>
                <span class="bulk-badge" id="selectedCountBadge">0 selected</span>
            </div>
            <?php endif; ?>

            <!-- ── Generate Certificate selection bar ── -->
            <div class="bulk-bar" id="bulkGenBar">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Select one record to generate a certificate for</span>
                <button class="btn btn-success" type="button" id="continueGenBtn" disabled>Continue →</button>
                <button class="btn btn-muted" type="button" id="cancelGenBtn">Cancel</button>
                <span class="bulk-badge" id="genSelectedBadge">0 selected</span>
            </div>

            <div class="table-container">
                <table class="cert-table" style="table-layout:auto;min-width:900px;">
                    <thead>
                        <tr>
                            <?php if (!$isGuest): ?>
                            <th class="deleteColHeader" id="deleteColHeader" style="display:none;width:36px;"><input type="checkbox" id="selectAllDelete"></th>
                            <th class="genColHeader" id="genColHeader" style="display:none;width:36px;"></th>
                            <?php endif; ?>
                            <th>No.</th>
                            <th>Date</th>
                            <th>Certificate No.</th>
                            <th>Machine Code</th>
                            <th>Date of Cal.</th>
                            <th>Calibrated By</th>
                            <th>Instr. Received</th>
                            <th>Cal. Location</th>
                            <th>Reference</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="certTableBody">
                    <?php if (empty($records)): ?>
                        <tr id="emptyRow">
                            <td colspan="100">
                                <div class="cert-empty">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                                    <span class="cert-empty-label"><?= $selectedYear > 0 ? "No certificates for {$selectedYear}" : "No certificates registered yet" ?></span>
                                    <span class="cert-empty-sub"><?= $selectedYear > 0 ? "Try selecting a different year or \"All Years\"." : 'Click "+ Add New" to get started.' ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $i => $r): ?>
                        <tr class="data-row"
                            data-id="<?= $r['id'] ?>"
                            data-date="<?= htmlspecialchars($r['date'] ?? '') ?>"
                            data-certnum="<?= htmlspecialchars($r['certificate_number'] ?? '') ?>"
                            data-machine="<?= htmlspecialchars($r['machine_code'] ?? '', ENT_QUOTES) ?>"
                            data-dateofcal="<?= htmlspecialchars($r['date_of_calibration'] ?? '') ?>"
                            data-calibby="<?= htmlspecialchars($r['calibrated_by'] ?? '', ENT_QUOTES) ?>"
                            data-datercv="<?= htmlspecialchars($r['instrument_date_received'] ?? '') ?>"
                            data-location="<?= htmlspecialchars($r['calibration_location'] ?? '', ENT_QUOTES) ?>"
                            data-reference="<?= htmlspecialchars($r['reference'] ?? '', ENT_QUOTES) ?>"
                            data-remarks="<?= htmlspecialchars($r['remarks'] ?? '', ENT_QUOTES) ?>">
                            <?php if (!$isGuest): ?>
                            <td class="deleteCol" style="display:none;width:36px;text-align:center;" onclick="event.stopPropagation()">
                                <input type="checkbox" class="delete-cb" value="<?= $r['id'] ?>">
                            </td>
                            <td class="genCol" style="display:none;width:36px;text-align:center;" onclick="event.stopPropagation()">
                                <input type="checkbox" class="gen-cb" value="<?= $r['id'] ?>" data-machine="<?= htmlspecialchars($r['machine_code'] ?? '', ENT_QUOTES) ?>">
                            </td>
                            <?php endif; ?>
                            <td class="num-cell"><?= count($records) - $i ?></td>
                            <td class="date-cell"><?= fmtDate($r['date']) ?></td>
                            <td class="certnum"><?= htmlspecialchars($r['certificate_number']) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($r['machine_code']) ?></td>
                            <td class="date-cell"><?= fmtDate($r['date_of_calibration']) ?></td>
                            <td><?= htmlspecialchars($r['calibrated_by']) ?></td>
                            <td class="date-cell"><?= fmtDate($r['instrument_date_received']) ?></td>
                            <td><?= htmlspecialchars($r['calibration_location']) ?></td>
                            <td style="font-size:12px;color:var(--text-3);"><?= htmlspecialchars($r['reference']) ?></td>
                            <td style="font-size:12px;color:var(--text-3);white-space:normal;max-width:160px;line-height:1.4;"><?= nl2br(htmlspecialchars($r['remarks'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination" id="pagination"></div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     DETAIL MODAL
═════════════════════════════════════════════════════════ -->
<div id="detailModal" class="modal-overlay">
    <div class="form-box">
        <div class="form-header">
            <div>
                <h3 id="dm_machine">—</h3>
                <p id="dm_certnum"></p>
            </div>
            <button class="modal-close-btn" id="closeDetailModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="form-body">
            <div class="form-grid">
                <div class="form-field">
                    <div class="form-label">Date</div>
                    <div class="detail-val mono" id="dm_date">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Date of Calibration</div>
                    <div class="detail-val mono" id="dm_dateofcal">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Instrument Received</div>
                    <div class="detail-val mono" id="dm_datercv">—</div>
                </div>
                <div class="form-field wide">
                    <div class="form-label">Machine Code</div>
                    <div class="detail-val bold" id="dm_machine2">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Certificate No.</div>
                    <div class="detail-val mono" id="dm_certnum2">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Calibrated By</div>
                    <div class="detail-val" id="dm_calibby">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Date Received</div>
                    <div class="detail-val mono" id="dm_datercv2">—</div>
                </div>
                <div class="form-field">
                    <div class="form-label">Calibration Location</div>
                    <div class="detail-val" id="dm_location">—</div>
                </div>
                <div class="form-field full">
                    <div class="form-label">Reference</div>
                    <div class="detail-val mono" id="dm_reference" style="white-space:pre-wrap;line-height:1.5;">—</div>
                </div>
                <div class="form-field full">
                    <div class="form-label">Remarks</div>
                    <div class="detail-val" id="dm_remarks" style="white-space:pre-wrap;line-height:1.5;">—</div>
                </div>
            </div>
        </div>
        <div class="form-footer">
            <button class="btn btn-muted" id="closeDetailBtn">Close</button>
            <?php if (!$isGuest): ?>
            <button class="btn btn-danger" id="deleteFromDetailBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Delete
            </button>
            <button class="btn btn-primary" id="editFromDetailBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Record
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD / EDIT MODAL
═════════════════════════════════════════════════════════ -->
<div id="formModal" class="modal-overlay">
    <div class="form-box">
        <div class="form-header">
            <h3 id="formTitle">New Certificate Entry</h3>
            <button class="modal-close-btn" id="closeFormModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="form-body">
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">Date <span class="req">*</span></label>
                    <input type="date" id="cf_date" class="form-input">
                </div>
                <div class="form-field">
                    <label class="form-label">Certificate No.</label>
                    <input type="text" id="cf_certnum" class="form-input mono" readonly tabindex="-1">
                </div>
                <div class="form-field wide">
                    <label class="form-label">Machine Code <span class="req">*</span></label>
                    <div class="mc-wrap">
                        <input type="text" id="cf_machine" class="form-input" autocomplete="off">
                        <div class="mc-dropdown" id="mcDropdown"></div>
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label">Date of Calibration</label>
                    <input type="date" id="cf_dateofcal" class="form-input" readonly>
                </div>
                <div class="form-field">
                    <label class="form-label">Calibrated By <span class="req">*</span></label>
                    <input type="text" id="cf_calibby" class="form-input">
                </div>
                <div class="form-field">
                    <label class="form-label">Instrument Date Received</label>
                    <input type="date" id="cf_datercv" class="form-input">
                </div>
                <div class="form-field">
                    <label class="form-label">Calibration Location</label>
                    <input type="text" id="cf_location" class="form-input">
                </div>
                <div class="form-field full">
                    <label class="form-label">Reference</label>
                    <textarea id="cf_reference" class="form-input form-textarea mono" rows="2" readonly tabindex="-1"></textarea>
                </div>
                <div class="form-field full">
                    <label class="form-label">Remarks</label>
                    <textarea id="cf_remarks" class="form-input form-textarea" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="form-footer">
            <span class="form-error" id="formError"></span>
            <button class="btn btn-muted" id="cancelFormModal">Cancel</button>
            <button class="btn btn-primary" id="saveBtn">
                <span class="form-spinner" id="formSpinner" style="display:none;"></span>
                Save
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     SINGLE DELETE CONFIRM MODAL
═════════════════════════════════════════════════════════ -->
<div id="deleteModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
        </div>
        <div class="confirm-modal-body">
            <h3>Delete this certificate?</h3>
            <p>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="deleteYes">Yes, Delete</button>
            <button class="btn btn-muted" id="deleteNo">Cancel</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     PASSWORD VERIFY MODAL (bulk delete)
═════════════════════════════════════════════════════════ -->
<div id="passwordVerifyModal" class="modal-overlay">
    <div class="confirm-modal-box" style="max-width:420px;">
        <div class="confirm-modal-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <div class="confirm-modal-body">
            <h3>Verify Your Identity</h3>
            <p>Enter your account password to proceed with deletion.</p>
            <div class="confirm-pw-row">
                <label for="deletePasswordInput">Your Password <span style="color:#e53e3e;">*</span></label>
                <div class="confirm-pw-wrap">
                    <input type="password" id="deletePasswordInput" class="confirm-pw-input" placeholder="Enter your password…">
                    <button type="button" class="confirm-pw-eye" id="toggleDeletePwBtn">
                        <svg id="deletePwEyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="confirm-modal-error" id="passwordVerifyError"></div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="confirmPasswordBtn">
                <span class="pw-spinner" id="pwVerifySpinner" style="display:none;"></span>
                Verify &amp; Continue
            </button>
            <button class="btn btn-muted" id="cancelPasswordBtn">Cancel</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     BULK DELETE CONFIRM MODAL
═════════════════════════════════════════════════════════ -->
<div id="bulkDeleteConfirmModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
        </div>
        <div class="confirm-modal-body">
            <h3>Delete selected certificates?</h3>
            <p>You are about to delete <strong id="deleteCountSpan">0</strong> certificate(s).<br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-danger-solid" id="confirmBulkDeleteBtn">Yes, Delete</button>
            <button class="btn btn-muted" id="cancelBulkDeleteBtn">Cancel</button>
        </div>
    </div>
</div>

<form method="POST" action="delete_multiple_certificate.php" id="bulkDeleteForm" style="display:none;">
    <input type="hidden" name="redirect_year" value="<?= $selectedYear ?>">
    <div id="bulkDeleteHiddenInputs"></div>
</form>

<!-- ═══════════════════════════════════════════════════════
     GENERATE CERTIFICATE MODAL
═════════════════════════════════════════════════════════ -->
<div id="genCertModal" class="modal-overlay">
    <div class="gen-cert-box">
        <div class="gen-cert-header">
            <div class="gen-cert-header-left">
                <div class="gen-cert-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </div>
                <div>
                    <h3>Generate Calibration Certificate</h3>
                    <p id="gcModalSubtitle">Fill in details to produce a print-ready A4 certificate</p>
                </div>
            </div>
            <button class="modal-close-btn" id="closeGenCertModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="gen-cert-body">
            <div class="selected-record-banner" id="gcSelectedBanner" style="display:none;">
                <div class="srb-inner">
                    <div class="srb-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                    </div>
                    <div class="srb-info">
                        <span class="srb-machine" id="srb_machine">—</span>
                        <span class="srb-meta" id="srb_meta">—</span>
                    </div>
                    <span class="srb-change" id="gcChangeRecord">✕ Change</span>
                </div>
            </div>

            <div class="not-found-msg" id="gcNotFound">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Machine code not found in calibration records or standard samples.
            </div>
            <div class="not-found-msg" id="gcLoading">Loading machine data…</div>

            <div class="info-preview" id="gcInfoPreview">
                <div class="info-preview-header">Equipment Details — Auto-filled from records
                    <span id="gcDataSourcePill" class="gc-data-source" style="display:none;"></span>
                </div>
                <div class="info-preview-grid">
                    <div class="info-preview-item"><span class="info-preview-label">Type of Calibration</span><span class="info-preview-value" id="gp_caltype">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Equipment Code</span><span class="info-preview-value" id="gp_code">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Description</span><span class="info-preview-value" id="gp_desc">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Model</span><span class="info-preview-value" id="gp_model">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Manufacturer</span><span class="info-preview-value" id="gp_maker">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Serial No.</span><span class="info-preview-value" id="gp_serial">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Date Calibrated</span><span class="info-preview-value" id="gp_datecat">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Calibrated By</span><span class="info-preview-value" id="gp_calibby">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Date Received</span><span class="info-preview-value" id="gp_datercv">—</span></div>
                    <div class="info-preview-item"><span class="info-preview-label">Cal. Location</span><span class="info-preview-value" id="gp_location">—</span></div>
                    <div class="info-preview-item" style="grid-column:1/-1;"><span class="info-preview-label">Reference / Calibration Method</span><span class="info-preview-value" id="gp_ref">—</span></div>
                </div>
            </div>

            <div class="gen-section-label">Certificate Details</div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-field">
                    <label class="form-label">SDP Certificate No. <span class="req">*</span></label>
                    <input type="text" id="gc_certnum" class="form-input mono" placeholder="e.g. 26 089">
                </div>
                <div class="form-field">
                    <label class="form-label">Date of Issue</label>
                    <input type="date" id="gc_issue_date" class="form-input">
                </div>
            </div>

            <div class="gen-section-label">Result of Calibration</div>
            <div class="form-field">
                <label class="form-label">Calibration Result <span class="req">*</span></label>
                <div class="result-row">
                    <label class="result-option"><input type="radio" name="gc_result" value="PASS" checked><span class="result-box"></span><span class="result-label">✓ PASS</span></label>
                    <label class="result-option fail"><input type="radio" name="gc_result" value="FAIL"><span class="result-box"></span><span class="result-label">✗ FAIL</span></label>
                </div>
            </div>

            <div class="gen-section-label">Signatories</div>
            <p class="sig-persist-hint">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Names &amp; titles are remembered for your next visit.
            </p>
            <div class="sig-grid">
                <div class="sig-field"><span class="sig-sub-label">PE Calibration Engineer <span class="req">*</span></span><input type="text" class="sig-name-input" id="gc_engineer_name" value="Edmund Navarro"><input type="text" class="sig-title-input" id="gc_engineer_title" placeholder="Title / Position" value="PE Calibration Engineer"></div>
                <div class="sig-field"><span class="sig-sub-label">PE Section Manager <span class="req">*</span></span><input type="text" class="sig-name-input" id="gc_manager_name" value="Ariel Lacao"><input type="text" class="sig-title-input" id="gc_manager_title" placeholder="Title / Position" value="PE Section Manager"></div>
                <div class="sig-field"><span class="sig-sub-label">Concern Section Manager <span class="req">*</span></span><input type="text" class="sig-name-input" id="gc_concern_name" placeholder="Auto-filled from records" readonly><input type="text" class="sig-title-input" id="gc_concern_title" placeholder="Title / Position" value="Concern Section Manager"></div>
            </div>

            <div class="gen-section-label">Revision History</div>
            <p class="sig-persist-hint">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Revision rows are remembered for your next visit.
            </p>
            <div class="rev-history-table">
                <div class="rev-history-header"><span>Rev.</span><span>Rev. Date</span><span>Revised By</span><span>Nature / Description of Revision</span><span>Appr. (Section Mgr.)</span></div>
                <div class="rev-history-row"><input type="text" class="rev-input" id="rev1code" value="2" placeholder="Rev."><input type="text" class="rev-input" id="rev1date" value="12-Apr-18" placeholder="Date"><input type="text" class="rev-input" id="rev1by" value="E. Navarro" placeholder="By"><input type="text" class="rev-input" id="rev1desc" value="Revised logo to Shindengen Philippines Corp." placeholder="Description"><input type="text" class="rev-input" id="rev1appr1" placeholder="(signature)"></div>
                <div class="rev-history-row"><input type="text" class="rev-input" id="rev2code" value="3" placeholder="Rev."><input type="text" class="rev-input" id="rev2date" value="30-Sep-22" placeholder="Date"><input type="text" class="rev-input" id="rev2by" value="E. Navarro" placeholder="By"><input type="text" class="rev-input" id="rev2desc" value="Revised Calibration Certificate Format due to IATF audit findings." placeholder="Description"><input type="text" class="rev-input" id="rev2appr1" placeholder="(signature)"></div>
            </div>

            <div class="form-error-inline" id="genCertError"></div>
        </div>

        <div class="gen-cert-footer">
            <button class="btn btn-muted" id="cancelGenCert">Cancel</button>
            <button class="btn btn-success" id="printCertBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Preview &amp; Print
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const IS_GUEST = <?= $isGuest ? 'true' : 'false' ?>;
    const ROWS_PER = 15;
    const SIG_KEY  = 'certRegSignatories';
    const REV_KEY  = 'certRegRevisions';

    const tableBody    = document.getElementById('certTableBody');
    const searchInput  = document.getElementById('searchInput');
    const paginationEl = document.getElementById('pagination');
    const addBtn       = document.getElementById('addBtn');

    const detailModal         = document.getElementById('detailModal');
    const closeDetailModal    = document.getElementById('closeDetailModal');
    const closeDetailBtn      = document.getElementById('closeDetailBtn');
    const editFromDetailBtn   = document.getElementById('editFromDetailBtn');
    const deleteFromDetailBtn = document.getElementById('deleteFromDetailBtn');

    const formModal       = document.getElementById('formModal');
    const formTitle       = document.getElementById('formTitle');
    const closeFormModal  = document.getElementById('closeFormModal');
    const cancelFormModal = document.getElementById('cancelFormModal');
    const saveBtn         = document.getElementById('saveBtn');
    const formSpinner     = document.getElementById('formSpinner');
    const formError       = document.getElementById('formError');

    const cf_date      = document.getElementById('cf_date');
    const cf_certnum   = document.getElementById('cf_certnum');
    const cf_machine   = document.getElementById('cf_machine');
    const cf_dateofcal = document.getElementById('cf_dateofcal');
    const cf_reference = document.getElementById('cf_reference');
    const cf_calibby   = document.getElementById('cf_calibby');
    const cf_datercv   = document.getElementById('cf_datercv');
    const cf_location  = document.getElementById('cf_location');
    const cf_remarks   = document.getElementById('cf_remarks');
    const mcDropdown   = document.getElementById('mcDropdown');

    const deleteModal = document.getElementById('deleteModal');
    const deleteYes   = document.getElementById('deleteYes');
    const deleteNo    = document.getElementById('deleteNo');

    const toggleDeleteBtn        = document.getElementById('toggleDeleteBtn');
    const bulkDeleteBar          = document.getElementById('bulkDeleteBar');
    const deleteSelectedBtn      = document.getElementById('deleteSelectedBtn');
    const cancelDeleteBtn        = document.getElementById('cancelDeleteBtn');
    const selectedCountBadge     = document.getElementById('selectedCountBadge');
    const selectAllDelete        = document.getElementById('selectAllDelete');
    const deleteColHeader        = document.getElementById('deleteColHeader');
    const passwordVerifyModal    = document.getElementById('passwordVerifyModal');
    const deletePasswordInput    = document.getElementById('deletePasswordInput');
    const toggleDeletePwBtn      = document.getElementById('toggleDeletePwBtn');
    const confirmPasswordBtn     = document.getElementById('confirmPasswordBtn');
    const cancelPasswordBtn      = document.getElementById('cancelPasswordBtn');
    const pwVerifySpinner        = document.getElementById('pwVerifySpinner');
    const passwordVerifyError    = document.getElementById('passwordVerifyError');
    const bulkDeleteConfirmModal = document.getElementById('bulkDeleteConfirmModal');
    const deleteCountSpan        = document.getElementById('deleteCountSpan');
    const confirmBulkDeleteBtn   = document.getElementById('confirmBulkDeleteBtn');
    const cancelBulkDeleteBtn    = document.getElementById('cancelBulkDeleteBtn');
    const bulkDeleteForm         = document.getElementById('bulkDeleteForm');
    const bulkDeleteHiddenInputs = document.getElementById('bulkDeleteHiddenInputs');

    const genCertBtn        = document.getElementById('genCertBtn');
    const bulkGenBar        = document.getElementById('bulkGenBar');
    const continueGenBtn    = document.getElementById('continueGenBtn');
    const cancelGenBtn      = document.getElementById('cancelGenBtn');
    const genSelectedBadge  = document.getElementById('genSelectedBadge');
    const genColHeader      = document.getElementById('genColHeader');

    const genCertModal      = document.getElementById('genCertModal');
    const closeGenCertModal = document.getElementById('closeGenCertModal');
    const cancelGenCert     = document.getElementById('cancelGenCert');
    const printCertBtn      = document.getElementById('printCertBtn');
    const genCertError      = document.getElementById('genCertError');
    const gcSelectedBanner  = document.getElementById('gcSelectedBanner');
    const srb_machine       = document.getElementById('srb_machine');
    const srb_meta          = document.getElementById('srb_meta');
    const gcChangeRecord    = document.getElementById('gcChangeRecord');
    const gcInfoPreview     = document.getElementById('gcInfoPreview');
    const gcNotFound        = document.getElementById('gcNotFound');
    const gcLoading         = document.getElementById('gcLoading');
    const gc_certnum        = document.getElementById('gc_certnum');
    const gc_issue_date     = document.getElementById('gc_issue_date');
    const gcModalSubtitle   = document.getElementById('gcModalSubtitle');
    const gc_engineer_name  = document.getElementById('gc_engineer_name');
    const gc_engineer_title = document.getElementById('gc_engineer_title');
    const gc_manager_name   = document.getElementById('gc_manager_name');
    const gc_manager_title  = document.getElementById('gc_manager_title');
    const gc_concern_name   = document.getElementById('gc_concern_name');
    const gc_concern_title  = document.getElementById('gc_concern_title');

    const revFields = [
        { code:document.getElementById('rev1code'), date:document.getElementById('rev1date'), by:document.getElementById('rev1by'), desc:document.getElementById('rev1desc'), appr1:document.getElementById('rev1appr1') },
        { code:document.getElementById('rev2code'), date:document.getElementById('rev2date'), by:document.getElementById('rev2by'), desc:document.getElementById('rev2desc'), appr1:document.getElementById('rev2appr1') },
    ];

    let activeRow          = null;
    let editingId          = null;
    let deleteTargetId     = null;
    let searchTimer        = null;
    let currentPage        = 1;
    let filteredRows       = [];
    let currentMachineData = null;
    let genModeActive      = false;
    let deleteModeActive   = false;

    // ── Session: Signatories ──────────────────────────────────────────────────
    const sigInputs = [
        { el:gc_engineer_name,  key:'engineerName'  },
        { el:gc_engineer_title, key:'engineerTitle' },
        { el:gc_manager_name,   key:'managerName'   },
        { el:gc_manager_title,  key:'managerTitle'  },
    ];
    const SIG_DEFAULTS = { engineerName:'Edmund Navarro', engineerTitle:'PE Calibration Engineer', managerName:'Ariel Lacao', managerTitle:'PE Section Manager' };
    function loadSigSession() { sigInputs.forEach(s=>{if(SIG_DEFAULTS[s.key])s.el.value=SIG_DEFAULTS[s.key];}); try{const d=JSON.parse(sessionStorage.getItem(SIG_KEY));if(!d)return;sigInputs.forEach(s=>{if(d[s.key]!==undefined)s.el.value=d[s.key];});}catch(e){} }
    function saveSigSession() { try{const d={};sigInputs.forEach(s=>{d[s.key]=s.el.value;});sessionStorage.setItem(SIG_KEY,JSON.stringify(d));}catch(e){} }

    // ── Session: Revisions ────────────────────────────────────────────────────
    function loadRevSession() { try{const data=JSON.parse(sessionStorage.getItem(REV_KEY));if(!Array.isArray(data))return;data.forEach((d,i)=>{if(!revFields[i])return;const f=revFields[i];if(d.code!==undefined)f.code.value=d.code;if(d.date!==undefined)f.date.value=d.date;if(d.by!==undefined)f.by.value=d.by;if(d.desc!==undefined)f.desc.value=d.desc;if(d.appr1!==undefined)f.appr1.value=d.appr1;});}catch(e){} }
    function saveRevSession() { try{sessionStorage.setItem(REV_KEY,JSON.stringify(revFields.map(f=>({code:f.code.value,date:f.date.value,by:f.by.value,desc:f.desc.value,appr1:f.appr1.value}))));}catch(e){} }
    revFields.forEach(f=>{['code','date','by','desc','appr1'].forEach(k=>f[k].addEventListener('input',saveRevSession));});
    loadSigSession(); loadRevSession();

    // ── Helpers ───────────────────────────────────────────────────────────────
    function todayISO(){const n=new Date();return`${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,'0')}-${String(n.getDate()).padStart(2,'0')}`;}
    function fmtDate(str){if(!str)return'';const d=new Date(str+'T00:00:00');return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'2-digit'});}
    function fmtDateLong(str){if(!str||str==='0000-00-00')return'';const d=new Date(str+'T00:00:00');return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});}
    function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function showErr(msg){formError.textContent=msg;formError.style.display=msg?'inline-block':'none';}
    function showGenErr(msg){genCertError.textContent=msg;genCertError.style.display=msg?'block':'none';}

    // ── Filter + Pagination ───────────────────────────────────────────────────
    function applyFilter(){const q=searchInput.value.toLowerCase();filteredRows=Array.from(tableBody.querySelectorAll('tr.data-row')).filter(r=>r.innerText.toLowerCase().includes(q));currentPage=1;renderPage();}
    function renderPage(){Array.from(tableBody.querySelectorAll('tr.data-row')).forEach(r=>r.style.display='none');const start=(currentPage-1)*ROWS_PER;filteredRows.slice(start,start+ROWS_PER).forEach((r,i)=>{r.style.display='';const nc=r.querySelector('td.num-cell');if(nc)nc.textContent=filteredRows.length-start-i;});renderPagination();}
    function renderPagination(){const total=Math.ceil(filteredRows.length/ROWS_PER)||1;paginationEl.innerHTML='';const prev=document.createElement('button');prev.textContent='← Prev';prev.disabled=currentPage===1;prev.onclick=()=>{currentPage--;renderPage();};const info=document.createElement('span');info.className='pagination-info';info.textContent=`Page ${currentPage} of ${total}`;const next=document.createElement('button');next.textContent='Next →';next.disabled=currentPage===total;next.onclick=()=>{currentPage++;renderPage();};paginationEl.appendChild(prev);paginationEl.appendChild(info);paginationEl.appendChild(next);}
    searchInput.addEventListener('input',applyFilter);

    // ── Detail Modal ──────────────────────────────────────────────────────────
    function openDetail(row){activeRow=row;const d=row.dataset;function setVal(id,content,cls=''){const el=document.getElementById(id);if(!el)return;if(!content){el.className='detail-val empty-val'+(cls?' '+cls:'');el.textContent='—';}else{el.className='detail-val'+(cls?' '+cls:'');el.textContent=content;}}document.getElementById('dm_machine').textContent=d.machine||'—';document.getElementById('dm_certnum').textContent=d.certnum||'';document.getElementById('dm_machine2').textContent=d.machine||'—';document.getElementById('dm_certnum2').textContent=d.certnum||'—';setVal('dm_date',fmtDate(d.date),'mono');setVal('dm_dateofcal',fmtDate(d.dateofcal),'mono');setVal('dm_datercv',fmtDate(d.datercv),'mono');setVal('dm_datercv2',fmtDate(d.datercv),'mono');setVal('dm_calibby',d.calibby);setVal('dm_location',d.location);setVal('dm_reference',d.reference,'mono');setVal('dm_remarks',d.remarks);detailModal.classList.add('open');}
    function closeDetail(){detailModal.classList.remove('open');activeRow=null;}
    closeDetailModal.addEventListener('click',closeDetail);
    closeDetailBtn.addEventListener('click',closeDetail);
    detailModal.addEventListener('click',e=>{if(e.target===detailModal)closeDetail();});
    tableBody.addEventListener('click',e=>{if(genModeActive||deleteModeActive)return;const row=e.target.closest('tr.data-row');if(row)openDetail(row);});
    if(editFromDetailBtn)editFromDetailBtn.addEventListener('click',()=>{const r=activeRow;closeDetail();if(r)openEditForm(r);});
    if(deleteFromDetailBtn)deleteFromDetailBtn.addEventListener('click',()=>{if(!activeRow)return;deleteTargetId=activeRow.dataset.id;closeDetail();deleteModal.classList.add('open');});

    // ── Form ──────────────────────────────────────────────────────────────────
    async function fetchCertNumber(fullYear){try{cf_certnum.value='…';const r=await fetch('certificate_registration.php?action=get_cert_number&year='+fullYear);const d=await r.json();cf_certnum.value=d.certificate_number;}catch(e){cf_certnum.value='';}}
    cf_date.addEventListener('change',()=>{if(editingId)return;const yr=cf_date.value?new Date(cf_date.value+'T00:00:00').getFullYear():null;if(yr)fetchCertNumber(yr);});
    async function openAddForm(){editingId=null;formTitle.textContent='New Certificate Entry';cf_date.value=todayISO();cf_certnum.value='…';cf_machine.value='';cf_dateofcal.value='';cf_reference.value='';cf_calibby.value='';cf_datercv.value='';cf_location.value='';cf_remarks.value='';mcDropdown.style.display='none';showErr('');formModal.classList.add('open');setTimeout(()=>cf_machine.focus(),120);await fetchCertNumber(new Date(cf_date.value+'T00:00:00').getFullYear());}
    function openEditForm(row){editingId=row.dataset.id;formTitle.textContent='Edit Certificate Entry';cf_date.value=row.dataset.date||'';cf_certnum.value=row.dataset.certnum||'';cf_machine.value=row.dataset.machine||'';cf_dateofcal.value=row.dataset.dateofcal||'';cf_reference.value=row.dataset.reference||'';cf_calibby.value=row.dataset.calibby||'';cf_datercv.value=row.dataset.datercv||'';cf_location.value=row.dataset.location||'';cf_remarks.value=row.dataset.remarks||'';mcDropdown.style.display='none';showErr('');formModal.classList.add('open');setTimeout(()=>cf_calibby.focus(),120);}
    function closeForm(){formModal.classList.remove('open');showErr('');}
    if(addBtn)addBtn.addEventListener('click',openAddForm);
    closeFormModal.addEventListener('click',closeForm);
    cancelFormModal.addEventListener('click',closeForm);
    formModal.addEventListener('click',e=>{if(e.target===formModal)closeForm();});

    // ── Autocomplete ──────────────────────────────────────────────────────────
    cf_machine.addEventListener('input',()=>{clearTimeout(searchTimer);const q=cf_machine.value.trim();if(!q){mcDropdown.style.display='none';return;}searchTimer=setTimeout(()=>doSearch(q),220);});
    async function doSearch(q){try{const r=await fetch(`certificate_registration.php?action=search_unified&q=${encodeURIComponent(q)}`);const data=await r.json();renderDropdown(data);}catch(e){mcDropdown.style.display='none';}}
    function renderDropdown(items){mcDropdown.innerHTML='';if(!items.length){mcDropdown.style.display='none';return;}items.forEach(item=>{const div=document.createElement('div');div.className='mc-option';const isSS=item.source==='standard_samples';const badge=`<span class="mc-source-badge ${isSS?'mc-source-ss':'mc-source-cr'}">${isSS?'Std Sample':'Cal Report'}</span>`;div.innerHTML=`<span class="mc-option-code">${esc(item.machine_code)}</span>${badge}`;div.addEventListener('mousedown',e=>{e.preventDefault();cf_machine.value=item.machine_code;if(item.calibration_date&&item.calibration_date!=='0000-00-00')cf_dateofcal.value=item.calibration_date;cf_reference.value=item.reference||'';mcDropdown.style.display='none';});mcDropdown.appendChild(div);});mcDropdown.style.display='block';}
    document.addEventListener('click',e=>{if(!e.target.closest('.mc-wrap'))mcDropdown.style.display='none';});
    cf_machine.addEventListener('keydown',e=>{const opts=[...mcDropdown.querySelectorAll('.mc-option')];const active=mcDropdown.querySelector('.mc-option.active');let idx=opts.indexOf(active);if(!opts.length||mcDropdown.style.display==='none')return;if(e.key==='ArrowDown'){e.preventDefault();active?.classList.remove('active');opts[Math.min(idx+1,opts.length-1)].classList.add('active');}else if(e.key==='ArrowUp'){e.preventDefault();active?.classList.remove('active');opts[Math.max(idx-1,0)].classList.add('active');}else if(e.key==='Enter'){e.preventDefault();mcDropdown.querySelector('.mc-option.active')?.dispatchEvent(new MouseEvent('mousedown'));}else if(e.key==='Escape'){mcDropdown.style.display='none';}});

    // ── Save ──────────────────────────────────────────────────────────────────
    saveBtn.addEventListener('click',async()=>{showErr('');if(!cf_date.value){showErr('Date is required.');cf_date.focus();return;}if(!cf_machine.value.trim()){showErr('Machine code is required.');cf_machine.focus();return;}if(!cf_calibby.value.trim()){showErr('Calibrated By is required.');cf_calibby.focus();return;}saveBtn.disabled=true;formSpinner.style.display='inline-block';try{const fd=new FormData();fd.append('action','save_certificate');fd.append('id',editingId||'');fd.append('date',cf_date.value);fd.append('certificate_number',cf_certnum.value);fd.append('machine_code',cf_machine.value.trim());fd.append('date_of_calibration',cf_dateofcal.value);fd.append('calibrated_by',cf_calibby.value.trim());fd.append('instrument_date_received',cf_datercv.value);fd.append('calibration_location',cf_location.value.trim());fd.append('reference',cf_reference.value.trim());fd.append('remarks',cf_remarks.value.trim());const res=await fetch('certificate_registration.php',{method:'POST',body:fd});const data=await res.json();if(data.success){editingId?updateRow(data.row):prependRow(data.row);closeForm();}else showErr(data.message||'Failed to save.');}catch(e){showErr('Network error. Please try again.');}finally{saveBtn.disabled=false;formSpinner.style.display='none';}});

    // ── Build rows ────────────────────────────────────────────────────────────
    function buildRow(r){const tr=document.createElement('tr');tr.className='data-row';tr.dataset.id=r.id;tr.dataset.date=r.date||'';tr.dataset.certnum=r.certificate_number||'';tr.dataset.machine=r.machine_code||'';tr.dataset.dateofcal=r.date_of_calibration||'';tr.dataset.calibby=r.calibrated_by||'';tr.dataset.datercv=r.instrument_date_received||'';tr.dataset.location=r.calibration_location||'';tr.dataset.reference=r.reference||'';tr.dataset.remarks=r.remarks||'';const deleteCell=IS_GUEST?'':`<td class="deleteCol" style="display:${deleteModeActive?'':'none'};width:36px;text-align:center;" onclick="event.stopPropagation()"><input type="checkbox" class="delete-cb" value="${r.id}"></td>`;const genCell=IS_GUEST?'':`<td class="genCol" style="display:${genModeActive?'':'none'};width:36px;text-align:center;" onclick="event.stopPropagation()"><input type="checkbox" class="gen-cb" value="${r.id}" data-machine="${esc(r.machine_code||'')}"></td>`;tr.innerHTML=`${deleteCell}${genCell}<td class="num-cell">—</td><td class="date-cell">${esc(fmtDate(r.date))}</td><td class="certnum">${esc(r.certificate_number)}</td><td style="font-weight:600;">${esc(r.machine_code)}</td><td class="date-cell">${esc(fmtDate(r.date_of_calibration))}</td><td>${esc(r.calibrated_by)}</td><td class="date-cell">${esc(fmtDate(r.instrument_date_received))}</td><td>${esc(r.calibration_location)}</td><td style="font-size:12px;color:var(--text-3);">${esc(r.reference)}</td><td style="font-size:12px;color:var(--text-3);white-space:normal;max-width:160px;line-height:1.4;">${esc(r.remarks).replace(/\n/g,'<br>')}</td>`;return tr;}
    function prependRow(r){const emptyRow=document.getElementById('emptyRow');if(emptyRow)emptyRow.remove();const tr=buildRow(r);tr.classList.add('row-new');tableBody.insertBefore(tr,tableBody.firstChild);setTimeout(()=>tr.classList.remove('row-new'),1200);applyFilter();}
    function updateRow(r){const existing=tableBody.querySelector(`tr[data-id="${r.id}"]`);if(!existing)return;const tr=buildRow(r);tr.classList.add('row-new');existing.replaceWith(tr);setTimeout(()=>tr.classList.remove('row-new'),1200);applyFilter();}

    // ── Single Delete ─────────────────────────────────────────────────────────
    deleteNo.addEventListener('click',()=>{deleteModal.classList.remove('open');deleteTargetId=null;});
    deleteModal.addEventListener('click',e=>{if(e.target===deleteModal){deleteModal.classList.remove('open');deleteTargetId=null;}});
    deleteYes.addEventListener('click',async()=>{if(!deleteTargetId)return;deleteModal.classList.remove('open');try{const fd=new FormData();fd.append('action','delete_certificate');fd.append('id',deleteTargetId);const res=await fetch('certificate_registration.php',{method:'POST',body:fd});const data=await res.json();if(data.success){const row=tableBody.querySelector(`tr[data-id="${deleteTargetId}"]`);if(row){row.remove();if(!tableBody.querySelector('tr.data-row')){tableBody.innerHTML=`<tr id="emptyRow"><td colspan="100"><div class="cert-empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg><span class="cert-empty-label">No certificates for this year</span><span class="cert-empty-sub">Try a different year or click "+ Add New".</span></div></td></tr>`;}applyFilter();}}}catch(e){}deleteTargetId=null;});

    // ── Bulk Delete ───────────────────────────────────────────────────────────
    function getCheckedDeleteIds(){return[...document.querySelectorAll('.delete-cb:checked')].map(cb=>cb.value);}
    function updateDeleteBadge(){const count=getCheckedDeleteIds().length;selectedCountBadge.textContent=`${count} selected`;deleteSelectedBtn.disabled=count===0;if(selectAllDelete){const all=document.querySelectorAll('.delete-cb');const chk=document.querySelectorAll('.delete-cb:checked');selectAllDelete.checked=all.length>0&&chk.length===all.length;selectAllDelete.indeterminate=chk.length>0&&chk.length<all.length;}}
    function enterDeleteMode(){deleteModeActive=true;bulkDeleteBar.classList.add('visible');if(deleteColHeader)deleteColHeader.style.display='';document.querySelectorAll('.deleteCol').forEach(c=>c.style.display='');updateDeleteBadge();if(genModeActive)exitGenMode();}
    function exitDeleteMode(){deleteModeActive=false;bulkDeleteBar.classList.remove('visible');if(deleteColHeader)deleteColHeader.style.display='none';document.querySelectorAll('.deleteCol').forEach(c=>c.style.display='none');document.querySelectorAll('.delete-cb').forEach(cb=>cb.checked=false);if(selectAllDelete){selectAllDelete.checked=false;selectAllDelete.indeterminate=false;}updateDeleteBadge();}
    if(toggleDeleteBtn)toggleDeleteBtn.addEventListener('click',()=>{deleteModeActive?exitDeleteMode():enterDeleteMode();});
    if(cancelDeleteBtn)cancelDeleteBtn.addEventListener('click',exitDeleteMode);
    if(selectAllDelete){selectAllDelete.addEventListener('change',()=>{document.querySelectorAll('.delete-cb').forEach(cb=>cb.checked=selectAllDelete.checked);updateDeleteBadge();});}
    tableBody.addEventListener('change',e=>{if(e.target.matches('.delete-cb'))updateDeleteBadge();});
    if(deleteSelectedBtn){deleteSelectedBtn.addEventListener('click',()=>{if(!getCheckedDeleteIds().length)return;deletePasswordInput.value='';deletePasswordInput.classList.remove('error-input');passwordVerifyError.textContent='';passwordVerifyError.classList.remove('show');passwordVerifyModal.classList.add('open');setTimeout(()=>deletePasswordInput.focus(),120);});}
    if(toggleDeletePwBtn){toggleDeletePwBtn.addEventListener('click',()=>{deletePasswordInput.type=deletePasswordInput.type==='password'?'text':'password';});}
    if(cancelPasswordBtn)cancelPasswordBtn.addEventListener('click',()=>passwordVerifyModal.classList.remove('open'));
    passwordVerifyModal.addEventListener('click',e=>{if(e.target===passwordVerifyModal)passwordVerifyModal.classList.remove('open');});
    if(confirmPasswordBtn){confirmPasswordBtn.addEventListener('click',async()=>{const pw=deletePasswordInput.value.trim();if(!pw){deletePasswordInput.classList.add('error-input');passwordVerifyError.textContent='Please enter your password.';passwordVerifyError.classList.add('show');deletePasswordInput.focus();return;}confirmPasswordBtn.disabled=true;pwVerifySpinner.style.display='inline-block';passwordVerifyError.classList.remove('show');deletePasswordInput.classList.remove('error-input');try{const fd=new FormData();fd.append('action','verify_delete_password');fd.append('password',pw);const res=await fetch('certificate_registration.php',{method:'POST',body:fd});const data=await res.json();if(data.success){passwordVerifyModal.classList.remove('open');deleteCountSpan.textContent=getCheckedDeleteIds().length;bulkDeleteConfirmModal.classList.add('open');}else{deletePasswordInput.classList.add('error-input');passwordVerifyError.textContent=data.message||'Incorrect password.';passwordVerifyError.classList.add('show');deletePasswordInput.focus();}}catch(e){passwordVerifyError.textContent='Network error. Please try again.';passwordVerifyError.classList.add('show');}finally{confirmPasswordBtn.disabled=false;pwVerifySpinner.style.display='none';}});}
    if(deletePasswordInput)deletePasswordInput.addEventListener('keydown',e=>{if(e.key==='Enter')confirmPasswordBtn.click();});
    if(cancelBulkDeleteBtn)cancelBulkDeleteBtn.addEventListener('click',()=>bulkDeleteConfirmModal.classList.remove('open'));
    bulkDeleteConfirmModal.addEventListener('click',e=>{if(e.target===bulkDeleteConfirmModal)bulkDeleteConfirmModal.classList.remove('open');});
    if(confirmBulkDeleteBtn){confirmBulkDeleteBtn.addEventListener('click',()=>{const ids=getCheckedDeleteIds();if(!ids.length)return;bulkDeleteHiddenInputs.innerHTML='';ids.forEach(id=>{const inp=document.createElement('input');inp.type='hidden';inp.name='selected_ids[]';inp.value=id;bulkDeleteHiddenInputs.appendChild(inp);});bulkDeleteForm.submit();});}

    // ── Generate Certificate — Selection Mode ─────────────────────────────────
    function enterGenMode(){genModeActive=true;bulkGenBar.classList.add('visible');if(genColHeader)genColHeader.style.display='';document.querySelectorAll('.genCol').forEach(c=>c.style.display='');updateGenBadge();if(deleteModeActive)exitDeleteMode();}
    function exitGenMode(){genModeActive=false;bulkGenBar.classList.remove('visible');if(genColHeader)genColHeader.style.display='none';document.querySelectorAll('.genCol').forEach(c=>c.style.display='none');document.querySelectorAll('.gen-cb').forEach(cb=>cb.checked=false);updateGenBadge();}
    function updateGenBadge(){const count=document.querySelectorAll('.gen-cb:checked').length;genSelectedBadge.textContent=`${count} selected`;continueGenBtn.disabled=count!==1;}
    tableBody.addEventListener('change',e=>{if(!e.target.matches('.gen-cb'))return;if(e.target.checked)document.querySelectorAll('.gen-cb').forEach(cb=>{if(cb!==e.target)cb.checked=false;});updateGenBadge();});
    if(genCertBtn)genCertBtn.addEventListener('click',enterGenMode);
    cancelGenBtn.addEventListener('click',exitGenMode);
    continueGenBtn.addEventListener('click',async()=>{const checked=document.querySelector('.gen-cb:checked');if(!checked)return;const certId=checked.value,machineCode=checked.dataset.machine;const selectedRow=tableBody.querySelector(`tr[data-id="${certId}"]`);exitGenMode();await openGenCertModal(certId,machineCode,selectedRow);});

    // ── Gen Cert Modal ────────────────────────────────────────────────────────
    async function openGenCertModal(certId,machineCode,selectedRow){gc_certnum.value='';gc_issue_date.value=todayISO();document.querySelector('input[name="gc_result"][value="PASS"]').checked=true;gcInfoPreview.classList.remove('show');gcNotFound.style.display='none';gcLoading.style.display='none';showGenErr('');loadSigSession();loadRevSession();if(selectedRow&&selectedRow.dataset.certnum)gc_certnum.value=selectedRow.dataset.certnum;if(selectedRow){srb_machine.textContent=machineCode||'—';srb_meta.textContent=`Cert No: ${selectedRow.dataset.certnum||'—'}  •  Date: ${selectedRow.dataset.date?fmtDate(selectedRow.dataset.date):'—'}`;gcSelectedBanner.style.display='block';}gcModalSubtitle.textContent=`Generating for: ${machineCode}`;genCertModal.classList.add('open');gcLoading.style.display='block';try{const r=await fetch(`certificate_registration.php?action=get_machine_info&machine_code=${encodeURIComponent(machineCode)}&cert_id=${encodeURIComponent(certId)}`);const d=await r.json();gcLoading.style.display='none';if(!d.success){gcNotFound.style.display='block';return;}currentMachineData=d;const rep=d.report||{},cert=d.cert||{};// ── Show data source pill ──────────────────────────────────────
        const srcPill=document.getElementById('gcDataSourcePill');if(srcPill){const isSS=d.data_source==='standard_samples';srcPill.textContent=isSS?'📋 From Standard Samples':'📑 From Calibration Report';srcPill.className='gc-data-source '+(isSS?'from-ss':'from-cr');srcPill.style.display='inline-flex';}const rawCalType=(rep.cal_type||'').trim();const fullCalType=rawCalType==='Internal'?'Internal Calibration':rawCalType==='External'?'External Calibration':rawCalType||'—';setPreview('gp_caltype',fullCalType);setPreview('gp_code',rep.machine_code||'—');setPreview('gp_desc',rep.description||'—');setPreview('gp_model',rep.model||'—');setPreview('gp_maker',rep.maker||'—');setPreview('gp_serial',rep.serial_number||rep.serial_no||'—');const calDate=cert.date_of_calibration||rep.calibration_date||'';setPreview('gp_datecat',calDate?fmtDateLong(calDate):'—');setPreview('gp_calibby',cert.calibrated_by||rep.calibrator||'—');setPreview('gp_datercv',cert.instrument_date_received?fmtDateLong(cert.instrument_date_received):'—');setPreview('gp_location',cert.calibration_location||'—');setPreview('gp_ref',cert.reference||rep.reference||'—');if(rep.manager)gc_concern_name.value=rep.manager;gcInfoPreview.classList.add('show');}catch(e){gcLoading.style.display='none';gcNotFound.style.display='block';}}
    function closeGenCert(){genCertModal.classList.remove('open');currentMachineData=null;gcSelectedBanner.style.display='none';showGenErr('');}
    closeGenCertModal.addEventListener('click',closeGenCert);
    cancelGenCert.addEventListener('click',closeGenCert);
    genCertModal.addEventListener('click',e=>{if(e.target===genCertModal)closeGenCert();});
    gcChangeRecord.addEventListener('click',()=>{closeGenCert();enterGenMode();});
    function setPreview(id,val){const el=document.getElementById(id);if(!el)return;el.textContent=val;if(!val||val==='—')el.classList.add('empty');else el.classList.remove('empty');}

    printCertBtn.addEventListener('click',()=>{showGenErr('');if(!currentMachineData){showGenErr('Machine data not loaded. Please try again.');return;}if(!gc_engineer_name.value.trim()){showGenErr('PE Calibration Engineer name is required.');gc_engineer_name.focus();return;}if(!gc_manager_name.value.trim()){showGenErr('PE Section Manager name is required.');gc_manager_name.focus();return;}const result=document.querySelector('input[name="gc_result"]:checked')?.value||'PASS';const revisions=revFields.map(f=>({code:f.code.value.trim(),date:f.date.value.trim(),by:f.by.value.trim(),desc:f.desc.value.trim(),appr1:f.appr1.value.trim()}));const params=new URLSearchParams({machine_code:currentMachineData.report?.machine_code||'',cert_number:gc_certnum.value.trim(),issue_date:gc_issue_date.value||'',result,engineer_name:gc_engineer_name.value.trim(),engineer_title:gc_engineer_title.value.trim()||'PE Calibration Engineer',manager_name:gc_manager_name.value.trim(),manager_title:gc_manager_title.value.trim()||'PE Section Manager',concern_name:gc_concern_name.value.trim(),concern_title:gc_concern_title.value.trim()||'Concern Section Manager',revisions:JSON.stringify(revisions)});window.open('certificate_print.php?'+params.toString(),'_blank');});

    applyFilter();
})();
</script>
</body>
</html>