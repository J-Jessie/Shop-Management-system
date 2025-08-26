<?php
date_default_timezone_set('Africa/Nairobi');
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

$sales_staff_id = $_SESSION['user_id'];
$response = ["success" => true, "data" => []];
$conn->begin_transaction();

try {
    // Fetch actual sales for the logged-in user by month
    $stmt = $conn->prepare("
        SELECT
            MONTH(o.order_date) AS month,
            YEAR(o.order_date) AS year,
            SUM(o.total_amount) AS total_sales
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.sales_staff_id = ?
        GROUP BY YEAR(o.order_date), MONTH(o.order_date)
        ORDER BY YEAR(o.order_date) DESC, MONTH(o.order_date) DESC
        LIMIT 12
    ");
    $stmt->bind_param("i", $sales_staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $actual_sales = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch sales targets for the logged-in user by month
    $stmt = $conn->prepare("
        SELECT
            month,
            year,
            target_amount
        FROM sales_targets
        WHERE sales_staff_id = ?
        ORDER BY year DESC, month DESC
        LIMIT 12
    ");
    $stmt->bind_param("i", $sales_staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sales_targets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $response['data']['actualSales'] = $actual_sales;
    $response['data']['salesTargets'] = $sales_targets;

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    $response = ["success" => false, "message" => "Database error: " . $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>