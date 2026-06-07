<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isGuest       = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';
$fromDashboard = ($_GET['from'] ?? '') === 'dashboard';
$cancelUrl     = $fromDashboard ? 'dashboard.php' : 'samples.php';

if (!isset($_GET['id'])) {
    header("Location: samples.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM samples WHERE id = :id");
$stmt->execute([':id' => $id]);
$sample = $stmt->fetch();

if (!$sample) {
    header("Location: samples.php");
    exit();
}

$display_item_no = 0;
$counter = 0;
$list = $pdo->query("SELECT id FROM samples ORDER BY id ASC");
while ($r = $list->fetch()) {
    $counter++;
    if ($r['id'] == $id) { $display_item_no = $counter; break; }
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isGuest) { header("Location: " . $cancelUrl); exit(); }

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
        $update = $pdo->prepare("
            UPDATE samples SET
                description = :description,
                qc_batch_lot = :qc_batch_lot,
                model_maker = :model_maker,
                model = :model,
                maker = :maker,
                expiry_date = :expiry_date,
                quantity = :quantity,
                sample_condition = :sample_condition,
                remarks = :remarks,
                serial_number = :serial_number,
                section = :section,
                section_manager = :section_manager,
                calibration_date = :calibration_date,
                period = :period
            WHERE id = :id
        ");
        $update->execute([
            ':description'      => $description,
            ':qc_batch_lot'     => $qc_batch_lot,
            ':model_maker'      => $model_maker,
            ':model'            => $model,
            ':maker'            => $maker,
            ':expiry_date'      => $expiry_date,
            ':quantity'         => $quantity,
            ':sample_condition' => $sample_condition,
            ':remarks'          => $remarks,
            ':serial_number'    => $serial_number,
            ':section'          => $section,
            ':section_manager'  => $section_manager,
            ':calibration_date' => $calibration_date ?: null,
            ':period'           => $period ?: null,
            ':id'               => $id,
        ]);

        log_audit($pdo, 'UPDATE', 'samples', $id,
            "Updated: {$description} | Lot: {$qc_batch_lot} | Condition: {$sample_condition} | Expiry: {$expiry_date} | Period: {$period}");

    } catch (PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }

    header("Location: " . $cancelUrl);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Sample — Calibration Management</title>
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
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

.alert-banner{display:flex;align-items:center;gap:9px;border-radius:var(--r-sm);padding:10px 14px;font-size:12.5px;font-weight:600;margin-bottom:20px;}
.alert-banner svg{flex-shrink:0;width:15px;height:15px;}
.alert-guest{background:#fef9ec;border:1px solid #fcd34d;color:#92400e;}

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
.form-group select[disabled]{background:#f0f4f8;color:var(--text-3);cursor:not-allowed;border-color:var(--border);}

.form-group input.auto-value-blue{background:var(--accent-soft);color:var(--accent);font-family:var(--mono);font-size:12px;border-color:rgba(26,144,217,0.25);cursor:default;font-weight:700;}
.form-group input.mono-input,
.form-group input[type="date"]{font-family:var(--mono);font-size:12px;letter-spacing:0.2px;}

.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:14px;margin-top:4px;border-top:1px solid var(--border);flex-wrap:wrap;}

/* ── Modal overlay ── */
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,0.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;}
.modal-overlay.open{display:flex;}

/* ── Delete confirm modal ── */
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:100%;max-width:380px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;text-align:center;}
.confirm-modal-icon{padding:26px 0 6px;display:flex;justify-content:center;}
.confirm-modal-icon svg{color:#dc2626;}
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
            <div class="card-header-left">
                <h2>Edit Sample</h2>
                <p><?= htmlspecialchars($sample['description']) ?></p>
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

            <form method="POST">
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
                        <input type="text" name="description" value="<?= htmlspecialchars($sample['description']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Serial Number <span class="optional-tag">optional</span></label>
                        <input type="text" name="serial_number" class="mono-input" value="<?= htmlspecialchars($sample['serial_number'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?> placeholder="e.g. SN-20240101">
                    </div>

                    <div class="form-group">
                        <label>QC / Batch / Lot <span class="required-star">*</span></label>
                        <input type="text" name="qc_batch_lot" class="mono-input" value="<?= htmlspecialchars($sample['qc_batch_lot']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Quantity <span class="required-star">*</span></label>
                        <input type="text" name="quantity" value="<?= htmlspecialchars($sample['quantity']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <!-- ── Period ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span class="section-divider-label">Period</span>
                    </div>

                    <div class="form-group">
                        <label>Month / Period</label>
                        <select name="period" <?= $isGuest ? 'disabled' : '' ?>>
                            <option value="">— No period —</option>
                            <?php
                            $savedPeriod = $sample['period'] ?? '';
                            $allOptions  = $periodOptions;
                            if ($savedPeriod && !in_array($savedPeriod, $allOptions)) {
                                array_unshift($allOptions, $savedPeriod);
                            }
                            foreach ($allOptions as $p):
                            ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $savedPeriod === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
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
                        <input type="text" id="modelInput" name="model" value="<?= htmlspecialchars($sample['model'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?> placeholder="e.g. Model X200">
                    </div>

                    <div class="form-group">
                        <label>Maker <span class="optional-tag">optional</span></label>
                        <input type="text" id="makerInput" name="maker" value="<?= htmlspecialchars($sample['maker'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?> placeholder="e.g. Sigma-Aldrich">
                    </div>

                    <div class="form-group">
                        <label>Model / Maker <span class="autofill-pill">auto</span></label>
                        <?php
                        $mPrev   = trim($sample['model'] ?? '');
                        $kPrev   = trim($sample['maker'] ?? '');
                        $prevVal = ($mPrev && $kPrev) ? "$mPrev / $kPrev" : ($mPrev ?: $kPrev);
                        ?>
                        <input type="text" id="modelMakerPreview" class="auto-value-blue" readonly value="<?= htmlspecialchars($prevVal) ?>" placeholder="Combined from Model + Maker">
                    </div>

                    <!-- ── Section ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <span class="section-divider-label">Section</span>
                    </div>

                    <div class="form-group">
                        <label>Section <span class="optional-tag">optional</span></label>
                        <input type="text" name="section" value="<?= htmlspecialchars($sample['section'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?> placeholder="e.g. Quality Control">
                    </div>

                    <div class="form-group span-2">
                        <label>Section Manager <span class="optional-tag">optional</span></label>
                        <input type="text" name="section_manager" value="<?= htmlspecialchars($sample['section_manager'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?> placeholder="e.g. Juan dela Cruz">
                    </div>

                    <!-- ── Condition & Dates ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span class="section-divider-label">Condition &amp; Dates</span>
                    </div>

                    <div class="form-group">
                        <label>Expiry Date <span class="required-star">*</span></label>
                        <input type="date" name="expiry_date" value="<?= htmlspecialchars($sample['expiry_date']) ?>" <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Calibration Date <span class="optional-tag">optional</span></label>
                        <input type="date" name="calibration_date" value="<?= htmlspecialchars($sample['calibration_date'] ?? '') ?>" <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>Condition <span class="required-star">*</span></label>
                        <select name="sample_condition" <?= $isGuest ? 'disabled' : '' ?>>
                            <?php foreach (['Good', 'Fair', 'Poor', 'Expired'] as $c): ?>
                            <option value="<?= $c ?>" <?= $sample['sample_condition'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group span-full">
                        <label>Remarks <span class="optional-tag">optional</span></label>
                        <textarea name="remarks" <?= $isGuest ? 'readonly' : '' ?>><?= htmlspecialchars($sample['remarks']) ?></textarea>
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
                        <button type="submit" class="btn btn-primary">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Update Sample
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
            <h3>Delete this sample?</h3>
            <p><strong><?= htmlspecialchars($sample['description']) ?></strong><br>This action cannot be undone.</p>
        </div>
        <div class="confirm-modal-footer">
            <a href="delete_sample.php?id=<?= $id ?><?= $fromDashboard ? '&from=dashboard' : '' ?>" class="btn btn-danger">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Yes, Delete
            </a>
            <button type="button" class="btn btn-muted" id="cancelDeleteBtn">Cancel</button>
        </div>
    </div>
</div>

<script>
document.getElementById('deleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.add('open'));
document.getElementById('cancelDeleteBtn').addEventListener('click', () => document.getElementById('deleteOverlay').classList.remove('open'));
document.getElementById('deleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});

const modelInput        = document.getElementById('modelInput');
const makerInput        = document.getElementById('makerInput');
const modelMakerPreview = document.getElementById('modelMakerPreview');

function updatePreview() {
    const m = (modelInput.value || '').trim();
    const k = (makerInput.value || '').trim();
    if (m && k)  modelMakerPreview.value = m + ' / ' + k;
    else if (m)  modelMakerPreview.value = m;
    else if (k)  modelMakerPreview.value = k;
    else         modelMakerPreview.value = '';
}

if (modelInput)  modelInput.addEventListener('input', updatePreview);
if (makerInput)  makerInput.addEventListener('input', updatePreview);
</script>
</body>
</html>