<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
require_once 'audit_helper.php';

if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && count($_POST['selected_ids']) > 0) {

    // Sanitize IDs to integers
    $ids = array_map('intval', $_POST['selected_ids']);

    // Fetch descriptions before deleting so we can log them meaningfully
    $placeholdersSelect = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
    $paramsSelect = [];
    foreach ($ids as $i => $id) {
        $paramsSelect[":id$i"] = $id;
    }
    $descStmt = $pdo->prepare("SELECT id, description, machine_code FROM calibration_report WHERE id IN ($placeholdersSelect)");
    $descStmt->execute($paramsSelect);
    $recordMap = [];
    while ($r = $descStmt->fetch(PDO::FETCH_ASSOC)) {
        $recordMap[$r['id']] = "{$r['description']} | Code: {$r['machine_code']}";
    }

    // Build placeholders for DELETE
    $placeholders = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
    $params = [];
    foreach ($ids as $i => $id) {
        $params[":id$i"] = $id;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM calibration_report WHERE id IN ($placeholders)");
        $stmt->execute($params);

        // Log each deleted record individually
        foreach ($ids as $deletedId) {
            $detail = isset($recordMap[$deletedId])
                ? "Deleted: " . $recordMap[$deletedId]
                : "Deleted record ID {$deletedId}";
            log_audit($pdo, 'DELETE', 'calibration_report', $deletedId, $detail);
        }

        $_SESSION['message'] = count($ids) . " item(s) deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting selected items: " . $e->getMessage();
    }

} else {
    $_SESSION['message'] = "No items selected for deletion.";
}

header("Location: calibration_report.php");
exit();