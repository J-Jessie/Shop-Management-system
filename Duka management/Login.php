<?php
require_once 'db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']); // ADDED TRIM HERE
    $password = $_POST['password'];
    $error = '';

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check in all three tables with proper role identification
        $tables = [
            'customers' => 'customer',
            'shopkeepers' => 'shopkeeper', 
            'sales_staff' => 'sales_staff'
        ];

        $user = null;
        $userRole = '';

        foreach ($tables as $table => $role) {
            $stmt = $conn->prepare("SELECT id, email, password FROM $table WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $userRole = $role;

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $userRole;

                    // Redirect based on role
                    switch ($userRole) {
                        case 'shopkeeper':
                            header("Location: shopkeeper_dashboard.php");
                            exit();
                        case 'sales_staff':
                            header("Location: salesstaff_dashboard.php");
                            exit();
                        case 'customer':
                            header("Location: customer_dashboard.php");
                            exit();
                    }
                } else {
                    $error = "Invalid email or password"; // Password mismatch
                }
                break; // Stop searching once a user is found (even if password doesn't match)
            }
        }

        if (!$user) { // No user found in any table
            $error = "Invalid email or password";
        }
    }
}

// Check for success message from registration
$showSuccess = isset($_GET['status']) && $_GET['status'] === 'success';
$registeredEmail = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Duka Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-image: url('images/image3.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: linear-gradient(135deg,rgb(71, 209, 17),rgb(212, 245, 26));
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(5px); 
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color:rgb(28, 25, 238);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color:rgb(202, 221, 223);
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-btn:hover {
            background: linear-gradient(135deg, #2980b9, #3498db);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .success-message {
            color: #2ecc71;
            text-align: center;
            margin-bottom: 1rem;
            background-color: rgba(46, 204, 113, 0.1);
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #2ecc71;
        }
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .footer-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .footer-links a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer-links a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to access your account</p>
        </div>

        <?php if ($showSuccess): ?>
            <div class="success-message">
                <?= htmlspecialchars(urldecode($_GET['message'])) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                    value="<?= htmlspecialchars($registeredEmail) ?>" 
                    placeholder="Enter your email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                    placeholder="Enter your password">
            </div>
            <button type="submit" class="login-btn">Sign In</button>
        </form>

        <div class="footer-links">
            <p>Don't have an account? <a href="Signin.php">Sign up</a></p>
            <p><a href="forgot_password.php">Forgot password?</a></p>
        </div>
    </div>
</body>
</html>