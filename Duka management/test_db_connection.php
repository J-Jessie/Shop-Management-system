<?php
// Your database credentials
$servername = "localhost"; // Must be localhost for InfinityFree PHP scripts
$username = "if0_38648513";
$password = "M3epPVE5u9ZNtCc";
$dbname = "if0_38648513_duka_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection and output the result
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
} else {
    echo "Connection successful!";
}

$conn->close();
?>