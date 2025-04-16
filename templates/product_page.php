<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($this->getProduct()['name']); ?> - AgriMarket</title>
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

        .product-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .add-to-cart {
            flex: 1;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .add-to-cart:hover {
            background: var(--primary-dark);
        }

        .view-details {
            padding: 0.75rem;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .view-details:hover {
            background: rgba(76, 175, 80, 0.1);
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
        <?php $product = $this->getProduct(); ?>
        
        <div class="breadcrumb">
            <a href="products.php">Products</a>
            <i class="fas fa-chevron-right"></i>
            <a href="products.php?category_id=<?php echo urlencode($product['category_id']); ?>">
                <?php echo htmlspecialchars($product['category_name']); ?>
            </a>
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
                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <div class="rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $this->getAverageRating()): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $this->getAverageRating()): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo $this->getAverageRating(); ?> (<?php echo $this->getTotalReviews(); ?> reviews)</span>
                    </div>
                </div>

                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>

                <div class="stock-info">
                    <?php if (isset($product['stock']) && $product['stock'] > 0): ?>
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><?php echo $product['stock']; ?> in stock</span>
                    <?php else: ?>
                        <i class="fas fa-times-circle" style="color: var(--danger-color);"></i>
                        <span>Out of stock</span>
                    <?php endif; ?>
                </div>

                <div class="product-actions">
                    <?php if (isset($product['vendor_user_id']) && $product['vendor_user_id'] == $this->getUserId()): ?>
                        <a href="edit_delete_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary">Edit</a>
                    <?php elseif ($this->getRole() !== 'vendor'): // Only show Add to Cart for non-vendors ?>
                        <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="add-to-cart">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    <?php endif; ?>
                </div>

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
                <?php if (isset($_SESSION['user_id']) && $this->getRole() !== 'vendor'): ?>
                    <a href="add-review.php?product_id=<?php echo $product['product_id']; ?>" class="btn btn-primary">Write a Review</a>
                <?php endif; ?>
            </div>

            <?php if ($this->getTotalReviews() > 0): ?>
                <?php foreach ($this->getReviews() as $review): ?>
                    <div class="review-card">
                        <div class="reviewer-info">
                            <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                            <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $review['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </div>

        <?php if (count($this->getRelatedProducts()) > 0): ?>
            <div class="related-products">
                <h2>Related Products</h2>
                <div class="related-grid">
                    <?php foreach ($this->getRelatedProducts() as $related): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($related['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>"
                                 class="product-image">
                            <div class="product-details">
                                <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                                <div class="product-price">$<?php echo number_format($related['price'], 2); ?></div>
                                <a href="product.php?id=<?php echo $related['product_id']; ?>" class="btn btn-primary">
                                    View Product
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    function addToCart(productId) {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `product_id=${productId}&action=add`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count in navbar
                const cartCount = document.querySelector('.cart-count');
                if (cartCount) {
                    cartCount.textContent = parseInt(cartCount.textContent || 0) + 1;
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