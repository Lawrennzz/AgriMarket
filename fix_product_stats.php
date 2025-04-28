<?php
require_once 'includes/db_connection.php';

// First, check if any products are missing from product_stats
$check_query = "SELECT p.product_id 
                FROM products p 
                LEFT JOIN product_stats ps ON p.product_id = ps.product_id 
                WHERE ps.stat_id IS NULL";

$missing_products = mysqli_query($conn, $check_query);
$count_missing = mysqli_num_rows($missing_products);
echo "Found {$count_missing} products missing from product_stats<br>";

if ($count_missing > 0) {
    // Initialize stats for missing products
    $init_query = "INSERT INTO product_stats (product_id, total_sales, total_views, total_revenue, last_view_date)
                   SELECT 
                       p.product_id,
                       COALESCE((
                           SELECT SUM(oi.quantity)
                           FROM order_items oi
                           WHERE oi.product_id = p.product_id
                       ), 0) as total_sales,
                       COALESCE((
                           SELECT COUNT(*)
                           FROM product_visits pv
                           WHERE pv.product_id = p.product_id
                       ), 0) as total_views,
                       COALESCE((
                           SELECT SUM(oi.quantity * oi.price)
                           FROM order_items oi
                           WHERE oi.product_id = p.product_id
                       ), 0) as total_revenue,
                       (
                           SELECT MAX(visit_date)
                           FROM product_visits pv
                           WHERE pv.product_id = p.product_id
                       ) as last_view_date
                   FROM products p
                   WHERE NOT EXISTS (
                       SELECT 1 
                       FROM product_stats ps 
                       WHERE ps.product_id = p.product_id
                   )";
    
    if (mysqli_query($conn, $init_query)) {
        echo "Successfully initialized stats for missing products<br>";
    } else {
        echo "Error initializing stats: " . mysqli_error($conn) . "<br>";
    }
}

// Update existing stats with current view counts
$update_views = "UPDATE product_stats ps
                 SET total_views = (
                     SELECT COUNT(*)
                     FROM product_visits pv
                     WHERE pv.product_id = ps.product_id
                 ),
                 last_view_date = (
                     SELECT MAX(visit_date)
                     FROM product_visits pv
                     WHERE pv.product_id = ps.product_id
                 )";

if (mysqli_query($conn, $update_views)) {
    echo "Successfully updated view counts for all products<br>";
} else {
    echo "Error updating view counts: " . mysqli_error($conn) . "<br>";
}

// Display current stats
$stats_query = "SELECT 
                    p.name,
                    p.product_id,
                    COUNT(pv.id) as view_count,
                    MAX(pv.visit_date) as last_viewed
                FROM products p
                LEFT JOIN product_visits pv ON p.product_id = pv.product_id
                GROUP BY p.product_id, p.name
                ORDER BY view_count DESC
                LIMIT 10";

$result = mysqli_query($conn, $stats_query);

echo "<h2>Current Product Stats (from product_visits)</h2>";
echo "<table border='1'>";
echo "<tr><th>Product</th><th>Views</th><th>Last Viewed</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . $row['view_count'] . "</td>";
    echo "<td>" . ($row['last_viewed'] ? date('Y-m-d H:i', strtotime($row['last_viewed'])) : 'Never') . "</td>";
    echo "</tr>";
}

echo "</table>";
?> 