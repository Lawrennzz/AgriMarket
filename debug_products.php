<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/ProductsPage.php';

// Get database connection
$conn = getConnection();

// Create an instance of the ProductsPage class
$productsPage = new ProductsPage();

// Retrieve products using the getProducts method
$products = $productsPage->getProducts();

// Output debugging information
echo "<h1>ProductsPage Debug Info</h1>";
echo "<pre>";

echo "Total products found: " . count($products) . "\n\n";

echo "SQL conditions:\n";
echo "Category ID: " . ($productsPage->getCategoryId() ? $productsPage->getCategoryId() : "None") . "\n";
echo "Search term: " . ($productsPage->getSearch() ? $productsPage->getSearch() : "None") . "\n";
echo "Sort order: " . $productsPage->getSort() . "\n";
echo "View type: " . $productsPage->getView() . "\n\n";

// If there are products, show first 3 for debugging
if (!empty($products)) {
    echo "First 3 products (sample data):\n";
    for ($i = 0; $i < min(3, count($products)); $i++) {
        print_r($products[$i]);
        echo "\n";
    }
} else {
    echo "No products found! Database query returned zero results.\n\n";
    
    // Check if there are any products in the database at all
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE deleted_at IS NULL");
    $row = mysqli_fetch_assoc($result);
    echo "Total non-deleted products in database: " . $row['total'] . "\n";
    
    // Check if JOIN conditions might be causing issues
    $result = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE p.deleted_at IS NULL
        AND c.category_id IS NOT NULL
        AND v.vendor_id IS NOT NULL
    ");
    $row = mysqli_fetch_assoc($result);
    echo "Products with valid category and vendor: " . $row['total'] . "\n";
    
    // Check connection to database
    if (mysqli_ping($conn)) {
        echo "Database connection is working.\n";
    } else {
        echo "Database connection issue: " . mysqli_error($conn) . "\n";
    }
}

echo "</pre>";
?> 