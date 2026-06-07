<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') {
    echo json_encode(['success' => false, 'message' => 'Guests are not allowed to update records.']);
    exit();
}

$id = intval($_POST['id']);

$stmt = $pdo->prepare("
    UPDATE calibration_report
    SET description      = :description,
        machine_code     = :machine_code,
        location         = :location,
        calibration_date = :calibration_date,
        next_calibration = :next_calibration,
        cal_frequency    = :cal_frequency,
        calibrator       = :calibrator,
        present_status   = :present_status
    WHERE id = :id
");

try {
    $stmt->execute([
        ':description'      => $_POST['description'],
        ':machine_code'     => $_POST['machine_code'],
        ':location'         => $_POST['location'],
        ':calibration_date' => $_POST['calibration_date'],
        ':next_calibration' => $_POST['next_calibration'],
        ':cal_frequency'    => $_POST['cal_frequency'],
        ':calibrator'       => $_POST['calibrator'],
        ':present_status'   => $_POST['present_status'],
        ':id'               => $id,
    ]);

    log_audit($pdo, 'UPDATE', 'calibration_report', $id,
        "Marked done: {$_POST['description']} | Code: {$_POST['machine_code']} | Cal Date: {$_POST['calibration_date']} | Next: {$_POST['next_calibration']}");

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}