<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: exclusion_summary.php");
    exit();
}

$id = intval($_GET['id']);

// Delete record
try {
    $stmt = $pdo->prepare("DELETE FROM exclusion_summary WHERE id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    die("Delete failed: " . $e->getMessage());
}

header("Location: exclusion_summary.php");
exit();
?>