<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is a shopkeeper
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'shopkeeper') {
    header("Location: login.php");
    exit();
}

$shopkeeper_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate inputs
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($product_id <= 0 || $quantity <= 0 || $price <= 0) {
            throw new Exception("All fields are required");
        }

        // Verify the product belongs to the shopkeeper
        $check_stmt = $conn->prepare("SELECT product_name, product_type, quantity, buying_cost FROM inventory WHERE id = ? AND shopkeeper_id = ?");
        $check_stmt->bind_param("ii", $product_id, $shopkeeper_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Product not found in your inventory");
        }
        
        $product = $check_result->fetch_assoc();
        
        // Validate quantity
        if ($product['quantity'] < $quantity) {
            throw new Exception("Not enough quantity in inventory (Available: {$product['quantity']})");
        }
        
        // Validate price
        if ($price < $product['buying_cost']) {
            throw new Exception("Selling price cannot be below buying cost (".number_format($product['buying_cost'], 2).")");
        }
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Validate image
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, and GIF images are allowed");
            }
            
            if ($_FILES['image']['size'] > 2097152) { // 2MB
                throw new Exception("Image size must be less than 2MB");
            }
            
            $upload_dir = 'uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            } else {
                throw new Exception("Failed to upload image");
            }
        } else {
            throw new Exception("Product image is required");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into customer products table
            $insert_stmt = $conn->prepare("INSERT INTO customer_products 
                                         (inventory_id, shopkeeper_id, product_name, product_type, description, price, quantity, image_url, posted_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("iisssdss", 
                $product_id, 
                $shopkeeper_id, 
                $product['product_name'], 
                $product['product_type'], 
                $description, 
                $price, 
                $quantity, 
                $image_path
            );
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Error posting product: " . $conn->error);
            }
            
            // Deduct from inventory
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $update_stmt->bind_param("ii", $quantity, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating inventory: " . $conn->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['order_message'] = "Product posted successfully to customers!";
            $_SESSION['order_message_type'] = "success";
            header("Location: shopkeeper_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
} catch (Exception $e) {
    $_SESSION['order_message'] = $e->getMessage();
    $_SESSION['order_message_type'] = "danger";
    header("Location: shopkeeper_dashboard.php#post");
    exit();
}
?>