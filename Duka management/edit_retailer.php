<?php
session_start();
require 'db_connection.php';

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "shopkeeper") {
    header("Location: login.php");
    exit();
}

// Get retailer ID
$retailer_id = $_GET['id'] ?? 0;

// Fetch retailer data
$stmt = $conn->prepare("SELECT * FROM retailers WHERE id = ?");
$stmt->bind_param("i", $retailer_id);
$stmt->execute();
$retailer = $stmt->get_result()->fetch_assoc();

if (!$retailer) {
    header("Location: shopkeeper_dashboard.php?error=Retailer+not+found");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: edit_retailer.php?id=$retailer_id&error=Invalid+email");
        exit();
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE retailers SET name=?, email=?, phone=?, contact_person=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $contact_person, $retailer_id);
    
    if ($stmt->execute()) {
        header("Location: shopkeeper_dashboard.php?success=Retailer+updated");
    } else {
        header("Location: edit_retailer.php?id=$retailer_id&error=Error+updating");
    }
    exit();
}

// Display edit form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Retailer</title>
    <!-- Include your existing styles -->
</head>
<body>
    <div class="dashboard-container">
        <h1>Edit Retailer</h1>
        
        <form method="post">
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($retailer['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($retailer['email']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone:</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($retailer['phone']) ?>">
            </div>
            
            <div class="form-group">
                <label>Contact Person:</label>
                <input type="text" name="contact_person" value="<?= htmlspecialchars($retailer['contact_person']) ?>">
            </div>
            
            <button type="submit" class="btn">Update Retailer</button>
            <a href="shopkeeper_dashboard.php" class="btn btn-danger">Cancel</a>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>