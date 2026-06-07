<?php
require "db.php";

$result = $pdo->query("PRAGMA table_info(users)");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($columns);
echo "</pre>";