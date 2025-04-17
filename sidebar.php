<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Determine if we're in admin directory to set correct paths
$in_admin_dir = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$admin_prefix = $in_admin_dir ? '../' : '';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>AgriMarket Admin</h3>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <li class="<?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
        </li>
        <li class="<?php echo $current_page === 'manage_vendors.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>manage_vendors.php"><i class="fas fa-store"></i> Manage Vendors</a>
        </li>
        <li class="<?php echo $current_page === 'manage_staff.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>manage_staff.php"><i class="fas fa-users-cog"></i> Manage Staff</a>
        </li>
        <li class="<?php echo $current_page === 'manage_products.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>manage_products.php"><i class="fas fa-box"></i> Manage Products</a>
        </li>
        <li class="<?php echo $current_page === 'manage_orders.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>manage_orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a>
        </li>
        <li class="<?php echo $current_page === 'audit_logs.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>audit_logs.php"><i class="fas fa-history"></i> Audit Logs</a>
        </li>
        <li class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>admin/reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        </li>
        <li class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
            <a href="<?php echo $in_admin_dir ? 'notifications.php' : 'admin/notifications.php'; ?>"><i class="fas fa-bell"></i> Notifications</a>
        </li>
        <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <a href="<?php echo $admin_prefix; ?>settings.php"><i class="fas fa-cog"></i> Settings</a>
        </li>
        <li>
            <a href="<?php echo $admin_prefix; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background-color: #333;
    color: #fff;
    padding-top: 0;
    overflow-y: auto;
}

.sidebar-header {
    padding: 20px;
    background-color: #333;
    margin-bottom: 20px;
    border-bottom: 1px solid #444;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 500;
    color: white;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu li a {
    display: block;
    padding: 10px 20px;
    color: #ddd;
    text-decoration: none;
    transition: all 0.3s;
}

.sidebar-menu li a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar-menu li a:hover {
    background-color: #444;
    color: #fff;
}

.sidebar-menu li.active a {
    background-color: #4CAF50;
    color: white;
}

.content {
    margin-left: 250px;
    padding: 20px;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .content {
        margin-left: 0;
    }
}
</style> 