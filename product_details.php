<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/ratings.php';

// Initialize variables
$product = null;
$error_message = '';

// Initialize RatingSystem
$ratingSystem = new RatingSystem($conn);

// Check if product ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Fetch product details
    $query = "
        SELECT p.*, c.name as category_name, v.business_name as vendor_name, v.vendor_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE p.product_id = ? AND p.deleted_at IS NULL
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $product = mysqli_fetch_assoc($result);
            
            // Track product view using the new tracking system
            require_once 'includes/product_view_tracker.php';
            track_product_view($product_id, isset($_GET['source']) ? $_GET['source'] : 'direct_visit');
            
            // Get product reviews
            $reviews_query = "
                SELECT r.*, u.name as reviewer_name, 
                       (SELECT COUNT(*) FROM reviews WHERE user_id = r.user_id) as total_reviews,
                       (SELECT COUNT(*) FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.order_id 
                        WHERE o.user_id = r.user_id AND oi.product_id = r.product_id) as verified_purchase
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.product_id = ?
                ORDER BY r.created_at DESC
                LIMIT 5
            ";
            
            $reviews_stmt = mysqli_prepare($conn, $reviews_query);
            mysqli_stmt_bind_param($reviews_stmt, "i", $product_id);
            mysqli_stmt_execute($reviews_stmt);
            $reviews_result = mysqli_stmt_get_result($reviews_stmt);
            
            // Calculate average rating
            $avg_rating_query = "SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = ?";
            $avg_rating_stmt = mysqli_prepare($conn, $avg_rating_query);
            mysqli_stmt_bind_param($avg_rating_stmt, "i", $product_id);
            mysqli_stmt_execute($avg_rating_stmt);
            $avg_rating_result = mysqli_stmt_get_result($avg_rating_stmt);
            $avg_rating_row = mysqli_fetch_assoc($avg_rating_result);
            $avg_rating = $avg_rating_row['avg_rating'] ? round($avg_rating_row['avg_rating'], 1) : 0;
            
            // Get related products in the same category
            $related_query = "
                SELECT p.*, v.business_name as vendor_name
                FROM products p
                LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                WHERE p.category_id = ? AND p.product_id != ? AND p.deleted_at IS NULL
                LIMIT 4
            ";
            
            $related_stmt = mysqli_prepare($conn, $related_query);
            mysqli_stmt_bind_param($related_stmt, "ii", $product['category_id'], $product_id);
            mysqli_stmt_execute($related_stmt);
            $related_result = mysqli_stmt_get_result($related_stmt);

            if (isset($_SESSION['user_id'])) {
                // Check if user has purchased this product
                $purchase_check_query = "
                    SELECT o.order_id, o.created_at as purchase_date, oi.quantity
                    FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
                    ORDER BY o.created_at DESC
                    LIMIT 1
                ";
                $purchase_stmt = mysqli_prepare($conn, $purchase_check_query);
                mysqli_stmt_bind_param($purchase_stmt, "ii", $_SESSION['user_id'], $product_id);
                mysqli_stmt_execute($purchase_stmt);
                $purchase_result = mysqli_stmt_get_result($purchase_stmt);
                $purchase_data = mysqli_fetch_assoc($purchase_result);
                
                $can_review = $purchase_data !== null;
                $has_reviewed = $ratingSystem->hasUserReviewedProduct($_SESSION['user_id'], $product['product_id']);
                
                if ($can_review) {
                    $purchase_date = date('F j, Y', strtotime($purchase_data['purchase_date']));
                }
            }
        } else {
            $error_message = "Product not found";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Database error: " . mysqli_error($conn);
    }
} else {
    $error_message = "Invalid product ID";
}

$page_title = $product ? $product['name'] : "Product Not Found";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .product-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
        }
        
        .product-image {
            flex: 0 0 45%;
            max-width: 500px;
        }
        
        .product-image img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .product-details {
            flex: 1;
            min-width: 300px;
        }
        
        .product-title {
            font-size: 2rem;
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .product-vendor {
            color: #666;
            margin-bottom: 15px;
        }
        
        .product-price {
            font-size: 1.6rem;
            color: #4CAF50;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .product-description {
            line-height: 1.6;
            margin: 20px 0;
            color: #444;
        }
        
        .product-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background-color: #f8f9fa;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #555;
        }
        
        .quantity-selector {
            margin-bottom: 20px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            max-width: 120px;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        input[type="number"] {
            width: 60px;
            height: 30px;
            border: 1px solid #ddd;
            text-align: center;
            margin: 0 5px;
        }
        
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        
        .add-cart-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .add-cart-btn:hover {
            background-color: #45a049;
        }
        
        .wishlist-btn, .compare-btn {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .wishlist-btn:hover, .compare-btn:hover {
            background-color: #e9ecef;
        }
        
        .out-of-stock-notice {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .product-tabs {
            margin-top: 40px;
        }
        
        .tab-links {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab-link {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            margin-right: 5px;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
        }
        
        .tab-link.active {
            background-color: white;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .reviews-container {
            margin-top: 20px;
        }
        
        .review-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .reviewer-name {
            font-weight: bold;
        }
        
        .review-date {
            color: #777;
            font-size: 14px;
        }
        
        .star-rating {
            color: #FFD700;
            margin-bottom: 10px;
        }
        
        .review-text {
            line-height: 1.6;
        }
        
        .related-products {
            margin-top: 40px;
        }
        
        .related-products h2 {
            font-size: 1.6rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .related-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .related-item {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .related-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .related-image {
            height: 150px;
            overflow: hidden;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-info {
            padding: 15px;
        }
        
        .related-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .related-price {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .related-vendor {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
        }
        
        .related-link {
            display: block;
            text-align: center;
            padding: 8px 0;
            background-color: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-top: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .related-link:hover {
            background-color: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .product-container {
                flex-direction: column;
            }
            
            .product-image {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .related-items {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }
        
        .reviews-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .average-rating {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .rating-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .total-reviews {
            color: #666;
            font-size: 0.9rem;
        }
        
        .review-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .reviewer-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .verified-badge {
            color: #28a745;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .reviewer-stats {
            color: #666;
            font-size: 0.8rem;
        }
        
        .purchase-info {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cannot-review, .login-to-review {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .info-message {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .browse-products {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 10px;
        }
        
        .browse-products:hover {
            background-color: #45a049;
        }
        
        .write-review-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #2196F3;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .write-review-btn:hover {
            background-color: #1976D2;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Home</a> &gt; 
            <?php if (!$error_message && $product): ?>
                <a href="shop.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> &gt; 
                <?php echo htmlspecialchars($product['name']); ?>
            <?php else: ?>
                <span>Product Details</span>
            <?php endif; ?>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <p><?php echo $error_message; ?></p>
                <a href="index.php" class="btn">Return to Home</a>
            </div>
        <?php elseif ($product): ?>
            <div class="product-container">
                <div class="product-image">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <div class="product-details">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-vendor">
                        Sold by: <a href="vendor.php?id=<?php echo $product['vendor_id']; ?>"><?php echo htmlspecialchars($product['vendor_name']); ?></a>
                    </div>
                    
                    <div class="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $avg_rating): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i <= $avg_rating + 0.5): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span>(<?php echo $avg_rating; ?>/5)</span>
                    </div>
                    
                    <div class="product-price">
                        $<?php echo number_format($product['price'], 2); ?>
                    </div>
                    
                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <div class="product-info">
                        <div class="info-item">
                            <i class="fas fa-box"></i> 
                            <?php if ($product['stock'] > 0): ?>
                                In Stock (<?php echo $product['stock']; ?>)
                            <?php else: ?>
                                Out of Stock
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-tag"></i> Category: <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                        
                        <?php if (!empty($product['packaging'])): ?>
                            <div class="info-item">
                                <i class="fas fa-archive"></i> Packaging: <?php echo htmlspecialchars($product['packaging']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-actions">
                        <?php if ($product['stock'] > 0): ?>
                            <form method="post" action="cart.php" class="add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                
                                <div class="quantity-selector">
                                    <label for="quantity">Quantity:</label>
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-btn minus" onclick="decrementQuantity()">-</button>
                                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                        <button type="button" class="quantity-btn plus" onclick="incrementQuantity()">+</button>
                                    </div>
                                </div>
                                
                                <div class="button-group">
                                    <button type="submit" class="add-cart-btn">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                    
                                    <button type="button" class="wishlist-btn" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-heart"></i> Add to Wishlist
                                    </button>
                                    
                                    <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" class="compare-btn">
                                        <i class="fas fa-balance-scale"></i> Add to Compare
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="out-of-stock-notice">
                                <i class="fas fa-exclamation-circle"></i> Currently Out of Stock
                            </div>
                            <div class="button-group">
                                <button type="button" class="wishlist-btn" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-heart"></i> Add to Wishlist
                                </button>
                                
                                <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" class="compare-btn">
                                    <i class="fas fa-balance-scale"></i> Add to Compare
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="product-tabs">
                <div class="tab-links">
                    <div class="tab-link active" data-tab="description">Description</div>
                    <div class="tab-link" data-tab="specifications">Specifications</div>
                    <div class="tab-link" data-tab="reviews">Reviews</div>
                </div>
                
                <div id="description" class="tab-content active">
                    <h3>Product Description</h3>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                </div>
                
                <div id="specifications" class="tab-content">
                    <h3>Specifications</h3>
                    <div class="specs-content">
                        <?php if (isset($product['specifications']) && !empty($product['specifications'])): ?>
                            <?php 
                                $specs = json_decode($product['specifications'], true);
                                if ($specs && is_array($specs)):
                            ?>
                                <ul class="specs-list">
                                    <?php foreach ($specs as $key => $value): ?>
                                        <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No detailed specifications available.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>No specifications available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="reviews" class="tab-content">
                    <h3>Customer Reviews</h3>
                    <div class="reviews-container">
                        <?php if (mysqli_num_rows($reviews_result) > 0): ?>
                            <div class="reviews-summary">
                                <div class="average-rating">
                                    <span class="rating-number"><?php echo number_format($avg_rating, 1); ?></span>
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $avg_rating): ?>
                                                <i class="fas fa-star"></i>
                                            <?php elseif ($i <= $avg_rating + 0.5): ?>
                                                <i class="fas fa-star-half-alt"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="total-reviews">(<?php echo mysqli_num_rows($reviews_result); ?> reviews)</span>
                                </div>
                            </div>
                            
                            <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                            <?php if ($review['verified_purchase'] > 0): ?>
                                                <span class="verified-badge">
                                                    <i class="fas fa-check-circle"></i> Verified Purchase
                                                </span>
                                            <?php endif; ?>
                                            <div class="reviewer-stats">
                                                <?php echo $review['total_reviews']; ?> reviews
                                            </div>
                                        </div>
                                        <div class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $review['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="review-text">
                                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            
                            <div class="review-action">
                                <a href="reviews.php?product_id=<?php echo $product['product_id']; ?>" class="view-all-reviews">View All Reviews</a>
                            </div>
                        <?php else: ?>
                            <div class="no-reviews">
                                <p>No reviews yet. Be the first to review this product!</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($can_review && !$has_reviewed): ?>
                                <div class="add-review">
                                    <div class="purchase-info">
                                        <i class="fas fa-shopping-bag"></i>
                                        Purchased on <?php echo $purchase_date; ?>
                                    </div>
                                    <a href="add-review.php?product_id=<?php echo $product['product_id']; ?>" class="write-review-btn">
                                        <i class="fas fa-pen"></i> Write a Review
                                    </a>
                                </div>
                            <?php elseif (!$can_review): ?>
                                <div class="cannot-review">
                                    <div class="info-message">
                                        <i class="fas fa-info-circle"></i>
                                        <p>You can only review this product after purchasing and receiving it.</p>
                                    </div>
                                    <a href="shop.php" class="browse-products">Browse Products</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="login-to-review">
                                <div class="info-message">
                                    <i class="fas fa-user"></i>
                                    <p>Please <a href="login.php">login</a> to leave a review.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($related_result) > 0): ?>
                <div class="related-products">
                    <h2>Related Products</h2>
                    <div class="related-items">
                        <?php while ($related = mysqli_fetch_assoc($related_result)): ?>
                            <div class="related-item">
                                <div class="related-image">
                                    <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                </div>
                                <div class="related-info">
                                    <h3 class="related-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                                    <p class="related-vendor"><?php echo htmlspecialchars($related['vendor_name']); ?></p>
                                    <p class="related-price">$<?php echo number_format($related['price'], 2); ?></p>
                                </div>
                                <a href="product_details.php?id=<?php echo $related['product_id']; ?>" class="related-link">View Product</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/product-tracking.js"></script>
    <input type="hidden" id="current-product-id" value="<?php echo $product_id; ?>">
    
    <script>
        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }
        
        function incrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.getAttribute('max'));
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        }
        
        function addToWishlist(productId) {
            // Using fetch API to add to wishlist without page reload
            fetch('add_to_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to wishlist!');
                } else {
                    if (data.message === 'login_required') {
                        window.location.href = 'login.php';
                    } else {
                        alert(data.message || 'Error adding to wishlist');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        // Tab handling
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    tabLinks.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to current tab
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 