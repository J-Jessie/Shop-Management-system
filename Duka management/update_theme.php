<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'light-mode';
    $_SESSION['theme'] = $theme;
    
    // Return success response
    echo json_encode(['status' => 'success', 'theme' => $theme]);
    exit();
}

// Return error if not a POST request
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>