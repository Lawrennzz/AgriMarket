<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="admin-header">
    <div class="container">
        <div class="admin-nav">
            <div class="admin-brand">
                <a href="../admin_dashboard.php">AgriMarket Admin</a>
            </div>
            <nav class="admin-menu">
                <ul>
                    <li><a href="../admin_dashboard.php" class="<?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="../manage_products.php" class="<?php echo $current_page === 'manage_products.php' ? 'active' : ''; ?>">Products</a></li>
                    <li><a href="../manage_users.php" class="<?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>">Users</a></li>
                    <li><a href="../manage_vendors.php" class="<?php echo $current_page === 'manage_vendors.php' ? 'active' : ''; ?>">Vendors</a></li>
                    <li><a href="../manage_orders.php" class="<?php echo $current_page === 'manage_orders.php' ? 'active' : ''; ?>">Orders</a></li>
                    <li><a href="notifications.php" class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">Notifications</a></li>
                    <li><a href="email_settings.php" class="<?php echo $current_page === 'email_settings.php' ? 'active' : ''; ?>">Email Settings</a></li>
                    <li><a href="../settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">Settings</a></li>
                    <li><a href="../audit_logs.php" class="<?php echo $current_page === 'audit_logs.php' ? 'active' : ''; ?>">Audit Logs</a></li>
                </ul>
            </nav>
            <div class="admin-user">
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? ($_SESSION['email'] ?? 'Admin User')); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
</header>

<style>
    .admin-header {
        background-color: #2c3e50;
        color: white;
        padding: 1rem 0;
    }
    
    .admin-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .admin-brand a {
        color: white;
        font-size: 1.5rem;
        font-weight: bold;
        text-decoration: none;
    }
    
    .admin-menu ul {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .admin-menu ul li {
        margin-right: 1rem;
    }
    
    .admin-menu ul li a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        padding: 0.5rem;
        transition: color 0.3s;
    }
    
    .admin-menu ul li a:hover, 
    .admin-menu ul li a.active {
        color: white;
        border-bottom: 2px solid #4CAF50;
    }
    
    .admin-user {
        display: flex;
        align-items: center;
    }
    
    .admin-user span {
        margin-right: 1rem;
    }
    
    .logout-btn {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        text-decoration: none;
        transition: background-color 0.3s;
    }
    
    .logout-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    @media (max-width: 992px) {
        .admin-nav {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .admin-brand {
            margin-bottom: 1rem;
        }
        
        .admin-menu {
            margin-bottom: 1rem;
            width: 100%;
            overflow-x: auto;
        }
        
        .admin-menu ul {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        
        .admin-user {
            width: 100%;
            justify-content: space-between;
        }
    }
</style> 