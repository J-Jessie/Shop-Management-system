<?php
session_start();

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Admin';
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        $message = "Username and password are required";
        $message_type = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match";
        $message_type = "danger";
    } else {
        try {
            // Check if username exists in any table
            $tables = ['sales_staff', 'shopkeepers', 'customers'];
            $username_exists = false;
            
            foreach ($tables as $table) {
                $stmt = $conn->prepare("SELECT id FROM $table WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $username_exists = true;
                    break;
                }
            }
            
            if ($username_exists) {
                $message = "Username already exists";
                $message_type = "danger";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert into the appropriate table
                $stmt = $conn->prepare("INSERT INTO $user_type (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashed_password);
                
                if ($stmt->execute()) {
                    $message = "User created successfully";
                    $message_type = "success";
                    $_POST = []; // Clear form
                    
                    // Set success message for redirect
                    $_SESSION['user_message'] = $message;
                    $_SESSION['user_message_type'] = $message_type;
                    header("Location: manage_users.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation (same as manage_users.php) -->
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (same as manage_users.php) -->
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="user_type" class="form-label">User Type</label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="sales_staff">Sales Staff</option>
                                    <option value="shopkeepers">Shopkeeper</option>
                                    <option value="customers">Customer</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create User
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
$conn->close();
?>