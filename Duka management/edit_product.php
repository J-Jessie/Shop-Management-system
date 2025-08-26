<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'sales_staff') {
    header("Location: Login.php");
    exit();
}

require_once 'db_connection.php';

$current_username = $_SESSION['username'] ?? 'Sales Staff';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;
$shopkeepers = [];
$message = '';
$message_type = '';

try {
    // Fetch shopkeepers for dropdown
    $shopkeeper_result = $conn->query("SELECT id, name FROM shopkeepers ORDER BY name");
    if ($shopkeeper_result) {
        $shopkeepers = $shopkeeper_result->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch product details
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if (!$product) {
        $message = "Product not found";
        $message_type = "danger";
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
        $name = trim($_POST['name']);
        $product_type = trim($_POST['product_type']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $shopkeeper_id = intval($_POST['shopkeeper_id']);

        $stmt = $conn->prepare("UPDATE products SET 
                              shopkeeper_id = ?, 
                              name = ?, 
                              product_type = ?, 
                              description = ?, 
                              price = ?, 
                              quantity = ? 
                              WHERE id = ?");
        $stmt->bind_param("isssdii", $shopkeeper_id, $name, $product_type, $description, $price, $quantity, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product updated successfully!";
            $message_type = "success";
            // Refresh product data
            $product = array_merge($product, [
                'name' => $name,
                'product_type' => $product_type,
                'description' => $description,
                'price' => $price,
                'quantity' => $quantity,
                'shopkeeper_id' => $shopkeeper_id
            ]);
            
            // Set success message for redirect
            $_SESSION['product_message'] = $message;
            $_SESSION['product_message_type'] = $message_type;
            header("Location: manage_products.php");
            exit();
        } else {
            $message = "Error updating product: " . $conn->error;
            $message_type = "danger";
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
    <title>Edit Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>DUKA Sales Portal
            </a>
            <div class="d-flex align-items-center">
                <div class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($current_username); ?>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> Edit Product</h2>
            <a href="manage_products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="product_type" class="form-label">Product Type</label>
                            <input type="text" class="form-control" id="product_type" name="product_type" 
                                   value="<?php echo htmlspecialchars($product['product_type']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php 
                                echo htmlspecialchars($product['description']); 
                            ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price (KSH)</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                       value="<?php echo htmlspecialchars($product['price']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                       value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="shopkeeper_id" class="form-label">Shopkeeper</label>
                            <select class="form-select" id="shopkeeper_id" name="shopkeeper_id" required>
                                <option value="">Select Shopkeeper</option>
                                <?php foreach ($shopkeepers as $shopkeeper): ?>
                                    <option value="<?php echo $shopkeeper['id']; ?>" 
                                        <?php echo $product['shopkeeper_id'] == $shopkeeper['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($shopkeeper['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">Product not found</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>