<?php
/**
 * Product Stats Update Script
 * 
 * This script ensures product_stats table has accurate data
 * based on the product_views table.
 */

// Include database connection
require_once 'db_connection.php';
$conn = getConnection();

// Calculate date ranges for different time periods
$current_date = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('last sunday'));
$month_start = date('Y-m-01');

// First, clear the existing product_stats table
$conn->query("TRUNCATE TABLE product_stats");

// Get all products that have views
$query = "
    SELECT 
        product_id, 
        COUNT(*) as total_views,
        SUM(DATE(view_date) = '$current_date') as daily_views,
        SUM(DATE(view_date) >= '$week_start') as weekly_views,
        SUM(DATE(view_date) >= '$month_start') as monthly_views,
        MAX(view_date) as last_view_date
    FROM 
        product_views
    GROUP BY 
        product_id
";

$result = $conn->query($query);

if ($result) {
    // Insert updated stats into product_stats table
    while ($row = $result->fetch_assoc()) {
        $insert_query = "
            INSERT INTO product_stats 
                (product_id, total_views, daily_views, weekly_views, monthly_views, last_view_date)
            VALUES 
                (?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('iiiiss', 
            $row['product_id'], 
            $row['total_views'], 
            $row['daily_views'], 
            $row['weekly_views'], 
            $row['monthly_views'], 
            $row['last_view_date']
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    echo "Product stats updated successfully!\n";
} else {
    echo "Error updating product stats: " . $conn->error . "\n";
}

// Also add entries for products with no views
$products_query = "
    SELECT 
        p.product_id 
    FROM 
        products p
    LEFT JOIN 
        product_stats ps ON p.product_id = ps.product_id
    WHERE 
        ps.product_id IS NULL
";

$products_result = $conn->query($products_query);

if ($products_result) {
    while ($product = $products_result->fetch_assoc()) {
        $insert_query = "
            INSERT INTO product_stats 
                (product_id, total_views, daily_views, weekly_views, monthly_views)
            VALUES 
                (?, 0, 0, 0, 0)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('i', $product['product_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    echo "Added stats for products with no views.\n";
} else {
    echo "Error adding stats for products with no views: " . $conn->error . "\n";
}

$conn->close();
?> 