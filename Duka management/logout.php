<?php
session_start();

// Store referring URL before any redirects
$previous_page = $_SERVER['HTTP_REFERER'] ?? 'index.php';

// Determine default redirect based on user role
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'customer':
            $default_redirect = 'customer_dashboard.php';
            break;
        case 'sales_staff':
            $default_redirect = 'salesstaff_dashboard.php';
            break;
        case 'shopkeeper':
            $default_redirect = 'shopkeeper_dashboard.php';
            break;
        default:
            $default_redirect = 'index.php';
    }
} else {
    $default_redirect = 'index.php';
}

// Check if logout is confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_logout'])) {
        // Clear all session variables
        $_SESSION = array();
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            ); 
        }
        
        // Destroy the session
        session_destroy();
        
        // Prevent caching and back-button access
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Location: Login.php");
        exit();
    }
    
    // If "No" was clicked, redirect back to appropriate dashboard
    if (isset($_POST['cancel_logout'])) {
        header("Location: $default_redirect");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        /* Watermark effect for background image */
        body::after {
            content: "";
            background-image: url('images/image5.jpg');
            background-size: cover;
            opacity: 0.9;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            position: absolute;
            z-index: -1;
        }
        
        .logout-container {
            text-align: center;
            max-width: 500px;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            background: linear-gradient(135deg,rgb(71, 209, 17),rgb(212, 245, 26));
            backdrop-filter: blur(5px);
            animation: fadeIn 0.5s ease-in-out;
        }
        .sad-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .btn-container {
            margin-top: 30px;
        }
        .btn-lg {
            min-width: 150px;
            margin: 5px;
        }
        h2 {
            color: #343a40;
            margin-bottom: 15px;
        }
        p.text-muted {
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="logout-container animate__animated animate__fadeIn">
        <div class="sad-icon animate__animated animate__bounceIn">
            <i class="fas fa-sad-tear"></i>
        </div>
        <h2>Are you sure you want to logout?</h2>
        <p class="text-muted">We'll be sad to see you go...</p>
        
        <div class="btn-container">
            <form method="POST" action="logout.php">
                <button type="submit" name="confirm_logout" class="btn btn-danger btn-lg">
                    <i class="fas fa-sign-out-alt"></i> Yes, Logout
                </button>
                <button type="submit" name="cancel_logout" class="btn btn-primary btn-lg">
                    <i class="fas fa-home"></i> No, Stay
                </button>
            </form>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <!-- Script to prevent back-button after logout -->
    <script>
        // Prevent caching of this page
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
        
        // Prevent going back after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>