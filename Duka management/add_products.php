<?php
session_start(); 
require_once 'db_connection.php';

// Check if user is logged in and is a shopkeeper
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'shopkeeper''sales staff') {
    header("Location: login.php");
    exit();
}

$current_username = $_SESSION['username'] ?? 'Shopkeeper';
$message = '';
$message_type = '';
$shopkeeper_id = $_SESSION['user_id'];

try {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if required fields exist before accessing them
        $required_fields = ['product_name', 'product_type', 'buying_cost', 'quantity'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $product_name = trim($_POST['product_name'] ?? '');
        $product_type = trim($_POST['product_type'] ?? '');
        $buying_cost = floatval($_POST['buying_cost'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $total_price = $buying_cost * $quantity;

        // Validate inputs
        if (empty($product_name) || empty($product_type) || $buying_cost <= 0 || $quantity <= 0) {
            throw new Exception("Please fill all required fields with valid values");
        }

        // Prepare SQL without image_url column
        $stmt = $conn->prepare("INSERT INTO inventory 
                               (shopkeeper_id, product_name, product_type, buying_cost, quantity, total_price, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issdii", $shopkeeper_id, $product_name, $product_type, $buying_cost, $quantity, $total_price);
        
        if ($stmt->execute()) {
            $message = "Product added successfully to your inventory!";
            $message_type = "success";
            $_POST = [];
            
            $_SESSION['product_message'] = $message;
            $_SESSION['product_message_type'] = $message_type;
            header("Location: shopkeeper_dashboard.php");
            exit();
        } else {
            throw new Exception("Error adding product: " . $conn->error);
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-plus"></i> Add New Product to Your Inventory</h2>
            <a href="shopkeeper_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_name" class="form-label">Product Name*</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" 
                                   value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="product_type" class="form-label">Product Type*</label>
                            <select class="form-select" id="product_type" name="product_type" required>
                                <option value="">Select Type</option>
                                <option value="Electronics" <?= ($_POST['product_type'] ?? '') === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                <option value="Clothing" <?= ($_POST['product_type'] ?? '') === 'Clothing' ? 'selected' : '' ?>>Clothing</option>
                                <option value="Groceries" <?= ($_POST['product_type'] ?? '') === 'Groceries' ? 'selected' : '' ?>>Groceries</option>
                                <option value="Furniture" <?= ($_POST['product_type'] ?? '') === 'Furniture' ? 'selected' : '' ?>>Furniture</option>
                                <option value="Other" <?= ($_POST['product_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="buying_cost" class="form-label">Buying Cost (KSH)*</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="buying_cost" name="buying_cost" 
                                   value="<?php echo htmlspecialchars($_POST['buying_cost'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity*</label>
                            <input type="number" min="1" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="total_price" class="form-label">Total Value (KSH)</label>
                            <input type="text" class="form-control" id="total_price" name="total_price" readonly>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Add to Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate total when price or quantity changes
        function calculateTotal() {
            const buying_cost = parseFloat(document.getElementById('buying_cost').value) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const total_price = buying_cost * quantity;
            document.getElementById('total_price').value = total_price.toFixed(2);
        }
        
        document.getElementById('buying_cost').addEventListener('input', calculateTotal);
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        
        // Initial calculation
        calculateTotal();
    </script>
</body>
</html>
<?php $conn->close(); ?>
