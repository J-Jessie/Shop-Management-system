<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Sales Staff';
$orders = [];
$message = '';
$message_type = '';

// Fetch all orders with customer names
try {
    $query = "SELECT o.*, c.fullname as customer_name 
              FROM orders o
              LEFT JOIN customers c ON o.customer_id = c.id
              ORDER BY o.order_date DESC";
    $result = $conn->query($query);
    if ($result) {
        $orders = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

// Display any messages from other pages
$order_message = $_SESSION['order_message'] ?? '';
$order_message_type = $_SESSION['order_message_type'] ?? '';
unset($_SESSION['order_message'], $_SESSION['order_message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
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
            <h2><i class="fas fa-shopping-cart"></i> All Orders</h2>
            <div>
                <!-- FIX: Changed href to salesstaff_dashboard.php -->
                <a href="https://dukamanagement.free.nf/salesstaff_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($order_message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($order_message_type); ?>">
                <?php echo htmlspecialchars($order_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $status_class = '';
                                    if ($order['status'] == 'Completed') $status_class = 'bg-success';
                                    if ($order['status'] == 'Processing') $status_class = 'bg-warning';
                                    if ($order['status'] == 'Cancelled') $status_class = 'bg-danger';
                                ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>KSH <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No orders found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>