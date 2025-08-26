<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$message = '';
$message_type = '';
$staff_members = [];

// Fetch list of sales staff for the dropdown
try {
    $query = "SELECT id, fullname FROM sales_staff";
    $result = $conn->query($query);
    if ($result) {
        $staff_members = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // In a real system, log this error
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sales_staff_id = $_POST['sales_staff_id'] ?? null;
    $target_amount = $_POST['target_amount'] ?? null;
    $month = $_POST['month'] ?? null;
    $year = $_POST['year'] ?? null;

    if ($sales_staff_id && $target_amount && $month && $year) {
        try {
            $stmt = $conn->prepare("INSERT INTO sales_targets (sales_staff_id, target_amount, month, year) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idii", $sales_staff_id, $target_amount, $month, $year);
            if ($stmt->execute()) {
                $_SESSION['order_message'] = "New sales target added successfully!";
                $_SESSION['order_message_type'] = "success";
                header("Location: sales_targets.php");
                exit();
            } else {
                $message = "Error adding target: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Please fill in all the required fields.";
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Sales Target</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>DUKA Sales Portal
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-plus"></i> Add New Sales Target</h2>
            <a href="sales_targets.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Targets
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="add_target.php" method="POST">
                    <div class="mb-3">
                        <label for="sales_staff_id" class="form-label">Sales Staff</label>
                        <select class="form-select" id="sales_staff_id" name="sales_staff_id" required>
                            <option value="">Select Staff Member</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo htmlspecialchars($staff['id']); ?>"><?php echo htmlspecialchars($staff['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="target_amount" class="form-label">Target Amount (KSH)</label>
                        <input type="number" class="form-control" id="target_amount" name="target_amount" step="0.01" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="month" class="form-label">Month</label>
                            <select class="form-select" id="month" name="month" required>
                                <option value="">Select Month</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo date('F', mktime(0, 0, 0, $i, 1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year" min="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Target</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>