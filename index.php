<?php
// Include database connection
include 'config.php';

// Fetch featured products from the database
$query = "SELECT * FROM products WHERE featured = 1"; // Adjust the query as needed
$result = mysqli_query($conn, $query);

// Initialize the featured products array
$featuredProducts = [];

// Check if the query was successful and fetch results
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $featuredProducts[] = $row; // Populate the array with product data
    }
} else {
    // Handle query error if needed
    // echo "Error fetching products: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>AgriMarket</title>
</head>
<body>
    <?php include 'navbar.php'; ?> <!-- Include the navbar -->

    <header class="hero">
        <div>
            <h1>Welcome to AgriMarket</h1>
            <p>Your trusted marketplace for agricultural products</p>
            <a href="products.php" class="btn">Shop Now</a>
        </div>
    </header>

    <main class="container">
        <h2 class="section-title">Featured Products</h2>
        <div class="grid grid-3">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                        <div class="product-details">
                            <h3 class="product-title"><?php echo $product['name']; ?></h3>
                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No featured products available at this time.</p> <!-- Fallback message -->
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?> <!-- Include the footer -->
</body>
</html>