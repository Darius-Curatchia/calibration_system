<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch description/code BEFORE deleting for meaningful audit log
    $fetch = $pdo->prepare("SELECT description, equipment_code FROM standard_samples WHERE id = :id");
    $fetch->execute([':id' => $id]);
    $record = $fetch->fetch();

    try {
        $stmt = $pdo->prepare("DELETE FROM standard_samples WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $detail = $record
            ? "Deleted: {$record['description']} | Code: {$record['equipment_code']}"
            : "Deleted standard sample ID {$id}";
        log_audit($pdo, 'DELETE', 'standard_samples', $id, $detail);

        header("Location: standard_samples.php");
        exit();
    } catch (PDOException $e) {
        echo "Error deleting record: " . $e->getMessage();
    }
} else {
    header("Location: standard_samples.php");
    exit();
}
?>