<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/product_view_tracker.php';
require_once 'classes/Database.php';
require_once 'classes/ProductPage.php';

// Get database connection
$conn = getConnection();

// Get product ID from GET parameter
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id > 0) {
    // Get product details
    $query = "SELECT p.*, v.business_name as vendor_name 
              FROM products p 
              JOIN vendors v ON p.vendor_id = v.vendor_id 
              WHERE p.product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if ($product) {
        // Track the product view using the standard tracking function
        $source = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'direct';
        track_product_view($product_id, $source);
    }
}

// Create an instance of the ProductPage class with the product ID
$productPage = new ProductPage($product_id);

// Render the product page
$productPage->render();
?>