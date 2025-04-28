<?php
session_start();
require_once '../includes/db_connection.php';

// Check if vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    header('Location: login.php');
    exit();
}

$vendor_id = $_SESSION['vendor_id'];

// Get vendor's products performance
$products_query = "SELECT 
    p.name,
    p.product_id,
    ps.total_views,
    ps.total_sales,
    ps.total_revenue
FROM products p
JOIN product_stats ps ON p.product_id = ps.product_id
WHERE p.vendor_id = ?
ORDER BY ps.total_views DESC";

$stmt = mysqli_prepare($conn, $products_query);
mysqli_stmt_bind_param($stmt, "i", $vendor_id);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);

// Get total stats
$total_stats_query = "SELECT 
    SUM(ps.total_views) as total_views,
    SUM(ps.total_sales) as total_sales,
    SUM(ps.total_revenue) as total_revenue
FROM products p
JOIN product_stats ps ON p.product_id = ps.product_id
WHERE p.vendor_id = ?";

$stmt = mysqli_prepare($conn, $total_stats_query);
mysqli_stmt_bind_param($stmt, "i", $vendor_id);
mysqli_stmt_execute($stmt);
$total_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vendor Reports - AgriMarket</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h2>Performance Reports</h2>
        
        <div class="stats-summary">
            <div class="stat-box">
                <h3>Total Views</h3>
                <p><?php echo number_format($total_stats['total_views']); ?></p>
            </div>
            <div class="stat-box">
                <h3>Total Sales</h3>
                <p><?php echo number_format($total_stats['total_sales']); ?></p>
            </div>
            <div class="stat-box">
                <h3>Total Revenue</h3>
                <p>₱<?php echo number_format($total_stats['total_revenue'], 2); ?></p>
            </div>
        </div>

        <h3>Product Performance</h3>
        <table class="reports-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Views</th>
                    <th>Sales</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo number_format($product['total_views']); ?></td>
                    <td><?php echo number_format($product['total_sales']); ?></td>
                    <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 