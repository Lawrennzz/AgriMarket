<?php
session_start();
include 'config.php'; // Include your database connection

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check if wishlist table exists
$check_table = "SHOW TABLES LIKE 'wishlist'";
$table_result = mysqli_query($conn, $check_table);

if (!$table_result || mysqli_num_rows($table_result) === 0) {
    // Create wishlist table if it doesn't exist
    $create_table = "CREATE TABLE wishlist (
        wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_product (user_id, product_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $create_table);
}

// Handle add to wishlist
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    
    // Check if product exists
    $product_check = "SELECT product_id FROM products WHERE product_id = ?";
    $check_stmt = mysqli_prepare($conn, $product_check);
    mysqli_stmt_bind_param($check_stmt, "i", $product_id);
    mysqli_stmt_execute($check_stmt);
    $product_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($product_result) > 0) {
        // Add to wishlist (ignore duplicates)
        $add_query = "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)";
        $add_stmt = mysqli_prepare($conn, $add_query);
        mysqli_stmt_bind_param($add_stmt, "ii", $user_id, $product_id);
        
        if (mysqli_stmt_execute($add_stmt)) {
            if (mysqli_stmt_affected_rows($add_stmt) > 0) {
                $success_message = "Product added to your wishlist.";
            } else {
                $error_message = "Product is already in your wishlist.";
            }
        } else {
            $error_message = "Failed to add product to wishlist.";
        }
        mysqli_stmt_close($add_stmt);
    } else {
        $error_message = "Product not found.";
    }
    mysqli_stmt_close($check_stmt);
    
    // Redirect to remove the query parameter
    if (isset($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    }
}

// Handle remove from wishlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    
    $remove_query = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
    $remove_stmt = mysqli_prepare($conn, $remove_query);
    mysqli_stmt_bind_param($remove_stmt, "ii", $user_id, $product_id);
    
    if (mysqli_stmt_execute($remove_stmt)) {
        if (mysqli_stmt_affected_rows($remove_stmt) > 0) {
            $success_message = "Product removed from your wishlist.";
        } else {
            $error_message = "Product was not in your wishlist.";
        }
    } else {
        $error_message = "Failed to remove product from wishlist.";
    }
    mysqli_stmt_close($remove_stmt);
}

// Fetch wishlist items
$wishlist_query = "SELECT w.wishlist_id, w.created_at, p.product_id, p.name, p.price, p.image_url, 
                  p.description, p.stock, c.name as category_name, v.business_name as vendor_name
                  FROM wishlist w
                  JOIN products p ON w.product_id = p.product_id
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                  WHERE w.user_id = ?
                  ORDER BY w.created_at DESC";
$wishlist_stmt = mysqli_prepare($conn, $wishlist_query);

// Check if statement preparation was successful
if ($wishlist_stmt === false) {
    $error_message = "Error preparing wishlist query: " . mysqli_error($conn);
    $wishlist_result = null;
    $wishlist_count = 0;
} else {
    mysqli_stmt_bind_param($wishlist_stmt, "i", $user_id);
    mysqli_stmt_execute($wishlist_stmt);
    $wishlist_result = mysqli_stmt_get_result($wishlist_stmt);
    $wishlist_count = mysqli_num_rows($wishlist_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Your Wishlist</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .wishlist-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .wishlist-header {
            margin-bottom: 2rem;
        }
        
        .wishlist-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .wishlist-subtitle {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .wishlist-count {
            background-color: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .wishlist-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        
        .wishlist-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .wishlist-image {
            height: 180px;
            width: 100%;
            object-fit: cover;
        }
        
        .wishlist-content {
            padding: 1.25rem;
        }
        
        .wishlist-category {
            font-size: 0.75rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .wishlist-name {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            font-weight: 600;
        }
        
        .wishlist-vendor {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }
        
        .wishlist-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }
        
        .wishlist-actions {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
        }
        
        .wishlist-btn {
            flex: 1;
            padding: 0.75rem;
            font-size: 0.85rem;
            text-align: center;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            text-decoration: none;
        }
        
        .wishlist-btn.primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        .wishlist-btn.primary:hover {
            background: var(--primary-dark);
        }
        
        .wishlist-btn.secondary {
            background: white;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .wishlist-btn.secondary:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .stock-status {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .in-stock {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
        }
        
        .low-stock {
            background: rgba(255, 152, 0, 0.15);
            color: #ff9800;
        }
        
        .out-of-stock {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }
        
        .empty-state p {
            color: var(--medium-gray);
            margin-bottom: 1.5rem;
        }
        
        .continue-shopping {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .continue-shopping:hover {
            background: var(--primary-dark);
        }
        
        @media (max-width: 768px) {
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="wishlist-container">
        <div class="wishlist-header">
            <h1 class="wishlist-title">
                <i class="far fa-heart"></i> My Wishlist
                <span class="wishlist-count"><?php echo $wishlist_count; ?> items</span>
            </h1>
            <p class="wishlist-subtitle">Save items you like and come back to them later.</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($wishlist_result && $wishlist_count > 0): ?>
            <div class="wishlist-grid">
                <?php while ($item = mysqli_fetch_assoc($wishlist_result)): ?>
                    <div class="wishlist-card">
                        <?php if ($item['stock'] > 10): ?>
                            <span class="stock-status in-stock">In Stock</span>
                        <?php elseif ($item['stock'] > 0): ?>
                            <span class="stock-status low-stock">Low Stock</span>
                        <?php else: ?>
                            <span class="stock-status out-of-stock">Out of Stock</span>
                        <?php endif; ?>
                        
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="wishlist-image">
                        
                        <div class="wishlist-content">
                            <?php if (!empty($item['category_name'])): ?>
                                <div class="wishlist-category"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            <?php endif; ?>
                            
                            <h3 class="wishlist-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                            
                            <?php if (!empty($item['vendor_name'])): ?>
                                <div class="wishlist-vendor">
                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($item['vendor_name']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="wishlist-price">$<?php echo number_format($item['price'], 2); ?></div>
                            
                            <div class="wishlist-actions">
                                <?php if ($item['stock'] > 0): ?>
                                    <a href="cart.php?add=<?php echo $item['product_id']; ?>&redirect=wishlist.php" class="wishlist-btn primary">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </a>
                                <?php else: ?>
                                    <button class="wishlist-btn primary" disabled>
                                        <i class="fas fa-cart-plus"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                                
                                <a href="?remove=<?php echo $item['product_id']; ?>" class="wishlist-btn secondary">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-heart"></i>
                <h3>Your wishlist is empty</h3>
                <p>Save items you like and they will appear here.</p>
                <a href="products.php" class="continue-shopping">
                    <i class="fas fa-shopping-basket"></i> Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>

