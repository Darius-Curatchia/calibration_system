<?php
session_start();
include 'db.php';
require_once 'audit_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && count($_POST['selected_ids']) > 0) {

    $ids = array_map('intval', $_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Fetch descriptions/codes BEFORE deleting for meaningful audit logs
    $fetchStmt = $pdo->prepare("SELECT id, description, equipment_code FROM standard_samples WHERE id IN ($placeholders)");
    $fetchStmt->execute($ids);
    $records = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    $recordMap = [];
    foreach ($records as $rec) {
        $recordMap[$rec['id']] = "{$rec['description']} | Code: {$rec['equipment_code']}";
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM standard_samples WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        foreach ($ids as $deletedId) {
            $detail = isset($recordMap[$deletedId])
                ? "Deleted: " . $recordMap[$deletedId]
                : "Deleted standard sample ID {$deletedId}";
            log_audit($pdo, 'DELETE', 'standard_samples', $deletedId, $detail);
        }

        header("Location: standard_samples.php");
        exit();
    } catch (PDOException $e) {
        echo "Error deleting records: " . $e->getMessage();
    }
} else {
    header("Location: standard_samples.php");
    exit();
}
?>