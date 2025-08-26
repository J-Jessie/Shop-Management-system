<?php
// NUCLEAR-GRADE SIGNUP PROCESSOR WITH FAILSAFES
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Keep off for production
ini_set('log_errors', '1');    // Always log errors

/**
 * Handles emergency shutdowns by sending a JSON error response.
 *
 * @param string $message The error message to display.
 */
function emergency_shutdown(string $message) {
    error_log("EMERGENCY SHUTDOWN: " . $message); // Always log server-side
    
    // Ensure no prior output before sending JSON
    if (ob_get_length() > 0) {
        ob_clean(); // Discard any buffered output
    }

    if (!headers_sent()) {
        header('Content-Type: application/json'); // Explicitly set JSON content type
        echo json_encode(['status' => 'error', 'message' => $message]);
    } else {
        // Fallback if headers were already sent (should ideally not happen with ob_clean)
        echo "{\"status\":\"error\",\"message\":\"" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\"}";
    }
    exit(); // Always exit immediately
}

// 2. DATABASE CONNECTION
try {
    require_once 'db_connection.php'; // Ensure db_connection.php is in the same directory
    if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
        emergency_shutdown("Database unavailable. Please try again later. (DB-001)");
    }
} catch (Throwable $e) {
    error_log("Database connection failed: " . $e->getMessage());
    emergency_shutdown("System error: DB-001 - Database connection issue.");
}

// 3. REVALIDATE ALL INPUTS
$required = ['role', 'fullname', 'email', 'password', 'confirm_password'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) { // Check for existence and emptiness after trimming
        emergency_shutdown("Missing required field: " . $field);
    }
}

$role = $_POST['role'];
$fullname = trim($_POST['fullname']);
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// 4. DATA VALIDATION
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    emergency_shutdown("Invalid email format provided.");
}

// Password strength validation (client-side provides feedback, server-side enforces)
if (strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)) {
    emergency_shutdown("Password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character.");
}

if ($password !== $confirm_password) {
    emergency_shutdown("Passwords do not match. Please re-enter.");
}

// 5. DUPLICATE CHECK - MODIFIED TO CHECK ALL TABLES
$all_tables = ['customers', 'shopkeepers', 'sales_staff']; // List all tables to check
$email_exists = false;

try {
    foreach ($all_tables as $table_to_check) {
        $stmt = $conn->prepare("SELECT email FROM {$table_to_check} WHERE email = ? LIMIT 1");
        if ($stmt === false) {
            throw new Exception("Failed to prepare duplicate check statement for table {$table_to_check}: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $email_exists = true;
            break; // Email found in one table, no need to check others
        }
        $stmt->close(); // Close statement for current table before next iteration
    }

    if ($email_exists) {
        emergency_shutdown("The email address '$email' is already registered. Please use a different email or log in.");
    }
} catch (Exception $e) {
    error_log("Duplicate check failed: " . $e->getMessage());
    emergency_shutdown("System error: DB-002 - Error checking for existing email.");
}

// 6. HASHING
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
if ($hashed_password === false) {
    emergency_shutdown("System error: SEC-001 - Password hashing failed.");
}

// 7. ROLE-SPECIFIC PROCESSING
$additional_fields = [];
$types = "sss"; // base: fullname, email, password

switch ($role) {
    case 'customer':
    case 'shopkeeper':
        if (!isset($_POST['phone']) || empty(trim($_POST['phone']))) {
            emergency_shutdown("Missing phone number for $role registration.");
        }
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']); // Sanitize phone
        if (strlen($phone) < 7) { // Basic phone length check
            emergency_shutdown("Invalid phone number format.");
        }
        $additional_fields[] = $phone;
        $types .= "s";
        
        if ($role === 'shopkeeper') {
            if (!isset($_POST['business_name']) || empty(trim($_POST['business_name']))) {
                emergency_shutdown("Missing business name for shopkeeper registration.");
            }
            $business_name = trim($_POST['business_name']);
            $additional_fields[] = $business_name;
            $types .= "s";
        }
        break;
    case 'sales_staff':
        // No additional fields for sales staff in this structure
        break;
    default:
        emergency_shutdown("Unknown role encountered.");
}

// 8. DATABASE INSERTION
$tables_map = [ // Map for actual table names
    'customer' => 'customers',
    'shopkeeper' => 'shopkeepers',
    'sales_staff' => 'sales_staff'
];

if (!isset($tables_map[$role])) {
    emergency_shutdown("Invalid role selected for database insertion.");
}
$target_table = $tables_map[$role];

try {
    $columns = '';
    $placeholders = '';
    switch($role) {
        case 'customer':
            $columns = 'fullname, email, phone, password';
            $placeholders = '?, ?, ?, ?';
            break;
        case 'shopkeeper':
            $columns = 'fullname, email, phone, business_name, password';
            $placeholders = '?, ?, ?, ?, ?';
            break;
        case 'sales_staff':
            $columns = 'fullname, email, password';
            $placeholders = '?, ?, ?';
            break;
        default:
            emergency_shutdown("Role not handled for DB insertion.");
    }
    
    $stmt = $conn->prepare("INSERT INTO {$target_table} ($columns) VALUES ($placeholders)");
    if ($stmt === false) {
        throw new Exception("Failed to prepare insert statement: " . $conn->error);
    }

    $params = array_merge([$fullname, $email], $additional_fields, [$hashed_password]);
    
    $bind_params = [];
    $bind_params[] = $types;
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key]; // Pass by reference for bind_param
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    
    if (!$stmt->execute()) {
        error_log("Database insertion failed for role '{$role}': " . $stmt->error);
        emergency_shutdown("Registration failed due to a database error: " . $stmt->error);
    }
    $stmt->close();

} catch (Throwable $e) {
    error_log("REGISTRATION DATABASE INSERTION ERROR: " . $e->getMessage());
    emergency_shutdown("System error: DB-003 - Failed to save registration data: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// 9. SUCCESSFUL REGISTRATION - Send JSON response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Registration complete! You can now log in.',
    'redirect_url' => 'Login.php?status=success&email=' . urlencode($email) . '&message=' . urlencode("Registration complete! You can now log in.")
]);
exit(); // Crucial: Exit immediately after sending JSON
?>