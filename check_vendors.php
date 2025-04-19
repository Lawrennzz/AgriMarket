<?php
// Include necessary files
require_once 'includes/db_connection.php';

// Get connection
$conn = getConnection();

// Check vendors
$vendors_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM vendors");
$vendors_row = mysqli_fetch_assoc($vendors_result);
echo "Total vendors: " . $vendors_row['total'] . "<br>";

// Check categories
$categories_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM categories");
$categories_row = mysqli_fetch_assoc($categories_result);
echo "Total categories: " . $categories_row['total'] . "<br>";

// Check for products with vendor and category
$join_result = mysqli_query($conn, "
    SELECT p.product_id, p.name as product_name, v.company_name as vendor_name, c.name as category_name 
    FROM products p
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NULL
    LIMIT 10
");

if ($join_result && mysqli_num_rows($join_result) > 0) {
    echo "<h3>Products with Vendor and Category:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Product</th><th>Vendor</th><th>Category</th></tr>";
    while ($row = mysqli_fetch_assoc($join_result)) {
        echo "<tr>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['product_name'] . "</td>";
        echo "<td>" . $row['vendor_name'] . "</td>";
        echo "<td>" . $row['category_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<br>No products with valid vendor and category found.";
}

// Debug - check for products where vendor or category is missing
$missing_result = mysqli_query($conn, "
    SELECT p.product_id, p.name, p.vendor_id, p.category_id
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE (v.vendor_id IS NULL OR c.category_id IS NULL) AND p.deleted_at IS NULL
");

if ($missing_result && mysqli_num_rows($missing_result) > 0) {
    echo "<h3>Products with Missing Vendor or Category:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Vendor ID</th><th>Category ID</th></tr>";
    while ($row = mysqli_fetch_assoc($missing_result)) {
        echo "<tr>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . ($row['vendor_id'] ?? 'Missing') . "</td>";
        echo "<td>" . ($row['category_id'] ?? 'Missing') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<br>No products with missing vendor or category found.";
}

// Check database columns
echo "<h3>Product Table Structure:</h3>";
$columns_result = mysqli_query($conn, "SHOW COLUMNS FROM products");
if ($columns_result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($columns_result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} 