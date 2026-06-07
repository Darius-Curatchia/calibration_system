<?php
// th_load.php
// AJAX endpoint — returns all saved rows for one room + month + year as JSON.
// Called by temp_humidity.php on page load, tab switch, and month/year change.
// Guests are allowed to read (view-only).

session_start();
header('Content-Type: application/json');

// ── Auth guard ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit();
}

include_once __DIR__ . '/db.php';   // provides $pdo

// ── Validate GET params ────────────────────────────────────────
$allowed_rooms = ['cal', 'insp', 'cal_ext'];
$room  = $_GET['room']  ?? '';
$year  = intval($_GET['year']  ?? 0);
$month = intval($_GET['month'] ?? 0);

if (!in_array($room, $allowed_rooms, true) ||
    $year  < 2000 || $year  > 2100 ||
    $month < 1    || $month > 12) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit();
}

// ── Query ──────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT day, time_recorded,
               t_max, t_min, t_actual,
               h_max, h_min, h_actual,
               remarks, checked_by,
               is_nonworking
        FROM   temp_humidity_logs
        WHERE  room  = :room
          AND  year  = :year
          AND  month = :month
        ORDER  BY day ASC
    ");
    $stmt->execute([':room' => $room, ':year' => $year, ':month' => $month]);
    $rows = $stmt->fetchAll();

    // Re-key by day number for easy JS lookup: { "1": {...}, "5": {...} }
    $byDay = [];
    foreach ($rows as $r) {
        $byDay[(int)$r['day']] = [
            'time_recorded' => $r['time_recorded'],
            't_max'         => $r['t_max'],
            't_min'         => $r['t_min'],
            't_actual'      => $r['t_actual'],
            'h_max'         => $r['h_max'],
            'h_min'         => $r['h_min'],
            'h_actual'      => $r['h_actual'],
            'remarks'       => $r['remarks'],
            'checked_by'    => $r['checked_by'],
            'is_nonworking' => (bool)$r['is_nonworking'],
        ];
    }

    echo json_encode(['ok' => true, 'data' => $byDay]);

} catch (Exception $e) {
    error_log('th_load error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}