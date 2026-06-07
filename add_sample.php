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
    header("Location: samples.php");
    exit();
}

// Get next item number
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM samples");
$row = $stmt->fetch();
$next_item_no = $row['total'] + 1;

function generatePeriodOptions() {
    $options = [];
    $now = new DateTime();
    $now->modify('-3 months');
    for ($i = 0; $i < 7; $i++) {
        $options[] = $now->format('F Y');
        $now->modify('+1 month');
    }
    return $options;
}
$periodOptions = generatePeriodOptions();
$currentPeriod = (new DateTime())->format('F Y');

$errorMsg = '';

if (isset($_POST['submit'])) {
    $item_no          = $_POST['item_no'];
    $description      = $_POST['description'];
    $qc_batch_lot     = $_POST['qc_batch_lot'];
    $model            = trim($_POST['model'] ?? '');
    $maker            = trim($_POST['maker'] ?? '');
    $model_maker      = ($model !== '' && $maker !== '')
                            ? $model . ' / ' . $maker
                            : ($model ?: $maker);
    $expiry_date      = $_POST['expiry_date'];
    $quantity         = $_POST['quantity'];
    $sample_condition = $_POST['sample_condition'];
    $remarks          = $_POST['remarks'];
    $serial_number    = $_POST['serial_number'] ?? '';
    $section          = $_POST['section'] ?? '';
    $section_manager  = $_POST['section_manager'] ?? '';
    $calibration_date = $_POST['calibration_date'] ?? '';
    $period           = $_POST['period'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO samples
            (item_no, description, qc_batch_lot, model_maker, expiry_date, quantity, sample_condition, remarks,
             serial_number, section, section_manager, model, maker, calibration_date, period)
            VALUES
            (:item_no, :description, :qc_batch_lot, :model_maker, :expiry_date, :quantity, :sample_condition, :remarks,
             :serial_number, :section, :section_manager, :model, :maker, :calibration_date, :period)
        ");
        $stmt->execute([
            ':item_no'          => $item_no,
            ':description'      => $description,
            ':qc_batch_lot'     => $qc_batch_lot,
            ':model_maker'      => $model_maker,
            ':expiry_date'      => $expiry_date,
            ':quantity'         => $quantity,
            ':sample_condition' => $sample_condition,
            ':remarks'          => $remarks,
            ':serial_number'    => $serial_number,
            ':section'          => $section,
            ':section_manager'  => $section_manager,
            ':model'            => $model,
            ':maker'            => $maker,
            ':calibration_date' => $calibration_date ?: null,
            ':period'           => $period ?: null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        log_audit($pdo, 'INSERT', 'samples', $newId,
            "Added: {$description} | Lot: {$qc_batch_lot} | Condition: {$sample_condition} | Expiry: {$expiry_date} | Period: {$period}");

        header("Location: samples.php");
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
<title>Add New Sample — Calibration Management</title>
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

.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px 20px;}
@media(max-width:960px){.form-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;}.card-body{padding:16px;}}

.section-divider{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--border);margin-top:4px;}
.section-divider svg{color:var(--text-3);flex-shrink:0;}
.section-divider-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--navy);}

.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.span-2{grid-column:span 2;}
.form-group.span-full{grid-column:1/-1;}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:4px;flex-wrap:wrap;}
.required-star{color:#e53e3e;}
.optional-tag{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text-3);background:#f0f4f8;border:1px solid var(--border-mid);border-radius:4px;padding:1px 5px;margin-left:2px;}
.autofill-pill{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--accent);background:var(--accent-soft);border:1px solid rgba(26,144,217,0.20);border-radius:4px;padding:1px 5px;margin-left:2px;}

.form-group input,
.form-group select,
.form-group textarea{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group textarea{height:auto;padding:9px 12px;min-height:64px;resize:vertical;font-family:'Plus Jakarta Sans',sans-serif;}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder,
.form-group textarea::placeholder{color:var(--text-3);font-weight:400;}
.form-group input[readonly],
.form-group input.readonly,
.form-group select[disabled]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value-item{background:#dcfce7;color:#166534;font-family:var(--mono);font-size:12px;border-color:#bbf7d0;cursor:default;text-align:center;font-weight:700;}
.form-group input.auto-value-blue{background:var(--accent-soft);color:var(--accent);font-family:var(--mono);font-size:12px;border-color:rgba(26,144,217,0.25);cursor:default;font-weight:600;}

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
                <h2>Add New Sample</h2>
                <p>Fill in all required fields to register a new sample record.</p>
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
                        <input type="text" name="item_no" value="<?= $next_item_no ?>" class="auto-value-item" readonly>
                    </div>

                    <div class="form-group span-2">
                        <label>Description <span class="required-star">*</span></label>
                        <input type="text" name="description" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Serial Number <span class="optional-tag">optional</span></label>
                        <input type="text" name="serial_number" class="mono-input" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>QC / Batch / Lot <span class="required-star">*</span></label>
                        <input type="text" name="qc_batch_lot" class="mono-input" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Quantity <span class="required-star">*</span></label>
                        <input type="text" name="quantity" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <!-- ── Period ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span class="section-divider-label">Period</span>
                    </div>

                    <div class="form-group">
                        <label>Month / Period <span class="required-star">*</span></label>
                        <select name="period" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="">Select period</option>
                            <?php foreach ($periodOptions as $p): ?>
                            <option value="<?= $p ?>" <?= $p === $currentPeriod ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ── Model & Maker ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                        <span class="section-divider-label">Model &amp; Maker</span>
                    </div>

                    <div class="form-group">
                        <label>Model <span class="optional-tag">optional</span></label>
                        <input type="text" id="modelInput" name="model" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>Maker <span class="optional-tag">optional</span></label>
                        <input type="text" id="makerInput" name="maker" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>Model / Maker <span class="autofill-pill">auto</span></label>
                        <input type="text" id="modelMakerPreview" class="auto-value-blue" readonly >
                    </div>

                    <!-- ── Section ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <span class="section-divider-label">Section</span>
                    </div>

                    <div class="form-group">
                        <label>Section <span class="optional-tag">optional</span></label>
                        <input type="text" name="section" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group span-2">
                        <label>Section Manager <span class="optional-tag">optional</span></label>
                        <input type="text" name="section_manager" <?= $isGuest ? 'readonly' : '' ?> >
                    </div>

                    <!-- ── Condition & Dates ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span class="section-divider-label">Condition &amp; Dates</span>
                    </div>

                    <div class="form-group">
                        <label>Expiry Date <span class="required-star">*</span></label>
                        <input type="date" name="expiry_date" <?= $isGuest ? 'readonly class="readonly"' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Calibration Date <span class="optional-tag">optional</span></label>
                        <input type="date" name="calibration_date" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>Condition <span class="required-star">*</span></label>
                        <select name="sample_condition" <?= $isGuest ? 'disabled' : 'required' ?>>
                            <option value="">Select condition</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>

                    <div class="form-group span-full">
                        <label>Remarks <span class="optional-tag">optional</span></label>
                        <textarea name="remarks" <?= $isGuest ? 'readonly' : '' ?> ></textarea>
                    </div>

                    <!-- ── Actions ── -->
                    <div class="form-actions">
                        <a href="samples.php" class="btn btn-muted">
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
const modelInput        = document.getElementById('modelInput');
const makerInput        = document.getElementById('makerInput');
const modelMakerPreview = document.getElementById('modelMakerPreview');

function updatePreview() {
    const m = (modelInput.value || '').trim();
    const k = (makerInput.value || '').trim();
    if (m && k)       modelMakerPreview.value = m + ' / ' + k;
    else if (m)       modelMakerPreview.value = m;
    else if (k)       modelMakerPreview.value = k;
    else              modelMakerPreview.value = '';
}

if (modelInput)  modelInput.addEventListener('input', updatePreview);
if (makerInput)  makerInput.addEventListener('input', updatePreview);
</script>
</body>
</html>