<?php
// th_save.php
// AJAX endpoint — upserts one day-row for a given room/month/year.
// Called by temp_humidity.php via fetch() on every cell change + Save button.

session_start();
header('Content-Type: application/json');

// ── Auth guard ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit();
}
if (($_SESSION['role'] ?? '') === 'guest') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Guests cannot save data']);
    exit();
}

include_once __DIR__ . '/db.php';         // provides $pdo
include_once __DIR__ . '/audit_helper.php'; // provides log_audit()

// ── Parse JSON body ────────────────────────────────────────────
$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit();
}

// ── Validate required fields ───────────────────────────────────
$allowed_rooms = ['cal', 'insp', 'cal_ext'];
$room  = $raw['room']  ?? '';
$year  = intval($raw['year']  ?? 0);
$month = intval($raw['month'] ?? 0);
$day   = intval($raw['day']   ?? 0);

if (!in_array($room, $allowed_rooms, true) ||
    $year  < 2000 || $year  > 2100 ||
    $month < 1    || $month > 12   ||
    $day   < 1    || $day   > 31) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid room / date parameters']);
    exit();
}

// ── Sanitise optional fields ───────────────────────────────────
function toFloat(?string $v): ?float  { return ($v === null || $v === '') ? null : (float)$v; }
function toStr(?string $v):   ?string { return ($v === null || $v === '') ? null : trim($v); }

$time_recorded = toStr($raw['time_recorded'] ?? null);
$t_max         = toFloat($raw['t_max']    ?? null);
$t_min         = toFloat($raw['t_min']    ?? null);
$t_actual      = toFloat($raw['t_actual'] ?? null);
$h_max         = toFloat($raw['h_max']    ?? null);
$h_min         = toFloat($raw['h_min']    ?? null);
$h_actual      = toFloat($raw['h_actual'] ?? null);
$remarks       = toStr($raw['remarks']    ?? null);
$checked_by    = toStr($raw['checked_by'] ?? null);
$is_nonworking = isset($raw['is_nonworking']) ? (int)(bool)$raw['is_nonworking'] : 0;
$updated_by    = (int)$_SESSION['user_id'];

// ── Upsert ─────────────────────────────────────────────────────
// SQLite 3.24+ supports "ON CONFLICT ... DO UPDATE" (UPSERT).
// phpdesktop ships PHP 8.3 so the bundled SQLite will be recent enough.
try {
    $sql = "
        INSERT INTO temp_humidity_logs
            (room, year, month, day,
             time_recorded,
             t_max, t_min, t_actual,
             h_max, h_min, h_actual,
             remarks, checked_by,
             is_nonworking, updated_by, updated_at)
        VALUES
            (:room, :year, :month, :day,
             :time_recorded,
             :t_max, :t_min, :t_actual,
             :h_max, :h_min, :h_actual,
             :remarks, :checked_by,
             :is_nonworking, :updated_by, datetime('now','localtime'))
        ON CONFLICT(room, year, month, day) DO UPDATE SET
            time_recorded = excluded.time_recorded,
            t_max         = excluded.t_max,
            t_min         = excluded.t_min,
            t_actual      = excluded.t_actual,
            h_max         = excluded.h_max,
            h_min         = excluded.h_min,
            h_actual      = excluded.h_actual,
            remarks       = excluded.remarks,
            checked_by    = excluded.checked_by,
            is_nonworking = excluded.is_nonworking,
            updated_by    = excluded.updated_by,
            updated_at    = datetime('now','localtime')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':room'          => $room,
        ':year'          => $year,
        ':month'         => $month,
        ':day'           => $day,
        ':time_recorded' => $time_recorded,
        ':t_max'         => $t_max,
        ':t_min'         => $t_min,
        ':t_actual'      => $t_actual,
        ':h_max'         => $h_max,
        ':h_min'         => $h_min,
        ':h_actual'      => $h_actual,
        ':remarks'       => $remarks,
        ':checked_by'    => $checked_by,
        ':is_nonworking' => $is_nonworking,
        ':updated_by'    => $updated_by,
    ]);

    // ── Audit log ──────────────────────────────────────────────
    $roomLabels = ['cal' => 'Calibration Room', 'insp' => 'Inspection Room', 'cal_ext' => 'Calibration Extension Room'];
    $roomLabel  = $roomLabels[$room] ?? $room;
    $dateLabel  = sprintf('%04d-%02d-%02d', $year, $month, $day);

    $allNull = ($time_recorded === null && $t_max === null && $t_min === null &&
                $t_actual === null && $h_max === null && $h_min === null &&
                $h_actual === null && $remarks === null && $checked_by === null);

    if ($is_nonworking) {
        // Marked as non-working day
        $action  = 'NON-WORKING';
        $details = "Marked Day {$day} ({$dateLabel}) as Non-Working in {$roomLabel}";
    } elseif ($allNull) {
        // All fields null = row was cleared
        $action  = 'DELETE';
        $details = "Cleared row for Day {$day} ({$dateLabel}) in {$roomLabel}";
    } else {
        // Normal data save
        $action  = 'EDIT';
        $parts   = [];
        if ($time_recorded !== null) $parts[] = "Time: {$time_recorded}";
        if ($t_actual      !== null) $parts[] = "Temp: {$t_actual}°C";
        if ($h_actual      !== null) $parts[] = "Humidity: {$h_actual}%";
        if ($checked_by    !== null) $parts[] = "Checked by: {$checked_by}";
        $details = "Saved Day {$day} ({$dateLabel}) in {$roomLabel}" . ($parts ? ' — ' . implode(', ', $parts) : '');
    }

    log_audit($pdo, $action, 'temp_humidity_logs', null, $details);

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    error_log('th_save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}