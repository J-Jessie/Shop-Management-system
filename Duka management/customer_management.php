<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

$current_username = $_SESSION['username'] ?? 'Sales Staff';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
    </style>
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
            <h2><i class="fas fa-chart-bar"></i> Customer Analytics</h2>
            <div>
                <a href="salesstaff_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card text-white bg-info h-100 shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-white-50 small">Total Customers</div>
                                <h4 class="mb-0" id="totalCustomersCount"><i class="fas fa-spinner fa-spin me-2"></i></h4>
                            </div>
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success h-100 shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-white-50 small">Active in Last 7 Days</div>
                                <h4 class="mb-0" id="activeCustomersCount"><i class="fas fa-spinner fa-spin me-2"></i></h4>
                            </div>
                            <i class="fas fa-calendar-check fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning h-100 shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-white-50 small">Online Now (approx.)</div>
                                <h4 class="mb-0" id="onlineCustomersCount"><i class="fas fa-spinner fa-spin me-2"></i></h4>
                            </div>
                            <i class="fas fa-wifi fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4 g-4">
            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-light">Top Products by Sales Quantity</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-light">Top Product Types by Sales Quantity</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topProductTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('customer_stats_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update counts
                        document.getElementById('totalCustomersCount').innerText = data.data.totalCustomers;
                        document.getElementById('activeCustomersCount').innerText = data.data.customersOnline7Days;
                        document.getElementById('onlineCustomersCount').innerText = data.data.customersOnline;

                        // Get the product data
                        const topProducts = data.data.topProducts;
                        const productLabels = topProducts.map(p => p.product_name);
                        const productData = topProducts.map(p => p.total_quantity_sold);

                        // Create Top Products Pie Chart
                        new Chart(document.getElementById('topProductsChart'), {
                            type: 'pie',
                            data: {
                                labels: productLabels,
                                datasets: [{
                                    label: 'Quantity Sold',
                                    data: productData,
                                    backgroundColor: [
                                        '#0d6efd',
                                        '#6610f2',
                                        '#6f42c1',
                                        '#d63384',
                                        '#fd7e14'
                                    ],
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: false
                                    }
                                }
                            }
                        });

                        // Get the product type data
                        const topProductTypes = data.data.topProductTypes;
                        const productTypeLabels = topProductTypes.map(p => p.product_type);
                        const productTypeData = topProductTypes.map(p => p.total_quantity_sold);

                        // Create Top Product Types Horizontal Bar Chart
                        new Chart(document.getElementById('topProductTypesChart'), {
                            type: 'bar',
                            data: {
                                labels: productTypeLabels,
                                datasets: [{
                                    label: 'Quantity Sold',
                                    data: productTypeData,
                                    backgroundColor: [
                                        '#20c997',
                                        '#198754',
                                        '#0dcaf0',
                                        '#ffc107',
                                        '#6c757d'
                                    ],
                                    borderRadius: 8 // Rounded edges
                                }]
                            },
                            options: {
                                indexAxis: 'y', // Makes it a horizontal bar chart
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    title: {
                                        display: false
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Quantity Sold'
                                        }
                                    }
                                }
                            }
                        });

                    } else {
                        console.error('API Error:', data.message);
                        // Fallback message for counts
                        document.getElementById('totalCustomersCount').innerText = 'Error';
                        document.getElementById('activeCustomersCount').innerText = 'Error';
                        document.getElementById('onlineCustomersCount').innerText = 'Error';
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    // Fallback message for counts
                    document.getElementById('totalCustomersCount').innerText = 'Error';
                    document.getElementById('activeCustomersCount').innerText = 'Error';
                    document.getElementById('onlineCustomersCount').innerText = 'Error';
                });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>