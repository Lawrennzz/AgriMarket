<?php
include 'config.php';

if ($_SESSION['role'] != 'vendor') {
    header("Location: index.php");
}

$vendor_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT vendor_id FROM vendors WHERE user_id={$_SESSION['user_id']}"))['vendor_id'];
$products = mysqli_query($conn, "SELECT * FROM products WHERE vendor_id=$vendor_id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'index.php'; ?>
    <div class="container">
        <h2>Vendor Dashboard</h2>
        <a href="product_upload.php" class="btn">Add Products</a>
        <h3>Your Products</h3>
        <div class="product-grid">
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <div class="product-card">
                    <h3><?php echo $product['name']; ?></h3>
                    <p>Price: $<?php echo $product['price']; ?></p>
                    <p>Stock: <?php echo $product['stock']; ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>