<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and verify paths
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Define root directory
define('ROOT_DIR', __DIR__);

// Verify and include database connection
$db_path = ROOT_DIR . '/db_connection.php';
if (!file_exists($db_path)) {
    die("Error: Database configuration file not found at: " . htmlspecialchars($db_path));
}
require_once $db_path;

// Initialize variables
$error = '';
$success = '';
$name = $contact_person = $email = $phone = '';

/**
 * Async-like database query execution using mysqli async queries simulation
 */
function async_db_query($conn, $query, $params = [], $types = '') {
    // Note: True async queries require mysqli_reap_async_query and non-blocking connection
    // This function simulates async by immediate execution but can be extended
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        return ['error' => "Prepare failed: " . $conn->error];
    }
    
    if (!empty($params)) {
        // Bind parameters dynamically with type checking
        $bind_names[] = $types;
        for ($i=0; $i<count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    
    if (!$stmt->execute()) {
        return ['error' => "Execute failed: " . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    $stmt->close();
    
    return ['data' => $data];
}
   
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging output for CSRF tokens
    error_log("Session CSRF token: " . ($_SESSION['csrf_token'] ?? 'not set'));
    error_log("POST CSRF token: " . ($_POST['csrf_token'] ?? 'not set'));
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission";
    } else {
        // Sanitize and validate inputs
        $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '');
        $contact_person = trim(filter_input(INPUT_POST, 'contact_person', FILTER_SANITIZE_STRING) ?? '');
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?? '');
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            $error = "Name and email are required fields";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address";
        } else {
            // Check if email exists (async-like approach)
            $emailCheck = async_db_query($conn, "SELECT id FROM retailers WHERE email = ?", [$email], 's');
            
            if (isset($emailCheck['error'])) {
                $error = "Database error: " . $emailCheck['error'];
            } elseif (!empty($emailCheck['data'])) {
                $error = "A retailer with this email already exists";
            } else {
                // Insert retailer (async-like approach)
                $insertResult = async_db_query(
                    $conn, 
                    "INSERT INTO retailers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)",
                    [$name, $contact_person, $email, $phone],
                    'ssss'
                );
                
                if (isset($insertResult['error'])) {
                    $error = "Error adding retailer: " . $insertResult['error'];
                } else {
                    $success = "Retailer added successfully!";
                    // Clear form fields
                    $name = $contact_person = $email = $phone = '';
                }
            }
        }
    }
}

// Pagination setup
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Fetch total count for pagination (async-like)
$countResult = async_db_query($conn, "SELECT COUNT(*) as total FROM retailers");
if (isset($countResult['error'])) {
    $error = "Error counting retailers: " . $countResult['error'];
    $total_retailers = 0;
} else {
    $total_retailers = $countResult['data'][0]['total'] ?? 0;
}
$total_pages = ceil($total_retailers / $per_page);

// Fetch paginated retailers (async-like)
$limit = $per_page;
$offset_val = $offset;

$retailersResult = async_db_query(
    $conn, 
    "SELECT * FROM retailers ORDER BY name ASC LIMIT ? OFFSET ?",
    [$limit, $offset_val],
    'ii'
);

if (isset($retailersResult['error'])) {
    $error = "Error fetching retailers: " . $retailersResult['error'];
    $retailers = [];
} else {
    $retailers = $retailersResult['data'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Retailers</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .error {
            color: #d9534f;
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success {
            color: #3c763d;
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #4cae4c;
        }
        
        .btn-secondary {
            background-color: #337ab7;
        }
        
        .btn-secondary:hover {
            background-color: #286090;
        }
        
        .btn-danger {
            background-color: #d9534f;
        }
        
        .btn-danger:hover {
            background-color: #c9302c;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f8f8;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .action-buttons a {
            margin-right: 5px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            color: #333;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        
        .pagination a.active {
            background-color: #5cb85c;
            color: white;
            border: 1px solid #5cb85c;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php 
    // Verify header file exists
    $header_path = ROOT_DIR . '/header.php';
    if (file_exists($header_path)) {
        include $header_path;
    } else {
        echo '<div class="error">Header file not found. Please ensure header.php exists in your root directory.</div>';
    }
    ?>
    
    <div class="container">
        <h1>Manage Retailers</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error ?? 'Unknown error'); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success ?? 'Success'); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Add New Retailer</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="name">Retailer Name:*</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_person">Contact Person:</label>
                    <input type="text" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($contact_person ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:*</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn">Add Retailer</button>
            </form>
        </div>
        
        <div class="card">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search retailers..." id="searchInput">
            </div>
            
            <h2>Existing Retailers</h2>
            <div class="table-responsive">
                <table id="retailersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($retailers)): ?>
                            <?php foreach ($retailers as $retailer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($retailer['name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($retailer['contact_person'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($retailer['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($retailer['phone'] ?? ''); ?></td>
                                    <td class="action-buttons">
                                        <?php
                                        $edit_path = 'edit_retailer.php';
                                        if (file_exists(ROOT_DIR . '/' . $edit_path)): ?>
                                            <a href="<?php echo htmlspecialchars($edit_path); ?>?id=<?php echo htmlspecialchars($retailer['id'] ?? ''); ?>" class="btn btn-secondary">Edit</a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>Edit (File Missing)</button>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $delete_path = 'delete_retailer.php';
                                        if (file_exists(ROOT_DIR . '/' . $delete_path)): ?>
                                            <a href="<?php echo htmlspecialchars($delete_path); ?>?id=<?php echo htmlspecialchars($retailer['id'] ?? ''); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this retailer?')">Delete</a>
                                        <?php else: ?>
                                            <button class="btn btn-danger" disabled>Delete (File Missing)</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No retailers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo (int)$page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo (int)$page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Simple client-side search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#retailersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>