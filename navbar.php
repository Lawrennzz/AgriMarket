<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get notification count if user is logged in
$notification_count = 0;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $user_id = $_SESSION['user_id'];
    $notification_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND read_status = 0";
    $notification_result = mysqli_query($conn, $notification_query);
    if ($notification_result) {
        $notification_count = mysqli_fetch_assoc($notification_result)['count'];
    }
}

// Get cart count if user is logged in
$cart_count = 0;
if (isset($_SESSION['user_id']) && isset($conn) && $_SESSION['role'] === 'customer') {
    $user_id = $_SESSION['user_id'];
    $cart_query = "SELECT SUM(quantity) as count FROM cart WHERE user_id = $user_id";
    $cart_result = mysqli_query($conn, $cart_query);
    if ($cart_result) {
        $cart_count = mysqli_fetch_assoc($cart_result)['count'] ?? 0;
    }
} elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 1;
    }
}
?>
<nav class="navbar">
    <div class="container navbar-container">
        <a href="index.php" class="navbar-brand">AgriMarket</a>
        <ul class="navbar-nav">
            <li><a href="index.php" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Home</a></li>
            <li><a href="products.php" class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">Products</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php" class="nav-link <?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">Admin Dashboard</a></li>
                <?php elseif ($_SESSION['role'] === 'vendor'): ?>
                    <li><a href="product_upload.php" class="nav-link <?php echo $current_page === 'product_upload.php' ? 'active' : ''; ?>">Upload Product</a></li>
                    <li><a href="orders.php" class="nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">Orders</a></li>
                <?php endif; ?>
                
                <li><a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                
                <!-- Notifications for all users -->
                <li><a href="notifications.php" class="nav-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-count"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a></li>
                
                <!-- Cart for customers only -->
                <?php if ($_SESSION['role'] === 'customer'): ?>
                    <li><a href="cart.php" class="nav-link <?php echo $current_page === 'cart.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a></li>
                <?php endif; ?>
                
                <!-- Links for all users -->
                <li><a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">Products</a></li>
                
                <!-- Links for customer role only -->
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer'): ?>
                    <li><a href="cart.php" class="<?php echo $current_page === 'cart.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <?php if (isset($cart_count) && $cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="wishlist.php" class="<?php echo $current_page === 'wishlist.php' ? 'active' : ''; ?>">
                        <i class="far fa-heart"></i> Wishlist
                    </a></li>
                <?php endif; ?>
                
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>">Login</a></li>
                <li><a href="register.php" class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
.navbar {
    padding: 1rem 0;
}

.navbar .active {
    color: var(--primary-color);
    font-weight: 500;
}

.cart-count, .notification-count {
    background-color: var(--danger-color);
    color: white;
    border-radius: 50%;
    padding: 0.2rem 0.5rem;
    font-size: 0.8rem;
    position: relative;
    top: -8px;
    margin-left: -8px;
}

.notification-count {
    background-color: var(--warning-color);
}

/* Make icons slightly larger */
.navbar-nav i {
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .navbar-container {
        padding: 0 1rem;
    }
}
</style> 