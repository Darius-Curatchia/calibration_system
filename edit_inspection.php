<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
include 'audit_helper.php';

$isGuest       = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';
$fromDashboard = ($_GET['from'] ?? '') === 'dashboard';
$cancelUrl     = $fromDashboard ? 'dashboard.php' : 'inspection.php';

if (!isset($_GET['id'])) {
    header("Location: inspection.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE id = :id");
$stmt->execute([':id' => $id]);
$inspection = $stmt->fetch();
if (!$inspection) die("Record not found.");

$display_item_no = 0;
$counter = 0;
$list = $pdo->query("SELECT id FROM inspection_report ORDER BY equipment_code ASC");
while ($r = $list->fetch()) {
    $counter++;
    if ($r['id'] == $id) { $display_item_no = $counter; break; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isGuest) { header("Location: " . $cancelUrl); exit(); }

    $description          = $_POST['description'];
    $equipment_code       = $_POST['equipment_code'];
    $location             = $_POST['location'];
    $inspection_date      = $_POST['inspection_date'];
    $next_inspection      = $_POST['next_inspection'];
    $inspection_frequency = $_POST['inspection_frequency'];
    $inspection_result    = $_POST['inspection_result'];
    $remarks              = $_POST['remarks']          ?? '';
    $area_of_location     = $_POST['area_of_location'] ?? '';

    $changes = [];
    if ($inspection['description']         !== $description)          $changes[] = "Description: \"{$inspection['description']}\" → \"{$description}\"";
    if ($inspection['equipment_code']       !== $equipment_code)       $changes[] = "Equipment Code: \"{$inspection['equipment_code']}\" → \"{$equipment_code}\"";
    if ($inspection['location']             !== $location)             $changes[] = "Location: \"{$inspection['location']}\" → \"{$location}\"";
    if ($inspection['inspection_date']      !== $inspection_date)      $changes[] = "Inspection Date: \"{$inspection['inspection_date']}\" → \"{$inspection_date}\"";
    if ($inspection['next_inspection']      !== $next_inspection)      $changes[] = "Next Inspection: \"{$inspection['next_inspection']}\" → \"{$next_inspection}\"";
    if ($inspection['inspection_frequency'] !== $inspection_frequency) $changes[] = "Frequency: \"{$inspection['inspection_frequency']}\" → \"{$inspection_frequency}\"";
    if ($inspection['inspection_result']    !== $inspection_result)    $changes[] = "Result: \"{$inspection['inspection_result']}\" → \"{$inspection_result}\"";
    if (($inspection['remarks']          ?? '') !== $remarks)          $changes[] = "Remarks updated";
    if (($inspection['area_of_location'] ?? '') !== $area_of_location) $changes[] = "Area: \"{$inspection['area_of_location']}\" → \"{$area_of_location}\"";
    $details = $changes ? implode('; ', $changes) : "No field changes detected";

    try {
        $stmt = $pdo->prepare("
            UPDATE inspection_report SET
                description=:description, equipment_code=:equipment_code,
                location=:location, inspection_date=:inspection_date,
                next_inspection=:next_inspection,
                inspection_frequency=:inspection_frequency,
                inspection_result=:inspection_result,
                remarks=:remarks,
                area_of_location=:area_of_location
            WHERE id=:id
        ");
        $stmt->execute([
            ':description'          => $description,
            ':equipment_code'       => $equipment_code,
            ':location'             => $location,
            ':inspection_date'      => $inspection_date,
            ':next_inspection'      => $next_inspection,
            ':inspection_frequency' => $inspection_frequency,
            ':inspection_result'    => $inspection_result,
            ':remarks'              => $remarks,
            ':area_of_location'     => $area_of_location,
            ':id'                   => $id,
        ]);
        log_audit($pdo, 'EDIT', 'inspection_report', $id, "Edited {$equipment_code}: {$details}");
    } catch (PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }

    header("Location: " . $cancelUrl);
    exit();
}

// FIX 1: trim() prevents whitespace/casing mismatches from breaking neutral detection
$originalResult = trim($inspection['inspection_result'] ?? 'Good');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Inspection Record — Calibration Management</title>
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
.form-group.span-3{grid-column:span 3;}
@media(max-width:960px){.form-group.span-3{grid-column:span 2;}}
@media(max-width:600px){.form-group.span-2,.form-group.span-3{grid-column:span 1;}}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:4px;}
.required-star{color:#e53e3e;}
.autofill-pill{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--accent);background:var(--accent-soft);border:1px solid rgba(26,144,217,0.20);border-radius:4px;padding:1px 5px;margin-left:2px;}
.optional-pill{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text-3);background:var(--bg-raised);border:1px solid var(--border);border-radius:4px;padding:1px 5px;margin-left:2px;}

.form-group input,
.form-group select,
.form-group textarea{padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group input,
.form-group select{height:34px;}
.form-group textarea{padding:8px 12px;resize:vertical;min-height:72px;line-height:1.5;}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder,
.form-group textarea::placeholder{color:var(--text-3);font-weight:400;}
.form-group input[readonly],
.form-group select[disabled],
.form-group textarea[readonly]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value-blue{background:var(--accent-soft);color:var(--accent);font-family:var(--mono);font-size:12px;border-color:rgba(26,144,217,0.25);cursor:default;}
.form-group input.auto-value-green{background:#f0fdf4;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;}

.form-group input.mono-input,
.form-group input[type="date"]{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}

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
                <h2>Edit Inspection Record</h2>
                <p><?= htmlspecialchars($inspection['equipment_code']) ?> — <?= htmlspecialchars($inspection['description']) ?></p>
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

            <form method="POST" id="editInspForm">
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
                        <input type="text" name="description" value="<?= htmlspecialchars($inspection['description']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Equipment Code <span class="required-star">*</span></label>
                        <input type="text" name="equipment_code" class="mono-input" value="<?= htmlspecialchars($inspection['equipment_code']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Location <span class="required-star">*</span></label>
                        <input type="text" name="location" value="<?= htmlspecialchars($inspection['location']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Area of Inspection <span class="required-star">*</span></label>
                        <select name="area_of_location" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="">— Select area —</option>
                            <option value="Onsite"           <?= ($inspection['area_of_location'] ?? '') === 'Onsite'           ? 'selected' : '' ?>>Onsite</option>
                            <option value="Calibration Room" <?= ($inspection['area_of_location'] ?? '') === 'Calibration Room' ? 'selected' : '' ?>>Calibration Room</option>
                        </select>
                    </div>

                    <!-- ── Inspection Details ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span class="section-divider-label">Inspection Details</span>
                    </div>

                    <div class="form-group">
                        <label>Inspection Date <span class="required-star">*</span></label>
                        <input type="date" name="inspection_date" id="inspection_date" value="<?= $inspection['inspection_date'] ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Next Inspection <span class="autofill-pill">auto</span></label>
                        <input type="date" name="next_inspection" id="next_inspection" value="<?= $inspection['next_inspection'] ?>" class="auto-value-green" readonly>
                    </div>

                    <div class="form-group">
                        <label>Inspection Frequency <span class="required-star">*</span></label>
                        <select name="inspection_frequency" id="inspection_frequency" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="">Select frequency</option>
                            <option value="6 months" <?= $inspection['inspection_frequency'] === '6 months' ? 'selected' : '' ?>>6 months</option>
                            <option value="1 year"   <?= $inspection['inspection_frequency'] === '1 year'   ? 'selected' : '' ?>>1 year</option>
                            <option value="2 years"  <?= $inspection['inspection_frequency'] === '2 years'  ? 'selected' : '' ?>>2 years</option>
                            <option value="5 years"  <?= $inspection['inspection_frequency'] === '5 years'  ? 'selected' : '' ?>>5 years</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Inspection Result <span class="required-star">*</span></label>
                        <select name="inspection_result" id="inspection_result" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="Good"              <?= $inspection['inspection_result'] === 'Good'              ? 'selected' : '' ?>>Good</option>
                            <option value="For Disposal"      <?= $inspection['inspection_result'] === 'For Disposal'      ? 'selected' : '' ?>>For Disposal</option>
                            <option value="Missing"           <?= $inspection['inspection_result'] === 'Missing'           ? 'selected' : '' ?>>Missing</option>
                            <option value="No Good"           <?= $inspection['inspection_result'] === 'No Good'           ? 'selected' : '' ?>>No Good</option>
                            <option value="Not Yet Inspected" <?= $inspection['inspection_result'] === 'Not Yet Inspected' ? 'selected' : '' ?>>Not Yet Inspected</option>
                            <option value="Safekeep"          <?= $inspection['inspection_result'] === 'Safekeep'          ? 'selected' : '' ?>>Safekeep</option>
                        </select>
                    </div>

                    <!-- ── Additional Info ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <span class="section-divider-label">Additional Info</span>
                    </div>

                    <div class="form-group span-3">
                        <label>Remarks <span class="optional-pill">optional</span></label>
                        <textarea name="remarks"
                                  <?= $isGuest ? 'readonly' : '' ?>
                                  placeholder="Notes, condition details, observations…"><?= htmlspecialchars($inspection['remarks'] ?? '') ?></textarea>
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
            <p><strong><?= htmlspecialchars($inspection['equipment_code']) ?></strong> — <?= htmlspecialchars($inspection['description']) ?><br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <a href="delete_inspection.php?id=<?= $id ?><?= $fromDashboard ? '&from=dashboard' : '' ?>" class="btn btn-danger">
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
            <h3 id="exclModalTitle">Inspection Result Changed</h3>
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
                    <label>New Result</label>
                    <input type="text" id="excl_status_display" readonly>
                </div>
                <div class="excl-fg">
                    <label>Remarks <span style="color:#e53e3e;">*</span></label>
                    <textarea id="excl_remarks" rows="3" placeholder="Reason for result change, condition details, etc."></textarea>
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
const ORIGINAL_RESULT = <?= json_encode($originalResult) ?>;
const RECORD_ID       = <?= $id ?>;
const CANCEL_URL      = <?= json_encode($cancelUrl) ?>;

/*
 * Neutral statuses — switching between any of these never triggers
 * the exclusion modal. Only leaving this group (or entering it from
 * outside) matters.
 *
 * Good              = active, no issue
 * Not Yet Inspected = pending — treated the same as Good
 *                    for exclusion purposes (not a defect)
 */
const NEUTRAL_RESULTS = ['good', 'not yet inspected'];

// FIX 2: Added .trim() so whitespace/casing mismatches in the DB value
// don't cause a neutral status to be misidentified.
function isNeutral(result) {
    return NEUTRAL_RESULTS.includes(result.toLowerCase().trim());
}

function updateNextInspection() {
    const freq  = document.getElementById('inspection_frequency').value;
    const insIn = document.getElementById('inspection_date');
    const nxtIn = document.getElementById('next_inspection');
    if (!insIn.value || !freq) { nxtIn.value = ''; return; }
    const d = new Date(insIn.value);
    switch (freq) {
        case '6 months': d.setMonth(d.getMonth() + 6);      break;
        case '1 year':   d.setFullYear(d.getFullYear() + 1); break;
        case '2 years':  d.setFullYear(d.getFullYear() + 2); break;
        case '5 years':  d.setFullYear(d.getFullYear() + 5); break;
    }
    nxtIn.value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
document.getElementById('inspection_date').addEventListener('change', updateNextInspection);
document.getElementById('inspection_frequency').addEventListener('change', updateNextInspection);

/* ── Modal open/close ── */
document.getElementById('deleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.add('open'));
document.getElementById('cancelDeleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.remove('open'));

['deleteOverlay', 'exclusionOverlay'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
document.getElementById('exclModalClose').addEventListener('click', () => document.getElementById('exclusionOverlay').classList.remove('open'));
document.getElementById('exclCancelBtn').addEventListener('click',  () => document.getElementById('exclusionOverlay').classList.remove('open'));

/* ── Exclusion modal logic ──────────────────────────────────────────
 *
 * Direction rules (using NEUTRAL_RESULTS):
 *
 *   neutral  → neutral    : no modal  (e.g. Good ↔ Not Yet Inspected)
 *   neutral  → non-neutral: 'excluded' modal
 *   non-neutral → neutral : 'included' modal
 *   non-neutral → non-neutral: no modal (already excluded, just updating)
 */
function getChangeDirection(origResult, newResult) {
    const origNeutral = isNeutral(origResult);
    const newNeutral  = isNeutral(newResult);

    if (origNeutral && !newNeutral) return 'excluded';   /* going bad   */
    if (!origNeutral && newNeutral) return 'included';   /* coming back */
    return null;                                          /* no modal needed */
}

function openExclusionModal(direction, equipLabel, newResult) {
    const isExcluded = direction === 'excluded';
    document.getElementById('exclModalTitle').textContent =
        isExcluded ? 'Inspection Result Changed' : 'Equipment Being Re-included';
    const banner = document.getElementById('exclBanner');
    banner.className = 'excl-banner ' + (isExcluded ? 'excl-banner-amber' : 'excl-banner-green');
    banner.innerHTML = isExcluded
        ? `Inspection result is changing from a Good/Neutral status to a non-Good result. This record will be added to the <strong>Exclusion Summary</strong> as <strong>Excluded</strong>. Please fill in the details below.`
        : `Inspection result is changing back to a Good/Neutral status. This record will be added to the <strong>Exclusion Summary</strong> as <strong>Included</strong>. Please fill in the details below.`;
    const confirmBtn = document.getElementById('exclConfirmBtn');
    confirmBtn.className = 'btn ' + (isExcluded ? 'btn-primary' : 'btn-success');
    confirmBtn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm &amp; Save`;
    const statusInput = document.getElementById('excl_status_display');
    statusInput.style.cssText = isExcluded
        ? 'height:34px;padding:0 12px;border-radius:8px;border:1px solid #fda4af;font-size:12.5px;font-family:Plus Jakarta Sans,sans-serif;background:#fff1f2;color:#be123c;font-weight:700;width:100%;cursor:not-allowed;'
        : 'height:34px;padding:0 12px;border-radius:8px;border:1px solid #86efac;font-size:12.5px;font-family:Plus Jakarta Sans,sans-serif;background:#f0fdf4;color:#166534;font-weight:700;width:100%;cursor:not-allowed;';
    document.getElementById('excl_equipment_display').value = equipLabel;
    document.getElementById('excl_status_display').value    = newResult;
    document.getElementById('excl_remarks').value           = '';
    document.getElementById('excl_recorded_by').value       = '';
    document.getElementById('excl_error').style.display     = 'none';

    // FIX 3: Store direction on the overlay so the confirm handler can read it
    // (same pattern used in edit_calibration.php)
    document.getElementById('exclusionOverlay').dataset.direction = direction;

    document.getElementById('exclusionOverlay').classList.add('open');
}

document.getElementById('editInspForm').addEventListener('submit', function(e) {
    const newResult = document.getElementById('inspection_result').value;
    const direction = getChangeDirection(ORIGINAL_RESULT, newResult);
    if (direction) {
        e.preventDefault();
        const equipCode   = document.querySelector('[name="equipment_code"]').value;
        const description = document.querySelector('[name="description"]').value;
        openExclusionModal(direction, equipCode + ' — ' + description, newResult);
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

    const newResult = document.getElementById('inspection_result').value;
    const equipCode = document.querySelector('[name="equipment_code"]').value;
    const formFd    = new FormData(document.getElementById('editInspForm'));

    // FIX 4: Read the direction stored when the modal opened, then pass it
    // as exclusion_type — this is what save_exclusion_from_dashboard.php reads
    // to decide whether to write 'excluded' or 'included'. Without this,
    // exclusion_type was never sent, so it always defaulted to 'excluded'.
    const direction = document.getElementById('exclusionOverlay').dataset.direction || 'excluded';

    const exclFd = new FormData();
    exclFd.append('description',    document.querySelector('[name="description"]').value);
    exclFd.append('control_number', equipCode);
    exclFd.append('model_maker',    '');
    exclFd.append('serial_number',  equipCode);
    exclFd.append('location',       document.querySelector('[name="location"]').value);
    exclFd.append('cal_date',       document.querySelector('[name="inspection_date"]').value);
    exclFd.append('present_status', newResult);
    exclFd.append('exclusion_type', direction); // ← THE KEY FIX: was missing before
    exclFd.append('remarks',        remarks);
    exclFd.append('recorded_by',    recordedBy);

    try {
        const [, exclResp] = await Promise.all([
            fetch('edit_inspection.php?id=' + RECORD_ID, { method: 'POST', body: formFd }),
            fetch('save_exclusion_from_dashboard.php',   { method: 'POST', body: exclFd }).then(r => r.json()),
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

document.addEventListener('DOMContentLoaded', updateNextInspection);
</script>

</body>
</html>