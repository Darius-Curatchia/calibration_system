<?php
/**
 * save_exclusion_from_dashboard.php
 * Called via fetch() from dashboard.php when a calibration record's status
 * changes from Good to anything else. Inserts a new row into exclusion_summary.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$description    = trim($_POST['description']    ?? '');
$control_number = trim($_POST['control_number'] ?? ''); // machine_code used here
$model_maker    = trim($_POST['model_maker']    ?? '');
$serial_number  = trim($_POST['serial_number']  ?? '');
$location       = trim($_POST['location']       ?? '');
$cal_date       = trim($_POST['cal_date']       ?? '');
$present_status = trim($_POST['present_status'] ?? '');
$remarks        = trim($_POST['remarks']        ?? '');
$recorded_by    = trim($_POST['recorded_by']    ?? '');

if (!$description || !$present_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Use the direction sent from the client instead of re-deriving from status
$exclusion_type = trim($_POST['exclusion_type'] ?? '');
$exclusionStatus = ($exclusion_type === 'included') ? 'included' : 'excluded';

try {
    $stmt = $pdo->prepare("
        INSERT INTO exclusion_summary
            (date_added, description, control_number, model_maker,
             serial_number, location, calibration_inspection_date,
             present_status, remarks, recorded_by)
        VALUES
            (:date_added, :description, :control_number, :model_maker,
             :serial_number, :location, :cal_date,
             :present_status, :remarks, :recorded_by)
    ");
    $stmt->execute([
        ':date_added'      => date('Y-m-d'),
        ':description'     => $description,
        ':control_number'  => $control_number,
        ':model_maker'     => $model_maker,
        ':serial_number'   => $serial_number,
        ':location'        => $location,
        ':cal_date'        => $cal_date ?: null,
        ':present_status'  => $exclusionStatus,
        ':remarks'         => $remarks,
        ':recorded_by'     => $recorded_by,
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();