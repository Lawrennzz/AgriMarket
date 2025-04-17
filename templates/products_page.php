<!DOCTYPE html>
<html>
<head>
    <title>Products - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .products-container {
            padding: 2rem 0;
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .search-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
        }

        .search-input input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            font-size: 1rem;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .view-options {
            display: flex;
            gap: 0.5rem;
        }

        .view-option {
            padding: 0.5rem;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            cursor: pointer;
            color: var(--medium-gray);
            transition: var(--transition);
        }

        .view-option.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .products-grid {
            display: grid;
            gap: 2rem;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }

        .products-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .product-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-details {
            padding: 1.5rem;
        }

        .product-category {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
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

        .product-stock {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 1rem;
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

        /* List View Styles */
        .product-card.list-view {
            display: flex;
            gap: 2rem;
        }

        .product-card.list-view .product-image {
            width: 200px;
            height: 200px;
        }

        .product-card.list-view .product-details {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-card.list-view .product-actions {
            margin-top: auto;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--medium-gray);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container products-container">
        <div class="products-header">
            <h1>Agricultural Products</h1>
        </div>

        <div class="search-filters">
            <form method="GET" class="search-row">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($this->getSearch()); ?>">
                </div>
                <select name="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($this->getCategories() as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>" <?php echo $this->getCategoryId() === $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($cat['name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" class="form-control">
                    <option value="name_asc" <?php echo $this->getSort() === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php echo $this->getSort() === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                    <option value="price_asc" <?php echo $this->getSort() === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                    <option value="price_desc" <?php echo $this->getSort() === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </form>
            <div class="filters-row">
                <div class="view-options">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                       class="view-option <?php echo $this->getView() === 'grid' ? 'active' : ''; ?>"
                       title="Grid View">
                        <i class="fas fa-th"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
                       class="view-option <?php echo $this->getView() === 'list' ? 'active' : ''; ?>"
                       title="List View">
                        <i class="fas fa-list"></i>
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($this->getProducts())): ?>
            <div class="<?php echo $this->getView() === 'grid' ? 'products-grid' : 'products-list'; ?>">
                <?php foreach ($this->getProducts() as $product): ?>
                    <div class="product-card <?php echo $this->getView() === 'list' ? 'list-view' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="product-image">
                        <div class="product-details">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-vendor">
                                <i class="fas fa-store"></i> 
                                <?php echo htmlspecialchars($product['vendor_name']); ?>
                            </div>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock">
                                <?php if (isset($product['stock']) && $product['stock'] > 0): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success-color);"></i> 
                                    <?php echo $product['stock']; ?> in stock
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color: var(--danger-color);"></i> 
                                    Out of stock
                                <?php endif; ?>
                            </div>
                            <div class="product-actions">
                                <?php if ($product['vendor_user_id'] == $this->getUserId()): ?>
                                    <a href="edit_delete_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary">Edit</a>
                                    <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" 
                                       class="view-details" title="Add to Compare">
                                        <i class="fas fa-balance-scale"></i>
                                    </a>
                                <?php elseif ($this->getRole() !== 'vendor'): // Only show Add to Cart for non-vendors ?>
                                    <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="add-to-cart">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                    <a href="wishlist.php?add=<?php echo $product['product_id']; ?>&redirect=products.php<?php echo isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" 
                                       class="view-details" title="Add to Wishlist">
                                        <i class="far fa-heart"></i>
                                    </a>
                                    <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" 
                                       class="view-details" title="Add to Compare">
                                        <i class="fas fa-balance-scale"></i>
                                    </a>
                                <?php else: // Vendor viewing other vendor's products ?>
                                    <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" 
                                       class="view-details" title="Add to Compare">
                                        <i class="fas fa-balance-scale"></i>
                                    </a>
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
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Products Found</h3>
                <p>We couldn't find any products matching your criteria.</p>
                <a href="products.php" class="btn btn-primary">Clear Filters</a>
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