<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
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
                <?php else: ?>
                    <li><a href="cart.php" class="nav-link <?php echo $current_page === 'cart.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <?php 
                        if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                            echo '<span class="cart-count">' . count($_SESSION['cart']) . '</span>';
                        }
                        ?>
                    </a></li>
                <?php endif; ?>
                <li><a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="notifications.php" class="nav-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                </a></li>
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>">Login</a></li>
                <li><a href="register.php" class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
.navbar .active {
    color: var(--primary-color);
    font-weight: 500;
}

.cart-count {
    background-color: var(--danger-color);
    color: white;
    border-radius: 50%;
    padding: 0.2rem 0.5rem;
    font-size: 0.8rem;
    position: relative;
    top: -8px;
    margin-left: -8px;
}

@media (max-width: 768px) {
    .navbar-container {
        padding: 0 1rem;
    }
}
</style> 