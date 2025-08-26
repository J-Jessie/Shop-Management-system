<?php
date_default_timezone_set('Africa/Nairobi');
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff')) {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$target_id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;

if ($target_id && $status) {
    try {
        $stmt = $conn->prepare("UPDATE sales_targets SET status = ? WHERE id = ? AND sales_staff_id = ?");
        $stmt->bind_param("sii", $status, $target_id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['order_message'] = "Target status updated successfully!";
        $_SESSION['order_message_type'] = "success";

    } catch (Exception $e) {
        $_SESSION['order_message'] = "Error updating target: " . $e->getMessage();
        $_SESSION['order_message_type'] = "danger";
    }
} else {
    $_SESSION['order_message'] = "Invalid request.";
    $_SESSION['order_message_type'] = "danger";
}

$conn->close();
header("Location: sales_targets.php");
exit();
?>