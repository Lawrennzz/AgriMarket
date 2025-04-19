<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/ProductPage.php';

// Get database connection
$conn = getConnection();

// Get product ID from GET parameter
if (!isset($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Create an instance of the ProductPage class with the product ID
$productPage = new ProductPage($product_id);

// Render the product page
$productPage->render();
?>