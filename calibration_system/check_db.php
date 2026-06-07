<?php
require "db.php";

$result = $pdo->query("PRAGMA table_info(standard_samples)");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($columns);
echo "</pre>";