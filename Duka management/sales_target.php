<?php
date_default_timezone_set('Africa/Nairobi');
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Sales Staff';
$targets = [];
$message = '';
$message_type = '';

// Check for session messages from other pages (like add_target.php)
if (isset($_SESSION['order_message'])) {
    $message = $_SESSION['order_message'];
    $message_type = $_SESSION['order_message_type'];
    unset($_SESSION['order_message'], $_SESSION['order_message_type']);
}

// === NEW LOGIC FOR "NEVER ACHIEVED" STATUS ===
try {
    $currentYear = date('Y');
    $currentMonth = date('n');

    $update_query = "UPDATE sales_targets SET status = 'Never Achieved' WHERE sales_staff_id = ? AND status = 'Pending' AND (
        (year = ? AND month < ?) OR (year < ?)
    )";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("iiii", $_SESSION['user_id'], $currentYear, $currentMonth, $currentYear);
    $update_stmt->execute();
    $update_stmt->close();
} catch (Exception $e) {
    // Log the error but don't stop the page from loading
    error_log("Error updating 'Never Achieved' status: " . $e->getMessage());
}

// Fetch all sales targets for the logged-in staff member
try {
    $query = "SELECT * FROM sales_targets WHERE sales_staff_id = ? ORDER BY year DESC, month DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $targets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales Targets</title>
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
<body class="bg-light">
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
            <h2><i class="fas fa-bullseye"></i> My Sales Targets</h2>
            <div>
                <a href="salesstaff_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="add_target.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Target
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-light text-primary fw-bold">Monthly Sales Performance</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (!empty($targets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Target Amount</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Created On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($targets as $target): 
                                    $status_class = '';
                                    if ($target['status'] == 'Achieved') $status_class = 'bg-success text-white';
                                    if ($target['status'] == 'Pending') $status_class = 'bg-warning text-dark';
                                    if ($target['status'] == 'Never Achieved') $status_class = 'bg-danger text-white';
                                ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($target['id']); ?></td>
                                        <td>KSH <?php echo number_format($target['target_amount'], 2); ?></td>
                                        <td><?php echo date('F', mktime(0, 0, 0, $target['month'], 10)) . ', ' . htmlspecialchars($target['year']); ?></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($target['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($target['created_at'])); ?></td>
                                        <td>
                                            <?php if ($target['status'] === 'Pending'): ?>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-success mark-achieved-btn" data-target-id="<?php echo $target['id']; ?>" data-target-month="<?php echo $target['month']; ?>" data-target-year="<?php echo $target['year']; ?>">
                                                    <i class="fas fa-check"></i> Achieved
                                                </button>
                                                <button class="btn btn-sm btn-danger mark-not-achieved-btn" data-target-id="<?php echo $target['id']; ?>" data-target-month="<?php echo $target['month']; ?>" data-target-year="<?php echo $target['year']; ?>">
                                                    <i class="fas fa-times"></i> Never Achieved
                                                </button>
                                                <a href="edit_target.php?id=<?php echo $target['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No sales targets found for your account.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="celebrationModal" tabindex="-1" aria-labelledby="celebrationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body p-4">
                    <img src="https://i.pinimg.com/originals/f3/7a/00/f37a00f86237ac6531398c8c7f766324.gif" alt="Well Done!" style="width: 150px; height: 150px;">
                    <h3 class="mt-3 text-success">Well Done!</h3>
                    <p class="lead">You've successfully achieved your sales target!</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="errorToastBody">
                    An error occurred.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // AJAX & Modal/Toast Logic
            const markAchievedBtns = document.querySelectorAll('.mark-achieved-btn');
            const markNotAchievedBtns = document.querySelectorAll('.mark-not-achieved-btn');
            const celebrationModal = new bootstrap.Modal(document.getElementById('celebrationModal'));
            const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));

            function handleTargetUpdate(btn, status) {
                const targetId = btn.getAttribute('data-target-id');
                const targetMonth = btn.getAttribute('data-target-month');
                const targetYear = btn.getAttribute('data-target-year');
                
                const btnGroup = btn.closest('.btn-group');
                btnGroup.style.display = 'none';

                fetch(`update_target.php?id=${targetId}&status=${status}&month=${targetMonth}&year=${targetYear}`, {
                    method: 'GET',
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const statusBadge = btn.closest('tr').querySelector('.badge');
                    if (data.success) {
                        if (status === 'Achieved') {
                            celebrationModal.show();
                            statusBadge.textContent = 'Achieved';
                            statusBadge.classList.remove('bg-warning', 'text-dark');
                            statusBadge.classList.add('bg-success', 'text-white');
                        } else if (status === 'Never Achieved') {
                            statusBadge.textContent = 'Never Achieved';
                            statusBadge.classList.remove('bg-warning', 'text-dark');
                            statusBadge.classList.add('bg-danger', 'text-white');
                        }
                    } else {
                        document.getElementById('errorToastBody').textContent = data.message;
                        errorToast.show();
                        btnGroup.style.display = 'inline-block';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('errorToastBody').textContent = 'An unexpected network error occurred.';
                    errorToast.show();
                    btnGroup.style.display = 'inline-block';
                });
            }

            markAchievedBtns.forEach(btn => {
                btn.addEventListener('click', () => handleTargetUpdate(btn, 'Achieved'));
            });

            markNotAchievedBtns.forEach(btn => {
                btn.addEventListener('click', () => handleTargetUpdate(btn, 'Never Achieved'));
            });

            // Chart rendering logic
            fetch('sales_stats_api.php')
                .then(response => response.json())
                .then(apiData => {
                    if (apiData.success) {
                        const salesTargets = apiData.data.salesTargets || [];
                        const actualSales = apiData.data.actualSales || [];

                        // Combine and format data for the chart
                        const allMonths = [...new Set([
                            ...salesTargets.map(t => `${t.year}-${t.month}`), 
                            ...actualSales.map(s => `${s.year}-${s.month}`)
                        ])].sort((a, b) => {
                            const [yearA, monthA] = a.split('-').map(Number);
                            const [yearB, monthB] = b.split('-').map(Number);
                            if (yearA !== yearB) return yearA - yearB;
                            return monthA - monthB;
                        });
                        
                        const chartLabels = allMonths.map(m => {
                            const [year, month] = m.split('-');
                            return `${new Date(year, month - 1).toLocaleString('default', { month: 'short' })} ${year}`;
                        });
                        
                        const targetData = allMonths.map(m => {
                            const target = salesTargets.find(t => `${t.year}-${t.month}` === m);
                            return target ? target.target_amount : 0;
                        });

                        const actualData = allMonths.map(m => {
                            const sales = actualSales.find(s => `${s.year}-${s.month}` === m);
                            return sales ? sales.total_sales : 0;
                        });

                        const ctx = document.getElementById('salesPerformanceChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: chartLabels,
                                datasets: [{
                                    label: 'Actual Sales (KSH)',
                                    data: actualData,
                                    backgroundColor: 'rgba(52, 144, 220, 0.7)', // A definite blue
                                    borderColor: 'rgba(52, 144, 220, 1)',
                                    borderWidth: 1,
                                    type: 'bar',
                                    order: 1
                                }, {
                                    label: 'Sales Target (KSH)',
                                    data: targetData,
                                    borderColor: 'rgba(255, 99, 132, 1)', // Red
                                    borderWidth: 2,
                                    type: 'line',
                                    fill: false,
                                    order: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Amount (KSH)'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Month'
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        console.error('Error fetching sales data:', apiData.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching sales data:', error);
                });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>