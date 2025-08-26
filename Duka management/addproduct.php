<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// --- Session and Role Verification ---
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'sales_staff') {
    header("Location: unauthorized.php");
    exit();
}

require_once 'db_connection.php';

// --- Variable Initialization ---
$current_user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// --- CSRF token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token. Please try again.";
        $message_type = "danger";
    } else {
        // Sanitize and validate inputs
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

        // Basic validation
        if (empty($product_name) || empty($description) || $price === false || $stock === false) {
            $message = "All fields are required and must be in the correct format.";
            $message_type = "danger";
        } else {
            // Handle image upload
            $target_dir = "uploads/";
            $image_file_name = '';

            // Check if directory exists, if not, create it
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                $file_name = $_FILES['product_image']['name'];
                $file_tmp = $_FILES['product_image']['tmp_name'];
                $file_type = $_FILES['product_image']['type'];
                $file_size = $_FILES['product_image']['size'];

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                    $message = "Invalid file. Please upload a JPEG, PNG, or GIF image less than 5MB.";
                    $message_type = "danger";
                } else {
                    $image_file_name = uniqid('prod_', true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                    $target_file = $target_dir . $image_file_name;

                    if (!move_uploaded_file($file_tmp, $target_file)) {
                        $message = "Failed to upload image.";
                        $message_type = "danger";
                        $image_file_name = ''; // Reset image file name if upload fails
                    }
                }
            }

            // If no errors so far, proceed with database insertion
            if ($message === '') {
                try {
                    $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, image_url, added_by_sales_staff_id) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssdissi", $product_name, $description, $price, $stock, $image_file_name, $current_user_id);

                        if ($stmt->execute()) {
                            $_SESSION['order_message'] = "Product '{$product_name}' added successfully!";
                            $_SESSION['order_message_type'] = "success";
                            header("Location: manage_products.php");
                            exit();
                        } else {
                            $message = "Error adding product: " . $stmt->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    } else {
                        $message = "Failed to prepare statement: " . $conn->error;
                        $message_type = "danger";
                    }
                } catch (Exception $e) {
                    $message = "System error: " . $e->getMessage();
                    $message_type = "danger";
                    error_log("Add Product Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                } finally {
                    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                        $conn->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUKA | Add Product</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --gradient-danger: linear-gradient(135deg, #FF3860 0%, #FF5A80 100%);
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            --transition-speed: 0.3s;
        }

        body 
        .nav-buttons a {
        text-decoration: none;
        color: white;
    }
    .nav-buttons a:hover {
        color: #ddd;
    }
    {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        /* Dark Mode Styles */
        body.dark-mode {
            --light-color: #2e2e3a;
            --dark-color: #f8f9ff;
            --border-color: #444;
            --card-shadow: 0 4px 15px rgba(255, 255, 255, 0.05);
        }

        .card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: background-color var(--transition-speed), box-shadow var(--transition-speed);
        }

        .dark-mode .card {
            background-color: #3e3e4a;
            color: var(--dark-color);
        }

        .dark-mode .form-control, .dark-mode .form-select {
            background-color: #4a4a58;
            border-color: #555;
            color: var(--dark-color);
        }
        
        .dark-mode .form-control::placeholder {
            color: #8c8c9e;
        }

        .dark-mode .btn-primary {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .dark-mode .btn-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .dark-mode .btn-light {
            background-color: #555;
            color: white;
            border-color: #555;
        }

        .dark-mode .btn-light:hover {
            background-color: #666;
            color: white;
        }

        .form-label {
            font-weight: 500;
        }

        .profile-image-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 1rem;
            border: 2px solid var(--border-color);
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 1s ease-out, transform 1s ease-out;
        }

        .fade-in.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .btn-loading .spinner-border {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
        }
    </style>
</head>

<body>
    <!-- Script to apply dark mode from local storage immediately -->
    <script>
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme === 'dark-mode') {
            document.body.classList.add('dark-mode');
        }
    </script>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
              <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                      <i class="fas fa-store me-2"></i>DUKA Sales Portal
                  </a>
                  <div class="d-flex align-items-center">
                      <a href="salesstaff_dashboard.php" class="btn btn-outline-light me-3">
                          <i class="fas fa-tachometer-alt"></i> Dashboard
                      </a>
                      <div class="text-white me-3 d-none d-md-block">
                          <i class="fas fa-user-circle me-1"></i>
                          Sales Staff
                      </div>
                      <a href="logout.php" class="btn btn-outline-light btn-sm">
                          <i class="fas fa-sign-out-alt"></i> Logout
                      </a>
                  </div>
              </div>
          </nav>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-5 fade-in">
                    <h2 class="card-title text-center fw-bold mb-4">Add New Product</h2>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="addproduct.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="mb-3 text-center">
                            <img id="product-image-preview" src="https://placehold.co/150x150/EFEFEF/282828?text=Product+Image" alt="Product Image Preview" class="profile-image-preview">
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_image" class="form-label">Product Image</label>
                            <input class="form-control" type="file" id="product_image" name="product_image" accept="image/png, image/jpeg, image/gif">
                        </div>

                        <div class="mb-3">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">Ksh</span>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stock" class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" id="stock" name="stock" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill btn-lg" id="submit-btn">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                Add Product
                            </button>
                            <a href="manage_products.php" class="btn btn-light rounded-pill btn-lg">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap and custom JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fade-in animation
            const formCard = document.querySelector('.card.fade-in');
            formCard.classList.add('animated');

            // Image preview functionality
            const productImageInput = document.getElementById('product_image');
            const productImagePreview = document.getElementById('product-image-preview');

            if (productImageInput && productImagePreview) {
                productImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            productImagePreview.src = evt.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Show loading spinner on form submission
            const form = document.querySelector('form');
            const submitBtn = document.getElementById('submit-btn');
            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-loading');
                    submitBtn.querySelector('.spinner-border').classList.remove('d-none');
                });
            }
        });
    </script>
</body>
</html>