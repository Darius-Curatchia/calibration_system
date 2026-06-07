<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: edit_calibration.php");
    exit();
}

$id         = intval($_GET['id']);
$returnPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Fetch record details before deleting for a meaningful audit log
$fetch = $pdo->prepare("SELECT description, machine_code FROM calibration_report WHERE id = :id");
$fetch->execute([':id' => $id]);
$record = $fetch->fetch(PDO::FETCH_ASSOC);

// Delete the record
$stmt = $pdo->prepare("DELETE FROM calibration_report WHERE id = :id");
$stmt->execute([':id' => $id]);

// Log after delete
$detail = $record
    ? "Deleted: {$record['description']} | Code: {$record['machine_code']}"
    : "Deleted record ID {$id}";
log_audit($pdo, 'DELETE', 'calibration_report', $id, $detail);

header("Location: calibration_report.php?page={$returnPage}");
exit();