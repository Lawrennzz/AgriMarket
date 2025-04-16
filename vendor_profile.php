<?php
session_start();
include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$vendor_id = (int)$_GET['id'];

// Get vendor information
$vendor_query = "SELECT v.*, u.name, u.email, u.created_at as joined_date 
                FROM vendors v 
                JOIN users u ON v.user_id = u.user_id 
                WHERE v.vendor_id = ? AND v.deleted_at IS NULL";
$vendor_stmt = mysqli_prepare($conn, $vendor_query);
mysqli_stmt_bind_param($vendor_stmt, "i", $vendor_id);
mysqli_stmt_execute($vendor_stmt);
$vendor_result = mysqli_stmt_get_result($vendor_stmt);

if (mysqli_num_rows($vendor_result) === 0) {
    // Vendor not found or deleted
    header("Location: products.php");
    exit();
}

$vendor = mysqli_fetch_assoc($vendor_result);

// Get vendor products
$products_query = "SELECT p.*, c.name as category_name,
                  (SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id 
                   WHERE oi.product_id = p.product_id AND o.status != 'cancelled') as orders_count,
                  (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  WHERE p.vendor_id = ? AND p.deleted_at IS NULL
                  ORDER BY p.created_at DESC";
$products_stmt = mysqli_prepare($conn, $products_query);
mysqli_stmt_bind_param($products_stmt, "i", $vendor_id);
mysqli_stmt_execute($products_stmt);
$products_result = mysqli_stmt_get_result($products_stmt);

// Get vendor statistics
$stats_query = "SELECT 
               COUNT(DISTINCT p.product_id) as total_products,
               COUNT(DISTINCT r.review_id) as total_reviews,
               AVG(r.rating) as avg_rating,
               (SELECT COUNT(DISTINCT o.order_id) 
                FROM orders o 
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE p.vendor_id = ? AND o.status != 'cancelled') as total_orders
               FROM products p
               LEFT JOIN reviews r ON p.product_id = r.product_id
               WHERE p.vendor_id = ? AND p.deleted_at IS NULL";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ii", $vendor_id, $vendor_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Get top rated products
$top_rated_query = "SELECT p.*, 
                   (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating
                   FROM products p
                   WHERE p.vendor_id = ? AND p.deleted_at IS NULL
                   AND EXISTS (SELECT 1 FROM reviews r WHERE r.product_id = p.product_id)
                   ORDER BY avg_rating DESC, p.created_at DESC
                   LIMIT 3";
$top_rated_stmt = mysqli_prepare($conn, $top_rated_query);
mysqli_stmt_bind_param($top_rated_stmt, "i", $vendor_id);
mysqli_stmt_execute($top_rated_stmt);
$top_rated_result = mysqli_stmt_get_result($top_rated_stmt);

// Get recent reviews for vendor's products
$reviews_query = "SELECT r.*, p.name as product_name, u.name as reviewer_name
                 FROM reviews r
                 JOIN products p ON r.product_id = p.product_id
                 JOIN users u ON r.user_id = u.user_id
                 WHERE p.vendor_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT 5";
$reviews_stmt = mysqli_prepare($conn, $reviews_query);
mysqli_stmt_bind_param($reviews_stmt, "i", $vendor_id);
mysqli_stmt_execute($reviews_stmt);
$reviews_result = mysqli_stmt_get_result($reviews_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vendor['business_name'] ?? $vendor['name']); ?> - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .vendor-header {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            padding: 3rem 0;
            color: white;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .vendor-avatar {
            width: 100px;
            height: 100px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 3px solid white;
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        
        .vendor-name {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .vendor-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1.5rem 0;
        }
        
        .vendor-meta-item {
            text-align: center;
        }
        
        .meta-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .meta-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .vendor-rating {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            color: #FFD700;
        }
        
        .vendor-details {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            font-size: 0.9rem;
        }
        
        .vendor-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            color: var(--primary-color);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .product-price {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }
        
        .product-meta {
            display: flex;
            gap: 1rem;
            margin-top: auto;
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .product-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .product-rating {
            color: #FFD700;
        }
        
        .reviews-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 3rem;
        }
        
        .review-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .reviewer-name {
            font-weight: 600;
        }
        
        .review-date {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .review-product {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-style: italic;
        }
        
        .review-content {
            line-height: 1.6;
        }
        
        .top-products-section {
            margin-bottom: 3rem;
        }
        
        @media (max-width: 768px) {
            .vendor-meta {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .vendor-meta-item {
                flex-basis: calc(50% - 1rem);
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <header class="vendor-header">
        <div class="container">
            <div class="vendor-avatar">
                <i class="fas fa-store"></i>
            </div>
            <h1 class="vendor-name"><?php echo htmlspecialchars($vendor['business_name'] ?? $vendor['name']); ?></h1>
            
            <div class="vendor-rating">
                <div class="rating-stars">
                    <?php
                    $rating = round($stats['avg_rating'] ?? 0);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $rating) {
                            echo '<i class="fas fa-star"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                </div>
                <span><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?> / 5</span>
            </div>
            
            <div class="vendor-meta">
                <div class="vendor-meta-item">
                    <div class="meta-value"><?php echo $stats['total_products'] ?? 0; ?></div>
                    <div class="meta-label">Products</div>
                </div>
                <div class="vendor-meta-item">
                    <div class="meta-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    <div class="meta-label">Orders</div>
                </div>
                <div class="vendor-meta-item">
                    <div class="meta-value"><?php echo $stats['total_reviews'] ?? 0; ?></div>
                    <div class="meta-label">Reviews</div>
                </div>
                <div class="vendor-meta-item">
                    <div class="meta-value"><?php echo date('Y', strtotime($vendor['joined_date'])); ?></div>
                    <div class="meta-label">Joined</div>
                </div>
            </div>
            
            <div class="vendor-details">
                <div class="vendor-detail-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($vendor['email']); ?></span>
                </div>
                <div class="vendor-detail-item">
                    <i class="fas fa-tag"></i>
                    <span><?php echo ucfirst(htmlspecialchars($vendor['subscription_tier'])); ?> Seller</span>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container">
        <?php if (mysqli_num_rows($top_rated_result) > 0): ?>
            <section class="top-products-section">
                <div class="section-header">
                    <h2 class="section-title">Top Rated Products</h2>
                </div>
                
                <div class="product-grid">
                    <?php while ($product = mysqli_fetch_assoc($top_rated_result)): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="product-image">
                            <div class="product-content">
                                <div class="product-rating">
                                    <?php
                                    $product_rating = round($product['avg_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $product_rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                    <span><?php echo number_format($product['avg_rating'] ?? 0, 1); ?></span>
                                </div>
                                <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>
        
        <section class="all-products-section">
            <div class="section-header">
                <h2 class="section-title">All Products</h2>
                <div class="section-actions">
                    <span><?php echo mysqli_num_rows($products_result); ?> products</span>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($products_result) > 0): ?>
                <div class="product-grid">
                    <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="product-image">
                            <div class="product-content">
                                <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                <div class="product-meta">
                                    <div class="product-meta-item">
                                        <i class="fas fa-box"></i>
                                        <span><?php echo $product['stock']; ?> in stock</span>
                                    </div>
                                    <div class="product-meta-item">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span><?php echo $product['orders_count'] ?? 0; ?> sold</span>
                                    </div>
                                </div>
                                <div class="product-actions" style="margin-top: 1rem;">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-block">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This vendor has no products available at the moment.
                </div>
            <?php endif; ?>
        </section>
        
        <section class="reviews-section">
            <div class="section-header">
                <h2 class="section-title">Recent Reviews</h2>
            </div>
            
            <?php if (mysqli_num_rows($reviews_result) > 0): ?>
                <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                            <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                        </div>
                        <div class="review-product">
                            <a href="product.php?id=<?php echo $review['product_id']; ?>">
                                <?php echo htmlspecialchars($review['product_name']); ?>
                            </a>
                        </div>
                        <div class="product-rating" style="margin-bottom: 0.5rem;">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No reviews available for this vendor yet.
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html> 