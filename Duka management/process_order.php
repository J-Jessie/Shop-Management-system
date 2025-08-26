<?php
// process_order.php

require_once 'db_connection.php'; // Include your database connection file

//error_reporting(E_ALL);  // Enable for detailed error reporting.  Remove or comment out in production!
//ini_set('display_errors', 1); //  Ditto.

// Function to validate the cart data.
function validateCart($cart) {
    if (!is_array($cart)) {
        error_log("validateCart: Cart is not an array.");
        return false;
    }
    foreach ($cart as $item) {
        if (!isset($item['id'], $item['name'], $item['price'], $item['quantity']) ||
            !is_numeric($item['id']) ||
            !is_string($item['name']) ||
            !is_numeric($item['price']) ||
            !is_numeric($item['quantity']) ||
            $item['quantity'] < 1 ||
            $item['price'] < 0) {
                error_log("validateCart: Invalid cart item: " . print_r($item, true));
            return false;
        }
    }
    return true;
}

// Get the JSON data from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if the data is valid
if (!isset($data['cart'], $data['userId']) || !validateCart($data['cart']) || !is_numeric($data['userId'])) {
    error_log("Invalid order data received: " . $json_data);
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid order data.']);
    exit;
}

$cart = $data['cart'];
$userId = $data['userId'];

error_log("Order request received for user ID: " . $userId . ", cart: " . print_r($cart, true)); // Log the entire cart

// Start a transaction.  This ensures that either the entire order is saved, or nothing is saved.
$conn->begin_transaction();

try {
    // 1. Create the order in the orders table
    $totalAmount = 0;
    foreach ($cart as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }

    $orderQuery = "INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (?, ?, 'pending', NOW())";
    $orderStmt = $conn->prepare($orderQuery);
    if (!$orderStmt) {
        throw new Exception("Failed to prepare order insert statement: " . $conn->error);
    }
    $orderStmt->bind_param('id', $userId, $totalAmount);
    $orderStmt->execute();
    $orderId = $conn->insert_id;  // Get the ID of the newly inserted order.
    $orderStmt->close();

    if (!$orderId) {
        throw new Exception("Failed to insert order.");
    }

    // 2. Insert the order items into the order_items table
    $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price, product_name) VALUES (?, ?, ?, ?, ?)";
    $itemStmt = $conn->prepare($itemQuery);
    if (!$itemStmt) {
        throw new Exception("Failed to prepare order item insert statement: " . $conn->error);
    }

    foreach ($cart as $item) {
        $itemStmt->bind_param('iiids', $orderId, $item['id'], $item['quantity'], $item['price'], $item['name']);
        $itemStmt->execute();
        if ($itemStmt->affected_rows == 0) {
            throw new Exception("Failed to insert order item for product ID: " . $item['id'] . ".  Cart Item: " . print_r($item, true));
        }
    }
    $itemStmt->close();

    // If everything was successful, commit the transaction
    $conn->commit();

    // Send a success response back to the client
    echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);

} catch (Exception $e) {
    // If there was an error, roll back the transaction
    $conn->rollback();

    // Log the error message
    error_log("Error placing order: " . $e->getMessage() . ".  Request Data: " . $json_data); // Include the request data

    // Send an error response back to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
}

// Close the database connection
$conn->close();
?>