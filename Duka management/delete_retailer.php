<?php
session_start();
require 'db_connection.php';

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "shopkeeper") {
    header("Location: login.php");
    exit();
}

// Get retailer ID
$retailer_id = $_GET['id'] ?? 0;

// Delete retailer
$stmt = $conn->prepare("DELETE FROM retailers WHERE id = ?");
$stmt->bind_param("i", $retailer_id);

if ($stmt->execute()) {
    header("Location: shopkeeper_dashboard.php?success=Retailer+deleted");
} else {
    header("Location: shopkeeper_dashboard.php?error=Error+deleting");
}
exit();
?>