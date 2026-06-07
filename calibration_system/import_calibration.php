<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['import'])) {

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
        die("Please upload a valid CSV file.");
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], "r");

    // Skip header row
    fgetcsv($file);

    while (($row = fgetcsv($file, 1000, ",")) !== FALSE) {

        // Trim values
        $description       = trim($row[0]);
        $machine_code      = trim($row[1]);
        $model_maker       = trim($row[2]);
        $serial_number     = trim($row[3]);
        $location          = trim($row[4]);
        $calibration_date  = trim($row[5]);
        $cal_frequency     = trim($row[6]);
        $calibrator        = trim($row[7]);
        $present_status    = trim($row[8]);

        // Skip empty rows
        if ($description == "" || $calibration_date == "") {
            continue;
        }

        // Compute next calibration
        $next_calibration = null;
        if ($cal_frequency && $calibration_date) {
            $date = new DateTime($calibration_date);

            if (strpos($cal_frequency, 'month') !== false) {
                $months = (int)$cal_frequency;
                $date->modify("+{$months} months");
            } elseif (strpos($cal_frequency, 'year') !== false) {
                $years = (int)$cal_frequency;
                $date->modify("+{$years} years");
            }

            $next_calibration = $date->format('Y-m-d');
        }

        // Insert record
        $stmt = $conn->prepare("
            INSERT INTO calibration_report
            (description, machine_code, model_maker, serial_number, location,
             calibration_date, next_calibration, cal_frequency, calibrator, present_status)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "ssssssssss",
            $description,
            $machine_code,
            $model_maker,
            $serial_number,
            $location,
            $calibration_date,
            $next_calibration,
            $cal_frequency,
            $calibrator,
            $present_status
        );

        $stmt->execute();
    }

    fclose($file);

    header("Location: calibration_report.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Import Calibration CSV</title>
<link rel="stylesheet" href="assets/css/main.css">
<style>
.card {
    max-width: 500px;
    margin: 50px auto;
    padding: 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
button {
    background: #073c64;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
}
</style>
</head>
<body>

<div class="card">
    <h2>Import Calibration Data</h2>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required><br><br>
        <button type="submit" name="import">Import CSV</button>
    </form>
</div>

</body>
</html>
