<?php
// Start session and include DB connection
session_start();
require_once 'db_connection.php';

// Debugging: Check session status
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in at all, redirect to login
    header("Location: Login.php");
    exit();
}

// Verify user role exists in session
if (!isset($_SESSION['user_role'])) {
    die("Error: User role not set in session. Please login again.");
}

// Check if user has shopkeeper role
if ($_SESSION['user_role'] !== 'shopkeeper') {
    die("Access Denied: You don't have shopkeeper privileges.");
}

// Get current shopkeeper ID
$shopkeeper_id = $_SESSION['user_id'];

// Handle success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$order_message = $_SESSION['order_message'] ?? '';
$order_message_type = $_SESSION['order_message_type'] ?? '';
unset($_SESSION['order_message'], $_SESSION['order_message_type']);

// Get inventory data with low stock alerts
$inventory_stmt = $conn->prepare("
    SELECT *, 
           CASE WHEN quantity < 10 THEN 'danger'
                WHEN quantity < 20 THEN 'warning'
                ELSE 'success' 
           END as stock_status
    FROM inventory 
    WHERE shopkeeper_id = ?
    ORDER BY quantity ASC
");
$inventory_stmt->bind_param("i", $shopkeeper_id);
$inventory_stmt->execute();
$inventory = $inventory_stmt->get_result();

// Count low stock items
$low_stock_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM inventory 
    WHERE shopkeeper_id = ? AND quantity < 10
");
$low_stock_stmt->bind_param("i", $shopkeeper_id);
$low_stock_stmt->execute();
$low_stock_result = $low_stock_stmt->get_result()->fetch_assoc();
$low_stock_count = $low_stock_result['count'];

// Get retailer orders
$orders_stmt = $conn->prepare("
    SELECT * FROM retailer_orders 
    WHERE shopkeeper_id = ?
    ORDER BY order_date DESC
    LIMIT 5
");
$orders_stmt->bind_param("i", $shopkeeper_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// Get retailers (no need for prepared statement as no user input)
$retailers = $conn->query("SELECT * FROM retailers");

// Close statements
$inventory_stmt->close();
$low_stock_stmt->close();
$orders_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopkeeper Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-container {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: bold;
        }
        .stock-danger {
            background-color: #ffdddd;
        }
        .stock-warning {
            background-color: #fff3cd;
        }
        .stock-success {
            background-color: #ddffdd;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-success {
            background-color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-container">
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($order_message): ?>
            <div class="alert alert-<?= $order_message_type ?>"><?= htmlspecialchars($order_message) ?></div>
        <?php endif; ?>

        <h1 class="mb-4">Shopkeeper Dashboard</h1>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-boxes"></i> Inventory Items
                    </div>
                    <div class="card-body">
                        <h3><?= $inventory->num_rows ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Items
                    </div>
                    <div class="card-body">
                        <h3><?= $low_stock_count ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-truck"></i> Pending Orders
                    </div>
                    <div class="card-body">
                        <h3><?= $orders->num_rows ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">Inventory</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="post-tab" data-bs-toggle="tab" data-bs-target="#post" type="button" role="tab">Post Products</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="retailers-tab" data-bs-toggle="tab" data-bs-target="#retailers" type="button" role="tab">Retailers</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">Place Orders</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="dashboardTabsContent">

<!-- Inventory Tab -->
<div class="tab-pane fade show active" id="inventory" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Inventory Management</h4>
            <a href="add_products.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Product
            </a>
        </div>
        <div class="card-body p-0"> <!-- Added p-0 to remove padding -->
            <table class="table table-striped mb-0"> <!-- Added mb-0 to remove margin bottom -->
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Buying Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $inventory->fetch_assoc()): ?>
                        <tr class="stock-<?= $item['stock_status'] ?>">
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= htmlspecialchars($item['product_type']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($item['buying_cost'], 2, '.', '') ?></td>
                            <td><?= number_format($item['total_price'], 2, '.', '') ?></td>
                            <td>
                                <?php if ($item['quantity'] < 10): ?>
                                    <span class="badge bg-danger">Low Stock</span>
                                <?php elseif ($item['quantity'] < 20): ?>
                                    <span class="badge bg-warning">Warning</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Good</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

                    </div> 
                </div>
            </div>

<!-- Post Products Tab -->
<div class="tab-pane fade" id="post" role="tabpanel">
    <div class="card">
        <div class="card-header">
            <h4>Post Products to Customer Dashboard</h4>
        </div>
        <div class="card-body">
            <form action="post_product.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="product_select" class="form-label">Select Product from Inventory</label>
                    <select class="form-control" id="product_select" name="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php 
                        // Re-fetch inventory items for the select dropdown
                        $inventory_items = $conn->prepare("SELECT id, product_name FROM inventory WHERE shopkeeper_id = ?");
                        $inventory_items->bind_param("i", $shopkeeper_id);
                        $inventory_items->execute();
                        $products = $inventory_items->get_result();
                        
                        while ($product = $products->fetch_assoc()): ?>
                            <option value="<?= $product['id'] ?>">
                                <?= htmlspecialchars($product['product_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="post_quantity" class="form-label">Quantity to Post</label>
                    <input type="number" class="form-control" id="post_quantity" name="quantity" min="1" required>
                    <small class="text-muted">Available quantity: <span id="available_quantity">0</span></small>
                </div>
                
                <div class="mb-3">
                    <label for="post_price" class="form-label">Selling Price</label>
                    <input type="number" step="0.01" class="form-control" id="post_price" name="price" min="0.01" required>
                    <small class="text-muted">Buying cost: <span id="buying_cost">0.00</span></small>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="image" class="form-label">Product Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                    <small class="text-muted">Max size: 2MB (JPG, PNG, GIF)</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Post Product</button>
            </form>
        </div>
    </div>
</div>
            <!-- Retailers Tab -->
            <div class="tab-pane fade" id="retailers" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h4>Manage Retailers</h4>
                        <button class="btn btn-sm btn-success float-end" data-bs-toggle="modal" data-bs-target="#addRetailerModal">
                            <i class="fas fa-plus"></i> Add Retailer
                        </button>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($retailer = $retailers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($retailer['name']) ?></td>
                                        <td><?= htmlspecialchars($retailer['contact_person']) ?></td>
                                        <td><?= htmlspecialchars($retailer['email']) ?></td>
                                        <td><?= htmlspecialchars($retailer['phone']) ?></td>
                                        <td>
                                            <a href="edit_retailer.php?id=<?= $retailer['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="delete_retailer.php?id=<?= $retailer['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this retailer?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Place Orders Tab -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h4>Place Order to Retailer</h4>
                    </div>
                    <div class="card-body">
                        <form action="place_retailer_order.php" method="post">
                            <div class="mb-3">
                                <label for="retailer_name" class="form-label">Retailer</label>
                                <select class="form-control" id="retailer_name" name="retailer_name" required>
                                    <option value="">Select Retailer</option>
                                    <?php 
                                    $retailers_list = $conn->query("SELECT * FROM retailers");
                                    while ($retailer = $retailers_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($retailer['name']) ?>">
                                            <?= htmlspecialchars($retailer['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="product" class="form-label">Product</label>
                                <input type="text" class="form-control" id="product" name="product" required>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" required>
                            </div>
                            <button type="submit" name="place_order" class="btn btn-primary">Place Order</button>
                        </form>

                        <hr>
                        <h5>Recent Orders</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Retailer</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['retailer_name']) ?></td>
                                        <td><?= htmlspecialchars($order['product']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $order['status'] === 'Pending' ? 'bg-warning' : 'bg-success' ?>">
                                                <?= $order['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Retailer Modal -->
        <div class="modal fade" id="addRetailerModal" tabindex="-1" aria-labelledby="addRetailerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRetailerModalLabel">Add New Retailer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="manage_retailers.php">
                            <div class="mb-3">
                                <label for="name" class="form-label">Retailer Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Add Retailer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Auto-select tab from URL hash
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                var tabTrigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>