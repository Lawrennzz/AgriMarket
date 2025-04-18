<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

// Get staff member info
$staff_id = $_SESSION['user_id'];
$staff_query = "SELECT * FROM users WHERE user_id = ? AND role = 'staff'";
$staff_stmt = mysqli_prepare($conn, $staff_query);
if ($staff_stmt === false) {
    die("Error in preparing staff query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($staff_stmt, "i", $staff_id);
mysqli_stmt_execute($staff_stmt);
$staff_result = mysqli_stmt_get_result($staff_stmt);
$staff_data = mysqli_fetch_assoc($staff_result);

// Get task statistics
$task_stats_query = "
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) AS overdue_tasks
    FROM staff_tasks
    WHERE staff_id = ?
";
$task_stats_stmt = mysqli_prepare($conn, $task_stats_query);
if ($task_stats_stmt === false) {
    die("Error in preparing task stats query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($task_stats_stmt, "i", $staff_id);
mysqli_stmt_execute($task_stats_stmt);
$task_stats_result = mysqli_stmt_get_result($task_stats_stmt);
$task_stats = mysqli_fetch_assoc($task_stats_result);

// Check if title column exists in staff_tasks
$check_title_column = "
    SELECT COUNT(*) as title_exists 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'staff_tasks' 
    AND column_name = 'title'
";
$title_check_stmt = mysqli_prepare($conn, $check_title_column);
if ($title_check_stmt === false) {
    die("Error checking table structure: " . mysqli_error($conn));
}
mysqli_stmt_execute($title_check_stmt);
$title_check_result = mysqli_stmt_get_result($title_check_stmt);
$title_exists = mysqli_fetch_assoc($title_check_result)['title_exists'] > 0;

// Get recent tasks - adapt to table structure
if ($title_exists) {
    $recent_tasks_query = "
        SELECT task_id, title, description, status, due_date, priority 
        FROM staff_tasks 
        WHERE staff_id = ? 
        ORDER BY due_date ASC 
        LIMIT 5
    ";
} else {
    // If no title column, use description as title
    $recent_tasks_query = "
        SELECT task_id, description as title, description, status, due_date, priority 
        FROM staff_tasks 
        WHERE staff_id = ? 
        ORDER BY due_date ASC 
        LIMIT 5
    ";
}

$recent_tasks_stmt = mysqli_prepare($conn, $recent_tasks_query);
if ($recent_tasks_stmt === false) {
    die("Error in preparing recent tasks query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($recent_tasks_stmt, "i", $staff_id);
mysqli_stmt_execute($recent_tasks_stmt);
$recent_tasks_result = mysqli_stmt_get_result($recent_tasks_stmt);

// Get pending orders count
$pending_orders_query = "
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'processing'
";
$pending_orders_stmt = mysqli_prepare($conn, $pending_orders_query);
if ($pending_orders_stmt === false) {
    die("Error in preparing pending orders query: " . mysqli_error($conn));
}
mysqli_stmt_execute($pending_orders_stmt);
$pending_orders_result = mysqli_stmt_get_result($pending_orders_stmt);
$pending_orders = mysqli_fetch_assoc($pending_orders_result)['count'];

// Get recent orders
$recent_orders_query = "
    SELECT o.*, u.name AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
";
$recent_orders_stmt = mysqli_prepare($conn, $recent_orders_query);
if ($recent_orders_stmt === false) {
    die("Error in preparing recent orders query: " . mysqli_error($conn));
}
mysqli_stmt_execute($recent_orders_stmt);
$recent_orders_result = mysqli_stmt_get_result($recent_orders_stmt);

// Check if is_read column exists in notifications
$check_is_read_column = "
    SELECT COUNT(*) as is_read_exists 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'notifications' 
    AND column_name = 'is_read'
";
$is_read_check_stmt = mysqli_prepare($conn, $check_is_read_column);
if ($is_read_check_stmt === false) {
    die("Error checking notifications table structure: " . mysqli_error($conn));
}
mysqli_stmt_execute($is_read_check_stmt);
$is_read_check_result = mysqli_stmt_get_result($is_read_check_stmt);
$is_read_exists = mysqli_fetch_assoc($is_read_check_result)['is_read_exists'] > 0;

// Get customer messages count - adapt to table structure
if ($is_read_exists) {
    $customer_messages_query = "
        SELECT COUNT(*) AS count 
        FROM notifications 
        WHERE is_read = 0 AND user_id = ?
    ";
} else {
    // If no is_read column, count all recent notifications for this user
    $customer_messages_query = "
        SELECT COUNT(*) AS count 
        FROM notifications 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
}

$customer_messages_stmt = mysqli_prepare($conn, $customer_messages_query);
if ($customer_messages_stmt === false) {
    die("Error in preparing customer messages query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($customer_messages_stmt, "i", $staff_id);
mysqli_stmt_execute($customer_messages_stmt);
$customer_messages_result = mysqli_stmt_get_result($customer_messages_stmt);
$unread_messages = mysqli_fetch_assoc($customer_messages_result)['count'];

// Get performance metrics
$performance_query = "
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE status IN ('delivered', 'shipped')) AS orders_processed,
        COUNT(task_id) AS tasks_completed,
        AVG(CASE WHEN status = 'completed' THEN DATEDIFF(completion_date, created_at) ELSE NULL END) AS avg_task_completion_time
    FROM staff_tasks
    WHERE staff_id = ? AND status = 'completed'
";
$performance_stmt = mysqli_prepare($conn, $performance_query);
if ($performance_stmt === false) {
    die("Error in preparing performance query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($performance_stmt, "i", $staff_id);
mysqli_stmt_execute($performance_stmt);
$performance_result = mysqli_stmt_get_result($performance_stmt);
$performance_data = mysqli_fetch_assoc($performance_result);

$page_title = "Staff Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .welcome-message {
            font-size: 1.5rem;
            color: #333;
        }
        
        .date-display {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-data {
            flex: 1;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .view-all {
            color: #3498db;
            font-size: 14px;
            text-decoration: none;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .task-list, .order-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .task-item, .order-item {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .task-item:last-child, .order-item:last-child {
            border-bottom: none;
        }
        
        .task-title, .order-title {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }
        
        .task-meta, .order-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6c757d;
        }
        
        .task-status, .order-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            color: #3498db;
        }
        
        .action-icon {
            font-size: 30px;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .action-title {
            font-weight: 500;
            margin: 0;
        }
        
        .action-description {
            font-size: 12px;
            color: #6c757d;
            margin: 5px 0 0 0;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid, .grid-container {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="welcome-message">
                Welcome back, <?php echo htmlspecialchars($staff_data['name']); ?>
            </div>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #3498db;">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-data">
                    <p class="stat-value"><?php echo $task_stats['pending_tasks'] + $task_stats['in_progress_tasks']; ?></p>
                    <p class="stat-label">Active Tasks</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #2ecc71;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-data">
                    <p class="stat-value"><?php echo $pending_orders; ?></p>
                    <p class="stat-label">Pending Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-data">
                    <p class="stat-value"><?php echo $task_stats['overdue_tasks']; ?></p>
                    <p class="stat-label">Overdue Tasks</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #9b59b6;">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-data">
                    <p class="stat-value"><?php echo $unread_messages; ?></p>
                    <p class="stat-label">Unread Messages</p>
                </div>
            </div>
        </div>
        
        <h2>Quick Actions</h2>
        <div class="quick-actions">
            <a href="my_tasks.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="action-title">View Tasks</h3>
                <p class="action-description">Check your assigned tasks</p>
            </a>
            
            <a href="process_orders.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3 class="action-title">Process Orders</h3>
                <p class="action-description">Manage pending orders</p>
            </a>
            
            <a href="add_product.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3 class="action-title">Add Product</h3>
                <p class="action-description">Create a new product</p>
            </a>
            
            <a href="customer_support.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3 class="action-title">Customer Support</h3>
                <p class="action-description">Handle customer inquiries</p>
            </a>
        </div>
        
        <div class="grid-container">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Tasks</h3>
                    <a href="my_tasks.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <ul class="task-list">
                        <?php if (mysqli_num_rows($recent_tasks_result) > 0): ?>
                            <?php while ($task = mysqli_fetch_assoc($recent_tasks_result)): ?>
                                <li class="task-item">
                                    <span class="task-title"><?php echo htmlspecialchars($task['title']); ?></span>
                                    <div class="task-meta">
                                        <span>Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                                        <?php
                                        $status_class = '';
                                        if ($task['status'] == 'pending') {
                                            $status_class = 'status-pending';
                                        } elseif ($task['status'] == 'in_progress') {
                                            $status_class = 'status-in-progress';
                                        } elseif ($task['status'] == 'completed') {
                                            $status_class = 'status-completed';
                                        }
                                        
                                        // Check if overdue
                                        if ($task['status'] != 'completed' && strtotime($task['due_date']) < time()) {
                                            $status_class = 'status-overdue';
                                            $status_text = 'Overdue';
                                        } else {
                                            $status_text = ucfirst($task['status']);
                                        }
                                        ?>
                                        <span class="task-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="task-item">No tasks assigned yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Orders</h3>
                    <a href="view_order.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <ul class="order-list">
                        <?php if (mysqli_num_rows($recent_orders_result) > 0): ?>
                            <?php while ($order = mysqli_fetch_assoc($recent_orders_result)): ?>
                                <li class="order-item">
                                    <span class="order-title">Order #<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    <div class="order-meta">
                                        <span><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                        <?php
                                        $status_class = '';
                                        if ($order['status'] == 'processing') {
                                            $status_class = 'status-processing';
                                        } elseif ($order['status'] == 'shipped') {
                                            $status_class = 'status-shipped';
                                        } elseif ($order['status'] == 'delivered') {
                                            $status_class = 'status-delivered';
                                        } elseif ($order['status'] == 'cancelled') {
                                            $status_class = 'status-cancelled';
                                        }
                                        ?>
                                        <span class="order-status <?php echo $status_class; ?>"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="order-item">No recent orders.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Your Performance</h3>
                <a href="my_performance.php" class="view-all">View Detailed Stats</a>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f39c12;">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-data">
                            <p class="stat-value"><?php echo $performance_data['orders_processed'] ?: 0; ?></p>
                            <p class="stat-label">Orders Processed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #27ae60;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-data">
                            <p class="stat-value"><?php echo $performance_data['tasks_completed'] ?: 0; ?></p>
                            <p class="stat-label">Tasks Completed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #3498db;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-data">
                            <p class="stat-value"><?php echo floor($performance_data['avg_task_completion_time'] ?: 0); ?></p>
                            <p class="stat-label">Avg. Completion Time (days)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // You can add JavaScript functionality here
        });
    </script>
</body>
</html> 