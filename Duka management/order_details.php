<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Sales Staff';
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$order = null;
$order_items = [];
$message = '';
$message_type = '';

// Fetch order details
try {
    // Get order info
    $stmt = $conn->prepare("SELECT o.*, c.name as customer_name, c.email, c.phone, c.address 
                           FROM orders o
                           LEFT JOIN customers c ON o.customer_id = c.id
                           WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        $message = "Order not found";
        $message_type = "danger";
    } else {
        // Get order items
        $items_stmt = $conn->prepare("SELECT oi.*, p.name as product_name 
                                    FROM order_items oi
                                    LEFT JOIN products p ON oi.product_id = p.id
                                    WHERE oi.order_id = ?");
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        
        if ($stmt->execute()) {
            $message = "Order status updated successfully!";
            $message_type = "success";
            // Refresh order data
            $order['status'] = $new_status;
            
            // Set success message for redirect
            $_SESSION['order_message'] = $message;
            $_SESSION['order_message_type'] = $message_type;
        } else {
            $message = "Error updating order status: " . $conn->error;
            $message_type = "danger";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>DUKA Sales Portal
            </a>
            <div class="d-flex align-items-center">
                <div class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($current_username); ?>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shopping-cart"></i> Order Details</h2>
            <a href="view_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Order #<?php echo htmlspecialchars($order['id']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Customer Information</h6>
                                    <p>
                                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($order['email']); ?><br>
                                        <?php echo htmlspecialchars($order['phone']); ?><br>
                                        <?php echo nl2br(htmlspecialchars($order['address'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Order Information</h6>
                                    <p>
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['order_date'])); ?><br>
                                        <strong>Status:</strong> 
                                        <span class="badge 
                                            <?php 
                                                if ($order['status'] == 'Completed') echo 'bg-success';
                                                elseif ($order['status'] == 'Processing') echo 'bg-warning';
                                                elseif ($order['status'] == 'Cancelled') echo 'bg-danger';
                                            ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span><br>
                                        <?php if (!empty($order['notes'])): ?>
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <h6>Order Items</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td>KSH <?php echo number_format($item['price'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                <td>KSH <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Subtotal:</th>
                                            <th>KSH <?php echo number_format($order['total_amount'], 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Order Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Processing" <?php echo $order['status'] == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Completed" <?php echo $order['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Cancelled" <?php echo $order['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">Order not found</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>