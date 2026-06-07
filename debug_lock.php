<?php
// debug_lock.php
// Place this in your app root and open it in the browser to diagnose
session_start();
require_once __DIR__ . '/lock.php';

echo "<pre>";
echo "session_id: " . session_id() . "\n";
echo "user_id in session: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "LOCK_FILE path: " . LOCK_FILE . "\n";
echo "lock file exists: " . (file_exists(LOCK_FILE) ? 'YES' : 'NO') . "\n";

if (file_exists(LOCK_FILE)) {
    $data = json_decode(file_get_contents(LOCK_FILE), true);
    echo "lock file contents:\n";
    print_r($data);
    echo "lock age (seconds): " . (time() - ($data['time'] ?? 0)) . "\n";
    echo "lock owned by this session: " . (($data['session'] ?? '') === session_id() ? 'YES' : 'NO') . "\n";
}

echo "\ntrying acquireLock()...\n";
$result = acquireLock();
echo "acquireLock() returned: " . ($result ? 'TRUE (write access)' : 'FALSE (read only)') . "\n";
echo "</pre>";