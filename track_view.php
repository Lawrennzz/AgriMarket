<?php
/**
 * Endpoint for tracking product views via AJAX
 */

// Include database connection
require_once 'includes/config.php';
require_once 'includes/product_view_tracker.php';

// Set response header to JSON
header('Content-Type: application/json');

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Validate and sanitize product ID
if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$source = isset($_POST['source']) ? filter_var($_POST['source'], FILTER_SANITIZE_STRING) : '';

// Track the product view
try {
    $result = track_product_view($product_id, $source);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'View tracked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to track view']);
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Error tracking view: ' . $e->getMessage()]);
}
?> 