<?php
include 'config.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
}

$searches = mysqli_query($conn, "SELECT COUNT(*) as count FROM analytics WHERE type='search'");
$popular_products = mysqli_query($conn, "SELECT p.name, COUNT(*) as count FROM analytics a JOIN products p ON a.product_id=p.product_id WHERE a.type='order' GROUP BY p.product_id ORDER BY count DESC LIMIT 5");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'index.php'; ?>
    <div class="container">
        <h2>Analytics & Reports</h2>
        <h3>Search Count</h3>
        <p><?php echo mysqli_fetch_assoc($searches)['count']; ?> searches</p>
        <h3>Top Products</h3>
        <?php while ($product = mysqli_fetch_assoc($popular_products)): ?>
            <p><?php echo $product['name']; ?> - <?php echo $product['count']; ?> orders</p>
        <?php endwhile; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>