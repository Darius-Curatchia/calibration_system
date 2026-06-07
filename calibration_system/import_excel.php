<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
require 'SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if (isset($_FILES['excel_file']['name'])) {

    $file = $_FILES['excel_file']['tmp_name'];

    if ($xlsx = SimpleXLSX::parse($file)) {

        $rows = $xlsx->rows();

        // Skip header row
        for ($i = 1; $i < count($rows); $i++) {

            $description       = $rows[$i][0] ?? '';
            $machine_code      = $rows[$i][1] ?? '';
            $model_maker       = $rows[$i][2] ?? '';
            $serial_number     = $rows[$i][3] ?? '';
            $location          = $rows[$i][4] ?? '';
            $calibration_date  = $rows[$i][5] ?? '';
            $next_calibration  = $rows[$i][6] ?? '';
            $cal_frequency     = $rows[$i][7] ?? '';
            $calibrator        = $rows[$i][8] ?? '';
            $present_status    = $rows[$i][9] ?? '';

            // Skip empty rows
            if (empty($description)) continue;

            $stmt = $conn->prepare("
                INSERT INTO calibration_report
                (description, machine_code, model_maker, serial_number, location,
                 calibration_date, next_calibration, cal_frequency, calibrator, present_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

        header("Location: calibration_report.php?import=success");
        exit();

    } else {
        echo SimpleXLSX::parseError();
    }

} else {
    echo "No file uploaded.";
}
