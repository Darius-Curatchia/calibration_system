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
    UPDATE standard_samples
    SET description           = :description,
        equipment_code        = :equipment_code,
        model_maker           = :model_maker,
        serial_no             = :serial_no,
        location              = :location,
        calibration_date      = :calibration_date,
        next_calibration_date = :next_calibration_date,
        calibration_frequency = :calibration_frequency,
        calibrator            = :calibrator,
        present_status        = :present_status
    WHERE id = :id
");

try {
    $stmt->execute([
        ':description'           => $_POST['description'],
        ':equipment_code'        => $_POST['equipment_code'],
        ':model_maker'           => $_POST['model_maker'],
        ':serial_no'             => $_POST['serial_no'],
        ':location'              => $_POST['location'],
        ':calibration_date'      => $_POST['calibration_date'],
        ':next_calibration_date' => $_POST['next_calibration_date'],
        ':calibration_frequency' => $_POST['calibration_frequency'],
        ':calibrator'            => $_POST['calibrator'],
        ':present_status'        => $_POST['present_status'],
        ':id'                    => $id,
    ]);

    log_audit($pdo, 'UPDATE', 'standard_samples', $id,
        "Marked done: {$_POST['description']} | Code: {$_POST['equipment_code']} | Cal Date: {$_POST['calibration_date']} | Next: {$_POST['next_calibration_date']}");

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}