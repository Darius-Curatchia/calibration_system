<?php
/**
 * dashboard_poll.php
 * Returns a hash of the current dashboard-relevant data state.
 * Detects: adds, deletes, AND edits (date changes, status changes).
 * 
 *  THE ONE RESPONSIBLE FOR AUTO REFRESH EVERY 30 SECONDS ON DASHBOARD.PHP
 * 
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(204);
    exit();
}
include 'db.php';

header('Content-Type: application/json');

// Collect all values the dashboard actually displays or filters on.
// Concatenate them into one string and MD5 it — any change anywhere flips the hash.
$parts = [];

// Calibration: count, max id, and all next_calibration + present_status values
$rows = $pdo->query("SELECT id, next_calibration, present_status FROM calibration_report ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $parts[] = $r['id'] . $r['next_calibration'] . $r['present_status'];
}

// Standard samples: count, max id, and all next_calibration_date + present_status values
$rows = $pdo->query("SELECT id, next_calibration_date, present_status FROM standard_samples ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $parts[] = $r['id'] . $r['next_calibration_date'] . $r['present_status'];
}

// Inspection: count, max id, and all next_inspection + inspection_result values
$rows = $pdo->query("SELECT id, next_inspection, inspection_result FROM inspection_report ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $parts[] = $r['id'] . $r['next_inspection'] . $r['inspection_result'];
}

echo json_encode(['hash' => md5(implode('|', $parts))]);