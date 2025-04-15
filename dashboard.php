<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user details
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

if ($role === 'vendor') {
    // Get vendor statistics
    $products_count = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM products WHERE vendor_id = $user_id"))['count'];
    
    // Prepare the SQL query
    $query = "
        SELECT COALESCE(SUM(oi.quantity * p.price), 0) AS total
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.user_id = ?;";

    // Prepare the statement
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_sales = mysqli_fetch_assoc($result);
    
    $recent_orders = mysqli_query($conn, 
        "SELECT o.*, p.name AS product_name, p.image_url, u.username AS vendor_name 
         FROM orders o 
         JOIN order_items oi ON o.order_id = oi.order_id 
         JOIN products p ON oi.product_id = p.product_id 
         JOIN users u ON p.vendor_id = u.user_id 
         WHERE o.user_id = $user_id 
         ORDER BY o.created_at DESC LIMIT 5");
    
    $low_stock_products = mysqli_query($conn, 
        "SELECT * FROM products 
         WHERE vendor_id = $user_id AND stock < 10 
         ORDER BY stock ASC LIMIT 5");
} else {
    // Get customer statistics
    $orders_count = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM orders WHERE user_id = $user_id"))['count'];
    
    // Prepare the SQL query
    $query = "
        SELECT COALESCE(SUM(oi.quantity * oi.price), 0) AS total
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.order_id 
        WHERE o.user_id = ?;";

    // Prepare the statement
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_spent = mysqli_fetch_assoc($result);
    
    $recent_orders = mysqli_query($conn, 
        "SELECT o.*, p.name AS product_name, p.image_url, u.username AS vendor_name 
         FROM orders o 
         JOIN order_items oi ON o.order_id = oi.order_id 
         JOIN products p ON oi.product_id = p.product_id 
         JOIN users u ON p.vendor_id = u.user_id 
         WHERE o.user_id = $user_id 
         ORDER BY o.created_at DESC LIMIT 5");
    
    $wishlist_items = mysqli_query($conn, 
        "SELECT p.*, u.name as vendor_name 
         FROM wishlist w 
         JOIN products p ON w.product_id = p.product_id 
         JOIN users u ON p.vendor_id = u.user_id 
         WHERE w.user_id = $user_id 
         LIMIT 4");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            padding: 2rem 0;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .welcome-message {
            font-size: 1.5rem;
            color: var(--dark-gray);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .stat-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .dashboard-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .order-card {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .order-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .order-details {
            flex: 1;
        }

        .order-product {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .order-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .product-card {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-details {
            flex: 1;
        }

        .product-stock {
            color: var(--danger-color);
            font-weight: 500;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .quick-action-btn {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark-gray);
            transition: var(--transition);
        }

        .quick-action-btn:hover {
            background: var(--light-gray);
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container dashboard-container">
        <div class="dashboard-header">
            <h1 class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        </div>

        <?php if ($role === 'vendor'): ?>
            <!-- Vendor Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <div class="stat-value"><?php echo $products_count; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-value">$<?php echo number_format($total_sales['total'], 2); ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-value"><?php echo mysqli_num_rows($recent_orders); ?></div>
                    <div class="stat-label">Recent Orders</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Orders</h2>
                        <a href="orders.php" class="btn btn-primary">View All</a>
                    </div>
                    <?php while ($order = mysqli_fetch_assoc($recent_orders)): ?>
                        <div class="order-card">
                            <div class="order-details">
                                <div class="order-product"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                <div class="order-meta">
                                    <span>Sold by <?php echo htmlspecialchars($order['vendor_name']); ?></span>
                                    <span> • </span>
                                    <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="order-meta">
                                    Quantity: <?php echo $order['quantity']; ?>
                                </div>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Low Stock Alert</h2>
                        <a href="products.php" class="btn btn-primary">Manage Products</a>
                    </div>
                    <?php while ($product = mysqli_fetch_assoc($low_stock_products)): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="product-image">
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-stock">Only <?php echo $product['stock']; ?> left in stock</div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="quick-actions">
                <a href="product_upload.php" class="quick-action-btn">
                    <i class="fas fa-plus"></i>
                    <div>Add New Product</div>
                </a>
                <a href="reports.php" class="quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <div>View Reports</div>
                </a>
                <a href="settings.php" class="quick-action-btn">
                    <i class="fas fa-cog"></i>
                    <div>Settings</div>
                </a>
            </div>

        <?php else: ?>
            <!-- Customer Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-shopping-bag"></i>
                    <div class="stat-value"><?php echo $orders_count; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-value">$<?php echo number_format($total_spent['total'], 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-heart"></i>
                    <div class="stat-value"><?php echo mysqli_num_rows($wishlist_items); ?></div>
                    <div class="stat-label">Wishlist Items</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Orders</h2>
                        <a href="orders.php" class="btn btn-primary">View All Orders</a>
                    </div>
                    <?php while ($order = mysqli_fetch_assoc($recent_orders)): ?>
                        <div class="order-card">
                            <img src="<?php echo htmlspecialchars($order['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($order['product_name']); ?>"
                                 class="order-image">
                            <div class="order-details">
                                <div class="order-product"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                <div class="order-meta">
                                    <span>Sold by <?php echo htmlspecialchars($order['vendor_name']); ?></span>
                                    <span> • </span>
                                    <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="order-meta">
                                    Quantity: <?php echo $order['quantity']; ?>
                                </div>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Wishlist</h2>
                        <a href="wishlist.php" class="btn btn-primary">View All</a>
                    </div>
                    <?php while ($item = mysqli_fetch_assoc($wishlist_items)): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 class="product-image">
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="product-meta">
                                    Sold by <?php echo htmlspecialchars($item['vendor_name']); ?>
                                </div>
                                <div class="product-price">$<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="quick-actions">
                <a href="products.php" class="quick-action-btn">
                    <i class="fas fa-shopping-bag"></i>
                    <div>Browse Products</div>
                </a>
                <a href="cart.php" class="quick-action-btn">
                    <i class="fas fa-shopping-cart"></i>
                    <div>View Cart</div>
                </a>
                <a href="settings.php" class="quick-action-btn">
                    <i class="fas fa-cog"></i>
                    <div>Settings</div>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>