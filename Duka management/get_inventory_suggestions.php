<?php
header('Content-Type: application/json');
include 'db_connection.php';

$query = $_GET['q'] ?? '';
$shopkeeper_id = $_GET['shopkeeper_id'] ?? 0;
$suggestions = [];

if (!empty($query) && $shopkeeper_id > 0) {
    $stmt = $conn->prepare("SELECT DISTINCT product_name 
                           FROM inventory 
                           WHERE shopkeeper_id = ? AND product_name LIKE ?
                           LIMIT 5");
    $searchTerm = "%$query%";
    $stmt->bind_param("is", $shopkeeper_id, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['product_name'];
    }
}

echo json_encode($suggestions);
?>