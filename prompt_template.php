<!-- Prompt template:

"ChatGPT, I want to make my page [PAGE_NAME].php behave exactly like my standard_sample.php. The page should have:

Sidebar and header includes.

A card layout with a table showing the records.

Top controls: Add new button, search box, status filter, and calibrator filter (or other applicable filters for that page).

Bulk delete functionality with checkboxes, 'Delete Selected' and 'Cancel' buttons, and a selected count badge.

Pagination showing X rows per page (default 10).

JS that filters rows, updates pagination, manages bulk delete checkboxes, and confirms bulk deletion.

Styling similar to standard_sample.php.

Use the same design patterns for table, buttons, and badge.

Please provide the full PHP, HTML, CSS, and JS code for [PAGE_NAME].php using the same layout and functionality as standard_sample.php. 

The database table for this page is [TABLE_NAME], and the relevant columns are [LIST_OF_COLUMNS]." -->




<!-- Prompt to recall your Calibration System goal:

"Recall my PHP-based Calibration Management System that I turned into a PhpDesktop desktop app. The database was originally MySQL via XAMPP on a host PC, and I want to migrate it to SQLite in a shared LAN folder so all PCs can see the same data. Only one user at a time should have write access; others can access in read-only mode. The system should bypass TCP port issues and still allow data persistence across LAN PCs."

If you give me this prompt in a future chat, I’ll instantly remember:

PhpDesktop setup

MySQL → SQLite migration

LAN shared database

Single-user write, multi-user read-only

Your aim to bypass network port issues -->

<!-- 

I am migrating a PHP calibration management system from MySQLi to PDO with SQLite. My db.php now creates a $pdo variable like this:
php

<?php
$pdo = new PDO("sqlite:C:/xampp/htdocs/calibration_system/calibration_db.sqlite");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA foreign_keys = ON');
Migration rules already established:
* $conn → $pdo everywhere
* $stmt->bind_param("s", $var) → $stmt->execute([':param' => $var])
* $result->fetch_assoc() → $stmt->fetch(PDO::FETCH_ASSOC)
* $result->num_rows → count($stmt->fetchAll())
* mysqli_data_seek() → removed (use arrays instead)
* COLLATE utf8mb4_general_ci → removed (not supported in SQLite)
* Errors use try/catch (PDOException $e) instead of $stmt->error
Files already converted: db.php, dashboard.php, calibration_report.php, add_calibration.php, fetch_calibration.php, fetch_inspection.php, fetch_standard_sample.php, update_calibration.php, update_inspection.php, update_standard_sample.php, login_process.php
Please help me convert the next file I paste. Apply the same migration rules — only change the PHP database layer, keep all HTML/CSS/JS identical.

 -->