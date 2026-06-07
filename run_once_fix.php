<?php
// run_once_fix.php — place this in your www folder and run it ONCE from the host PC browser
// Delete this file after running it.

require "db.php";

$mode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
echo "Current journal mode: $mode <br>";

$pdo->exec("PRAGMA journal_mode = DELETE");

$mode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
echo "New journal mode: $mode <br>";

// Confirm WAL files are gone
$base = __DIR__ . "/calibration_db.sqlite";
echo file_exists($base . "-wal") ? "⚠️ WAL file still exists<br>" : "✅ No WAL file<br>";
echo file_exists($base . "-shm") ? "⚠️ SHM file still exists<br>" : "✅ No SHM file<br>";