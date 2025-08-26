<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Your database credentials - FILLED IN WITH YOUR PROVIDED DETAILS
$servername = "localhost";
$username = "if0_38648513";
$password = "M3epPVE5u9ZNtCc";
$dbname = "if0_38648513_duka_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Dummy user ID for this example - replace with a real user session ID in a production app
$userId = "salesstaff_user_123";

$requestMethod = $_SERVER["REQUEST_METHOD"];

switch ($requestMethod) {
    case 'GET':
        // Fetch all products for the user
        $stmt = $conn->prepare("SELECT id, name, pricePerUnit, quantity, totalCost, description FROM products WHERE user_id = ? ORDER BY id DESC");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
        echo json_encode(["success" => true, "products" => $products]);
        break;

    case 'POST':
        // Add a new product or check for duplicates
        $data = json_decode(file_get_contents("php://input"));
        $name = $data->name;
        $pricePerUnit = $data->pricePerUnit;
        $quantity = $data->quantity;
        $totalCost = $data->totalCost;
        $description = isset($data->description) ? $data->description : '';
        $action = isset($data->action) ? $data->action : 'add';
        $productId = isset($data->id) ? $data->id : null;

        if ($action === 'check_duplicate') {
            $stmt = $conn->prepare("SELECT id, quantity, totalCost FROM products WHERE user_id = ? AND name = ?");
            $stmt->bind_param("ss", $userId, $name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $existingProduct = $result->fetch_assoc();
                echo json_encode(["success" => true, "duplicate" => true, "product" => $existingProduct]);
            } else {
                echo json_encode(["success" => true, "duplicate" => false]);
            }
            $stmt->close();
        } elseif ($action === 'combine_update') {
            $stmt = $conn->prepare("UPDATE products SET quantity = ?, totalCost = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iiss", $quantity, $totalCost, $productId, $userId);
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Product combined successfully!"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Error combining product: " . $stmt->error]);
            }
            $stmt->close();
        } elseif ($action === 'update_description') {
            $stmt = $conn->prepare("UPDATE products SET description = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sis", $description, $productId, $userId);
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Description saved!"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Error updating description: " . $stmt->error]);
            }
            $stmt->close();
        } else { // Add new product
            $stmt = $conn->prepare("INSERT INTO products (user_id, name, pricePerUnit, quantity, totalCost, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiids", $userId, $name, $pricePerUnit, $quantity, $totalCost, $description);
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Product added successfully!"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Error adding product: " . $stmt->error]);
            }
            $stmt->close();
        }
        break;

    case 'DELETE':
        // Delete a product
        $productId = $_GET['id'];
        if (empty($productId)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Product ID is required"]);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        $stmt->bind_param("is", $productId, $userId);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Product deleted successfully!"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error deleting product: " . $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}

$conn->close();
?>