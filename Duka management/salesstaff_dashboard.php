<?php
session_start();
// Check for theme preference in multiple sources with priority
$theme = '';
if (isset($_SESSION['theme'])) {
    $theme = $_SESSION['theme'];
} 
if (isset($_COOKIE['theme']) && empty($theme)) {
    $theme = $_COOKIE['theme'];
    $_SESSION['theme'] = $theme; // Sync to session for future requests
}
// Finally check localStorage via JavaScript will handle this

date_default_timezone_set('Africa/Nairobi');
// Enabling error reporting for debugging.VERY IMPORTANT: Disable or restrict on production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// preventing caching of restricted pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// --- Session and Role Verification ---
// Checking if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit(); //exit after a header redirect to prevent further script execution
}

// Verifying user role exists in session
if (!isset($_SESSION['user_role'])) {
    // If role not set, clear session and redirect to login with an error message
    session_unset();
    session_destroy();
    header("Location: Login.php?error=User role not set in session. Please login again.");
    exit(); //exit after a header redirect
}

// Check if user has Sales Staff role
if ($_SESSION['user_role'] !== 'sales_staff') {
    // Redirect to an unauthorized page or login with a message for incorrect role
    //this was just for fun to add on code: it will still work if removed
    //this is because my users are logging in using email and password
    //a single email can only be used once
    header("Location: unauthorized.php"); // Assuming you have an unauthorized.php or similar
    exit(); // Always exit after a header redirect
}

// --- Variable Initialization ---
// Initializing all variables with default values to prevent undefined variable warnings
$new_orders = 0;
$revenue = 0;
$active_customers = 0;
$completion_rate = 0;
$recent_orders = null; // Initializing as null; will be a mysqli_result object if query succeeds
$recent_activities = null; // Initializing as null; will be a mysqli_result object if query succeeds
$error = '';   // Initializing error message as empty string
$success = $_GET['success'] ?? ''; // Getting success message from URL, if any
$order_message = $_SESSION['order_message'] ?? ''; // Get order message from session, if any
$order_message_type = $_SESSION['order_message_type'] ?? 'info'; // Getting message type or default to 'info'
unset($_SESSION['order_message'], $_SESSION['order_message_type']); // Clearing session messages after use

$current_user_id = $_SESSION['user_id'];
$salesstaff_name = ''; //fetching from database
$business_name = 'DUKA | sales staff'; // Default, can be overridden by DB fetch if applicable
$welcome_message = 'Welcome back!'; // Default, will be overridden by time-based greeting

// --- Database Connection and Query Execution ---
// Including the database connection file. This file should establish $conn as a mysqli object.
//db_connection.php correctly throws an Exception on connection failure, which is good.
require_once 'db_connection.php';

// Enabling mysqli exceptions for better error handling. This makes query errors throw Exceptions
// which are then caught by the try-catch block below, setting the $error message.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Checking if the connection object is valid after db_connection.php has run.
    // This catches cases where db_connection.php might have failed to set $conn
    // or if the connection itself has an error.
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection object is invalid or has an error.");
    }

    // Get sales staff details
    $stmt = $conn->prepare("SELECT fullname FROM sales_staff WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $salesstaff_name = $row['fullname'];
            }
            $result->free(); // Free the result set
        }
        $stmt->close(); // Close the statement
    } else {
        throw new Exception("Failed to prepare sales staff details query: " . $conn->error);
    }

    // Set time-based greeting message using the fetched sales staff name
    $hour = date('G');
    if ($hour >= 5 && $hour < 12) {
        $welcome_message = "Good morning, " . htmlspecialchars($salesstaff_name) . "! Ready for a productive day?";
    } elseif ($hour >= 12 && $hour < 17) {
        $welcome_message = "Good afternoon, " . htmlspecialchars($salesstaff_name) . "! Hope you're having a great day!";
    } else {
        $welcome_message = "Good evening, " . htmlspecialchars($salesstaff_name) . "! Great work today!";
    }

    // Get today's orders count
    $new_orders_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders
                                    WHERE DATE(order_date) = CURDATE() AND sales_staff_id = ?");
    if ($new_orders_stmt) {
        $new_orders_stmt->bind_param("i", $current_user_id);
        if ($new_orders_stmt->execute()) {
            $result = $new_orders_stmt->get_result();
            // Use null coalescing operator (??) for safety if fetch_assoc returns null
            $new_orders = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
            $result->free(); // Free the result set
        }
        $new_orders_stmt->close();
    } else {
        throw new Exception("Failed to prepare new orders query: " . $conn->error);
    }

    // Get today's revenue
    $revenue_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders
                                   WHERE DATE(order_date) = CURDATE() AND status = 'Completed' AND sales_staff_id = ?");
    if ($revenue_stmt) {
        $revenue_stmt->bind_param("i", $current_user_id);
        if ($revenue_stmt->execute()) {
            $result = $revenue_stmt->get_result();
            $revenue = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
            $result->free();
        }
        $revenue_stmt->close();
    } else {
        throw new Exception("Failed to prepare revenue query: " . $conn->error);
    }

    // Get active customers count
    $customers_stmt = $conn->prepare("SELECT COUNT(DISTINCT customer_id) as count FROM orders
                                     WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND sales_staff_id = ?");
    if ($customers_stmt) {
        $customers_stmt->bind_param("i", $current_user_id);
        if ($customers_stmt->execute()) {
            $result = $customers_stmt->get_result();
            $active_customers = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
            $result->free();
        }
        $customers_stmt->close();
    } else {
        throw new Exception("Failed to prepare active customers query: " . $conn->error);
    }

    //below:
    // Getting completion rate
    // Note: This query calculates a percentage.therefore ensure database tables have data to avoid division by zero.
    // GREATEST(..., 1) prevents division by zero if COUNT(*) is 0.
    $completion_stmt = $conn->prepare("SELECT
                                      (SELECT COUNT(*) FROM orders WHERE status = 'Completed' AND sales_staff_id = ?) /
                                      GREATEST((SELECT COUNT(*) FROM orders WHERE sales_staff_id = ?), 1) * 100 as rate");
    if ($completion_stmt) {
        $completion_stmt->bind_param("ii", $current_user_id, $current_user_id);
        if ($completion_stmt->execute()) {
            $result = $completion_stmt->get_result();
            $completion_rate = round($result ? ($result->fetch_assoc()['rate'] ?? 0) : 0, 0);
            $result->free();
        }
        $completion_stmt->close();
    } else {
        throw new Exception("Failed to prepare completion rate query: " . $conn->error);
    }

    // Get recent orders
    $orders_stmt = $conn->prepare("SELECT o.*, c.fullname as customer_name
                                  FROM orders o
                                  LEFT JOIN customers c ON o.customer_id = c.id
                                  WHERE o.sales_staff_id = ?
                                  ORDER BY o.order_date DESC
                                  LIMIT 7");
    if ($orders_stmt) {
        $orders_stmt->bind_param("i", $current_user_id);
        if ($orders_stmt->execute()) {
            $recent_orders = $orders_stmt->get_result(); // This assigns a mysqli_result object
        }
        $orders_stmt->close(); // Close statement after use
    } else {
        throw new Exception("Failed to prepare recent orders query: " . $conn->error);
    }

 // Get recent activities
    // The UNION query is quite complex. Ensure tables/columns exist and data types are compatible.
    $activities_stmt = $conn->prepare("SELECT
                                      'order' as type, id as ref_id, CONCAT('New order #', id, ' from ',
                                      (SELECT fullname FROM customers WHERE id = customer_id)) as description,
                                      order_date as timestamp
                                      FROM orders
                                      WHERE sales_staff_id = ?
                                      -- REMOVED THE FOLLOWING SECTION AS 'referred_by' COLUMN DOES NOT EXIST IN 'customers' TABLE
                                      -- UNION ALL
                                      -- SELECT
                                      -- 'customer' as type, id as ref_id, CONCAT('New customer registered: ', fullname) as description,
                                      -- created_at as timestamp
                                      -- FROM customers
                                      -- WHERE referred_by = ?
                                      ORDER BY timestamp DESC
                                      LIMIT 4");
    if ($activities_stmt) {
        $activities_stmt->bind_param("i", $current_user_id);
        if ($activities_stmt->execute()) {
            $recent_activities = $activities_stmt->get_result(); //assigns a mysqli_result object
        }
        $activities_stmt->close(); // Close statement after use
    } else {
        throw new Exception("Failed to prepare recent activities query: " . $conn->error);
    }

} catch (Exception $e) {
    // TEMPORARY DEBUGGING: Display the actual exception message on the page.
    // REMOVE THIS LINE AFTER DEBUGGING!
    $error = "ERROR: " . $e->getMessage() . " (File: " . $e->getFile() . ", Line: " . $e->getLine() . ")";

    // Log the actual detailed error for debugging purposes (check server's PHP error logs)
    error_log("Dashboard PHP Block Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // Fallback for generic message if you don't want the full detail after debugging
    // $error = "System encountered an error. Please try again later.";
} finally {
    // Ensure the database connection is closed whether an error occurred or not
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($business_name); ?> - Sales Staff Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
    // This script immediately applies the stored theme from localStorage
    const storedTheme = localStorage.getItem('theme');
    if (storedTheme) {
        document.body.classList.add(storedTheme);
    }
</script>
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4A6BFF;
            --primary-dark: #3A56D4;
            --secondary-color: #FF7D4A;
            --secondary-dark: #E06D3A;
            --accent-color: #00BFA6;
            --accent-dark: #009C85;
            --danger-color: #FF3860;
            --danger-dark: #E02D4E;
            --success-color: #48C774;
            --warning-color: #FFDD57;
            --light-color: #F8F9FF;
            --dark-color: #2E2E3A;
            --gray-color: #8C8C9E;
            --border-color: #E2E3ED;
            
            --gradient-primary: linear-gradient(135deg, #4A6BFF 0%, #6B8AFF 100%);
            --gradient-secondary: linear-gradient(135deg, #FF7D4A 0%, #FF9D7A 100%);
            --gradient-accent: linear-gradient(135deg, #00BFA6 0%, #00D1B2 100%);
            --gradient-warning: linear-gradient(135deg, #FFDD57 0%, #FFE773 100%);
            --gradient-danger: linear-gradient(135deg, #FF3860 0%, #FF5E7F 100%);
            
            --card-shadow: 0 4px 20px rgba(74, 107, 255, 0.1);
            --hover-shadow: 0 15px 30px rgba(74, 107, 255, 0.2);
        }
        /* Dark mode styles for specific elements */
.dark-mode .table,
.dark-mode .list-group-item,
.dark-mode .alert {
    background-color: transparent;
    color: var(--dark-color); /* This will be the light text color */
}

.dark-mode .table thead,
.dark-mode .table tbody tr {
    background-color: transparent;
    color: inherit;
    border-color: var(--border-color); /* This will be the dark border color */
}

.dark-mode .table {
    --bs-table-bg: #3e3e4a;
    --bs-table-striped-bg: #4a4a58;
    --bs-table-striped-color: #f8f9ff;
    --bs-table-active-bg: #555;
    --bs-table-active-color: #fff;
    --bs-table-hover-bg: #4a4a58;
    --bs-table-hover-color: #f8f9ff;
    --bs-table-border-color: #444;
}

.dark-mode .btn-primary,
.dark-mode .btn-success,
.dark-mode .btn-warning,
.dark-mode .btn-info {
    color: var(--light-color) !important;
}

.dark-mode .list-group-item {
    background-color: transparent;
    color: var(--dark-color);
    border-color: var(--border-color);
}
        /* Enhanced Dark Mode Styles */
.dark-mode {
    --light-color: #2E2E3A;
    --dark-color: #F8F9FF;
    --gray-color: #8C8C9E;
    --border-color: #444;
    --card-bg: #343a40;
    --card-header-bg: #495057;
}

.dark-mode body {
    background-color: var(--light-color);
    color: var(--dark-color);
}

.dark-mode .sidebar {
    background-color: #343a40;
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
}

.dark-mode .main-content {
    background-color: var(--light-color);
}

.dark-mode .user-profile {
    border-bottom-color: var(--border-color);
}

.dark-mode .user-profile h5 {
    color: var(--dark-color);
}

.dark-mode .user-profile p {
    color: var(--gray-color);
}

.dark-mode .store-name {
    color: var(--primary-color);
}

.dark-mode .nav-link {
    color: var(--dark-color);
}

.dark-mode .nav-link:hover, 
.dark-mode .nav-link.active {
    background: var(--gradient-primary);
    color: white !important;
}

.dark-mode .content-header {
    border-bottom-color: var(--border-color);
}

.dark-mode .date-time {
    color: var(--gray-color);
}

.dark-mode .welcome-header {
    background: var(--card-bg);
    border-left-color: var(--primary-color);
}

.dark-mode .welcome-header h3 {
    color: var(--dark-color);
}

.dark-mode .welcome-header p {
    color: var(--gray-color);
}

.dark-mode .welcome-message {
    color: var(--dark-color);
}

.dark-mode .stat-card {
    background: var(--card-bg);
    border-color: var(--border-color);
}

.dark-mode .stat-label {
    color: var(--gray-color);
}

.dark-mode .stat-note {
    color: var(--gray-color);
}

.dark-mode .card {
    background: var(--card-bg);
    border-color: var(--border-color);
}

.dark-mode .card-header {
    background: var(--gradient-primary);
}

.dark-mode .table thead th {
    background-color: #495057;
    color: var(--dark-color);
    border-bottom-color: var(--border-color);
}

.dark-mode .table tbody tr:hover {
    background-color: #495057;
}

.dark-mode .activity-item {
    border-bottom-color: var(--border-color);
}

.dark-mode .activity-item:hover {
    background-color: #495057;
}

.dark-mode .activity-time {
    color: var(--gray-color);
}

.dark-mode .alert-info,
.dark-mode .alert-success,
.dark-mode .alert-danger {
    color: white;
}
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .navbar {
            background: var(--gradient-primary) !important;
            box-shadow: 0 4px 20px rgba(74, 107, 255, 0.2);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.3em;
        }
        
        .user-display {
            display: flex;
            align-items: center;
            color: white;
            font-weight: 500;
        }
        
        .user-display i {
            margin-right: 8px;
            font-size: 1.1em;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .container-fluid {
            padding: 0;
        }
        
        .sidebar {
            background-color: white;
            min-height: calc(100vh - 70px);
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 70px;
            padding: 20px 0;
        }
        
        .main-content {
            padding: 30px;
            background-color: var(--light-color);
            min-height: calc(100vh - 70px);
        }
        
        .user-profile {
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .user-profile img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.2);
        }
        
        .user-profile h5 {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .user-profile p {
            color: var(--gray-color);
            font-size: 0.9rem;
        }
        
        .store-name {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-top: 10px;
        }
        
        .store-name i {
            margin-right: 8px;
        }
        
        .nav-link {
            color: var(--dark-color);
            font-weight: 500;
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1em;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--gradient-primary);
            color: white !important;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.2);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .content-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .date-time {
            display: flex;
            align-items: center;
            color: var(--gray-color);
            font-weight: 500;
        }
        
        .date-time i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .date-time span {
            margin-left: 15px;
        }
        
        .welcome-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border-left: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: var(--gradient-primary);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .welcome-header h3 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .welcome-header p {
            color: var(--gray-color);
            margin-bottom: 0;
        }
        
        .welcome-header .lead strong {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .welcome-message {
            font-size: 1.1rem;
            margin-top: 15px;
            color: var(--dark-color);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
            border: 1px solid var(--border-color);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card:nth-child(2) .stat-value {
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            background-clip: text;
        }
        
        .stat-card:nth-child(3) .stat-value {
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            background-clip: text;
        }
        
        .stat-card:nth-child(4) .stat-value {
            background: var(--gradient-warning);
            -webkit-background-clip: text;
            background-clip: text;
        }
        
        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stat-note {
            font-size: 0.8rem;
            color: var(--gray-color);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
            border: none;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .recent-orders {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            color: var(--dark-color);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9ff;
            transform: translateX(5px);
        }
        
        .badge {
            padding: 6px 10px;
            font-weight: 500;
            font-size: 0.8rem;
            border-radius: 50px;
        }
        
        .bg-success {
            background: var(--gradient-accent) !important;
        }
        
        .bg-warning {
            background: var(--gradient-warning) !important;
            color: var(--dark-color) !important;
        }
        
        .bg-danger {
            background: var(--gradient-danger) !important;
        }
        
        .quick-actions .btn {
            width: 100%;
            margin-bottom: 10px;
            padding: 12px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .quick-actions .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background-color: #f8f9ff;
            padding-left: 10px;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-bottom: 5px;
        }
        
        .activity-text {
            font-size: 0.9rem;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .alert-success {
            background: var(--gradient-accent);
            color: white;
        }
        
        .alert-danger {
            background: var(--gradient-danger);
            color: white;
        }
        
        .alert-info {
            background: var(--gradient-primary);
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                min-height: auto;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-time {
                margin-top: 15px;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($theme); ?>">
    <script>
    // This script immediately applies the stored theme from localStorage
    const storedTheme = localStorage.getItem('theme');
    if (storedTheme) {
        document.body.classList.add(storedTheme);
    }
</script>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-store"></i><?php echo htmlspecialchars($business_name); ?>
            </a>
            <div class="d-flex align-items-center">
                <div class="user-display me-3">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($salesstaff_name); ?>
                </div>
                <span id="current-time"></span>
                <a href="logout.php" class="btn btn-logout btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($salesstaff_name); ?>&background=4A6BFF&color=fff&size=200" alt="User Profile">
                    <h5><?php echo htmlspecialchars($salesstaff_name); ?></h5>
                    <p>Sales Staff</p>
                    <div class="store-name">
                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($business_name); ?>
                    </div>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_products.php">
                            <i class="fas fa-boxes"></i> Manage Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_products.php">
                            <i class="fas fa-plus-square"></i> Add Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_orders.php">
                            <i class="fas fa-shopping-cart"></i> View Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_management.php">
                            <i class="fas fa-users"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sales_target.php">
                            <i class="fas fa-bullseye"></i> Sales Targets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>
 
             <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger fade-in"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success fade-in"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($order_message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($order_message_type); ?> fade-in">
                        <?php echo htmlspecialchars($order_message); ?>
                    </div>
                <?php endif; ?>

                <div class="welcome-header fade-in">
                    <!-- Initial friendly greeting (always visible) -->
                    <h3>Hi, <?php echo htmlspecialchars($salesstaff_name); ?>!</h3>
                    <h4>Are you ready to make some sales today?</h4>
                    
                    <!-- Time-based greeting (THIS WILL BE UPDATED BY JAVASCRIPT) -->
                    <div class="welcome-message" id="dynamicGreeting">
                        <i class="fas fa-lightbulb text-warning me-2"></i>
                        <?php
                        // PHP fallback greeting (this will be visible briefly, then replaced by JS)
                        // Keep your existing PHP time-based logic for $welcome_message here as a fallback
                        echo htmlspecialchars($welcome_message);
                        ?>
                    </div>
                </div>
                <!-- Stats Cards -->
                <div class="row fade-in">
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $new_orders; ?></div>
                            <div class="stat-label">Today's Orders</div>
                            <div class="stat-note">
                                <small>Orders you processed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-value">KSH <?php echo number_format($revenue, 2); ?></div>
                            <div class="stat-label">Today's Sales</div>
                            <div class="stat-note">
                                <small>Your completed sales</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $active_customers; ?></div>
                            <div class="stat-label">Active Customers</div>
                            <div class="stat-note">
                                <small>Last 30 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $completion_rate; ?>%</div>
                            <div class="stat-label">Completion Rate</div>
                            <div class="stat-note">
                                <small>Your order completion</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
<div class="row mt-4">
    <div class="col-lg-8 mb-4">
        <div class="card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Recent Orders</h5>
            </div>
            <div class="card-body recent-orders">
                <?php
                // IMPORTANT: Ensure $recent_orders is an array before this point.
                // If database fetching code is supposed to return a mysqli_result object,
                // then the issue is in how $recent_orders is being assigned before this block.
                // However, if it's intentionally an array of fetched rows, the fix is correct.
                if (isset($recent_orders) && is_array($recent_orders) && count($recent_orders) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // If $recent_orders is an array, you need to loop through it directly.
                            // The original `while ($order = $recent_orders->fetch_assoc())`
                            // is for mysqli_result objects.
                            // You need to change this loop if $recent_orders is an array of arrays.
                            // Assuming $recent_orders is an array of associative arrays:
                            foreach ($recent_orders as $order):
                                $status_class = '';
                                if ($order['status'] == 'Completed') $status_class = 'bg-success';
                                if ($order['status'] == 'Processing') $status_class = 'bg-warning';
                                if ($order['status'] == 'Cancelled') $status_class = 'bg-danger';
                            ?>
                                <tr class="fade-in">
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>KSH <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; // Changed from while to foreach ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No recent orders found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
                    
                    <!-- Right Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body quick-actions">
                                <a href="new_order.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Create New Order
                                </a>
                                <a href="customer_search.php" class="btn btn-accent">
                                    <i class="fas fa-user-plus me-2"></i> Add New Customer
                                </a>
                                <a href="inventory_check.php" class="btn btn-secondary">
                                    <i class="fas fa-clipboard-list me-2"></i> Check Inventory
                                </a>
                                <a href="sales_report.php" class="btn btn-warning text-white">
                                    <i class="fas fa-chart-line me-2"></i> Generate Report
                                </a>
                            </div>
                        </div>
                        
                       <!-- Recent Activities -->
                        <div class="card mt-4 fade-in">
                        <div class="card-header">
                           <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Activities</h5>
                        </div>
                        <div class="card-body">
                        <?php
                           if (isset($recent_activities) && is_array($recent_activities) && count($recent_activities) > 0): ?>
                        <?php
                           foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                        <div class="activity-time">
                        <i class="fas fa-clock me-1"></i>
                        <?php
                        $timestamp = strtotime($activity['timestamp']);
                        $timeDiff = time() - $timestamp;

                        if ($timeDiff < 60) {
                            echo "Just now";
                        } elseif ($timeDiff < 3600) {
                            echo floor($timeDiff/60) . " min ago";
                        } elseif ($timeDiff < 86400) {
                            echo floor($timeDiff/3600) . " hour" . (floor($timeDiff/3600) > 1 ? "s" : "") . " ago";
                        } else {
                            echo date('M j, g:i a', $timestamp);
                        }
                        ?>
                    </div>
                    <div class="activity-text">
                        <?php echo htmlspecialchars($activity['description']); ?>
                    </div>
                </div>
            <?php endforeach; // Changed from while to foreach ?>
        <?php else: ?>
            <div class="activity-item">
                <div class="activity-text text-muted">
                    No recent activities found
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // --- 1. Real-time Clock Update ---
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        const dateString = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

        const dateTimeElement = document.getElementById('current-time'); 
        if (dateTimeElement) {
            dateTimeElement.textContent = `${dateString} : ${timeString}`;
        } else {
            console.warn("Element with ID 'current-time' not found. Real-time clock cannot be displayed.");
        }
    }
    setInterval(updateTime, 1000); 
    updateTime(); 

    // --- 2. Dynamic Greeting based on User's Local Time ---
    const salesstaffName = "<?php echo htmlspecialchars($salesstaff_name); ?>";
    const dynamicGreetingElement = document.getElementById('dynamicGreeting'); 

    function updateGreeting() {
        const now = new Date();
        const hour = now.getHours();
        let greetingText = "";

        if (hour >= 5 && hour < 12) { // 5 AM to 11:59 AM
            greetingText = `Good morning, ${salesstaffName}! Ready for a productive day!`;
        } else if (hour >= 12 && hour < 17) { // 12 PM to 4:59 PM
            greetingText = `Good afternoon, ${salesstaffName}! Hope you're having a great day!`;
        } else { // 5 PM to 4:59 AM
            greetingText = `Good evening, ${salesstaffName}! Great work today!`;
        }
        
        if (dynamicGreetingElement) {
            dynamicGreetingElement.innerHTML = `<i class="fas fa-lightbulb text-warning me-2"></i> ${greetingText}`;
        } else {
            console.warn("Element with ID 'dynamicGreeting' not found. Dynamic greeting cannot be set.");
        }
    }

    // --- 3. Animation for Fade-in Elements on Scroll ---
    document.addEventListener('DOMContentLoaded', function() {
        updateGreeting(); 

        const fadeElements = document.querySelectorAll('.fade-in');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Just add the class, let CSS handle the transition properties
                    entry.target.classList.add('animated'); 
                    observer.unobserve(entry.target); // Stop observing once animated
                }
            });
        }, { threshold: 0.1 }); 
        
        fadeElements.forEach(element => {
            // Check if element is already in view on load and animate it immediately
            const rect = element.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                element.classList.add('animated');
            } else {
                observer.observe(element);
            }
        });
    });
    // Optional:want the greeting to update periodically
    // setInterval(updateGreeting, 60 * 1000); 
    // Function to sync theme across all open tabs/windows
function syncTheme() {
    const theme = localStorage.getItem('theme') || '';
    document.body.classList.toggle('dark-mode', theme === 'dark-mode');
    
    // Update session via AJAX to keep everything in sync
    fetch('update_theme.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'theme=' + theme
    });
}

// Listen for storage events (when localStorage changes in other tabs)
window.addEventListener('storage', function(e) {
    if (e.key === 'theme') {
        syncTheme();
    }
});

// Initial sync
syncTheme();
</script>
</body>
</html>