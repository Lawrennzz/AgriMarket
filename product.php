<?php
include 'config.php';

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Get product details with vendor information
$sql = "SELECT p.*, u.name as vendor_name, u.email as vendor_email 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.user_id 
        WHERE p.product_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$product = mysqli_fetch_assoc($result)) {
    header("Location: products.php");
    exit();
}

// Get related products
$related_sql = "SELECT * FROM products 
                WHERE category = ? AND product_id != ? 
                LIMIT 4";
$related_stmt = mysqli_prepare($conn, $related_sql);
mysqli_stmt_bind_param($related_stmt, "si", $product['category'], $product_id);
mysqli_stmt_execute($related_stmt);
$related_result = mysqli_stmt_get_result($related_stmt);

// Get product reviews
$reviews_sql = "SELECT r.*, u.name as reviewer_name 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.user_id 
                WHERE r.product_id = ? 
                ORDER BY r.created_at DESC";
$reviews_stmt = mysqli_prepare($conn, $reviews_sql);
mysqli_stmt_bind_param($reviews_stmt, "i", $product_id);
mysqli_stmt_execute($reviews_stmt);
$reviews_result = mysqli_stmt_get_result($reviews_stmt);

// Calculate average rating
$avg_rating = 0;
$total_reviews = mysqli_num_rows($reviews_result);
if ($total_reviews > 0) {
    $ratings_sum = 0;
    while ($review = mysqli_fetch_assoc($reviews_result)) {
        $ratings_sum += $review['rating'];
    }
    $avg_rating = round($ratings_sum / $total_reviews, 1);
    mysqli_data_seek($reviews_result, 0);
}

// Log product view
mysqli_query($conn, "INSERT INTO analytics (type, product_id, count) VALUES ('view', $product_id, 1)");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($product['name']); ?> - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-container {
            padding: 2rem 0;
        }

        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 2rem;
            color: var(--medium-gray);
        }

        .breadcrumb a {
            color: var(--medium-gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--primary-color);
        }

        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .product-gallery {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .product-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .product-category {
            color: var(--primary-color);
            font-size: 0.9rem;
            text-transform: capitalize;
            margin-bottom: 0.5rem;
        }

        .product-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }

        .product-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stars {
            color: #ffc107;
        }

        .product-price {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background: var(--light-gray);
        }

        .quantity {
            font-size: 1.2rem;
            min-width: 40px;
            text-align: center;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .vendor-info {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .vendor-info h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .vendor-meta {
            display: flex;
            gap: 1rem;
            color: var(--medium-gray);
        }

        .product-description {
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }

        .reviews-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 3rem;
        }

        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .review-card {
            border-bottom: 1px solid var(--light-gray);
            padding: 1.5rem 0;
        }

        .review-card:last-child {
            border-bottom: none;
        }

        .reviewer-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .reviewer-name {
            font-weight: 500;
        }

        .review-date {
            color: var(--medium-gray);
        }

        .related-products {
            margin-bottom: 3rem;
        }

        .related-products h2 {
            margin-bottom: 1.5rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .product-details {
                grid-template-columns: 1fr;
            }

            .main-image {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container product-container">
        <div class="breadcrumb">
            <a href="products.php">Products</a>
            <i class="fas fa-chevron-right"></i>
            <a href="products.php?category=<?php echo urlencode($product['category']); ?>"><?php echo ucfirst(htmlspecialchars($product['category'])); ?></a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <div class="product-details">
            <div class="product-gallery">
                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="main-image">
            </div>

            <div class="product-info">
                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <div class="rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $avg_rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                    </div>
                </div>

                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>

                <div class="stock-info">
                    <?php if ($product['stock'] > 0): ?>
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><?php echo $product['stock']; ?> in stock</span>
                    <?php else: ?>
                        <i class="fas fa-times-circle" style="color: var(--danger-color);"></i>
                        <span>Out of stock</span>
                    <?php endif; ?>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="quantity">1</span>
                        <button class="quantity-btn" onclick="updateQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="btn btn-primary add-to-cart-btn">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                <?php else: ?>
                    <button disabled class="btn add-to-cart-btn" style="background: var(--light-gray);">
                        <i class="fas fa-shopping-cart"></i> Out of Stock
                    </button>
                <?php endif; ?>

                <div class="vendor-info">
                    <h3>Seller Information</h3>
                    <div class="vendor-meta">
                        <div>
                            <i class="fas fa-store"></i>
                            <?php echo htmlspecialchars($product['vendor_name']); ?>
                        </div>
                        <div>
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($product['vendor_email']); ?>
                        </div>
                    </div>
                </div>

                <div class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
            </div>
        </div>

        <div class="reviews-section">
            <div class="reviews-header">
                <h2>Customer Reviews</h2>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] !== 'vendor'): ?>
                    <a href="add-review.php?product_id=<?php echo $product_id; ?>" class="btn btn-primary">Write a Review</a>
                <?php endif; ?>
            </div>

            <?php if (mysqli_num_rows($reviews_result) > 0): ?>
                <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                    <div class="review-card">
                        <div class="reviewer-info">
                            <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                            <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#e4e5e9'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </div>

        <?php if (mysqli_num_rows($related_result) > 0): ?>
            <div class="related-products">
                <h2>Related Products</h2>
                <div class="related-grid">
                    <?php while ($related = mysqli_fetch_assoc($related_result)): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($related['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>"
                                 class="product-image">
                            <div class="product-details">
                                <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                                <div class="product-price">$<?php echo number_format($related['price'], 2); ?></div>
                                <a href="product.php?id=<?php echo $related['product_id']; ?>" class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    let quantity = 1;

    function updateQuantity(change) {
        const newQuantity = quantity + change;
        if (newQuantity >= 1 && newQuantity <= <?php echo $product['stock']; ?>) {
            quantity = newQuantity;
            document.querySelector('.quantity').textContent = quantity;
        }
    }

    function addToCart(productId) {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `product_id=${productId}&quantity=${quantity}&action=add`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count in navbar
                const cartCount = document.querySelector('.cart-count');
                if (cartCount) {
                    cartCount.textContent = parseInt(cartCount.textContent || 0) + quantity;
                }
                // Show success message
                alert('Product added to cart!');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html> 