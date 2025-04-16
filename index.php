<?php
// Include database connection
include 'config.php';

// Fetch featured products from the database
$query = "SELECT p.*, c.name as category_name, v.vendor_id, u.name as vendor_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          LEFT JOIN vendors v ON p.vendor_id = v.vendor_id 
          LEFT JOIN users u ON v.user_id = u.user_id 
          WHERE p.featured = 1 
          AND p.deleted_at IS NULL 
          LIMIT 6";
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

// Fetch product categories for filtering
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>AgriMarket</title>
    <style>
        .hero {
            position: relative;
            color: white;
            text-align: center;
            padding: 8rem 2rem;
            margin-bottom: 3rem;
            background-color: #4CAF50;
            overflow: hidden;
        }
        
        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(46, 125, 50, 0.9), rgba(76, 175, 80, 0.7));
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        /* Market visuals */
        .market-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.2;
            z-index: 0;
            background-image: 
                linear-gradient(90deg, transparent 90%, #ffffff 90%, #ffffff 100%),
                linear-gradient(#ffffff 0.3em, transparent 0.3em);
            background-size: 10% 20%, 100% 10%;
        }
        
        .market-icon {
            position: absolute;
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.2);
            z-index: 0;
        }
        
        .market-icon.basket {
            top: 15%;
            left: 15%;
            font-size: 3rem;
        }
        
        .market-icon.farm {
            top: 25%;
            right: 20%;
            font-size: 2.5rem;
        }
        
        .market-icon.wheat {
            bottom: 20%;
            left: 25%;
            font-size: 2.2rem;
        }
        
        .market-icon.vegetable {
            bottom: 30%;
            right: 15%;
            font-size: 2.8rem;
        }
        
        .featured-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .featured-header h2 {
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .featured-header h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .product-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .featured-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: var(--primary-color);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            z-index: 2;
        }
        
        .product-details {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }
        
        .product-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            transition: color 0.3s ease;
        }
        
        .product-card:hover .product-title {
            color: var(--primary-color);
        }
        
        .product-vendor {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .product-actions {
            margin-top: auto;
            display: flex;
            gap: 0.5rem;
        }
        
        .add-to-cart-btn {
            flex: 1;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .add-to-cart-btn:hover {
            background: var(--primary-dark);
        }
        
        .view-details {
            padding: 0.75rem;
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-details:hover {
            background: rgba(76, 175, 80, 0.1);
        }
        
        .no-products {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .no-products i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }
        
        .no-products h3 {
            margin-bottom: 0.5rem;
        }
        
        .browse-categories {
            margin-top: 4rem;
            padding: 3rem 0;
            background-color: var(--light-gray);
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .category-card {
            background: white;
            text-align: center;
            padding: 2rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
        }
        
        .category-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .category-name {
            font-weight: 600;
            color: var(--dark-gray);
        }

        @media (max-width: 992px) {
            .grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <header class="hero">
        <div class="market-pattern"></div>
        <div class="market-icon basket"><i class="fas fa-shopping-basket"></i></div>
        <div class="market-icon farm"><i class="fas fa-tractor"></i></div>
        <div class="market-icon wheat"><i class="fas fa-seedling"></i></div>
        <div class="market-icon vegetable"><i class="fas fa-apple-alt"></i></div>
        <div class="hero-content">
            <h1>Welcome to AgriMarket</h1>
            <p>Your trusted marketplace for agricultural products</p>
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-shopping-basket"></i> Shop Now
            </a>
        </div>
    </header>

    <main class="container">
        <div class="featured-header">
            <h2>Featured Products</h2>
            <a href="products.php" class="btn btn-secondary">View All Products</a>
        </div>
        
        <?php if (!empty($featuredProducts)): ?>
            <div class="grid grid-3">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <div class="product-image-container">
                            <span class="featured-tag"><i class="fas fa-star"></i> Featured</span>
                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                class="product-image">
                        </div>
                        <div class="product-details">
                            <div class="product-category">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-vendor">
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($product['vendor_name'] ?? 'Unknown Vendor'); ?>
                            </div>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-actions">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                                    <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                <?php endif; ?>
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="view-details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-box-open"></i>
                <h3>No featured products available</h3>
                <p>Check back soon for exciting featured products!</p>
                <a href="products.php" class="btn btn-primary">Browse All Products</a>
            </div>
        <?php endif; ?>
        
        <section class="browse-categories">
            <div class="container">
                <div class="featured-header">
                    <h2>Browse Categories</h2>
                </div>
                
                <div class="category-grid">
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category_id=<?php echo $category['category_id']; ?>" class="category-card">
                            <?php 
                            // Choose icon based on category name
                            $icon = 'fa-seedling'; // Default icon
                            $categoryName = strtolower($category['name']);
                            
                            if (strpos($categoryName, 'livestock') !== false) {
                                $icon = 'fa-horse';
                            } elseif (strpos($categoryName, 'crop') !== false) {
                                $icon = 'fa-seedling';
                            } elseif (strpos($categoryName, 'dairy') !== false) {
                                $icon = 'fa-cheese';
                            } elseif (strpos($categoryName, 'fish') !== false) {
                                $icon = 'fa-fish';
                            } elseif (strpos($categoryName, 'forestry') !== false) {
                                $icon = 'fa-tree';
                            } elseif (strpos($categoryName, 'miscellaneous') !== false) {
                                $icon = 'fa-leaf';
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                            <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

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
                } else {
                    // Create cart count if it doesn't exist
                    const cartIcon = document.querySelector('.fa-shopping-cart');
                    if (cartIcon) {
                        const span = document.createElement('span');
                        span.className = 'cart-count';
                        span.textContent = '1';
                        cartIcon.parentNode.appendChild(span);
                    }
                }
                // Show success message
                alert('Product added to cart!');
            } else {
                alert(data.message || 'Failed to add product to cart.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    </script>
</body>
</html>