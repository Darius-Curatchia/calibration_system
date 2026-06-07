<?php
session_start();

// Assign Guest role
$_SESSION['user_id'] = 0;       // optional
$_SESSION['username'] = 'Guest';
$_SESSION['role'] = 'guest';

// Redirect to dashboard
header("Location: dashboard.php");
exit;
