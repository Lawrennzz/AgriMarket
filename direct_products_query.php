<?php
// Include necessary files
require_once 'includes/db_connection.php';

// Get connection
$conn = getConnection();

// Direct product query without JOINs
echo "<h2>Direct Products Query</h2>";
$result = mysqli_query($conn, "SELECT * FROM products WHERE deleted_at IS NULL");

if ($result && mysqli_num_rows($result) > 0) {
    echo "Found " . mysqli_num_rows($result) . " products in the direct query.<br><br>";
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Category ID</th><th>Vendor ID</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['price'] . "</td>";
        echo "<td>" . $row['stock'] . "</td>";
        echo "<td>" . $row['category_id'] . "</td>";
        echo "<td>" . $row['vendor_id'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "No products found in the direct query!<br>";
}

// Check database connection and other vital info
echo "<h2>Database Connection Info</h2>";
if (mysqli_ping($conn)) {
    echo "Database connection is active.<br>";
} else {
    echo "Database connection is NOT working!<br>";
}

// Check for SQL errors
if (mysqli_error($conn)) {
    echo "Last SQL Error: " . mysqli_error($conn) . "<br>";
}

// Add test product if none exist
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE deleted_at IS NULL");
$count_row = mysqli_fetch_assoc($count_result);

if ($count_row['total'] == 0) {
    echo "<h2>Adding Test Product</h2>";
    
    // First, check if we have at least one category and vendor
    $category_result = mysqli_query($conn, "SELECT category_id FROM categories LIMIT 1");
    $vendor_result = mysqli_query($conn, "SELECT vendor_id FROM vendors LIMIT 1");
    
    if (mysqli_num_rows($category_result) > 0 && mysqli_num_rows($vendor_result) > 0) {
        $category = mysqli_fetch_assoc($category_result);
        $vendor = mysqli_fetch_assoc($vendor_result);
        
        $insert_sql = "INSERT INTO products (name, description, price, stock, category_id, vendor_id, image_url) 
                      VALUES ('Test Product', 'This is a test product description', 9.99, 100, ?, ?, 'images/default-product.jpg')";
        
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "ii", $category['category_id'], $vendor['vendor_id']);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "Test product added successfully!<br>";
        } else {
            echo "Failed to add test product: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Cannot add test product - need at least one category and one vendor.<br>";
    }
} 