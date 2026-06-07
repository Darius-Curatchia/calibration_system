<?php
$servername = "10.184.2.75";  // host PC LAN IP
$username = "calib_user";
$password = "YourPassword";
$dbname = "calibration_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected successfully!";
}
?>
