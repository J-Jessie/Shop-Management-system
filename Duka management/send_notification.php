<?php
function sendRetailerNotification($retailer_id, $order_details) {
    include 'db_connection.php';
    
    // Get retailer contact info
    $stmt = $conn->prepare("SELECT name, email, phone FROM retailers WHERE id = ?");
    $stmt->bind_param("i", $retailer_id);
    $stmt->execute();
    $retailer = $stmt->get_result()->fetch_assoc();
    
    if ($retailer) {
        // Send email notification
        if (!empty($retailer['email'])) {
            $to = $retailer['email'];
            $subject = "New Order Notification - " . date('Y-m-d');
            $message = "
                <html>
                <head>
                    <title>New Order</title>
                </head>
                <body>
                    <h2>New Order Received</h2>
                    <p>Dear {$retailer['name']},</p>
                    <p>You have received a new order:</p>
                    <ul>
                        <li>Product: {$order_details['product_name']}</li>
                        <li>Quantity: {$order_details['quantity']}</li>
                        <li>Order Date: " . date('Y-m-d H:i') . "</li>
                    </ul>
                    <p>Please process this order at your earliest convenience.</p>
                </body>
                </html>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: orders@yourdukasystem.com\r\n";
            
            mail($to, $subject, $message, $headers);
        }
        
        // Could also add SMS notification here if you have phone numbers
    }
}
?>