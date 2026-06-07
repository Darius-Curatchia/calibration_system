<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Block guests
if (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') {
    header("Location: inspection.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: inspection.php");
    exit();
}

include 'db.php';
include 'audit_helper.php';

$id   = intval($_GET['id']);
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Fetch record details before deleting (for audit log)
$stmt = $pdo->prepare("SELECT equipment_code, description FROM inspection_report WHERE id = :id");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if ($record) {
    $pdo->prepare("DELETE FROM inspection_report WHERE id = :id")->execute([':id' => $id]);

    log_audit(
        $pdo,
        'DELETE',
        'inspection_report',
        $id,
        "Deleted inspection: {$record['equipment_code']} — {$record['description']}"
    );
}

header("Location: inspection.php?page={$page}");
exit();