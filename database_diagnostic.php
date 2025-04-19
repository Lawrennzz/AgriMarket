<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once 'includes/db_connection.php';

// Get connection
$conn = getConnection();

// Check tables
$tables = ['products', 'categories', 'vendors', 'users'];
$table_counts = [];

echo "<h1>Database Diagnostics</h1>";
echo "<style>
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
</style>";

// Check connection status
echo "<h2>Database Connection</h2>";
if (mysqli_ping($conn)) {
    echo "<p class='success'>Database connection is active ✓</p>";
} else {
    echo "<p class='error'>Database connection is NOT working! ✗</p>";
    echo "<p>Error: " . mysqli_error($conn) . "</p>";
    exit();
}

// Show the database schema
echo "<h2>Table Statistics</h2>";
echo "<table>";
echo "<tr><th>Table</th><th>Total Records</th><th>Active Records</th></tr>";

foreach ($tables as $table) {
    $total_query = "SELECT COUNT(*) as count FROM $table";
    $total_result = mysqli_query($conn, $total_query);
    
    $active_count = 0;
    if ($total_result) {
        $total_row = mysqli_fetch_assoc($total_result);
        $total_count = $total_row['count'];
        $table_counts[$table] = $total_count;
        
        // For tables with deleted_at column, count active records
        $active_query = "SHOW COLUMNS FROM $table LIKE 'deleted_at'";
        $active_result = mysqli_query($conn, $active_query);
        
        if (mysqli_num_rows($active_result) > 0) {
            $active_sql = "SELECT COUNT(*) as count FROM $table WHERE deleted_at IS NULL";
            $active_result = mysqli_query($conn, $active_sql);
            $active_row = mysqli_fetch_assoc($active_result);
            $active_count = $active_row['count'];
        } else {
            $active_count = $total_count;
        }
    } else {
        $total_count = "Error: " . mysqli_error($conn);
        $active_count = "N/A";
    }
    
    echo "<tr>";
    echo "<td>$table</td>";
    echo "<td>$total_count</td>";
    echo "<td>$active_count</td>";
    echo "</tr>";
}
echo "</table>";

// Check for orphaned records
echo "<h2>Relationship Diagnostics</h2>";

// Products without categories
$orphan_query = "SELECT COUNT(*) as count FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE c.category_id IS NULL AND p.deleted_at IS NULL";
$orphan_result = mysqli_query($conn, $orphan_query);
$orphan_row = mysqli_fetch_assoc($orphan_result);
$orphan_count = $orphan_row['count'];

echo "<p>Products without valid categories: ";
if ($orphan_count > 0) {
    echo "<span class='error'>$orphan_count ✗</span>";
} else {
    echo "<span class='success'>$orphan_count ✓</span>";
}
echo "</p>";

// Products without vendors
$orphan_query = "SELECT COUNT(*) as count FROM products p LEFT JOIN vendors v ON p.vendor_id = v.vendor_id WHERE v.vendor_id IS NULL AND p.deleted_at IS NULL";
$orphan_result = mysqli_query($conn, $orphan_query);
$orphan_row = mysqli_fetch_assoc($orphan_result);
$orphan_count = $orphan_row['count'];

echo "<p>Products without valid vendors: ";
if ($orphan_count > 0) {
    echo "<span class='error'>$orphan_count ✗</span>";
} else {
    echo "<span class='success'>$orphan_count ✓</span>";
}
echo "</p>";

// Execute the actual SQL query from ProductsPage
echo "<h2>ProductsPage Query Test</h2>";

$sql = "SELECT p.*, c.name as category_name, v.company_name as vendor_name, v.user_id as vendor_user_id, 
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE p.deleted_at IS NULL
        ORDER BY p.name ASC";

$result = mysqli_query($conn, $sql);

if ($result) {
    $product_count = mysqli_num_rows($result);
    echo "<p>Query found $product_count products.</p>";
    
    if ($product_count > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Vendor</th><th>Price</th><th>Stock</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['product_id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . ($row['category_name'] ?? 'NULL') . "</td>"; 
            echo "<td>" . ($row['vendor_name'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['price'] . "</td>";
            echo "<td>" . $row['stock'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='error'>No products found in query!</p>";
    }
} else {
    echo "<p class='error'>Query failed: " . mysqli_error($conn) . "</p>";
}

// If needed, add a sample product for testing
if (isset($_GET['add_sample']) && $_GET['add_sample'] == 1) {
    echo "<h2>Adding Sample Product</h2>";
    
    // Check for categories and vendors first
    if ($table_counts['categories'] > 0 && $table_counts['vendors'] > 0) {
        // Get first category and vendor
        $cat_result = mysqli_query($conn, "SELECT category_id FROM categories LIMIT 1");
        $vendor_result = mysqli_query($conn, "SELECT vendor_id FROM vendors LIMIT 1");
        
        if ($cat_row = mysqli_fetch_assoc($cat_result) && $vendor_row = mysqli_fetch_assoc($vendor_result)) {
            $cat_id = $cat_row['category_id'];
            $vendor_id = $vendor_row['vendor_id'];
            
            $insert_sql = "INSERT INTO products (name, description, price, stock, category_id, vendor_id, image_url) 
                           VALUES ('Sample Product', 'This is a sample product for testing', 19.99, 50, $cat_id, $vendor_id, 'images/default-product.jpg')";
            
            if (mysqli_query($conn, $insert_sql)) {
                $new_id = mysqli_insert_id($conn);
                echo "<p class='success'>Sample product added with ID: $new_id ✓</p>";
                echo "<p>Refresh the page to see updated counts.</p>";
            } else {
                echo "<p class='error'>Failed to add sample product: " . mysqli_error($conn) . " ✗</p>";
            }
        } else {
            echo "<p class='error'>Couldn't retrieve category or vendor IDs ✗</p>";
        }
    } else {
        echo "<p class='error'>Need at least one category and one vendor before adding a sample product ✗</p>";
    }
}

echo "<p><a href='?add_sample=1'>Add Sample Product</a> | <a href='products.php'>Go to Products Page</a></p>";
?> 