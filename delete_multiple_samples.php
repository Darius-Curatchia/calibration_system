<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
require_once 'audit_helper.php';

if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && count($_POST['selected_ids']) > 0) {

    $ids = array_map('intval', $_POST['selected_ids']);

    // Fetch descriptions before deleting for audit log
    $placeholdersSelect = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
    $paramsSelect = [];
    foreach ($ids as $i => $id) {
        $paramsSelect[":id$i"] = $id;
    }
    $descStmt = $pdo->prepare("SELECT id, description, qc_batch_lot FROM samples WHERE id IN ($placeholdersSelect)");
    $descStmt->execute($paramsSelect);
    $recordMap = [];
    while ($r = $descStmt->fetch(PDO::FETCH_ASSOC)) {
        $recordMap[$r['id']] = "{$r['description']} | Lot: {$r['qc_batch_lot']}";
    }

    // Build placeholders for DELETE
    $placeholders = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
    $params = [];
    foreach ($ids as $i => $id) {
        $params[":id$i"] = $id;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM samples WHERE id IN ($placeholders)");
        $stmt->execute($params);

        foreach ($ids as $deletedId) {
            $detail = isset($recordMap[$deletedId])
                ? "Deleted: " . $recordMap[$deletedId]
                : "Deleted record ID {$deletedId}";
            log_audit($pdo, 'DELETE', 'samples', $deletedId, $detail);
        }

        $_SESSION['message'] = count($ids) . " item(s) deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting selected items: " . $e->getMessage();
    }

} else {
    $_SESSION['message'] = "No items selected for deletion.";
}

header("Location: samples.php");
exit();