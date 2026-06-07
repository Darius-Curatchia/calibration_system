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
    UPDATE inspection_report
    SET description          = :description,
        equipment_code       = :equipment_code,
        location             = :location,
        inspection_date      = :inspection_date,
        next_inspection      = :next_inspection,
        inspection_frequency = :inspection_frequency,
        inspection_result    = :inspection_result
    WHERE id = :id
");

try {
    $stmt->execute([
        ':description'          => $_POST['description'],
        ':equipment_code'       => $_POST['equipment_code'],
        ':location'             => $_POST['location'],
        ':inspection_date'      => $_POST['inspection_date'],
        ':next_inspection'      => $_POST['next_inspection'],
        ':inspection_frequency' => $_POST['inspection_frequency'],
        ':inspection_result'    => $_POST['inspection_result'],
        ':id'                   => $id,
    ]);

    log_audit($pdo, 'UPDATE', 'inspection_report', $id,
        "Marked done: {$_POST['description']} | Code: {$_POST['equipment_code']} | Result: {$_POST['inspection_result']} | Next: {$_POST['next_inspection']}");

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}