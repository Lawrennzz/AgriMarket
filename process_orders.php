<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];

// Initialize messages
$success_message = '';
$error_message = '';

// Process order status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    $new_status = '';
    
    switch ($action) {
        case 'process':
            $new_status = 'processing';
            break;
        case 'ship':
            $new_status = 'shipped';
            break;
        case 'deliver':
            $new_status = 'delivered';
            break;
        case 'cancel':
            $new_status = 'cancelled';
            break;
    }
    
    if (!empty($new_status)) {
        // Update order status
        $update_query = "UPDATE orders SET status = ?, processed_by = ? WHERE order_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if ($update_stmt === false) {
            $error_message = "Failed to prepare update statement: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $staff_id, $order_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Insert into order status history
                $history_query = "INSERT INTO order_status_history (order_id, status, changed_at) VALUES (?, ?, NOW())";
                $history_stmt = mysqli_prepare($conn, $history_query);
                
                if ($history_stmt !== false) {
                    mysqli_stmt_bind_param($history_stmt, "is", $order_id, $new_status);
                    mysqli_stmt_execute($history_stmt);
                }
                
                // Log the action
                $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at) VALUES (?, ?, 'orders', ?, ?, NOW())";
                $log_details = "Updated order status to " . ucfirst($new_status) . " by staff #" . $staff_id;
                $log_action = "update_order_status";
                $log_stmt = mysqli_prepare($conn, $log_query);
                
                if ($log_stmt !== false) {
                    mysqli_stmt_bind_param($log_stmt, "isis", $staff_id, $log_action, $order_id, $log_details);
                    mysqli_stmt_execute($log_stmt);
                }
                
                // Send notification to customer if enabled
                $order_query = "SELECT o.user_id, u.email, u.name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?";
                $order_stmt = mysqli_prepare($conn, $order_query);
                
                if ($order_stmt !== false) {
                    mysqli_stmt_bind_param($order_stmt, "i", $order_id);
                    mysqli_stmt_execute($order_stmt);
                    $order_result = mysqli_stmt_get_result($order_stmt);
                    $order_data = mysqli_fetch_assoc($order_result);
                    
                    if ($order_data) {
                        // Insert notification for the customer
                        $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'order', NOW())";
                        $notification_message = "Your order #$order_id has been " . ($new_status == 'cancelled' ? 'cancelled' : 'updated to ' . $new_status);
                        $notification_stmt = mysqli_prepare($conn, $notification_query);
                        
                        if ($notification_stmt !== false) {
                            mysqli_stmt_bind_param($notification_stmt, "is", $order_data['user_id'], $notification_message);
                            mysqli_stmt_execute($notification_stmt);
                        }
                        
                        // Try to send email notification if Mailer class is available
                        if (file_exists('includes/Mailer.php')) {
                            require_once 'includes/Mailer.php';
                            
                            try {
                                $mailer = new Mailer();
                                $mailer->sendOrderStatusUpdate($order_id, $order_data['email'], $order_data['name'], $new_status, $order_data);
                            } catch (Exception $e) {
                                // Silently log email sending failures, but don't stop the process
                                error_log("Failed to send order status email: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                $success_message = "Order #$order_id has been updated to " . ucfirst($new_status);
            } else {
                $error_message = "Failed to update order status: " . mysqli_error($conn);
            }
        }
    }
    
    // Redirect back to the processing page to avoid resubmission on refresh
    if (empty($error_message)) {
        header("Location: process_orders.php?status=success&message=" . urlencode($success_message));
        exit();
    }
}

// Get success message from redirect
if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['message'])) {
    $success_message = $_GET['message'];
}

// Get pending orders
$pending_orders_query = "
    SELECT o.*, u.name as customer_name, 
           COUNT(oi.item_id) as item_count,
           (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.order_id) as total_items
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status = 'pending'
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
";
$pending_orders_result = mysqli_query($conn, $pending_orders_query);

// Get processing orders
$processing_orders_query = "
    SELECT o.*, u.name as customer_name, 
           COUNT(oi.item_id) as item_count,
           (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.order_id) as total_items
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status = 'processing'
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
";
$processing_orders_result = mysqli_query($conn, $processing_orders_query);

// Get shipped orders
$shipped_orders_query = "
    SELECT o.*, u.name as customer_name, 
           COUNT(oi.item_id) as item_count,
           (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.order_id) as total_items
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status = 'shipped'
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
    LIMIT 10
";
$shipped_orders_result = mysqli_query($conn, $shipped_orders_query);

$page_title = "Process Orders";
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .orders-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title .badge {
            font-size: 14px;
            background-color: #e9ecef;
            border-radius: 50px;
            padding: 2px 10px;
            margin-left: 10px;
            color: #495057;
        }
        
        .orders-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            color: #333;
        }
        
        .orders-table td {
            padding: 12px 15px;
            border-top: 1px solid #f2f2f2;
            vertical-align: middle;
        }
        
        .orders-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-action i {
            margin-right: 5px;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
        
        .btn-process {
            background-color: #007bff;
            color: white;
        }
        
        .btn-process:hover {
            background-color: #0069d9;
        }
        
        .btn-ship {
            background-color: #28a745;
            color: white;
        }
        
        .btn-ship:hover {
            background-color: #218838;
        }
        
        .btn-deliver {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-deliver:hover {
            background-color: #138496;
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
        }
        
        .section-footer {
            padding: 15px;
            border-top: 1px solid #f2f2f2;
            text-align: center;
        }
        
        .view-all-link {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
        }
        
        .empty-state i {
            font-size: 36px;
            color: #dee2e6;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Process Orders</h1>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Pending Orders -->
        <div class="orders-section">
            <h2 class="section-title">
                Pending Orders
                <span class="badge"><?php echo mysqli_num_rows($pending_orders_result); ?></span>
            </h2>
            
            <div class="orders-container">
                <?php if (mysqli_num_rows($pending_orders_result) > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($pending_orders_result)): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['total_items'] ?: 0; ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="process_orders.php?action=process&id=<?php echo $order['order_id']; ?>" class="btn-action btn-process" onclick="return confirm('Start processing order #<?php echo $order['order_id']; ?>?');">
                                                <i class="fas fa-box"></i> Process
                                            </a>
                                            <a href="process_orders.php?action=cancel&id=<?php echo $order['order_id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel order #<?php echo $order['order_id']; ?>?');">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>There are no pending orders at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Processing Orders -->
        <div class="orders-section">
            <h2 class="section-title">
                Processing Orders
                <span class="badge"><?php echo mysqli_num_rows($processing_orders_result); ?></span>
            </h2>
            
            <div class="orders-container">
                <?php if (mysqli_num_rows($processing_orders_result) > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($processing_orders_result)): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['total_items'] ?: 0; ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="process_orders.php?action=ship&id=<?php echo $order['order_id']; ?>" class="btn-action btn-ship" onclick="return confirm('Mark order #<?php echo $order['order_id']; ?> as shipped?');">
                                                <i class="fas fa-shipping-fast"></i> Ship
                                            </a>
                                            <a href="process_orders.php?action=cancel&id=<?php echo $order['order_id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel order #<?php echo $order['order_id']; ?>?');">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>There are no orders being processed at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Shipped Orders -->
        <div class="orders-section">
            <h2 class="section-title">
                Recent Shipped Orders
                <span class="badge"><?php echo mysqli_num_rows($shipped_orders_result); ?></span>
            </h2>
            
            <div class="orders-container">
                <?php if (mysqli_num_rows($shipped_orders_result) > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($shipped_orders_result)): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['total_items'] ?: 0; ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="process_orders.php?action=deliver&id=<?php echo $order['order_id']; ?>" class="btn-action btn-deliver" onclick="return confirm('Mark order #<?php echo $order['order_id']; ?> as delivered?');">
                                                <i class="fas fa-check"></i> Deliver
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="section-footer">
                        <a href="view_order.php?status=shipped" class="view-all-link">View All Shipped Orders</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shipping-fast"></i>
                        <p>There are no shipped orders to display.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 