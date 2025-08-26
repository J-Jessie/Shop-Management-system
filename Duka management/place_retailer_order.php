<?php
session_start();
require 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        // Prepare data - MATCHING YOUR EXACT COLUMN NAMES
        $stmt = $conn->prepare("INSERT INTO retailer_orders 
                              (retailer_name, product, quantity, shopkeeper_id, status) 
                              VALUES (?, ?, ?, ?, 'Pending')");
        
        $stmt->bind_param("ssii", 
            $_POST['retailer_name'],
            $_POST['product'],
            $_POST['quantity'],
            $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            $_SESSION['order_message'] = "Order placed successfully!";
            $_SESSION['order_message_type'] = "success";
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['order_message'] = "Error: " . $e->getMessage();
        $_SESSION['order_message_type'] = "danger";
        error_log("Order Error: " . $e->getMessage());
    }
    
    header("Location: shopkeeper_dashboard.php");
    exit();
}
?>