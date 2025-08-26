<?php
$servername = getenv('DB_HOST') ?: 'sql101.infinityfree.com';
$username = getenv('DB_USER') ?: 'if0_38648513';
$password = getenv('DB_PASS') ?: 'M3epPVE5u9ZNtCc';
$database = getenv('DB_NAME') ?: 'if0_38648513_duka_management';

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection issue. Please try again later.");
}
echo " ";
?>