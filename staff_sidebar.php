<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page filename for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Determine if we're in admin directory to set correct paths
$in_admin_dir = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$base_path = $in_admin_dir ? '../' : '';
?>

<div class="sidebar">
    <div class="logo">
        <a href="staff_dashboard.php">
            <img src="" alt="">
            <span>AgriMarket Staff</span>
        </a>
    </div>
    
    <div class="user-info">
        <div class="user-img">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-details">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Staff Member'); ?></span>
            <span class="user-role">Staff</span>
        </div>
    </div>
    
    <ul class="nav-links">
        <li class="<?php echo ($current_page == 'staff_dashboard.php') ? 'active' : ''; ?>">
            <a href="staff_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'my_tasks.php') ? 'active' : ''; ?>">
            <a href="my_tasks.php">
                <i class="fas fa-tasks"></i>
                <span>My Tasks</span>
                <?php
                // Count pending tasks
                $pending_count = 0;
                if (isset($_SESSION['user_id'])) {
                    // Check if connection is available, if not include it
                    if (!isset($conn) || $conn === null) {
                        require_once 'includes/db_connection.php';
                    }
                    
                    $task_count_query = "SELECT COUNT(*) as count FROM staff_tasks WHERE staff_id = ? AND status = 'pending'";
                    $task_count_stmt = mysqli_prepare($conn, $task_count_query);
                    
                    if ($task_count_stmt === false) {
                        // If prepare fails, just set pending_count to 0 and continue
                        $pending_count = 0;
                    } else {
                        mysqli_stmt_bind_param($task_count_stmt, "i", $_SESSION['user_id']);
                        mysqli_stmt_execute($task_count_stmt);
                        $task_count_result = mysqli_stmt_get_result($task_count_stmt);
                        $pending_count = mysqli_fetch_assoc($task_count_result)['count'];
                    }
                }
                
                if ($pending_count > 0):
                ?>
                <span class="badge"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="nav-dropdown <?php echo (in_array($current_page, ['process_orders.php', 'view_orders.php', 'order_details.php'])) ? 'active' : ''; ?>">
            <a href="javascript:void(0);" class="dropdown-toggle">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </a>
            <ul class="dropdown-menu">
                <li class="<?php echo ($current_page == 'process_orders.php') ? 'active' : ''; ?>">
                    <a href="process_orders.php">
                        <i class="fas fa-box-open"></i>
                        <span>Process Orders</span>
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'view_orders.php') ? 'active' : ''; ?>">
                    <a href="view_orders.php">
                        <i class="fas fa-list"></i>
                        <span>View Orders</span>
                    </a>
                </li>
            </ul>
        </li>
        
        <li class="nav-dropdown <?php echo (in_array($current_page, ['view_product.php', 'add_product.php'])) ? 'active' : ''; ?>">
            <a href="javascript:void(0);" class="dropdown-toggle">
                <i class="fas fa-box"></i>
                <span>Products</span>
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </a>
            <ul class="dropdown-menu">
                <li class="<?php echo ($current_page == 'view_product.php') ? 'active' : ''; ?>">
                    <a href="view_product.php">
                        <i class="fas fa-eye"></i>
                        <span>View Products</span>
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'add_product.php') ? 'active' : ''; ?>">
                    <a href="add_product.php">
                        <i class="fas fa-plus"></i>
                        <span>Add Product</span>
                    </a>
                </li>
            </ul>
        </li>
        
        <li class="<?php echo ($current_page == 'customer_support.php') ? 'active' : ''; ?>">
            <a href="customer_support.php">
                <i class="fas fa-headset"></i>
                <span>Customer Support</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'view_reports.php') ? 'active' : ''; ?>">
            <a href="view_reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>View Reports</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'my_performance.php') ? 'active' : ''; ?>">
            <a href="my_performance.php">
                <i class="fas fa-chart-line"></i>
                <span>My Performance</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <a href="profile.php">
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        height: 100vh;
        background-color: #263238;
        color: #ecf0f1;
        padding-top: 20px;
        overflow-y: auto;
        z-index: 1000;
    }
    
    .logo {
        padding: 0 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .logo a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: white;
    }
    
    .logo img {
        height: 40px;
        margin-right: 10px;
    }
    
    .logo span {
        font-size: 18px;
        font-weight: 500;
    }
    
    .user-info {
        padding: 20px 15px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .user-img {
        margin-right: 15px;
        font-size: 30px;
        color: #bbc;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 500;
        font-size: 14px;
    }
    
    .user-role {
        font-size: 12px;
        color: #aab;
    }
    
    .nav-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-links li {
        position: relative;
    }
    
    .nav-links li.active > a {
        background-color: rgba(255, 255, 255, 0.1);
        color: #3498db;
        border-left: 3px solid #3498db;
    }
    
    .nav-links li a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #ddd;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .nav-links li:not(.active) a:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: white;
    }
    
    .nav-links li a i {
        min-width: 30px;
        font-size: 16px;
    }
    
    .dropdown-toggle {
        display: flex;
        justify-content: space-between !important;
    }
    
    .dropdown-icon {
        transition: transform 0.3s;
    }
    
    .nav-dropdown.active .dropdown-icon {
        transform: rotate(180deg);
    }
    
    .dropdown-menu {
        list-style: none;
        padding-left: 30px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
    
    .nav-dropdown.active .dropdown-menu {
        max-height: 200px;
    }
    
    .badge {
        background-color: #e74c3c;
        color: white;
        font-size: 12px;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: auto;
    }
    
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        width: 100%;
        padding: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-footer a {
        display: flex;
        align-items: center;
        color: #ddd;
        text-decoration: none;
    }
    
    .sidebar-footer a:hover {
        color: white;
    }
    
    .sidebar-footer a i {
        min-width: 30px;
        font-size: 16px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle dropdown menus
        const dropdowns = document.querySelectorAll('.nav-dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdown.classList.toggle('active');
            });
        });
        
        // Auto-expand dropdown if a child link is active
        const activeDropdownItems = document.querySelectorAll('.dropdown-menu .active');
        activeDropdownItems.forEach(item => {
            const parentDropdown = item.closest('.nav-dropdown');
            if (parentDropdown) {
                parentDropdown.classList.add('active');
            }
        });
    });
</script> 