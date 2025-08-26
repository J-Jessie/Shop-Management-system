<?php
session_start();
if ($_SESSION['role'] !== "shopkeeper") {
    header("Location: login.php");
    exit();
}  // Ensure only shopkeepers can access this page
include("db_connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $product_name = $_POST['product_name'];
    $product_type = $_POST['product_type'];
    $quantity = $_POST['quantity'];
    $buying_cost = $_POST['buying_cost'];
    $total_price = $quantity * $buying_cost; // Calculate total price

    // Insert into database
    // In manage_inventory.php
$stmt = $conn->prepare("INSERT INTO inventory (product_name, product_type, quantity, buying_cost, total_price, shopkeeper_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssiddi", $product_name, $product_type, $quantity, $buying_cost, $total_price, $_SESSION['user_id']);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    if ($stmt->execute()) {
        echo "<script>alert('Product added successfully!'); window.location.href = 'shopkeeper_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error adding product.'); window.location.href = 'shopkeeper_dashboard.php';</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    die("Invalid request method.");
}
?>