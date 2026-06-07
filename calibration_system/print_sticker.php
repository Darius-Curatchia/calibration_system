<?php
include 'db.php';

if (!isset($_GET['id'])) die("No ID provided");

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM calibration_report WHERE id = $id");
if ($result->num_rows != 1) die("Record not found");

$row = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Sticker</title>
    <style>
        body {
            margin: 0;
        }
        .sticker {
            width: 90mm;      /* width of your small sticker */
            height: 30mm;     /* height of your sticker */
            border: 1px solid #000; /* optional, for visualization */
            padding: 5px;
            font-size: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        @media print {
    body { margin: 0; }
    .sticker {
        border: 1px solid #000; /* KEEP the border */
    }
}
    </style>
</head>
<body>
    <div class="sticker">
        <div>Cal. Date: <?= htmlspecialchars($row['calibration_date']) ?></div>
        <div>Next: <?= $row['next_calibration'] ?></div>
        <div>Eq. Code: <?= $row['machine_code'] ?></div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
