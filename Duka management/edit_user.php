<?php
session_start();

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Admin';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_type = isset($_GET['type']) ? $_GET['type'] : '';
$user = null;
$message = '';
$message_type = '';

// Validate user type
$valid_types = ['sales_staff', 'shopkeepers', 'customers'];
if (!in_array($user_type, $valid_types)) {
    die("Invalid user type");
}

// Fetch user details
try {
    $stmt = $conn->prepare("SELECT id, username FROM $user_type WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        $message = "User not found";
        $message_type = "danger";
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $username = trim($_POST['username']);
    $change_password = isset($_POST['change_password']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Validate inputs
        if (empty($username)) {
            $message = "Username is required";
            $message_type = "danger";
        } elseif ($change_password && ($password !== $confirm_password)) {
            $message = "Passwords do not match";
            $message_type = "danger";
        } else {
            if ($change_password) {
                // Update with password change
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE $user_type SET username = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $hashed_password, $user_id);
            } else {
                // Update without password change
                $stmt = $conn->prepare("UPDATE $user_type SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $username, $user_id);
            }
            
            if ($stmt->execute()) {
                $message = "User updated successfully";
                $message_type = "success";
                // Refresh user data
                $user['username'] = $username;
                
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
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
                    <h2><i class="fas fa-edit"></i> Edit User</h2>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($user): ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="change_password" name="change_password">
                                    <label class="form-check-label" for="change_password">Change Password</label>
                                </div>
                                <div id="password_fields" class="mb-3" style="display: none;">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="password" name="password">
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update User
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">User not found</div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide password fields
        document.getElementById('change_password').addEventListener('change', function() {
            const fields = document.getElementById('password_fields');
            fields.style.display = this.checked ? 'block' : 'none';
            
            // Make fields required if checked
            document.getElementById('password').required = this.checked;
            document.getElementById('confirm_password').required = this.checked;
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>