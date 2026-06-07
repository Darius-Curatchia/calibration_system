<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

include 'db.php';
require_once 'audit_helper.php';

$currentPage   = isset($_GET['page']) ? intval($_GET['page']) : 1;
$fromDashboard = ($_GET['from'] ?? '') === 'dashboard';
$cancelUrl     = $fromDashboard ? 'dashboard.php' : 'standard_samples.php';

if (!isset($_GET['id'])) {
    header("Location: standard_samples.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) die("Record not found.");

$display_item_no = 0;
$counter = 0;
$list = $pdo->query("SELECT id FROM standard_samples ORDER BY id ASC");
while ($r = $list->fetch()) {
    $counter++;
    if ($r['id'] == $id) { $display_item_no = $counter; break; }
}

$calibrators_arr = [];
$stmt2 = $pdo->query("SELECT DISTINCT calibrator FROM standard_samples WHERE calibrator IS NOT NULL AND calibrator != '' ORDER BY calibrator ASC");
foreach ($stmt2->fetchAll() as $r2) { $calibrators_arr[] = $r2['calibrator']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isGuest) {
    $description           = $_POST['description'];
    $equipment_code        = $_POST['equipment_code'];
    $model_maker           = $_POST['model_maker'];
    $serial_no             = $_POST['serial_no'];
    $location              = $_POST['location'];
    $calibration_date      = $_POST['calibration_date'];
    $next_calibration_date = $_POST['next_calibration_date'];
    $calibration_frequency = $_POST['calibration_frequency'];
    $calibrator            = trim($_POST['calibrator']);
    $present_status        = $_POST['present_status'];

    try {
        $update = $pdo->prepare("
            UPDATE standard_samples SET
                description=:description, equipment_code=:equipment_code,
                model_maker=:model_maker, serial_no=:serial_no, location=:location,
                calibration_date=:calibration_date,
                next_calibration_date=:next_calibration_date,
                calibration_frequency=:calibration_frequency,
                calibrator=:calibrator, present_status=:present_status
            WHERE id=:id
        ");
        $update->execute([
            ':description'           => $description,
            ':equipment_code'        => $equipment_code,
            ':model_maker'           => $model_maker,
            ':serial_no'             => $serial_no,
            ':location'              => $location,
            ':calibration_date'      => $calibration_date,
            ':next_calibration_date' => $next_calibration_date,
            ':calibration_frequency' => $calibration_frequency,
            ':calibrator'            => $calibrator,
            ':present_status'        => $present_status,
            ':id'                    => $id,
        ]);
        log_audit($pdo, 'UPDATE', 'standard_samples', $id,
            "Updated: {$description} | Code: {$equipment_code} | Calibrator: {$calibrator} | Status: {$present_status}");
    } catch (PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }

    header("Location: " . $cancelUrl);
    exit();
}

$freq_options   = ['6' => '6 months', '12' => '1 year', '24' => '2 years', '60' => '5 years'];
$originalStatus = $row['present_status'] ?? 'Good';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Standard Sample — Calibration Management</title>
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
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.card-header-left h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header-left p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.item-badge{display:inline-flex;align-items:center;gap:5px;background:var(--accent-soft);color:var(--accent);border:1px solid rgba(26,144,217,0.20);border-radius:20px;padding:4px 13px;font-size:12px;font-weight:700;font-family:var(--mono);white-space:nowrap;}
.card-body{padding:20px 24px;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn svg{flex-shrink:0;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-danger{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.25);}
.btn-danger:hover{background:#b91c1c;}
.btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,0.25);}
.btn-success:hover{background:#15803d;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

.alert-banner{display:flex;align-items:center;gap:9px;border-radius:var(--r-sm);padding:10px 14px;font-size:12.5px;font-weight:600;margin-bottom:20px;}
.alert-banner svg{flex-shrink:0;width:15px;height:15px;}
.alert-guest{background:#fef9ec;border:1px solid #fcd34d;color:#92400e;}

.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px 20px;}
@media(max-width:960px){.form-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;}.card-body{padding:16px;}}

.section-divider{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--border);margin-top:4px;}
.section-divider svg{color:var(--text-3);flex-shrink:0;}
.section-divider-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--navy);}

.form-group{display:flex;flex-direction:column;gap:6px;position:relative;}
.form-group.span-2{grid-column:span 2;}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:4px;}
.required-star{color:#e53e3e;}
.autofill-pill{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--accent);background:var(--accent-soft);border:1px solid rgba(26,144,217,0.20);border-radius:4px;padding:1px 5px;margin-left:2px;}

.form-group input,
.form-group select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group input:focus,
.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder{color:var(--text-3);font-weight:400;}
.form-group input[readonly],
.form-group select[disabled]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value-blue{background:var(--accent-soft);color:var(--accent);font-family:var(--mono);font-size:12px;border-color:rgba(26,144,217,0.25);cursor:default;font-weight:700;}
.form-group input.auto-value-green{background:#f0fdf4;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;}
.form-group input.mono-input,
.form-group input[type="date"]{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}

.suggestions-box{position:absolute;top:calc(100% + 3px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border-mid);border-radius:var(--r-sm);box-shadow:var(--shadow-lg);max-height:180px;overflow-y:auto;z-index:500;display:none;}
.suggestions-box div{padding:8px 12px;font-size:12.5px;font-weight:500;cursor:pointer;color:var(--text);border-bottom:1px solid var(--border);}
.suggestions-box div:last-child{border-bottom:none;}
.suggestions-box div:hover,.suggestions-box div.selected{background:var(--accent-soft);color:var(--accent);}

.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:14px;margin-top:4px;border-top:1px solid var(--border);flex-wrap:wrap;}

/* ── Modal overlay ── */
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,0.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;color:var(--text-2);}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}

/* ── Delete confirm modal ── */
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:380px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;text-align:center;}
.confirm-modal-icon{padding:26px 0 6px;display:flex;justify-content:center;}
.confirm-modal-icon svg{color:#dc2626;}
.confirm-modal-body{padding:0 28px 20px;}
.confirm-modal-body h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-body p{font-size:13px;color:var(--text-2);margin:0;line-height:1.55;}
.confirm-modal-footer{display:flex;gap:10px;justify-content:center;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}

/* ── Exclusion modal ── */
.excl-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:460px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.excl-modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 16px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#fff 100%);}
.excl-modal-header h3{font-size:14.5px;font-weight:700;color:var(--navy);margin:0;}
.excl-modal-body{padding:20px 22px;display:flex;flex-direction:column;gap:14px;}
.excl-form-grid{display:flex;flex-direction:column;gap:14px;}
.excl-fg{display:flex;flex-direction:column;gap:6px;}
.excl-fg label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);}
.excl-fg input,.excl-fg textarea{padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.excl-fg input{height:34px;}
.excl-fg textarea{padding:9px 12px;resize:vertical;min-height:72px;}
.excl-fg input:focus,.excl-fg textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.excl-fg input[readonly]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}
.excl-fg input::placeholder,.excl-fg textarea::placeholder{color:var(--text-3);font-weight:400;}
.excl-banner{border-radius:var(--r-sm);padding:11px 14px;font-size:12.5px;line-height:1.55;}
.excl-banner-amber{background:#fef9ec;border:1px solid #fcd34d;color:#92400e;}
.excl-banner-green{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
.excl-error{display:none;background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:#be123c;font-weight:600;}
.excl-modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border);background:var(--bg-raised);}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h2>Edit Standard Sample</h2>
                <p><?= htmlspecialchars($row['equipment_code']) ?> — <?= htmlspecialchars($row['description']) ?></p>
            </div>
            <span class="item-badge"># <?= $display_item_no ?></span>
        </div>

        <div class="card-body">

            <?php if ($isGuest): ?>
            <div class="alert-banner alert-guest">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                You are viewing this record as a guest. Changes cannot be saved.
            </div>
            <?php endif; ?>

            <form method="POST" id="editSampleForm">
                <div class="form-grid">

                    <!-- ── Identification ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                        <span class="section-divider-label">Identification</span>
                    </div>

                    <div class="form-group">
                        <label>Item No.</label>
                        <input type="text" value="<?= $display_item_no ?>" class="auto-value-blue" readonly>
                    </div>

                    <div class="form-group span-2">
                        <label>Description <span class="required-star">*</span></label>
                        <input type="text" name="description" value="<?= htmlspecialchars($row['description']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Equipment Code <span class="required-star">*</span></label>
                        <input type="text" name="equipment_code" class="mono-input" value="<?= htmlspecialchars($row['equipment_code']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Model / Maker <span class="required-star">*</span></label>
                        <input type="text" name="model_maker" value="<?= htmlspecialchars($row['model_maker']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Serial No. / Sub Parts <span class="required-star">*</span></label>
                        <input type="text" name="serial_no" class="mono-input" value="<?= htmlspecialchars($row['serial_no']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Location <span class="required-star">*</span></label>
                        <input type="text" name="location" value="<?= htmlspecialchars($row['location']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <!-- ── Calibration Details ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                        <span class="section-divider-label">Calibration Details</span>
                    </div>

                    <div class="form-group">
                        <label>Calibration Date <span class="required-star">*</span></label>
                        <input type="date" name="calibration_date" id="calibration_date" value="<?= $row['calibration_date'] ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Next Calibration <span class="autofill-pill">auto</span></label>
                        <input type="date" name="next_calibration_date" id="next_calibration_date" value="<?= $row['next_calibration_date'] ?>" class="auto-value-green" readonly>
                    </div>

                    <div class="form-group">
                        <label>Calibration Frequency <span class="required-star">*</span></label>
                        <select name="calibration_frequency" id="calibration_frequency" onchange="updateNextCalibration()" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="">Select frequency</option>
                            <?php foreach ($freq_options as $num => $label): ?>
                            <option value="<?= $num ?>" <?= $row['calibration_frequency'] == $num ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Calibrator <span class="required-star">*</span></label>
                        <input type="text" name="calibrator" id="calibrator_input" value="<?= htmlspecialchars($row['calibrator']) ?>" placeholder="Type calibrator…" autocomplete="off" <?= $isGuest ? 'readonly' : '' ?>>
                        <div id="calibrator_suggestions" class="suggestions-box"></div>
                    </div>

                    <div class="form-group">
                        <label>Present Status</label>
                        <select name="present_status" id="present_status" <?= $isGuest ? 'disabled' : '' ?>>
                            <option value="Good"               <?= $row['present_status'] === 'Good'               ? 'selected' : '' ?>>Good</option>
                            <option value="Not Good"           <?= $row['present_status'] === 'Not Good'           ? 'selected' : '' ?>>Not Good</option>
                            <option value="For Disposal"       <?= $row['present_status'] === 'For Disposal'       ? 'selected' : '' ?>>For Disposal</option>
                            <option value="Not In Use"         <?= $row['present_status'] === 'Not In Use'         ? 'selected' : '' ?>>Not In Use</option>
                            <option value="Not Yet Calibrated" <?= $row['present_status'] === 'Not Yet Calibrated' ? 'selected' : '' ?>>Not Yet Calibrated</option>
                        </select>
                    </div>

                    <!-- ── Actions ── -->
                    <div class="form-actions">
                        <a href="<?= $cancelUrl ?>" class="btn btn-muted">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            <?= $isGuest ? 'Back' : 'Cancel' ?>
                        </a>
                        <?php if (!$isGuest): ?>
                        <button type="button" class="btn btn-danger" id="deleteBtn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                            Delete
                        </button>
                        <button type="submit" class="btn btn-primary" id="updateBtn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Update Record
                        </button>
                        <?php endif; ?>
                    </div>

                </div><!-- /.form-grid -->
            </form>
        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div><!-- /.main-content -->

<!-- ── DELETE CONFIRM MODAL ── -->
<div id="deleteOverlay" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
        </div>
        <div class="confirm-modal-body">
            <h3>Delete this record?</h3>
            <p><strong><?= htmlspecialchars($row['equipment_code']) ?></strong> — <?= htmlspecialchars($row['description']) ?><br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <a href="delete_standard_sample.php?id=<?= $id ?>&page=<?= $currentPage ?><?= $fromDashboard ? '&from=dashboard' : '' ?>" class="btn btn-danger">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Yes, Delete
            </a>
            <button type="button" class="btn btn-muted" id="cancelDeleteBtn">Cancel</button>
        </div>
    </div>
</div>

<!-- ── EXCLUSION / INCLUSION MODAL ── -->
<div id="exclusionOverlay" class="modal-overlay" style="z-index:1010;">
    <div class="excl-modal-box">
        <div class="excl-modal-header">
            <h3 id="exclModalTitle">Equipment Status Changed</h3>
            <button class="modal-close-btn" id="exclModalClose">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="excl-modal-body">
            <div id="exclBanner" class="excl-banner excl-banner-amber"></div>
            <div class="excl-form-grid">
                <div class="excl-fg">
                    <label>Equipment</label>
                    <input type="text" id="excl_equipment_display" readonly>
                </div>
                <div class="excl-fg">
                    <label>New Status</label>
                    <input type="text" id="excl_status_display" readonly>
                </div>
                <div class="excl-fg">
                    <label>Remarks <span style="color:#e53e3e;">*</span></label>
                    <textarea id="excl_remarks" rows="3" placeholder="Reason for status change, condition details, etc."></textarea>
                </div>
                <div class="excl-fg">
                    <label>Recorded By <span style="color:#e53e3e;">*</span></label>
                    <input type="text" id="excl_recorded_by" placeholder="Enter your name">
                </div>
                <div class="excl-error" id="excl_error"></div>
            </div>
        </div>
        <div class="excl-modal-footer">
            <button type="button" class="btn btn-muted" id="exclCancelBtn">Cancel</button>
            <button type="button" class="btn btn-primary" id="exclConfirmBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Confirm &amp; Save
            </button>
        </div>
    </div>
</div>

<script>
const ORIGINAL_STATUS = <?= json_encode($originalStatus) ?>;
const RECORD_ID       = <?= $id ?>;
const CANCEL_URL      = <?= json_encode($cancelUrl) ?>;

function updateNextCalibration() {
    const freq = document.getElementById('calibration_frequency').value;
    const calDateInput = document.getElementById('calibration_date');
    const nextCalInput = document.getElementById('next_calibration_date');
    if (!calDateInput.value || !freq) { nextCalInput.value = ''; return; }
    let calDate = new Date(calDateInput.value);
    if      (freq == '6')  calDate.setMonth(calDate.getMonth() + 6);
    else if (freq == '12') calDate.setFullYear(calDate.getFullYear() + 1);
    else if (freq == '24') calDate.setFullYear(calDate.getFullYear() + 2);
    else if (freq == '60') calDate.setFullYear(calDate.getFullYear() + 5);
    nextCalInput.value = `${calDate.getFullYear()}-${String(calDate.getMonth()+1).padStart(2,'0')}-${String(calDate.getDate()).padStart(2,'0')}`;
}
document.getElementById('calibration_date').addEventListener('change', updateNextCalibration);
document.getElementById('calibration_frequency').addEventListener('change', updateNextCalibration);

/* ── Calibrator autocomplete ── */
const calibrators    = <?= json_encode($calibrators_arr) ?>;
const calInput       = document.getElementById('calibrator_input');
const suggestionsBox = document.getElementById('calibrator_suggestions');
let selectedIndex    = -1;

function showSuggestions() {
    const value = calInput.value.toLowerCase();
    suggestionsBox.innerHTML = ''; selectedIndex = -1;
    if (!value) { suggestionsBox.style.display = 'none'; return; }
    const matches = calibrators.filter(c => c.toLowerCase().includes(value));
    if (!matches.length) { suggestionsBox.style.display = 'none'; return; }
    matches.forEach(match => {
        const div = document.createElement('div');
        div.textContent = match;
        div.addEventListener('click', () => { calInput.value = match; suggestionsBox.style.display = 'none'; });
        suggestionsBox.appendChild(div);
    });
    suggestionsBox.style.display = 'block';
}
calInput.addEventListener('input', showSuggestions);
calInput.addEventListener('keydown', e => {
    const items = suggestionsBox.querySelectorAll('div');
    if (!items.length) return;
    if (e.key === 'ArrowDown')  { selectedIndex = (selectedIndex + 1) % items.length; updateSel(items); e.preventDefault(); }
    else if (e.key === 'ArrowUp')   { selectedIndex = (selectedIndex - 1 + items.length) % items.length; updateSel(items); e.preventDefault(); }
    else if (e.key === 'Enter' && selectedIndex >= 0) { calInput.value = items[selectedIndex].textContent; suggestionsBox.style.display = 'none'; e.preventDefault(); }
});
function updateSel(items) { items.forEach(i => i.classList.remove('selected')); if (selectedIndex >= 0) items[selectedIndex].classList.add('selected'); }
document.addEventListener('click', e => { if (e.target !== calInput) suggestionsBox.style.display = 'none'; });

/* ── Modal open/close ── */
document.getElementById('deleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.add('open'));
document.getElementById('cancelDeleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.remove('open'));

['deleteOverlay', 'exclusionOverlay'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
document.getElementById('exclModalClose').addEventListener('click', () => document.getElementById('exclusionOverlay').classList.remove('open'));
document.getElementById('exclCancelBtn').addEventListener('click',  () => document.getElementById('exclusionOverlay').classList.remove('open'));

/* ── Exclusion modal logic ──────────────────────────────────────────────────
   Only "Not Good", "For Disposal", and "Not In Use" are treated as excluded
   states. "Not Yet Calibrated" is a pre-calibration state and does NOT
   trigger the exclusion/inclusion modal.
────────────────────────────────────────────────────────────────────────── */
const EXCLUSION_TRIGGER_STATUSES = ['Not Good', 'For Disposal', 'Not In Use'];

function getChangeDirection(origStatus, newStatus) {
    const wasExcluded = EXCLUSION_TRIGGER_STATUSES.includes(origStatus);
    const isNowExcluded = EXCLUSION_TRIGGER_STATUSES.includes(newStatus);

    if (!wasExcluded && isNowExcluded) return 'excluded';  // Good / Not Yet Calibrated → bad status
    if (wasExcluded && !isNowExcluded) return 'included';  // bad status → Good / Not Yet Calibrated
    return null; // no modal needed (e.g. Not Good → For Disposal, or any NYC transition)
}

function openExclusionModal(direction, equipLabel, newStatus) {
    const isExcluded = direction === 'excluded';
    document.getElementById('exclModalTitle').textContent =
        isExcluded ? 'Equipment Status Changed' : 'Equipment Being Re-included';
    const banner = document.getElementById('exclBanner');
    banner.className = 'excl-banner ' + (isExcluded ? 'excl-banner-amber' : 'excl-banner-green');
    banner.innerHTML = isExcluded
        ? `Status is changing from <strong>Good</strong> to a non-Good status. This record will be added to the <strong>Exclusion Summary</strong> as <strong>Excluded</strong>. Please fill in the details below.`
        : `Status is changing back to <strong>Good</strong>. This record will be added to the <strong>Exclusion Summary</strong> as <strong>Included</strong>. Please fill in the details below.`;
    const confirmBtn = document.getElementById('exclConfirmBtn');
    confirmBtn.className = 'btn ' + (isExcluded ? 'btn-primary' : 'btn-success');
    confirmBtn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm &amp; Save`;
    const statusInput = document.getElementById('excl_status_display');
    statusInput.style.cssText = isExcluded
        ? 'height:34px;padding:0 12px;border-radius:8px;border:1px solid #fda4af;font-size:12.5px;font-family:Plus Jakarta Sans,sans-serif;background:#fff1f2;color:#be123c;font-weight:700;width:100%;cursor:not-allowed;'
        : 'height:34px;padding:0 12px;border-radius:8px;border:1px solid #86efac;font-size:12.5px;font-family:Plus Jakarta Sans,sans-serif;background:#f0fdf4;color:#166534;font-weight:700;width:100%;cursor:not-allowed;';
    document.getElementById('excl_equipment_display').value = equipLabel;
    document.getElementById('excl_status_display').value    = newStatus;
    document.getElementById('excl_remarks').value           = '';
    document.getElementById('excl_recorded_by').value       = '';
    document.getElementById('excl_error').style.display     = 'none';
    document.getElementById('exclusionOverlay').classList.add('open');
}

document.getElementById('editSampleForm').addEventListener('submit', function(e) {
    const newStatus = document.getElementById('present_status').value;
    const direction = getChangeDirection(ORIGINAL_STATUS, newStatus);
    if (direction) {
        e.preventDefault();
        const equipCode   = document.querySelector('[name="equipment_code"]').value;
        const description = document.querySelector('[name="description"]').value;
        openExclusionModal(direction, equipCode + ' — ' + description, newStatus);
    }
});

document.getElementById('exclConfirmBtn').addEventListener('click', async () => {
    const remarks    = document.getElementById('excl_remarks').value.trim();
    const recordedBy = document.getElementById('excl_recorded_by').value.trim();
    const errEl      = document.getElementById('excl_error');
    if (!remarks)    { errEl.textContent = 'Please enter a reason in the Remarks field.'; errEl.style.display = 'block'; document.getElementById('excl_remarks').focus(); return; }
    if (!recordedBy) { errEl.textContent = 'Please enter who is recording this.'; errEl.style.display = 'block'; document.getElementById('excl_recorded_by').focus(); return; }
    errEl.style.display = 'none';
    const confirmBtn    = document.getElementById('exclConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Saving…';
    const newStatus     = document.getElementById('present_status').value;
    const direction     = getChangeDirection(ORIGINAL_STATUS, newStatus); // 'excluded' | 'included'
    const formFd        = new FormData(document.getElementById('editSampleForm'));
    const exclFd        = new FormData();
    exclFd.append('description',    document.querySelector('[name="description"]').value);
    exclFd.append('control_number', document.querySelector('[name="equipment_code"]').value);
    exclFd.append('model_maker',    document.querySelector('[name="model_maker"]').value);
    exclFd.append('serial_number',  document.querySelector('[name="serial_no"]').value);
    exclFd.append('location',       document.querySelector('[name="location"]').value);
    exclFd.append('cal_date',       document.querySelector('[name="calibration_date"]').value);
    exclFd.append('present_status', newStatus);
    exclFd.append('exclusion_type', direction); // ← tells the server: 'excluded' or 'included'
    exclFd.append('remarks',        remarks);
    exclFd.append('recorded_by',    recordedBy);
    try {
        const [, exclResp] = await Promise.all([
            fetch('edit_standard_sample.php?id=' + RECORD_ID, { method: 'POST', body: formFd }),
            fetch('save_exclusion_from_dashboard.php', { method: 'POST', body: exclFd }).then(r => r.json()),
        ]);
        if (!exclResp.success) throw new Error(exclResp.message || 'Failed to save exclusion record.');
        window.location.href = CANCEL_URL;
    } catch (err) {
        errEl.textContent      = err.message;
        errEl.style.display    = 'block';
        confirmBtn.disabled    = false;
        confirmBtn.innerHTML   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm &amp; Save';
    }
});

document.addEventListener('DOMContentLoaded', () => { updateNextCalibration(); });
</script>
</body>
</html>