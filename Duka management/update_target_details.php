<?php
session_start();

// Check authentication and user role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_id = $_POST['id'] ?? null;
    $target_amount = $_POST['target_amount'] ?? null;
    $month = $_POST['month'] ?? null;
    $year = $_POST['year'] ?? null;
    $sales_staff_id = $_SESSION['user_id'];

    if ($target_id && $target_amount && $month && $year) {
        try {
            $query = "UPDATE sales_targets SET target_amount = ?, month = ?, year = ? WHERE id = ? AND sales_staff_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("diiii", $target_amount, $month, $year, $target_id, $sales_staff_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['order_message'] = "Target updated successfully!";
            $_SESSION['order_message_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['order_message'] = "Error updating target: " . $e->getMessage();
            $_SESSION['order_message_type'] = "danger";
        }
    } else {
        $_SESSION['order_message'] = "Invalid data provided.";
        $_SESSION['order_message_type'] = "danger";
    }
}

header("Location: sales_targets.php");
exit();
?>