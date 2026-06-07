<?php
include 'db.php';
if (!isset($_GET['ids'])) die("No IDs provided");

$ids         = explode(',', $_GET['ids']);
$orientation = $_GET['orientation'] ?? 'vertical';
?>
<!DOCTYPE html>
<html>
<head>
<title>Print Inspection Stickers</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    margin: 4mm;
    font-family: Arial, Helvetica, sans-serif;
    display: flex;
    gap: 3px;
    <?php if ($orientation === 'horizontal'): ?>
    flex-direction: row;
    flex-wrap: wrap;
    <?php else: ?>
    flex-direction: column;
    <?php endif; ?>
}

/* SMALLER STICKER */
.sticker {
    width: 14mm;
    height: 14mm;
    border: 1px solid #000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 2px;
    page-break-inside: avoid;
    flex-shrink: 0;
    text-align: center;
}

/* PES darker & bold */
.pes {
    font-size: 8pt;
    font-weight: bold;
    color: #0f1ea5; /* darker blue */
    letter-spacing: 0.2px;
}

.label {
    font-size: 3.8pt;
}

.date {
    font-size: 8.5pt;
    font-weight: bold;
    color: red;
    line-height: 1;
}

.sdp {
    font-size: 7pt;
    font-weight: bold;
    letter-spacing: 0.3px;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 4mm;
    }

    body { margin: 0; }

    .sticker {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
</head>
<body>

<?php
foreach ($ids as $rawId) {
    $id   = intval($rawId);
    $stmt = $pdo->prepare("SELECT * FROM inspection_report WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch();
    if (!$row) continue;

    $nextDate = (!empty($row['next_inspection']) && $row['next_inspection'] !== '0000-00-00')
        ? date('M-y', strtotime($row['next_inspection']))
        : '—';
?>
<div class="sticker">
    <span class="pes">PES</span>
    <span class="label">NEXT INSPECTION</span>
    <span class="date"><?= htmlspecialchars($nextDate) ?></span>
    <span class="sdp">SDP</span>
</div>
<?php } ?>

<script>
window.onload = function () { window.print(); };
</script>

</body>
</html>