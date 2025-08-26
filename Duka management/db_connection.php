<?php
// Database configuration
$servername = 'sql101.infinityfree.com';
$username = 'if0_38648513';
$password = 'M3epPVE5u9ZNtCc';
$database = 'if0_38648513_duka_management';

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection explicitly
if ($conn->connect_error) {
    error_log("DB Connection Failed: " . $conn->connect_error);
    throw new Exception("Database connection failed: " . $conn->connect_error);
} else {
    $conn->set_charset("utf8mb4");
}
?>