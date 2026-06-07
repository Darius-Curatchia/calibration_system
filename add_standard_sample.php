<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';

if ($isGuest && isset($_POST['submit'])) {
    header("Location: standard_samples.php");
    exit();
}

$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Get next item number
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM standard_samples");
$row = $stmt->fetch();
$next_item_no = $row['total'] + 1;

// Fetch existing calibrators for autocomplete
$calibrators_arr = [];
$stmt2 = $pdo->query("SELECT DISTINCT calibrator FROM standard_samples WHERE calibrator IS NOT NULL AND calibrator != '' ORDER BY calibrator ASC");
foreach ($stmt2->fetchAll() as $r) {
    $calibrators_arr[] = $r['calibrator'];
}

$errorMsg = '';

// Handle form submission
if (isset($_POST['submit'])) {
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
        $stmt = $pdo->prepare("INSERT INTO standard_samples
            (description, equipment_code, model_maker, serial_no, location,
             calibration_date, next_calibration_date, calibration_frequency, calibrator, present_status)
            VALUES (:description, :equipment_code, :model_maker, :serial_no, :location,
                    :calibration_date, :next_calibration_date, :calibration_frequency, :calibrator, :present_status)");
        $stmt->execute([
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
        ]);

        $newId = (int)$pdo->lastInsertId();
        log_audit($pdo, 'INSERT', 'standard_samples', $newId,
            "Added: {$description} | Code: {$equipment_code} | Calibrator: {$calibrator} | Status: {$present_status}");

        header("Location: standard_samples.php?page=$currentPage");
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
<title>Add Standard Sample — Calibration Management</title>
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
.form-group.span-full{grid-column:1/-1;}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:4px;}
.required-star{color:#e53e3e;}
.autofill-pill{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--accent);background:var(--accent-soft);border:1px solid rgba(26,144,217,0.20);border-radius:4px;padding:1px 5px;margin-left:2px;}

.form-group input,
.form-group select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group input:focus,
.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder{color:var(--text-3);font-weight:400;}
.form-group input[readonly],
.form-group input.readonly,
.form-group select[disabled],
.form-group select.readonly{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value-item{background:#dcfce7;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;text-align:center;font-weight:700;}
.form-group input.auto-value-green{background:#f0fdf4;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;}

.form-group input.mono-input,
.form-group input[type="date"]{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}

.suggestions-box{position:absolute;top:calc(100% + 3px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border-mid);border-radius:var(--r-sm);box-shadow:var(--shadow-lg);max-height:180px;overflow-y:auto;z-index:500;display:none;}
.suggestions-box div{padding:8px 12px;font-size:12.5px;font-weight:500;cursor:pointer;color:var(--text);border-bottom:1px solid var(--border);}
.suggestions-box div:last-child{border-bottom:none;}
.suggestions-box div:hover,.suggestions-box div.selected{background:var(--accent-soft);color:var(--accent);}

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
                <h2>Add New Standard Sample</h2>
                <p>Fill in all required fields to register a new standard sample record.</p>
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
                        <input type="text" name="description" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?> >
                    </div>

                    <div class="form-group">
                        <label>Equipment Code <span class="required-star">*</span></label>
                        <input type="text" name="equipment_code" class="mono-input" <?= $isGuest ? 'readonly' : 'required' ?> >
                    </div>

                    <div class="form-group">
                        <label>Model / Maker <span class="required-star">*</span></label>
                        <input type="text" name="model_maker" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?> >
                    </div>

                    <div class="form-group">
                        <label>Serial No. / Sub Parts <span class="required-star">*</span></label>
                        <input type="text" name="serial_no" class="mono-input" <?= $isGuest ? 'readonly' : 'required' ?> >
                    </div>

                    <div class="form-group">
                        <label>Location <span class="required-star">*</span></label>
                        <input type="text" name="location" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?> >
                    </div>

                    <!-- ── Calibration Details ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                        <span class="section-divider-label">Calibration Details</span>
                    </div>

                    <div class="form-group">
                        <label>Calibration Date <span class="required-star">*</span></label>
                        <input type="date" name="calibration_date" id="calibration_date" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Next Calibration <span class="autofill-pill">auto</span></label>
                        <input type="date" name="next_calibration_date" id="next_calibration_date" class="auto-value-green" readonly>
                    </div>

                    <div class="form-group">
                        <label>Calibration Frequency <span class="required-star">*</span></label>
                        <select name="calibration_frequency" id="calibration_frequency" onchange="updateNextCalibration()" <?= $isGuest ? 'disabled class="readonly"' : 'required' ?>>
                            <option value="">Select frequency</option>
                            <option value="6">6 months</option>
                            <option value="12">1 year</option>
                            <option value="24">2 years</option>
                            <option value="60">5 years</option>
                        </select>
                        <?php if ($isGuest): ?><input type="hidden" name="calibration_frequency" value=""><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Calibrator <span class="required-star">*</span></label>
                        <input type="text" name="calibrator" id="calibrator_input" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?> autocomplete="off">
                        <div id="calibrator_suggestions" class="suggestions-box"></div>
                    </div>

                    <div class="form-group">
                        <label>Present Status</label>
                        <select name="present_status" <?= $isGuest ? 'disabled class="readonly"' : '' ?>>
                            <option value="Good">Good</option>
                            <option value="Not Good">Not Good</option>
                            <option value="For Disposal">For Disposal</option>
                            <option value="Not In Use">Not In Use</option>
                            <option value="Not Yet Calibrated">Not Yet Calibrated</option>
                        </select>
                        <?php if ($isGuest): ?><input type="hidden" name="present_status" value="Good"><?php endif; ?>
                    </div>

                    <!-- ── Actions ── -->
                    <div class="form-actions">
                        <a href="standard_samples.php?page=<?= $currentPage ?>" class="btn btn-muted">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Cancel
                        </a>
                        <?php if (!$isGuest): ?>
                        <button type="submit" name="submit" class="btn btn-primary">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Sample
                        </button>
                        <?php endif; ?>
                    </div>

                </div><!-- /.form-grid -->
            </form>
        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div><!-- /.main-content -->

<script>
function updateNextCalibration() {
    const freq = parseInt(document.getElementById('calibration_frequency').value);
    const calDateInput = document.getElementById('calibration_date');
    const nextCalInput = document.getElementById('next_calibration_date');
    if (!calDateInput.value || !freq) { nextCalInput.value = ''; return; }
    let calDate = new Date(calDateInput.value);
    calDate.setMonth(calDate.getMonth() + freq);
    nextCalInput.value = `${calDate.getFullYear()}-${String(calDate.getMonth()+1).padStart(2,'0')}-${String(calDate.getDate()).padStart(2,'0')}`;
}
document.getElementById('calibration_date').addEventListener('change', updateNextCalibration);
document.getElementById('calibration_frequency').addEventListener('change', updateNextCalibration);

const calibrators    = <?php echo json_encode($calibrators_arr); ?>;
const input          = document.getElementById('calibrator_input');
const suggestionsBox = document.getElementById('calibrator_suggestions');
let selectedIndex    = -1;

function showSuggestions() {
    const value = input.value.toLowerCase();
    suggestionsBox.innerHTML = ''; selectedIndex = -1;
    if (!value) { suggestionsBox.style.display = 'none'; return; }
    const matches = calibrators.filter(c => c.toLowerCase().includes(value));
    if (matches.length === 0) { suggestionsBox.style.display = 'none'; return; }
    matches.forEach(match => {
        const div = document.createElement('div');
        div.textContent = match;
        div.addEventListener('click', () => { input.value = match; suggestionsBox.style.display = 'none'; });
        suggestionsBox.appendChild(div);
    });
    suggestionsBox.style.display = 'block';
}
input.addEventListener('input', showSuggestions);
input.addEventListener('keydown', e => {
    const items = suggestionsBox.querySelectorAll('div');
    if (items.length === 0) return;
    if (e.key === 'ArrowDown')  { selectedIndex = (selectedIndex + 1) % items.length; updateSelection(items); e.preventDefault(); }
    else if (e.key === 'ArrowUp')   { selectedIndex = (selectedIndex - 1 + items.length) % items.length; updateSelection(items); e.preventDefault(); }
    else if (e.key === 'Enter' && selectedIndex >= 0) { input.value = items[selectedIndex].textContent; suggestionsBox.style.display = 'none'; e.preventDefault(); }
});
function updateSelection(items) { items.forEach(i => i.classList.remove('selected')); if (selectedIndex >= 0) items[selectedIndex].classList.add('selected'); }
document.addEventListener('click', e => { if (e.target !== input) suggestionsBox.style.display = 'none'; });
</script>
</body>
</html>