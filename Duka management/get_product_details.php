<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in and is a shopkeeper
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'shopkeeper') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

$product_id = intval($_GET['id']);
$shopkeeper_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT product_name, quantity, buying_cost, description FROM inventory WHERE id = ? AND shopkeeper_id = ?");
$stmt->bind_param("ii", $product_id, $shopkeeper_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit();
}

$product = $result->fetch_assoc();
echo json_encode([
    'product_name' => $product['product_name'],
    'quantity' => $product['quantity'],
    'buying_cost' => $product['buying_cost'],
    'description' => $product['description'] ?? ''
]);

$stmt->close();
$conn->close();
?>