<?php
include 'db.php';
if (!isset($_GET['ids'])) die("No IDs provided");

$ids = explode(',', $_GET['ids']);
$size = $_GET['size'] ?? 'small';

// Sticker dimensions
if($size === 'small'){
    $width = '20mm';
    $height = '12.65mm';
    $baseFontSize = 5.5;
    $logoHeight = '9px';
    $showSection = false;
    $rowGap = 1;
    $padding = '2px 2px';
} else {
    $width = '50mm';
    $height = '24mm';
    $baseFontSize = 10;
    $logoHeight = '16px';
    $showSection = true;
    $rowGap = 2;
    $padding = '4px 5px';
}

$fontSize = $baseFontSize . 'px';
$sectionScale = 0.70;
$sectionFontSize = ($baseFontSize * $sectionScale) . 'px';
$labelWidth = $size === 'small' ? 'auto' : '85px';
?>
<!DOCTYPE html>
<html>
<head>
<title>Print Standard Sample Stickers</title>
<style>
body {
    margin: 4mm;
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-family: Arial, Helvetica, sans-serif;
}

.sticker {
    width: <?= $width ?>;
    height: <?= $height ?>;
    border: 2px solid #000;
    padding: <?= $padding ?>;
    font-size: <?= $fontSize ?>;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    page-break-inside: avoid;
    box-sizing: border-box;
}

.sticker img {
    height: <?= $logoHeight ?>;
    width: auto;
    display: block;
    margin: 0 auto <?= $rowGap ?>px auto;
}

.section-text {
    font-size: <?= $sectionFontSize ?>;
    color: #1283da;
    font-weight: bold;
    text-align: center;
    width: 100%;
    margin-bottom: <?= $rowGap ?>px;
}

.info {
    display: flex;
    flex-direction: column;
    gap: <?= $rowGap ?>px;
    width: 100%;
}

.row {
    display: flex;
    align-items: center;
    width: 100%;
}

.label {
    width: <?= $labelWidth ?>;
    text-align: left;
    padding-right: 2px;
}

.colon {
    display: <?= $size === 'small' ? 'none' : 'inline-block' ?>;
    width: 10px;
    text-align: center;
    font-weight: bold;
}

.value {
    flex: 1;
    text-align: center;
    overflow-wrap: anywhere;
}

.cal-due {
    color: red;
    font-weight: bold;
}

@media print {
    @page {
        margin-left: 4mm;
        margin-top: 4mm;
        margin-right: 2mm;
        margin-bottom: 2mm;
    }
    body {
        margin: 0;
    }
}
</style>
</head>
<body>

<?php
$stmt = $pdo->prepare("SELECT * FROM standard_samples WHERE id = :id");

foreach ($ids as $id) {
    $id = intval($id);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        if ($size === 'small') {
            $labels = ['Cal. Date', 'Cal Due', 'Eq. Code', 'Cal. By'];
        } else {
            $labels = ['Calibration Date', 'Calibration Due', 'Equipment Code', 'Calibrated By'];
        }

        $calDate = !empty($row['calibration_date'])      && $row['calibration_date']      !== '0000-00-00' ? date('d-M-y', strtotime($row['calibration_date']))      : '—';
        $nextCal = !empty($row['next_calibration_date']) && $row['next_calibration_date'] !== '0000-00-00' ? date('d-M-y', strtotime($row['next_calibration_date'])) : '—';
        $eqCode  = htmlspecialchars($row['equipment_code']);
        $calibBy = htmlspecialchars($row['calibrator']);
?>

<div class="sticker">
    <img src="Images/Shindengen_logo.png" alt="Logo">

    <?php if ($showSection): ?>
    <div class="section-text">
        Production Engineering Section
    </div>
    <?php endif; ?>

    <div class="info">

        <div class="row">
            <span class="label"><?= $labels[0] ?><?= $size === 'small' ? ':' : '' ?></span>
            <?php if ($size !== 'small'): ?><span class="colon">:</span><?php endif; ?>
            <span class="value"><?= $calDate ?></span>
        </div>

        <div class="row">
            <span class="label"><?= $labels[1] ?><?= $size === 'small' ? ':' : '' ?></span>
            <?php if ($size !== 'small'): ?><span class="colon">:</span><?php endif; ?>
            <span class="value cal-due"><?= $nextCal ?></span>
        </div>

        <div class="row">
            <span class="label"><?= $labels[2] ?><?= $size === 'small' ? ':' : '' ?></span>
            <?php if ($size !== 'small'): ?><span class="colon">:</span><?php endif; ?>
            <span class="value"><?= $eqCode ?></span>
        </div>

        <div class="row">
            <span class="label"><?= $labels[3] ?><?= $size === 'small' ? ':' : '' ?></span>
            <?php if ($size !== 'small'): ?><span class="colon">:</span><?php endif; ?>
            <span class="value"><?= $calibBy ?></span>
        </div>

    </div>
</div>

<?php
    }
}
?>

<script>
window.onload = function() { window.print(); };
</script>

</body>
</html>