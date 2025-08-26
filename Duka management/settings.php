<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// THIS IS THE CRITICAL LINE THAT USES YOUR EXISTING DB CONNECTION FILE
require_once 'db_connection.php';

// --- Basic context ---
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'sales_staff';
$message = '';
$message_type = '';

// CRITICAL FIX: Set the timezone to ensure all timestamps are correct
date_default_timezone_set('Africa/Nairobi');

// --- CSRF token (prevents cross-site attacks) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Dashboard link mapping ---
$dashboard_link = 'salesstaff_dashboard.php';
switch ($user_type) {
    case 'sales_staff':
        $dashboard_link = 'salesstaff_dashboard.php';
        break;
    case 'shopkeepers':
        $dashboard_link = 'shopkeeper_dashboard.php';
        break;
    case 'customers':
        $dashboard_link = 'customer_dashboard.php';
        break;
}

// Allowed tables (whitelist)
$valid_tables = ['sales_staff', 'shopkeepers', 'customers'];
if (!in_array($user_type, $valid_tables, true)) {
    error_log('Invalid user_type in session: ' . $user_type);
    $message = 'Invalid account type.';
    $message_type = 'danger';
    // Fallback to a valid type to prevent SQL injection attempts
    $user_type = 'sales_staff';
}

// Helper: log activity
function logActivity($conn, $user_id, $account_type, $action) {
    if (!isset($conn) || !$conn) return;
    
    // CRITICAL FIX: Get current time in UTC using gmdate()
    $utc_now = gmdate('Y-m-d H:i:s');
    
    // Prepare the statement to insert the UTC timestamp
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, account_type, action, created_at) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $user_id, $account_type, $action, $utc_now);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log('Failed to prepare activity_log insert: ' . ($conn->error ?? 'unknown'));
    }
}

// --- Fetch user info (standardize to fullname everywhere) ---
$current_username = 'User';
$user_password_hash = null;
$user_email = '';
$user_phone = '';
$user_profile_picture = '';
$last_login_time = 'Never logged in';
$created_at_date = 'Unknown';

if ($user_id && in_array($user_type, $valid_tables, true) && isset($conn) && $conn) {
    // Attempt a full query that includes all possible columns
    $columns = 'id, fullname, password, email, profile_picture, created_at, last_login';
    if ($user_type !== 'sales_staff') {
        $columns .= ', phone';
    }

    $query = "SELECT {$columns} FROM {$user_type} WHERE id = ? LIMIT 1";

    // Use a try-catch block to handle the case where a column might be missing
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_username = $row['fullname'] ?? 'User';
            $user_password_hash = $row['password'] ?? null;
            $user_email = $row['email'] ?? '';
            $user_phone = $row['phone'] ?? '';
            $user_profile_picture = $row['profile_picture'] ?? '';
            if (isset($row['created_at'])) {
                $created_at_date = date('Y-m-d', strtotime($row['created_at']));
            }
            if (isset($row['last_login']) && !empty($row['last_login'])) {
                $last_login_time = date('Y-m-d H:i:s', strtotime($row['last_login']));
            }
        } else {
            $message = 'User record not found.';
            $message_type = 'danger';
        }
        $stmt->close();
    } catch (Exception $e) {
        // Fallback to a simpler query if a column is missing
        $columns = 'id, fullname, password, email, profile_picture, created_at';
        if ($user_type !== 'sales_staff') {
            $columns .= ', phone';
        }
        $query = "SELECT {$columns} FROM {$user_type} WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $current_username = $row['fullname'] ?? 'User';
                $user_password_hash = $row['password'] ?? null;
                $user_email = $row['email'] ?? '';
                $user_phone = $row['phone'] ?? '';
                $user_profile_picture = $row['profile_picture'] ?? '';
                if (isset($row['created_at'])) {
                    $created_at_date = date('Y-m-d', strtotime($row['created_at']));
                }
            }
            $stmt->close();
        }
        $message = "Note: A database column is missing. Some features may not work as expected.";
        $message_type = "warning";
    }
}

// --- Handle profile update (fullname, email, phone, profile pic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh the page and try again.';
        $message_type = 'danger';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Basic validation
        if ($fullname === '' || $email === '') {
            $message = 'Full name and email are required.';
            $message_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address.';
            $message_type = 'danger';
        } else {
            // Handle optional profile picture upload
            $upload_ok = true;
            $new_profile_picture = $user_profile_picture;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $target_dir = __DIR__ . '/uploads/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                $tmp = $_FILES['profile_picture']['tmp_name'];
                $size = (int)($_FILES['profile_picture']['size'] ?? 0);
                if ($size > 2 * 1024 * 1024) {
                    $upload_ok = false;
                    $message = 'Profile picture too large (max 2MB).';
                    $message_type = 'danger';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmp);
                    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
                    if (!isset($allowed_mimes[$mime])) {
                        $upload_ok = false;
                        $message = 'Only JPG, PNG and GIF are allowed.';
                        $message_type = 'danger';
                    } else {
                        $ext = $allowed_mimes[$mime];
                        $newname = 'profile_' . $user_type . '_' . $user_id . '_' . time() . '.' . $ext;
                        $target_file = $target_dir . $newname;
                        if (!move_uploaded_file($tmp, $target_file)) {
                            $upload_ok = false;
                            $message = 'Failed to move uploaded profile picture.';
                            $message_type = 'danger';
                        } else {
                            $new_profile_picture = 'uploads/' . $newname;
                        }
                    }
                }
            }

            if ($upload_ok) {
                if ($user_type === 'sales_staff') {
                    $query = "UPDATE sales_staff SET fullname = ?, email = ?, profile_picture = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('sssi', $fullname, $email, $new_profile_picture, $user_id);
                } else {
                    $query = "UPDATE {$user_type} SET fullname = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('ssssi', $fullname, $email, $phone, $new_profile_picture, $user_id);
                }

                if ($stmt->execute()) {
                    $message = 'Profile updated successfully.';
                    $message_type = 'success';
                    $current_username = $fullname;
                    $user_profile_picture = $new_profile_picture;
                    logActivity($conn, $user_id, $user_type, 'Profile updated');
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } else {
                    error_log('Profile update failed: ' . $stmt->error);
                    $message = 'Error updating profile.';
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}
// --- Handle password change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh the page and try again.';
        $message_type = 'danger';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($user_password_hash === null) {
            $message = 'Cannot change password: user not loaded.';
            $message_type = 'danger';
        } elseif ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $message = 'All password fields are required.';
            $message_type = 'danger';
        } elseif (!password_verify($current_password, $user_password_hash)) {
            $message = 'Current password is incorrect.';
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match.';
            $message_type = 'danger';
        } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $message = 'New password does not meet the strength requirements.';
            $message_type = 'danger';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE {$user_type} SET password = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_hash, $user_id);
                if ($update_stmt->execute()) {
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                    $user_password_hash = $new_hash;
                    logActivity($conn, $user_id, $user_type, 'Password changed');
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } else {
                    error_log('Password update failed: ' . $update_stmt->error);
                    $message = 'Error updating password.';
                    $message_type = 'danger';
                }
                $update_stmt->close();
            } else {
                error_log('Password update prepare failed: ' . $conn->error);
                $message = 'Failed to prepare update statement.';
                $message_type = 'danger';
            }
        }
    }
}

// --- Handle account deletion (logs then deletes) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token.';
        $message_type = 'danger';
    } else {
        logActivity($conn, $user_id, $user_type, 'Account deleted');
        $del_stmt = $conn->prepare("DELETE FROM {$user_type} WHERE id = ?");
        if ($del_stmt) {
            $del_stmt->bind_param('i', $user_id);
            if ($del_stmt->execute()) {
                session_unset();
                session_destroy();
                header('Location: login.php?account_deleted=1');
                exit();
            } else {
                error_log('Account deletion failed: ' . $del_stmt->error);
                $message = 'Failed to delete account.';
                $message_type = 'danger';
            }
            $del_stmt->close();
        } else {
            error_log('Delete prepare failed: ' . $conn->error);
            $message = 'Failed to prepare account deletion.';
            $message_type = 'danger';
        }
    }
}

// --- Fetch last 5 activity log entries ---
$activity_logs = [];
if ($user_id && in_array($user_type, $valid_tables, true) && isset($conn) && $conn) {
    $log_stmt = $conn->prepare("SELECT action, created_at FROM activity_log WHERE user_id = ? AND account_type = ? ORDER BY created_at DESC LIMIT 5");
    if ($log_stmt) {
        $log_stmt->bind_param('is', $user_id, $user_type);
        $log_stmt->execute();
        $res = $log_stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $activity_logs[] = $r;
            }
        }
        $log_stmt->close();
    } else {
        error_log('Failed to prepare activity log fetch: ' . $conn->error);
    }
}

// Close the database connection at the end of the script
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --body-bg: #f8f9fa;
            --text-color: #212529;
            --card-bg: #ffffff;
            --card-header-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        /* The dark-mode class is now applied to the body tag */
        body.dark-mode {
            --body-bg: #212933;
            --text-color: #f8f9fa;
            --card-bg: #343a40;
            --card-header-bg: #495057;
            --border-color: #6c757d;
        }
        /* All selectors now reference the CSS variables */
        body { font-family: 'Inter', sans-serif; background-color: var(--body-bg); color: var(--text-color); transition: background-color .3s, color .3s; }
        .card { background-color: var(--card-bg); border-color: var(--border-color); }
        .card-header { background-color: var(--card-header-bg); }
        .form-control, .form-control-static { background-color: var(--card-bg); color: var(--text-color); }
        .form-control:focus { background-color: var(--card-bg); color: var(--text-color); }
        .invalid { color: #dc3545; }
        .valid { color: #28a745; }
        .password-strength-bar { height: 10px; transition: width .3s; }
        .form-switch .form-check-input { width: 3.5em; height: 2em; }
        .input-group-text.pointer { cursor: pointer; }
        .profile-pic { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #ddd; }
    </style>
</head>
<body class="<?php echo htmlspecialchars($_SESSION['theme'] ?? ''); ?>">
<script>
    // This script immediately applies the stored theme from localStorage
    const storedTheme = localStorage.getItem('theme');
    if (storedTheme) {
        document.body.classList.add(storedTheme);
        // Also update session to maintain consistency
        document.cookie = "theme=" + storedTheme + "; path=/; max-age=31536000"; // 1 year
    }
</script>
<nav class="navbar navbar-expand-lg <?php echo ($_SESSION['theme'] ?? '') === 'dark-mode' ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary'; ?>">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="fas fa-store me-2"></i>DUKA Sales Portal</a>
        <div class="d-flex align-items-center">
            <div class="text-white me-3">
                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($current_username); ?> (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user_type))); ?>)
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog"></i> Settings</h2>
        <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Custom message box element -->
    <div id="message-box" style="display:none; padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Security</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">Preferences</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">Activity Log</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="profile" role="tabpanel">
            <!-- Profile form content here -->
            <form id="profileForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Update Profile</h5></div>
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center mb-4">
                            <img id="profilePreview" src="<?php echo htmlspecialchars($user_profile_picture ?: 'https://placehold.co/120x120/E8F5E9/000000?text=👤'); ?>" class="profile-pic border border-3 mb-2" alt="Profile Picture">
                            <label class="btn btn-sm btn-outline-primary">
                                Change Picture <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="d-none">
                            </label>
                        </div>
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($current_username); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                        </div>
                        <?php if ($user_type !== 'sales_staff'): ?>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>">
                        </div>
                        <?php endif; ?>
                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" disabled>Save Changes</button>
                        <button type="button" id="discardProfileBtn" class="btn btn-secondary" disabled>Discard</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="tab-pane fade" id="security" role="tabpanel">
            <!-- Security form content here -->
            <form id="passwordForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Change Password</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
                                <span class="input-group-text pointer" id="toggleNewPassword"><i class="fas fa-eye-slash"></i></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                                <span class="input-group-text pointer" id="toggleConfirmPassword"><i class="fas fa-eye-slash"></i></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1">Password requirements:</p>
                            <ul class="list-unstyled">
                                <li><small id="pass-match-feedback" class="invalid"><i class="fas fa-times-circle me-1"></i> Passwords match</small></li>
                                <li><small id="pass-length-feedback" class="invalid"><i class="fas fa-times-circle me-1"></i> At least 8 characters</small></li>
                                <li><small id="pass-upper-feedback" class="invalid"><i class="fas fa-times-circle me-1"></i> An uppercase letter</small></li>
                                <li><small id="pass-number-feedback" class="invalid"><i class="fas fa-times-circle me-1"></i> A number</small></li>
                                <li><small id="pass-symbol-feedback" class="invalid"><i class="fas fa-times-circle me-1"></i> A special character</small></li>
                            </ul>
                        </div>
                        <button type="submit" name="change_password" id="changePasswordBtn" class="btn btn-primary" disabled>Change Password</button>
                        <button type="button" id="generatePasswordBtn" class="btn btn-secondary">Generate Password</button>
                    </div>
                </div>
            </form>
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Delete Account</h5></div>
                <div class="card-body">
                    <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" name="delete_account" class="btn btn-danger">Delete My Account</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="preferences" role="tabpanel">
            <!-- Preferences content here -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">User Preferences</h5></div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                        <label class="form-check-label" for="darkModeSwitch">Enable Dark Mode</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="activity" role="tabpanel">
            <!-- Activity log content here -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Recent Activity</h5></div>
                <div class="card-body">
                    <?php if (empty($activity_logs)): ?>
                        <p class="text-muted">No recent activity found.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($activity_logs as $log): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="text-secondary"><?php echo htmlspecialchars($log['action']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars(date('M j, Y, g:i a', strtotime($log['created_at']))); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Message Box Function
    function showMessage(text, type = 'info') {
        const msgBox = document.getElementById('message-box');
        if (!msgBox) return;

        msgBox.textContent = text;
        msgBox.style.display = 'block';

        // Set colors based on message type
        switch(type) {
            case 'success':
                msgBox.style.backgroundColor = '#d4edda';
                msgBox.style.color = '#155724';
                msgBox.style.border = '1px solid #c3e6cb';
                break;
            case 'error':
                msgBox.style.backgroundColor = '#f8d7da';
                msgBox.style.color = '#721c24';
                msgBox.style.border = '1px solid #f5c6cb';
                break;
            case 'info':
            default:
                msgBox.style.backgroundColor = '#e2e3e5';
                msgBox.style.color = '#383d41';
                msgBox.style.border = '1px solid #d6d8db';
                break;
        }

        // Hide the message after 5 seconds
        setTimeout(() => {
            msgBox.style.display = 'none';
        }, 5000);
    }

    // This is a self-invoking function to keep variables out of the global scope.
    (function() {
        // --- Password generation ---
        const generatePasswordBtn = document.getElementById('generatePasswordBtn');
        if (generatePasswordBtn) {
            generatePasswordBtn.addEventListener('click', () => {
                const length = 12;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_++";
                let password = "";
                for (let i = 0, n = charset.length; i < length; ++i) {
                    password += charset.charAt(Math.floor(Math.random() * n));
                }
                document.getElementById("new_password").value = password;
                document.getElementById("confirm_password").value = password;
                
                // Replaced alert() with a call to the custom showMessage function
                showMessage('A new password has been generated and pre-filled. Please save your changes to apply it.', 'info');

                // Trigger password strength check after generation
                checkPasswordStrength();
            });
        }

        // --- Profile form changes ---
        const profileForm = document.getElementById('profileForm');
        const saveProfileBtn = document.getElementById('saveProfileBtn');
        const discardProfileBtn = document.getElementById('discardProfileBtn');
        const profileInput = document.getElementById('profile_picture');

        // Capture initial state
        const initialFormState = {};
        if (profileForm) {
            profileForm.querySelectorAll('input').forEach(input => {
                initialFormState[input.name] = input.value;
            });
        }
        
        function checkProfileFormChanges() {
            let changed = false;
            profileForm.querySelectorAll('input').forEach(input => {
                if (input.name === 'profile_picture') {
                    if (input.files && input.files.length > 0) {
                        changed = true;
                    }
                } else if (input.value !== initialFormState[input.name]) {
                    changed = true;
                }
            });
            saveProfileBtn.disabled = !changed;
            discardProfileBtn.disabled = !changed;
        }

        if (profileInput) {
            profileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (evt) => {
                    document.getElementById('profilePreview').src = evt.target.result;
                };
                reader.readAsDataURL(file);
                checkProfileFormChanges();
            });
        }

        if (profileForm) {
            profileForm.addEventListener('input', checkProfileFormChanges);
            profileForm.addEventListener('change', checkProfileFormChanges);
        }

        // Discard changes by reloading
        if (discardProfileBtn) {
            discardProfileBtn.addEventListener('click', () => {
                window.location.reload();
            });
        }

        // --- Password strength and matching logic ---
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passMatchFeedback = document.getElementById('pass-match-feedback');
        const passLengthFeedback = document.getElementById('pass-length-feedback');
        const passUpperFeedback = document.getElementById('pass-upper-feedback');
        const passNumberFeedback = document.getElementById('pass-number-feedback');
        const passSymbolFeedback = document.getElementById('pass-symbol-feedback');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const currentPasswordInput = document.getElementById('current_password');

        function updateFeedback(element, isValid) {
            element.classList.toggle('valid', isValid);
            element.classList.toggle('invalid', !isValid);
            const icon = element.querySelector('i');
            icon.classList.toggle('fa-times-circle', !isValid);
            icon.classList.toggle('fa-check-circle', isValid);
        }

        function checkPasswordStrength() {
            const newPass = newPasswordInput.value;
            const confirmPass = confirmPasswordInput.value;
            const isMatch = newPass === confirmPass;
            const hasLength = newPass.length >= 8;
            const hasUpper = /[A-Z]/.test(newPass);
            const hasNumber = /[0-9]/.test(newPass);
            const hasSymbol = /[^A-Za-z0-9]/.test(newPass);
            
            updateFeedback(passMatchFeedback, isMatch);
            updateFeedback(passLengthFeedback, hasLength);
            updateFeedback(passUpperFeedback, hasUpper);
            updateFeedback(passNumberFeedback, hasNumber);
            updateFeedback(passSymbolFeedback, hasSymbol);

            const allValid = isMatch && hasLength && hasUpper && hasNumber && hasSymbol;
            changePasswordBtn.disabled = !allValid || currentPasswordInput.value.length === 0;
        }

        if (newPasswordInput && confirmPasswordInput && currentPasswordInput) {
            newPasswordInput.addEventListener('input', checkPasswordStrength);
            confirmPasswordInput.addEventListener('input', checkPasswordStrength);
            currentPasswordInput.addEventListener('input', checkPasswordStrength);
        }

        // Toggle password visibility
        const toggleNewPassword = document.getElementById('toggleNewPassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

        if (toggleNewPassword) {
            toggleNewPassword.addEventListener('click', () => {
                const type = newPasswordInput.type === 'password' ? 'text' : 'password';
                newPasswordInput.type = type;
                toggleNewPassword.querySelector('i').classList.toggle('fa-eye');
                toggleNewPassword.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        if (toggleConfirmPassword) {
            toggleConfirmPassword.addEventListener('click', () => {
                const type = confirmPasswordInput.type === 'password' ? 'text' : 'password';
                confirmPasswordInput.type = type;
                toggleConfirmPassword.querySelector('i').classList.toggle('fa-eye');
                toggleConfirmPassword.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        // --- Dark Mode switch logic ---
        const darkModeSwitch = document.getElementById('darkModeSwitch');
        if (darkModeSwitch) {
            // Set initial state
            if (localStorage.getItem('theme') === 'dark-mode') {
                darkModeSwitch.checked = true;
            }

            darkModeSwitch.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark-mode');
                    // Set a cookie that will be accessible across all pages
                    document.cookie = "theme=dark-mode; path=/; max-age=31536000"; // 1 year
                    
                    // Send a request to update the session as well
                    fetch('update_theme.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'theme=dark-mode'
                    });
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.removeItem('theme');
                    document.cookie = "theme=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
                    
                    // Send a request to update the session as well
                    fetch('update_theme.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'theme='
                    });
                }
            });
        }
    })();
</script>
</body>
</html>