<?php
// customer_dashboard.php

// Initialize session and include database connection
session_start();
require_once 'db_connection.php';

// Check if the user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== "customer") {
    header("Location: Login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Customer';

// Function to fetch products
function fetchProducts($conn) {
    $products = [];
    try {
        $productQuery = "SELECT * FROM customer_products";
        $productResult = $conn->query($productQuery);

        if (!$productResult) {
            throw new Exception("Error fetching products: " . $conn->error);
        }

        while ($product = $productResult->fetch_assoc()) {
            $products[] = $product;
        }
        return $products;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Function to fetch orders
function fetchOrders($conn, $userId) {
    $orders = [];
    try {
        $orderQuery = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
        $orderStmt = $conn->prepare($orderQuery);
        if (!$orderStmt) {
            throw new Exception("Error preparing order query: " . $conn->error);
        }
        $orderStmt->bind_param("i", $userId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        if (!$orderResult) {
            throw new Exception("Error fetching orders: " . $orderStmt->error);
        }

        while ($order = $orderResult->fetch_assoc()) {
            $orders[] = $order;
        }
        $orderStmt->close();
        return $orders;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Fetch data
$products = ($conn) ? fetchProducts($conn) : [];
$orders = ($conn) ? fetchOrders($conn, $userId) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - E-commerce</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    :root {
        --primary-color: #6C63FF; /* Vibrant purple */
        --primary-dark: #5649FF;
        --secondary-color: #FF6584; /* Pink */
        --secondary-dark: #E04D6D;
        --accent-color: #00BFA6; /* Teal */
        --accent-dark: #009C85;
        --danger-color: #FF3860; /* Red */
        --danger-dark: #E02D4E;
        --success-color: #48C774; /* Green */
        --warning-color: #FFDD57; /* Yellow */
        --light-color: #F8F9FF;
        --dark-color: #2E2E3A;
        --gray-color: #8C8C9E;
        --border-color: #E2E3ED;
        --card-shadow: 0 4px 20px rgba(108, 99, 255, 0.1);
        --hover-shadow: 0 15px 30px rgba(108, 99, 255, 0.2);
        
        /* Gradient colors */
        --gradient-primary: linear-gradient(135deg, #6C63FF 0%, #8B83FF 100%);
        --gradient-secondary: linear-gradient(135deg, #FF6584 0%, #FF83A0 100%);
        --gradient-accent: linear-gradient(135deg, #00BFA6 0%, #00D1B2 100%);
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--light-color);
        color: var(--dark-color);
        line-height: 1.6;
    }
    
    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    /* Header Styles */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 0;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .header h1 {
        font-size: 2rem;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
        display: flex;
        align-items: center;
    }
    
    .header h1 i {
        margin-right: 10px;
    }
    
    .header-actions {
        display: flex;
        gap: 15px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 24px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .btn-primary {
        background: var(--gradient-primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
    }
    
    .btn-secondary {
        background: var(--gradient-secondary);
        color: white;
    }
    
    .btn-secondary:hover {
        background: var(--secondary-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255, 101, 132, 0.3);
    }
    
    .btn-accent {
        background: var(--gradient-accent);
        color: white;
    }
    
    .btn-accent:hover {
        background: var(--accent-dark);
        transform: translateY(-3px);
    }
    
    .btn-danger {
        background: var(--danger-color);
        color: white;
    }
    
    .btn-danger:hover {
        background: var(--danger-dark);
        transform: translateY(-3px);
    }
    
    .btn i {
        margin-right: 8px;
    }
    
    /* Filter Section */
    .shop-filter {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: var(--card-shadow);
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
    }
    
    .shop-filter label {
        font-weight: 600;
        margin-right: 15px;
        color: var(--dark-color);
    }
    
    .shop-filter select {
        padding: 10px 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-family: 'Poppins', sans-serif;
        min-width: 200px;
        background-color: white;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .shop-filter select:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
    }
    
    /* Product Grid */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }
    
    .product-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
    }
    
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--hover-shadow);
        border-color: var(--primary-color);
    }
    
    .product-image-container {
        height: 200px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(45deg, #F8F9FF 0%, #E2E3FF 100%);
        position: relative;
    }
    
    .product-image {
        max-width: 80%;
        max-height: 80%;
        object-fit: contain;
        transition: transform 0.3s ease;
        z-index: 1;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.1);
    }
    
    .product-info {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--dark-color);
    }
    
    .product-shop {
        font-size: 0.9rem;
        color: var(--gray-color);
        margin-bottom: 5px;
    }
    
    .product-price {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 10px 0;
    }
    
    .product-actions {
        margin-top: auto;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .quantity-input {
        width: 70px;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        text-align: center;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
    }
    
    .add-to-cart {
        flex-grow: 1;
        padding: 10px;
        font-size: 14px;
    }
    
    /* Orders Section */
    .section-title {
        font-size: 1.5rem;
        color: var(--dark-color);
        margin: 30px 0 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -2px;
        width: 100px;
        height: 3px;
        background: var(--gradient-primary);
        border-radius: 3px;
    }
    
    .order-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
    }
    
    .order-table th {
        background: var(--gradient-primary);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 500;
    }
    
    .order-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .order-table tr:last-child td {
        border-bottom: none;
    }
    
    .order-table tr:nth-child(even) {
        background-color: #F8FAFF;
    }
    
    .order-table tr:hover {
        background-color: #F0F2FF;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-pending {
        background-color: #FFF3E0;
        color: #E65100;
    }
    
    .status-completed {
        background-color: #E8F5E9;
        color: #2E7D32;
    }
    
    .status-processing {
        background-color: #E3F2FD;
        color: #1565C0;
    }
    
    /* Cart Sidebar */
    .cart-sidebar {
        position: fixed;
        right: -400px;
        top: 0;
        width: 400px;
        height: 100vh;
        background: white;
        box-shadow: -5px 0 30px rgba(0, 0, 0, 0.15);
        padding: 25px;
        overflow-y: auto;
        z-index: 1000;
        transition: right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
    }
    
    .cart-sidebar.active {
        right: 0;
    }
    
    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .cart-header h2 {
        font-size: 1.5rem;
        background: var(--gradient-secondary);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
    }
    
    .close-cart {
        background: none;
        border: none;
        font-size: 1.8rem;
        cursor: pointer;
        color: var(--gray-color);
        transition: all 0.3s ease;
    }
    
    .close-cart:hover {
        color: var(--danger-color);
        transform: rotate(90deg);
    }
    
    .cart-item {
        display: flex;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .cart-item:hover {
        background: #FAFAFF;
        border-radius: 8px;
        padding: 10px;
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .cart-item-image {
        width: 80px;
        height: 80px;
        object-fit: contain;
        margin-right: 15px;
        background: linear-gradient(45deg, #F8F9FF 0%, #E2E3FF 100%);
        border-radius: 8px;
        padding: 10px;
    }
    
    .cart-item-details {
        flex-grow: 1;
    }
    
    .cart-item-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--dark-color);
    }
    
    .cart-item-shop {
        font-size: 0.8rem;
        color: var(--gray-color);
        margin-bottom: 5px;
    }
    
    .cart-item-price {
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .cart-item-actions {
        display: flex;
        align-items: center;
        margin-top: 5px;
    }
    
    .cart-item-quantity {
        width: 50px;
        padding: 5px;
        text-align: center;
        margin-right: 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-family: 'Poppins', sans-serif;
    }
    
    .remove-item {
        background: none;
        border: none;
        color: var(--danger-color);
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .remove-item:hover {
        color: var(--danger-dark);
    }
    
    .remove-item i {
        margin-right: 5px;
    }
    
    .cart-summary {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .cart-total {
        display: flex;
        justify-content: space-between;
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--dark-color);
    }
    
    .cart-total span:last-child {
        color: var(--primary-color);
    }
    
    .cart-empty {
        text-align: center;
        padding: 40px 0;
        color: var(--gray-color);
    }
    
    .cart-empty i {
        font-size: 3.5rem;
        margin-bottom: 15px;
        color: var(--border-color);
        opacity: 0.5;
    }
    
    .cart-empty h3 {
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    /* Overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    
    .overlay.active {
        opacity: 1;
        pointer-events: all;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .header-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
        
        .cart-sidebar {
            width: 90%;
            right: -90%;
        }
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
        animation: fadeIn 0.4s ease forwards;
    }
    
    /* Toast Notifications */
    .toast {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 15px 25px;
        border-radius: 50px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 15px;
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        z-index: 1100;
    }
    
    .toast.show {
        opacity: 1;
        bottom: 30px;
    }
    
    .toast-success {
        border-left: 4px solid var(--success-color);
    }
    
    .toast-error {
        border-left: 4px solid var(--danger-color);
    }
    
    .toast-info {
        border-left: 4px solid var(--accent-color);
    }
    
    .toast-success .toast-icon {
        color: var(--success-color);
    }
    
    .toast-error .toast-icon {
        color: var(--danger-color);
    }
    
    .toast-info .toast-icon {
        color: var(--accent-color);
    }
    
    .toast-message {
        font-weight: 500;
    }
    
    /* Floating Cart Button */
    .floating-cart-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--gradient-secondary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px rgba(255, 101, 132, 0.3);
        cursor: pointer;
        z-index: 100;
        transition: all 0.3s ease;
    }
    
    .floating-cart-btn:hover {
        transform: translateY(-5px) scale(1.1);
        box-shadow: 0 15px 30px rgba(255, 101, 132, 0.4);
    }
    
    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--danger-color);
        color: white;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
    }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($fullname); ?></h1>
            <div class="header-actions">
                <button id="cart-toggle" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Cart (<span id="cart-count">0</span>)
                </button>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="shop-filter">
            <label for="shop-select"><i class="fas fa-store"></i> Filter by Shop:</label>
            <select id="shop-select">
                <option value="all">All Shops</option>
                <?php
                if ($conn) {
                    $shopsQuery = "SELECT DISTINCT shop_name FROM customer_products";
                    $shopsResult = $conn->query($shopsQuery);
                    if ($shopsResult) {
                        while ($shop = $shopsResult->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($shop['shop_name']) . "'>" . htmlspecialchars($shop['shop_name']) . "</option>";
                        }
                    } else {
                        error_log("Error fetching shops: " . $conn->error);
                        echo "<option value=''>Error loading shops</option>";
                    }
                }
                ?>
            </select>
        </div>

        <div class="product-grid" id="products-container">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class='product-card' data-shop='<?php echo htmlspecialchars($product['shop_name']); ?>' data-product-id='<?php echo htmlspecialchars($product['id']); ?>'>
                        <div class="product-image-container">
                            <img src='<?php echo htmlspecialchars($product['image_url']); ?>' alt='<?php echo htmlspecialchars($product['name']); ?>' class='product-image'>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-shop"><i class="fas fa-store"></i> <?php echo htmlspecialchars($product['shop_name']); ?></p>
                            <p class="product-price">$<?php echo htmlspecialchars($product['price']); ?></p>
                            <div class="product-actions">
                                <input type='number' min='1' value='1' class='quantity-input'>
                                <button class='btn btn-primary add-to-cart' data-product-name='<?php echo htmlspecialchars($product['name']); ?>' data-product-price='<?php echo htmlspecialchars($product['price']); ?>'>
                                    <i class="fas fa-cart-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="cart-empty">
                    <i class="fas fa-box-open"></i>
                    <h3>No products available</h3>
                    <p>Check back later for new products</p>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Your Orders</h2>
        <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr class="fade-in">
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = '';
                                    if ($order['status'] === 'completed') $statusClass = 'status-completed';
                                    elseif ($order['status'] === 'processing') $statusClass = 'status-processing';
                                    else $statusClass = 'status-pending';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>$<?php echo htmlspecialchars($order['total_amount']); ?></td>
                                <td>
                                    <button class='btn btn-accent track-order' data-id='<?php echo htmlspecialchars($order['id']); ?>'>
                                        <i class="fas fa-truck"></i> Track
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cart-empty">
                <i class="fas fa-clipboard"></i>
                <h3>No orders found</h3>
                <p>Your orders will appear here once you place them</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Floating Cart Button (Mobile) -->
    <div class="floating-cart-btn" id="floating-cart-toggle">
        <i class="fas fa-shopping-cart"></i>
        <div class="cart-badge" id="floating-cart-count">0</div>
    </div>

    <!-- Cart Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cart-sidebar">
        <div class="cart-header">
            <h2><i class="fas fa-shopping-cart"></i> Your Cart</h2>
            <button class="close-cart" id="close-cart">&times;</button>
        </div>
        
        <div id="cart-items">
            <!-- Cart items will be inserted here by JavaScript -->
        </div>
        
        <div class="cart-summary">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cart-total">$0.00</span>
            </div>
            <form id="checkout-form">
                <button type="submit" class="btn btn-secondary checkout-btn" id="place-order-btn">
                    <i class="fas fa-credit-card"></i> Checkout Now
                </button>
            </form>
        </div>
    </div>

    <script>
        // JavaScript for enhanced cart management and UI interactions
        document.addEventListener('DOMContentLoaded', function() {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            const cartSidebar = document.getElementById('cart-sidebar');
            const overlay = document.getElementById('overlay');
            const cartToggle = document.getElementById('cart-toggle');
            const floatingCartToggle = document.getElementById('floating-cart-toggle');
            const closeCart = document.getElementById('close-cart');
            
            // Initialize cart count
            updateCartCount();
            
            // Toggle cart sidebar
            function toggleCart() {
                cartSidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                renderCart();
            }
            
            cartToggle.addEventListener('click', toggleCart);
            floatingCartToggle.addEventListener('click', toggleCart);
            
            // Close cart sidebar
            closeCart.addEventListener('click', function() {
                cartSidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            // Close cart when clicking on overlay
            overlay.addEventListener('click', function() {
                cartSidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            // Add to cart buttons
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productCard = this.closest('.product-card');
                    const productId = productCard.getAttribute('data-product-id');
                    const quantity = parseInt(productCard.querySelector('.quantity-input').value);
                    const productName = this.getAttribute('data-product-name');
                    const productPrice = parseFloat(this.getAttribute('data-product-price'));
                    const productShop = productCard.getAttribute('data-shop');
                    const productImage = productCard.querySelector('.product-image').src;
                    
                    // Check if product already in cart
                    const existingItem = cart.find(item => item.id === productId);
                    
                    if (existingItem) {
                        existingItem.quantity += quantity;
                    } else {
                        cart.push({ 
                            id: productId, 
                            name: productName, 
                            price: productPrice, 
                            quantity: quantity, 
                            shop: productShop,
                            image: productImage
                        });
                    }
                    
                    // Save to localStorage and update UI
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateCartCount();
                    
                    // Show success feedback
                    showToast('Added to cart!', 'success');
                    
                    // Open cart if it's not already open
                    if (!cartSidebar.classList.contains('active')) {
                        cartSidebar.classList.add('active');
                        overlay.classList.add('active');
                    }
                    
                    renderCart();
                    
                    // Add animation to product card
                    productCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        productCard.style.transform = '';
                    }, 300);
                });
            });
            
            // Filter products by shop
            document.getElementById('shop-select').addEventListener('change', function() {
                const shop = this.value;
                document.querySelectorAll('.product-card').forEach(card => {
                    card.style.display = (shop === 'all' || card.getAttribute('data-shop') === shop) ? 'flex' : 'none';
                });
            });
            
            // Place order button
            document.getElementById('checkout-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (cart.length === 0) {
                    showToast('Your cart is empty!', 'error');
                    return;
                }
                
                // Show loading state
                const checkoutBtn = document.getElementById('place-order-btn');
                const originalText = checkoutBtn.innerHTML;
                checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                checkoutBtn.disabled = true;
                *** Authorization Request in PHP ***|
 
$ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Authorization: Basic ' . base64_encode('YOUR_APP_CONSUMER_KEY:YOUR_APP_CONSUMER_SECRET')
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
echo json_decode($response);
                // Send order to server
                fetch('process_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        cart: cart, 
                        userId: <?php echo $userId; ?> 
                    }),
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Order placed successfully!', 'success');
                        
                        // Clear cart
                        cart = [];
                        localStorage.removeItem('cart');
                        updateCartCount();
                        renderCart();
                        
                        // Close cart sidebar
                        cartSidebar.classList.remove('active');
                        overlay.classList.remove('active');
                        
                        // Reload page to show new order
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        throw new Error(data.message || 'Order failed');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Order failed: ' + error.message, 'error');
                })
                .finally(() => {
                    checkoutBtn.innerHTML = originalText;
                    checkoutBtn.disabled = false;
                });
            });
            
            // Update cart count in header and floating button
            function updateCartCount() {
                const count = cart.reduce((sum, item) => sum + item.quantity, 0);
                document.getElementById('cart-count').textContent = count;
                document.getElementById('floating-cart-count').textContent = count;
                
                // Pulse animation when count changes
                if (count > 0) {
                    const badge = document.getElementById('floating-cart-count');
                    badge.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        badge.style.transform = 'scale(1)';
                    }, 300);
                }
            }
            
            // Render cart items
            function renderCart() {
                const cartItemsContainer = document.getElementById('cart-items');
                const cartTotalElement = document.getElementById('cart-total');
                let total = 0;
                
                if (cart.length === 0) {
                    cartItemsContainer.innerHTML = `
                        <div class="cart-empty">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Your cart is empty</h3>
                            <p>Add some products to get started</p>
                        </div>
                    `;
                    cartTotalElement.textContent = '$0.00';
                    return;
                }
                
                cartItemsContainer.innerHTML = '';
                
                cart.forEach((item, index) => {
                    const itemTotal = item.price * item.quantity;
                    total += itemTotal;
                    
                    const cartItem = document.createElement('div');
                    cartItem.className = 'cart-item';
                    cartItem.innerHTML = `
                        <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                        <div class="cart-item-details">
                            <h4 class="cart-item-title">${item.name}</h4>
                            <p class="cart-item-shop">${item.shop}</p>
                            <p class="cart-item-price">$${item.price.toFixed(2)} × ${item.quantity} = $${itemTotal.toFixed(2)}</p>
                            <div class="cart-item-actions">
                                <input type="number" min="1" value="${item.quantity}" class="cart-item-quantity" data-index="${index}">
                                <button class="remove-item" data-index="${index}">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    `;
                    
                    cartItemsContainer.appendChild(cartItem);
                });
                
                // Update total
                cartTotalElement.textContent = `$${total.toFixed(2)}`;
                
                // Add event listeners for quantity changes
                document.querySelectorAll('.cart-item-quantity').forEach(input => {
                    input.addEventListener('change', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        const newQuantity = parseInt(this.value);
                        
                        if (newQuantity > 0) {
                            cart[index].quantity = newQuantity;
                            localStorage.setItem('cart', JSON.stringify(cart));
                            updateCartCount();
                            renderCart();
                        } else {
                            this.value = cart[index].quantity;
                        }
                    });
                });
                
                // Add event listeners for remove buttons
                document.querySelectorAll('.remove-item').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        cart.splice(index, 1);
                        localStorage.setItem('cart', JSON.stringify(cart));
                        updateCartCount();
                        renderCart();
                        showToast('Item removed from cart', 'info');
                    });
                });
            }
            
            // Show toast notification
            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        ${type === 'success' ? '<i class="fas fa-check-circle"></i>' : 
                          type === 'error' ? '<i class="fas fa-exclamation-circle"></i>' : 
                          '<i class="fas fa-info-circle"></i>'}
                    </div>
                    <div class="toast-message">${message}</div>
                `;
                
                document.body.appendChild(toast);
                
                // Show toast
                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);
                
                // Hide after 3 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>