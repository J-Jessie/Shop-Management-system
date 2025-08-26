<?php
date_default_timezone_set('Africa/Nairobi');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

$target_id = $_GET['id'] ?? null;
$target = null;
$error_message = '';

if (!$target_id) {
    header("Location: sales_targets.php");
    exit();
}

try {
    $query = "SELECT * FROM sales_targets WHERE id = ? AND sales_staff_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $target_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $target = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

if (!$target) {
    $error_message = "Target not found or you don't have permission to edit it.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sales Target</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
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

    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-edit"></i> Edit Sales Target</h4>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php else: ?>
                    <form action="update_target_details.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($target['id']); ?>">
                        
                        <div class="mb-3">
                            <label for="target_amount" class="form-label">Target Amount (KSH)</label>
                            <input type="number" step="0.01" class="form-control" id="target_amount" name="target_amount" value="<?php echo htmlspecialchars($target['target_amount']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="month" class="form-label">Month</label>
                                <select class="form-select" id="month" name="month" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo ($m == $target['month']) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" name="year" required>
                                    <?php 
                                        $currentYear = date('Y');
                                        for ($y = $currentYear; $y <= $currentYear + 5; $y++): 
                                    ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == $target['year']) ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="sales_targets.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>