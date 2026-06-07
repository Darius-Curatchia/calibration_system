<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Block guests
if (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') {
    header("Location: exclusion_summary.php");
    exit();
}

include 'db.php';
include 'audit_helper.php';

if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && count($_POST['selected_ids']) > 0) {

    $ids          = array_map('intval', $_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Fetch records before deleting (for audit log)
    $fetchStmt = $pdo->prepare("SELECT id, description, control_number, present_status FROM exclusion_summary WHERE id IN ($placeholders)");
    $fetchStmt->execute($ids);
    $records = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmt = $pdo->prepare("DELETE FROM exclusion_summary WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        foreach ($records as $rec) {
            log_audit(
                $pdo,
                'DELETE',
                'exclusion_summary',
                (int)$rec['id'],
                "Bulk deleted exclusion record: {$rec['control_number']} — {$rec['description']} ({$rec['present_status']})"
            );
        }

        $_SESSION['message'] = count($ids) . " record(s) deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting selected records: " . $e->getMessage();
    }

} else {
    $_SESSION['message'] = "No items selected for deletion.";
}

header("Location: exclusion_summary.php");
exit();