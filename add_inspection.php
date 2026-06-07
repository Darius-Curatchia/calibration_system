<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
include 'audit_helper.php';

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

if ($isGuest && isset($_POST['submit'])) {
    header("Location: inspection.php");
    exit();
}

// Get next item number
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM inspection_report");
$row  = $stmt->fetch();
$next_item_no = $row['total'] + 1;

// Handle form submission
if (isset($_POST['submit'])) {
    $description          = $_POST['description'];
    $equipment_code       = $_POST['equipment_code'];
    $location             = $_POST['location'];
    $inspection_date      = $_POST['inspection_date'];
    $next_inspection      = $_POST['next_inspection'];
    $inspection_frequency = $_POST['inspection_frequency'];
    $inspection_result    = $_POST['inspection_result'];
    $remarks              = $_POST['remarks']          ?? '';
    $area_of_location     = $_POST['area_of_location'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO inspection_report
                (item_no, description, equipment_code, location, inspection_date,
                 next_inspection, inspection_frequency, inspection_result,
                 remarks, area_of_location)
            VALUES (:item_no, :description, :equipment_code, :location, :inspection_date,
                    :next_inspection, :inspection_frequency, :inspection_result,
                    :remarks, :area_of_location)
        ");
        $stmt->execute([
            ':item_no'              => $next_item_no,
            ':description'          => $description,
            ':equipment_code'       => $equipment_code,
            ':location'             => $location,
            ':inspection_date'      => $inspection_date,
            ':next_inspection'      => $next_inspection,
            ':inspection_frequency' => $inspection_frequency,
            ':inspection_result'    => $inspection_result,
            ':remarks'              => $remarks,
            ':area_of_location'     => $area_of_location,
        ]);

        $newId = (int)$pdo->lastInsertId();

        log_audit(
            $pdo,
            'ADD',
            'inspection_report',
            $newId,
            "Added inspection: {$equipment_code} — {$description} | Result: {$inspection_result}"
        );

        header("Location: inspection.php");
        exit();
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Inspection — Calibration Management</title>
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
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.item-badge{display:inline-flex;align-items:center;gap:6px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:20px;padding:4px 13px;font-size:12px;font-weight:700;font-family:var(--mono);white-space:nowrap;}
.card-body{padding:20px 24px;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn svg{flex-shrink:0;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

.alert-banner{display:flex;align-items:center;gap:9px;border-radius:var(--r-sm);padding:10px 14px;font-size:12.5px;font-weight:600;margin-bottom:20px;}
.alert-banner svg{flex-shrink:0;width:15px;height:15px;}
.alert-guest{background:#fef9ec;border:1px solid #fcd34d;color:#92400e;}
.alert-error{background:#fff1f2;border:1px solid #fda4af;color:#be123c;}

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
.form-group input.readonly,
.form-group select[disabled],
.form-group select.readonly,
.form-group textarea[readonly]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value{background:#f0fdf4;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;}
.form-group input.auto-value-item{background:#dcfce7;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;text-align:center;font-weight:700;}

.form-group input.mono-input,
.form-group input[type="date"]{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}

.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:14px;margin-top:4px;border-top:1px solid var(--border);}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Add New Inspection</h2>
                <p>Fill in all required fields to register a new inspection record.</p>
            </div>
            <span class="item-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12h6M12 9v6"/></svg>
                Item #<?= $next_item_no ?>
            </span>
        </div>

        <div class="card-body">

            <?php if ($isGuest): ?>
            <div class="alert-banner alert-guest">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                You are viewing this form as a guest. Records cannot be submitted.
            </div>
            <?php endif; ?>

            <?php if (!empty($errorMsg)): ?>
            <div class="alert-banner alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?= htmlspecialchars($errorMsg) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">

                    <!-- ── Identification ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                        <span class="section-divider-label">Identification</span>
                    </div>

                    <div class="form-group">
                        <label>Item No.</label>
                        <input type="text" value="<?= $next_item_no ?>" class="auto-value-item" readonly>
                    </div>

                    <div class="form-group span-2">
                        <label>Description <span class="required-star">*</span></label>
                        <input type="text" name="description"
                               <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Equipment Code <span class="required-star">*</span></label>
                        <input type="text" name="equipment_code" class="mono-input"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Location <span class="required-star">*</span></label>
                        <input type="text" name="location"
                               <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Area of Inspection <span class="required-star">*</span></label>
                        <select name="area_of_location" <?= $isGuest ? 'disabled class="readonly"' : 'required' ?>>
                            <option value="">— Select area —</option>
                            <option value="Onsite">Onsite</option>
                            <option value="Calibration Room">Calibration Room</option>
                        </select>
                        <?php if ($isGuest): ?>
                        <input type="hidden" name="area_of_location" value="">
                        <?php endif; ?>
                    </div>

                    <!-- ── Inspection Details ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span class="section-divider-label">Inspection Details</span>
                    </div>

                    <div class="form-group">
                        <label>Inspection Date <span class="required-star">*</span></label>
                        <input type="date" name="inspection_date" id="inspection_date"
                               <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Next Inspection <span class="autofill-pill">auto</span></label>
                        <input type="date" name="next_inspection" id="next_inspection"
                               class="auto-value" readonly>
                    </div>

                    <div class="form-group">
                        <label>Inspection Frequency <span class="required-star">*</span></label>
                        <select name="inspection_frequency" id="inspection_frequency"
                                <?= $isGuest ? 'disabled class="readonly"' : 'required' ?>>
                            <option value="">Select frequency</option>
                            <option value="6 months">6 months</option>
                            <option value="1 year">1 year</option>
                            <option value="2 years">2 years</option>
                            <option value="5 years">5 years</option>
                        </select>
                        <?php if ($isGuest): ?>
                        <input type="hidden" name="inspection_frequency" value="">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Inspection Result <span class="required-star">*</span></label>
                        <select name="inspection_result"
                                <?= $isGuest ? 'disabled class="readonly"' : 'required' ?>>
                            <option value="Good">Good</option>
                            <option value="For Disposal">For Disposal</option>
                            <option value="Missing">Missing</option>
                            <option value="No Good">No Good</option>
                            <option value="Not Yet Inspected">Not Yet Inspected</option>
                            <option value="Safekeep">Safekeep</option>
                        </select>
                        <?php if ($isGuest): ?>
                        <input type="hidden" name="inspection_result" value="Good">
                        <?php endif; ?>
                    </div>

                    <!-- ── Remarks ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <span class="section-divider-label">Additional Info</span>
                    </div>

                    <div class="form-group span-3">
                        <label>Remarks <span class="optional-pill">optional</span></label>
                        <textarea name="remarks"
                                  <?= $isGuest ? 'readonly' : '' ?>><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                    </div>

                    <!-- ── Actions ── -->
                    <div class="form-actions">
                        <a href="inspection.php" class="btn btn-muted">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Cancel
                        </a>
                        <?php if (!$isGuest): ?>
                        <button type="submit" name="submit" class="btn btn-primary">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Inspection
                        </button>
                        <?php endif; ?>
                    </div>

                </div><!-- /.form-grid -->
            </form>

        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div><!-- /.main-content -->

<script>
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
</script>

</body>
</html>