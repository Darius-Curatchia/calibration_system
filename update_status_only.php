<?php
// update_status_only.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') {
    echo json_encode(['success' => false, 'message' => 'Guests are not allowed to update records.']);
    exit();
}

include 'db.php';
require_once 'audit_helper.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$type   = trim($_POST['type']   ?? ''); // 'cal', 'sample', 'insp'
$id     = (int)($_POST['id']    ?? 0);
$status = trim($_POST['status'] ?? '');

if (!$id || !$type || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    if ($type === 'cal') {
        $stmt = $pdo->prepare("UPDATE calibration_report SET present_status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        $row = $pdo->prepare("SELECT description, machine_code FROM calibration_report WHERE id = :id");
        $row->execute([':id' => $id]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        log_audit($pdo, 'UPDATE', 'calibration_report', $id,
            "Status changed to '{$status}': {$row['description']} | Code: {$row['machine_code']}");

    } elseif ($type === 'sample') {
        $stmt = $pdo->prepare("UPDATE standard_samples SET present_status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        $row = $pdo->prepare("SELECT description, equipment_code FROM standard_samples WHERE id = :id");
        $row->execute([':id' => $id]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        log_audit($pdo, 'UPDATE', 'standard_samples', $id,
            "Status changed to '{$status}': {$row['description']} | Code: {$row['equipment_code']}");

    } elseif ($type === 'insp') {
        $stmt = $pdo->prepare("UPDATE inspection_report SET inspection_result = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        $row = $pdo->prepare("SELECT description, equipment_code FROM inspection_report WHERE id = :id");
        $row->execute([':id' => $id]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        log_audit($pdo, 'UPDATE', 'inspection_report', $id,
            "Status changed to '{$status}': {$row['description']} | Code: {$row['equipment_code']}");

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown type']);
        exit();
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();