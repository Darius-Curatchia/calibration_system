<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: samples.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch description before deleting for a meaningful audit log
$fetch = $pdo->prepare("SELECT description, qc_batch_lot FROM samples WHERE id = :id");
$fetch->execute([':id' => $id]);
$record = $fetch->fetch();

// Delete the sample
$stmt = $pdo->prepare("DELETE FROM samples WHERE id = :id");
$stmt->execute([':id' => $id]);

// Log after delete
$detail = $record
    ? "Deleted: {$record['description']} | Lot: {$record['qc_batch_lot']}"
    : "Deleted sample ID {$id}";
log_audit($pdo, 'DELETE', 'samples', $id, $detail);

header("Location: samples.php");
exit();