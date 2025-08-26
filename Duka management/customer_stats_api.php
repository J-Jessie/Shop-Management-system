<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

require_once 'db_connection.php';

$response = ["success" => true, "data" => []];
$conn->begin_transaction();

try {
    // 1. Total Customers
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_customers FROM customers");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_customers = $result->fetch_assoc()['total_customers'];
    $stmt->close();
    $response['data']['totalCustomers'] = $total_customers;

    // 2. Customers online in the last 7 days
    $stmt = $conn->prepare("SELECT COUNT(id) AS active_customers FROM customers WHERE last_login_at >= NOW() - INTERVAL 7 DAY");
    $stmt->execute();
    $result = $stmt->get_result();
    $active_customers = $result->fetch_assoc()['active_customers'];
    $stmt->close();
    $response['data']['customersOnline7Days'] = $active_customers;
    
    // 3. Customers currently online (an approximation)
    // A timestamp within the last 5 minutes is a reasonable approximation
    $stmt = $conn->prepare("SELECT COUNT(id) AS current_online FROM customers WHERE last_login_at >= NOW() - INTERVAL 5 MINUTE");
    $stmt->execute();
    $result = $stmt->get_result();
    $current_online = $result->fetch_assoc()['current_online'];
    $stmt->close();
    $response['data']['customersOnline'] = $current_online;

    // 4. Who bought what product (Top 5 products by quantity sold)
    $stmt = $conn->prepare("
        SELECT p.name AS product_name, SUM(oi.quantity) AS total_quantity_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY p.name
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $top_products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $response['data']['topProducts'] = $top_products;

    // 5. Who bought what product type (Top 5 product types by quantity sold)
    $stmt = $conn->prepare("
        SELECT p.product_type AS product_type, SUM(oi.quantity) AS total_quantity_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY p.product_type
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $top_product_types = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $response['data']['topProductTypes'] = $top_product_types;

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    $response = ["success" => false, "message" => "Database error: " . $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>