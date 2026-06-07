<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: exclusion_summary.php");
    exit();
}

$isGuest = isset($_SESSION['role']) && $_SESSION['role'] === 'guest';
$id = intval($_GET['id']);

// Fetch record
$stmt = $pdo->prepare("SELECT * FROM exclusion_summary WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    header("Location: exclusion_summary.php");
    exit();
}

// Compute display item no
$display_item_no = 0;
$counter = 0;
$list = $pdo->query("SELECT id FROM exclusion_summary ORDER BY id ASC");
while ($r = $list->fetch()) {
    $counter++;
    if ($r['id'] == $id) { $display_item_no = $counter; break; }
}

// Update record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exclusion'])) {
    if ($isGuest) { header("Location: exclusion_summary.php"); exit(); }

    $date_added  = $_POST['date_added'];
    $description = $_POST['description'];
    $control_no  = $_POST['control_number'];
    $model_maker = $_POST['model_maker'];
    $serial_no   = $_POST['serial_number'];
    $location    = $_POST['location'];
    $calib_date  = $_POST['calibration_inspection_date'];
    $status      = $_POST['present_status'];
    $remarks     = $_POST['remarks'];
    $recorded_by = $_POST['recorded_by'];

    try {
        $update = $pdo->prepare("
            UPDATE exclusion_summary SET
                date_added = :date_added,
                description = :description,
                control_number = :control_number,
                model_maker = :model_maker,
                serial_number = :serial_number,
                location = :location,
                calibration_inspection_date = :calibration_inspection_date,
                present_status = :present_status,
                remarks = :remarks,
                recorded_by = :recorded_by
            WHERE id = :id
        ");
        $update->execute([
            ':date_added'                  => $date_added,
            ':description'                 => $description,
            ':control_number'              => $control_no,
            ':model_maker'                 => $model_maker,
            ':serial_number'               => $serial_no,
            ':location'                    => $location,
            ':calibration_inspection_date' => $calib_date,
            ':present_status'              => $status,
            ':remarks'                     => $remarks,
            ':recorded_by'                 => $recorded_by,
            ':id'                          => $id,
        ]);
    } catch (PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }

    header("Location: exclusion_summary.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Exclusion Record — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">

<style>
:root {
    --db-bg:#f0f4f8;--db-card-bg:#ffffff;--db-navy:#05304f;--db-accent:#1a90d9;
    --db-accent-soft:rgba(26,144,217,0.10);--db-border:#dde4ec;
    --db-text:#1c2b3a;--db-text-muted:#64788a;--db-radius:12px;
    --db-shadow:0 2px 12px rgba(5,48,79,0.08);
}
body { font-family:'DM Sans',sans-serif; background:var(--db-bg); color:var(--db-text); }
.card { background:var(--db-card-bg); border-radius:var(--db-radius); box-shadow:var(--db-shadow); border:1px solid var(--db-border); margin-bottom:20px; overflow:visible; }
.card-header { padding:18px 24px; border-bottom:1px solid var(--db-border); background:linear-gradient(135deg,#f8fafc 0%,#fff 100%); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.card-header-left h2 { font-size:15px; font-weight:700; color:var(--db-navy); margin:0 0 2px; text-transform:uppercase; letter-spacing:.2px; }
.card-header-left p  { font-size:12px; color:var(--db-text-muted); margin:0; font-family:'DM Mono',monospace; }
.item-badge { display:inline-flex; align-items:center; gap:6px; background:var(--db-accent-soft); color:var(--db-accent); border:1px solid rgba(26,144,217,.2); border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; font-family:'DM Mono',monospace; white-space:nowrap; }
.card-body { padding:24px; }
.section-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--db-text-muted); margin:0 0 14px; padding-bottom:8px; border-bottom:1px solid var(--db-border); grid-column:1/-1; }
.form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px 20px; }
@media(max-width:900px){.form-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}.card-body{padding:16px}}
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group.span-2    { grid-column:span 2; }
.form-group.span-full { grid-column:1/-1; }
.form-group label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--db-text-muted); }
.form-group input,.form-group select,.form-group textarea {
    padding:10px 13px; border-radius:8px; border:1px solid var(--db-border);
    font-size:13.5px; font-family:'DM Sans',sans-serif; color:var(--db-text);
    background:#f8fafc; transition:border-color .15s,box-shadow .15s,background .15s;
    width:100%; box-sizing:border-box;
}
.form-group textarea { resize:vertical; min-height:80px; line-height:1.5; }
.form-group input:focus,.form-group select:focus,.form-group textarea:focus {
    outline:none; border-color:var(--db-accent);
    box-shadow:0 0 0 3px rgba(26,144,217,.12); background:#fff;
}
.form-group input[readonly],.form-group select[disabled] {
    background:#edf1f6; color:var(--db-text-muted); cursor:not-allowed; border-color:#e2e8f0;
}
.form-group input.display-value { background:var(--db-accent-soft); color:var(--db-accent); font-family:'DM Mono',monospace; font-weight:700; border-color:rgba(26,144,217,.2); text-align:center; }
.guest-banner { display:flex; align-items:center; gap:10px; background:#fef3dc; border:1px solid #fcd34d; border-radius:8px; padding:10px 16px; font-size:13px; color:#92400e; font-weight:500; margin-bottom:20px; }
.form-actions { grid-column:1/-1; display:flex; justify-content:flex-end; gap:10px; padding-top:8px; margin-top:6px; border-top:1px solid var(--db-border); flex-wrap:wrap; }
.btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border-radius:8px; font-size:13.5px; font-weight:600; font-family:'DM Sans',sans-serif; cursor:pointer; border:none; transition:background 0.18s ease; white-space:nowrap; text-decoration:none; }
.btn-primary { background:var(--db-navy); color:#fff; box-shadow:0 2px 8px rgba(5,48,79,.2); }
.btn-primary:hover { background:#07406e; }
.btn-danger  { background:#dc2626; color:#fff; box-shadow:0 2px 8px rgba(220,38,38,.25); }
.btn-danger:hover  { background:#b91c1c; }
.btn-muted { background:#f0f4f8; color:var(--db-text-muted); border:1px solid var(--db-border); }
.btn-muted:hover { background:#e2e8f0; color:var(--db-text); }
.optional-hint { font-size:10px; color:var(--db-text-muted); font-style:italic; font-weight:400; text-transform:none; letter-spacing:0; }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(5,48,79,.55); display:none; justify-content:center; align-items:center; z-index:1000; }
.modal-overlay.open { display:flex; }
.delete-confirm-box { background:#fff; border-radius:14px; padding:28px 30px; max-width:400px; width:92%; box-shadow:0 24px 64px rgba(5,48,79,.2); text-align:center; }
.delete-confirm-box .icon { font-size:36px; margin-bottom:10px; }
.delete-confirm-box h3 { font-size:16px; font-weight:700; color:var(--db-navy); margin:0 0 8px; }
.delete-confirm-box p  { font-size:13px; color:var(--db-text-muted); margin:0 0 22px; line-height:1.5; }
.delete-confirm-actions { display:flex; gap:10px; justify-content:center; }
</style>
</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h2>Edit Exclusion Record</h2>
                <p><?= htmlspecialchars($row['control_number']) ?> — <?= htmlspecialchars($row['description']) ?></p>
            </div>
            <span class="item-badge"># <?= $display_item_no ?></span>
        </div>

        <div class="card-body">

            <?php if ($isGuest): ?>
            <div class="guest-banner">👁 You are viewing this record as a guest. Changes cannot be saved.</div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">

                    <p class="section-label">Identification</p>

                    <div class="form-group">
                        <label>Item No.</label>
                        <input type="text" value="<?= $display_item_no ?>" class="display-value" readonly>
                    </div>

                    <div class="form-group span-2">
                        <label>Description</label>
                        <input type="text" name="description"
                               value="<?= htmlspecialchars($row['description']) ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Control Number</label>
                        <input type="text" name="control_number"
                               value="<?= htmlspecialchars($row['control_number']) ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Model / Maker <span class="optional-hint">(optional)</span></label>
                        <input type="text" name="model_maker"
                               value="<?= htmlspecialchars($row['model_maker']) ?>"
                               <?= $isGuest ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number"
                               value="<?= htmlspecialchars($row['serial_number']) ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location"
                               value="<?= htmlspecialchars($row['location']) ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Recorded By</label>
                        <input type="text" name="recorded_by"
                               value="<?= htmlspecialchars($row['recorded_by']) ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <p class="section-label">Calibration / Inspection Details</p>

                    <div class="form-group">
                        <label>Date Added</label>
                        <input type="date" name="date_added"
                               value="<?= $row['date_added'] ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Calib / Insp Date</label>
                        <input type="date" name="calibration_inspection_date"
                               value="<?= $row['calibration_inspection_date'] ?>"
                               <?= $isGuest ? 'readonly' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label>Present Status</label>
                        <select name="present_status" <?= $isGuest ? 'disabled' : '' ?>>
                            <option value="Excluded" <?= strtolower($row['present_status']) == 'excluded' ? 'selected' : '' ?>>Excluded</option>
                            <option value="Included" <?= strtolower($row['present_status']) == 'included' ? 'selected' : '' ?>>Included</option>
                        </select>
                    </div>

                    <div class="form-group span-full">
                        <label>Remarks</label>
                        <textarea name="remarks"
                                  placeholder="Enter remarks…"
                                  <?= $isGuest ? 'readonly' : '' ?>><?= htmlspecialchars($row['remarks']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <?php if (!$isGuest): ?>
                        <button type="submit" name="update_exclusion" class="btn btn-primary">✓ Update Record</button>
                        <button type="button" class="btn btn-danger"
                                onclick="document.getElementById('deleteOverlay').classList.add('open')">
                            🗑 Delete Record
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-muted"
                                onclick="window.location.href='exclusion_summary.php'">
                            <?= $isGuest ? '← Back' : 'Cancel' ?>
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteOverlay" class="modal-overlay">
    <div class="delete-confirm-box">
        <div class="icon">🗑️</div>
        <h3>Delete this record?</h3>
        <p>
            <strong><?= htmlspecialchars($row['control_number']) ?></strong> — <?= htmlspecialchars($row['description']) ?><br>
            This action cannot be undone.
        </p>
        <div class="delete-confirm-actions">
            <a href="delete_exclusion.php?id=<?= $id ?>" class="btn btn-danger">Yes, Delete</a>
            <button type="button" class="btn btn-muted"
                    onclick="document.getElementById('deleteOverlay').classList.remove('open')">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('deleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>