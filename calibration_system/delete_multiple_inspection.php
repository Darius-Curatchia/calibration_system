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

include 'db.php';
include 'audit_helper.php';

$page = isset($_POST['page']) ? intval($_POST['page']) : 1;

if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && count($_POST['selected_ids']) > 0) {

    $ids          = array_map('intval', $_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Fetch records before deleting (for audit log)
    $fetchStmt = $pdo->prepare("SELECT id, equipment_code, description FROM inspection_report WHERE id IN ($placeholders)");
    $fetchStmt->execute($ids);
    $records = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmt = $pdo->prepare("DELETE FROM inspection_report WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        // Log each deleted record individually so the audit trail is granular
        foreach ($records as $rec) {
            log_audit(
                $pdo,
                'DELETE',
                'inspection_report',
                (int)$rec['id'],
                "Bulk deleted inspection: {$rec['equipment_code']} — {$rec['description']}"
            );
        }

        $_SESSION['message'] = count($ids) . " inspection record(s) deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting selected records: " . $e->getMessage();
    }

} else {
    $_SESSION['message'] = "No items selected for deletion.";
}

header("Location: inspection.php?page={$page}");
exit();