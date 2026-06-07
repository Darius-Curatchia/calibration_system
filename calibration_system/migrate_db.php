<?php
require "db.php";

$pdo->exec("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT NULL");

echo "Done! created_at column added.";