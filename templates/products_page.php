<!DOCTYPE html>
<html>
<head>
    <title>Products - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Global style for text visibility -->
    <style>
        /* Fix for search text visibility */
        .search-input input,
        select.form-control,
        input[type="text"],
        input[type="search"],
        .form-control,
        #product-search {
            color: #000 !important;
            background-color: #fff !important;
        }
        
        /* Updated search and filter styles to match image */
        .search-filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
        }
        
        .search-container {
            position: relative;
            width: 100%;
            flex: 3;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .search-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 40px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 16px;
            color: #333;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .search-box:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.3);
            border-color: #4CAF50;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
            z-index: 1;
            pointer-events: none;
        }
        
        .clear-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            cursor: pointer;
            display: none;
            background: none;
            border: none;
            padding: 0;
            z-index: 1;
        }
        
        .search-box:valid ~ .clear-search,
        .search-box[value]:not([value=""]) ~ .clear-search {
            display: block;
        }
        
        /* Filter layout */
        .search-row {
            display: flex;
            gap: 1rem;
            align-items: stretch;
            width: 100%;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            width: 100%;
            align-items: center;
        }
        
        .form-control {
            height: 45px;
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 0 1rem;
            background: white;
            flex: 1;
        }
        
        /* Apply Filters Button */
        .btn-apply-filters {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-apply-filters:hover {
            background-color: #3e9142;
        }
        
        /* Reset Filters Button */
        .btn-reset-filters {
            background-color: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-reset-filters:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }
        
        /* View toggle buttons */
        .view-options {
            display: flex;
            gap: 5px;
            margin-left: auto;
        }
        
        .view-option {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .view-option.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
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
            <form method="GET" action="products.php">
                <div class="search-row">
                    <div class="search-container">
                        <div class="search-icon-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" id="product-search" class="search-box"
                                placeholder="search" 
                                value="<?php echo htmlspecialchars($this->getSearch()); ?>" 
                                autocomplete="off">
                            <button type="button" class="clear-search" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="filter-controls">
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
                    <button type="submit" class="btn-apply-filters">Apply Filters</button>
                    <button type="button" class="btn-reset-filters" onclick="resetFilters()">Reset</button>
                    
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
            </form>
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
    // Cart functionality 
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

    // Function to clear search input
    function clearSearch() {
        const searchInput = document.getElementById('product-search');
        searchInput.value = '';
        searchInput.focus();
        
        // Hide the clear button
        const clearButton = document.querySelector('.clear-search');
        if (clearButton) {
            clearButton.style.display = 'none';
        }
        
        // Optional: Submit the form to clear results
        // Uncomment the next line if you want to automatically clear results
        // document.querySelector('form').submit();
    }
    
    // Function to reset all filters
    function resetFilters() {
        // Clear search input
        const searchInput = document.getElementById('product-search');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Reset category dropdown to default
        const categorySelect = document.querySelector('select[name="category_id"]');
        if (categorySelect) {
            categorySelect.value = '';
        }
        
        // Reset sort dropdown to default (Name A-Z)
        const sortSelect = document.querySelector('select[name="sort"]');
        if (sortSelect) {
            sortSelect.value = 'name_asc';
        }
        
        // Hide clear button
        const clearButton = document.querySelector('.clear-search');
        if (clearButton) {
            clearButton.style.display = 'none';
        }
        
        // Focus on search input
        if (searchInput) {
            searchInput.focus();
        }
        
        // Redirect to products page without any filters
        window.location.href = 'products.php';
    }

    // Enhanced search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('product-search');
        if (searchInput) {
            // Show/hide clear button based on content
            const updateClearButton = function() {
                const clearButton = document.querySelector('.clear-search');
                if (clearButton) {
                    clearButton.style.display = searchInput.value ? 'block' : 'none';
                }
            };
            
            // Initialize clear button visibility
            updateClearButton();
            
            // Update on input
            searchInput.addEventListener('input', updateClearButton);
            
            // Focus search field on page load if empty or contains search term
            searchInput.focus();
            
            // Place cursor at the end of text if there's existing search text
            if (searchInput.value) {
                const len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            }
        }
    });
    </script>
</body>
</html> 