<?php
// Include necessary files
require_once 'includes/db_connection.php';

// Get connection
$conn = getConnection();

// Check total products
$total_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
$total_row = mysqli_fetch_assoc($total_result);
echo "Total products: " . $total_row['total'] . "<br>";

// Check non-deleted products
$non_deleted_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE deleted_at IS NULL");
$non_deleted_row = mysqli_fetch_assoc($non_deleted_result);
echo "Non-deleted products: " . $non_deleted_row['total'] . "<br>";

// Check deleted products
$deleted_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE deleted_at IS NOT NULL");
$deleted_row = mysqli_fetch_assoc($deleted_result);
echo "Deleted products: " . $deleted_row['total'] . "<br>";

// Check for products with NULL price, description, etc.
$broken_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE price IS NULL OR price <= 0 OR stock IS NULL OR stock < 0");
$broken_row = mysqli_fetch_assoc($broken_result);
echo "Products with invalid price or stock: " . $broken_row['total'] . "<br>";

// Check for products without categories
$no_category_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE c.category_id IS NULL");
$no_category_row = mysqli_fetch_assoc($no_category_result);
echo "Products without categories: " . $no_category_row['total'] . "<br>";

// Check for products without vendors
$no_vendor_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products p LEFT JOIN vendors v ON p.vendor_id = v.vendor_id WHERE v.vendor_id IS NULL");
$no_vendor_row = mysqli_fetch_assoc($no_vendor_result);
echo "Products without vendors: " . $no_vendor_row['total'] . "<br>";

// List first 5 products for inspection
echo "<h3>First 5 Products:</h3>";
$products_result = mysqli_query($conn, "SELECT p.*, c.name as category_name, v.company_name as vendor_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN vendors v ON p.vendor_id = v.vendor_id LIMIT 5");

if ($products_result && mysqli_num_rows($products_result) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Category</th><th>Vendor</th><th>Deleted</th></tr>";
    while ($product = mysqli_fetch_assoc($products_result)) {
        echo "<tr>";
        echo "<td>" . ($product['product_id'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['price'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['stock'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['category_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['vendor_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($product['deleted_at'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No products found or query error occurred.";
} 