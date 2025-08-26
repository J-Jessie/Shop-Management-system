<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

// Initialize variables with safe defaults
$current_username = $_SESSION['username'] ?? 'Guest';
$current_user_type = $_SESSION['user_type'] ?? 'unknown';
$current_user_id = $_SESSION['user_id'] ?? 0;
$message = '';
$message_type = '';
$all_users = [];

// Define database tables with exact names and username columns
$user_tables = [
    'sales_staff' => [
        'table_name' => 'sales_staff',
        'username_column' => 'name' // Change this to your actual column name
    ],
    'shopkeepers' => [
        'table_name' => 'shopkeepers',
        'username_column' => 'name' // Change this to your actual column name
    ], 
    'customers' => [
        'table_name' => 'customers',
        'username_column' => 'name' // Change this to your actual column name
    ]
];

try {
    // Handle user deletion
    if (isset($_GET['delete_id']) && isset($_GET['type'])) {
        $delete_id = intval($_GET['delete_id']);
        $user_type = $_GET['type'];
        
        // Validate user type exists in our tables
        if (!array_key_exists($user_type, $user_tables)) {
            throw new Exception("Invalid user type specified");
        }
        
        $table_name = $user_tables[$user_type]['table_name'];
        
        // Prevent self-deletion
        if ($delete_id == $current_user_id && $user_type == $current_user_type) {
            $message = "You cannot delete your own account!";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "User deleted successfully";
                $message_type = "success";
            } else {
                throw new Exception("Failed to delete user: " . $conn->error);
            }
        }
    }

    // Fetch users from all tables
    foreach ($user_tables as $type => $table_info) {
        $table_name = $table_info['table_name'];
        $username_column = $table_info['username_column'];
        
        // Use the configured username column
        $query = "SELECT id, $username_column as display_name, created_at, '$type' as user_type FROM $table_name";
        
        // Exclude current user from their own type if logged in
        if ($current_user_id > 0 && $type === $current_user_type) {
            $query .= " WHERE id != " . $current_user_id;
        }
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_users[] = $row;
            }
        } else {
            throw new Exception("Error fetching users from $table_name: " . $conn->error);
        }
    }

} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

// Handle session messages
$user_message = $_SESSION['user_message'] ?? '';
$user_message_type = $_SESSION['user_message_type'] ?? '';
unset($_SESSION['user_message'], $_SESSION['user_message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - DUKA Sales Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-type-badge {
            font-size: 0.8rem;
            text-transform: capitalize;
        }
        .sales_staff-badge { background-color: #3498db; }
        .shopkeepers-badge { background-color: #2ecc71; }
        .customers-badge { background-color: #9b59b6; }
        .unknown-badge { background-color: #95a5a6; }
        .sidebar { min-height: 100vh; }
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
                    <?= htmlspecialchars($current_username) ?> 
                    <span class="badge unknown-badge">
                        <?= ucfirst($current_user_type) ?>
                    </span>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar bg-light">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_users.php">
                                <i class="fas fa-users me-2"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_products.php">
                                <i class="fas fa-boxes me-2"></i> Manage Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_orders.php">
                                <i class="fas fa-shopping-cart me-2"></i> View Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Message Alerts -->
                <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>Manage Users</h2>
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add User
                    </a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (!empty($all_users)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id']) ?></td>
                                        <td><?= htmlspecialchars($user['display_name']) ?></td>
                                        <td>
                                            <span class="badge user-type-badge <?= $user['user_type'] ?>-badge">
                                                <?= ucfirst(str_replace('_', ' ', $user['user_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?= $user['id'] ?>&type=<?= $user['user_type'] ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="manage_users.php?delete_id=<?= $user['id'] ?>&type=<?= $user['user_type'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No users found in the system
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div><?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

// Initialize variables with safe defaults
$current_username = $_SESSION['username'] ?? 'Guest';
$current_user_type = $_SESSION['user_type'] ?? 'unknown';
$current_user_id = $_SESSION['user_id'] ?? 0;
$message = '';
$message_type = '';
$all_users = [];

// Define database tables with exact names and username columns
$user_tables = [
    'sales_staff' => [
        'table_name' => 'sales_staff',
        'username_column' => 'name' // Change this to your actual column name
    ],
    'shopkeepers' => [
        'table_name' => 'shopkeepers',
        'username_column' => 'name' // Change this to your actual column name
    ], 
    'customers' => [
        'table_name' => 'customers',
        'username_column' => 'name' // Change this to your actual column name
    ]
];

try {
    // Handle user deletion
    if (isset($_GET['delete_id']) && isset($_GET['type'])) {
        $delete_id = intval($_GET['delete_id']);
        $user_type = $_GET['type'];
        
        // Validate user type exists in our tables
        if (!array_key_exists($user_type, $user_tables)) {
            throw new Exception("Invalid user type specified");
        }
        
        $table_name = $user_tables[$user_type]['table_name'];
        
        // Prevent self-deletion
        if ($delete_id == $current_user_id && $user_type == $current_user_type) {
            $message = "You cannot delete your own account!";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "User deleted successfully";
                $message_type = "success";
            } else {
                throw new Exception("Failed to delete user: " . $conn->error);
            }
        }
    }

    // Fetch users from all tables
    foreach ($user_tables as $type => $table_info) {
        $table_name = $table_info['table_name'];
        $username_column = $table_info['username_column'];
        
        // Use the configured username column
        $query = "SELECT id, $username_column as display_name, created_at, '$type' as user_type FROM $table_name";
        
        // Exclude current user from their own type if logged in
        if ($current_user_id > 0 && $type === $current_user_type) {
            $query .= " WHERE id != " . $current_user_id;
        }
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_users[] = $row;
            }
        } else {
            throw new Exception("Error fetching users from $table_name: " . $conn->error);
        }
    }

} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

// Handle session messages
$user_message = $_SESSION['user_message'] ?? '';
$user_message_type = $_SESSION['user_message_type'] ?? '';
unset($_SESSION['user_message'], $_SESSION['user_message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - DUKA Sales Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-type-badge {
            font-size: 0.8rem;
            text-transform: capitalize;
        }
        .sales_staff-badge { background-color: #3498db; }
        .shopkeepers-badge { background-color: #2ecc71; }
        .customers-badge { background-color: #9b59b6; }
        .unknown-badge { background-color: #95a5a6; }
        .sidebar { min-height: 100vh; }
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
                    <?= htmlspecialchars($current_username) ?> 
                    <span class="badge unknown-badge">
                        <?= ucfirst($current_user_type) ?>
                    </span>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar bg-light">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_users.php">
                                <i class="fas fa-users me-2"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_products.php">
                                <i class="fas fa-boxes me-2"></i> Manage Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_orders.php">
                                <i class="fas fa-shopping-cart me-2"></i> View Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Message Alerts -->
                <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>Manage Users</h2>
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add User
                    </a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (!empty($all_users)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id']) ?></td>
                                        <td><?= htmlspecialchars($user['display_name']) ?></td>
                                        <td>
                                            <span class="badge user-type-badge <?= $user['user_type'] ?>-badge">
                                                <?= ucfirst(str_replace('_', ' ', $user['user_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?= $user['id'] ?>&type=<?= $user['user_type'] ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="manage_users.php?delete_id=<?= $user['id'] ?>&type=<?= $user['user_type'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No users found in the system
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close();
}
?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close();
}
?>