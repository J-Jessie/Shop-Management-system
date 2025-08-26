<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Sales Staff';
$report_data = [];
$error = '';

// Date range for reports (default to current month)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Handle date filter submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

try {
    // Get sales summary
    $sales_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                    FROM orders
                    WHERE order_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sales_query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $sales_result = $stmt->get_result();
    $report_data['sales_summary'] = $sales_result->fetch_assoc();

    // Get top products
    $products_query = "SELECT 
                      p.name as product_name,
                      SUM(oi.quantity) as total_quantity,
                      SUM(oi.price * oi.quantity) as total_revenue
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.order_date BETWEEN ? AND ?
                      GROUP BY p.name
                      ORDER BY total_revenue DESC
                      LIMIT 5";
    $stmt = $conn->prepare($products_query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data['top_products'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get sales by day
    $daily_query = "SELECT 
                   DATE(order_date) as day,
                   COUNT(*) as order_count,
                   SUM(total_amount) as daily_revenue
                   FROM orders
                   WHERE order_date BETWEEN ? AND ?
                   GROUP BY DATE(order_date)
                   ORDER BY day";
    $stmt = $conn->prepare($daily_query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data['daily_sales'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = "Error generating reports: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h2><i class="fas fa-chart-bar"></i> Sales Reports</h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Date Range</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-5">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Sales Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Orders:</span>
                            <strong><?php echo htmlspecialchars($report_data['sales_summary']['total_orders'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Revenue:</span>
                            <strong>KSH <?php echo number_format($report_data['sales_summary']['total_revenue'] ?? 0, 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Avg Order Value:</span>
                            <strong>KSH <?php echo number_format($report_data['sales_summary']['avg_order_value'] ?? 0, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Sales</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailySalesChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($report_data['top_products'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_products'] as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['total_quantity']); ?></td>
                                                <td>KSH <?php echo number_format($product['total_revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No product data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Revenue by Product</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="productRevenueChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Sales Chart
        const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
        const dailyLabels = <?php echo json_encode(array_column($report_data['daily_sales'] ?? [], 'day')); ?>;
        const dailyData = <?php echo json_encode(array_column($report_data['daily_sales'] ?? [], 'daily_revenue')); ?>;
        
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Daily Revenue (KSH)',
                    data: dailyData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Product Revenue Chart
        const productCtx = document.getElementById('productRevenueChart').getContext('2d');
        const productLabels = <?php echo json_encode(array_column($report_data['top_products'] ?? [], 'product_name')); ?>;
        const productData = <?php echo json_encode(array_column($report_data['top_products'] ?? [], 'total_revenue')); ?>;
        
        new Chart(productCtx, {
            type: 'pie',
            data: {
                labels: productLabels,
                datasets: [{
                    data: productData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `KSH ${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>