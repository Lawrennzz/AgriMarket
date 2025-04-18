<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$orders_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $orders_per_page;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$payment_status = isset($_GET['payment_status']) ? mysqli_real_escape_string($conn, $_GET['payment_status']) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'newest';

// Base query
$query = "
    SELECT o.*, u.name as customer_name, 
           COUNT(oi.item_id) as item_count,
           (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.order_id) as total_items
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE 1=1
";

// Apply filters
if (!empty($search)) {
    $query .= " AND (o.order_id LIKE '%$search%' OR u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

if (!empty($status)) {
    $query .= " AND o.status = '$status'";
}

if (!empty($payment_status)) {
    $query .= " AND o.payment_status = '$payment_status'";
}

if (!empty($date_from)) {
    $query .= " AND o.created_at >= '$date_from 00:00:00'";
}

if (!empty($date_to)) {
    $query .= " AND o.created_at <= '$date_to 23:59:59'";
}

// Group by order ID
$query .= " GROUP BY o.order_id";

// Apply sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY o.created_at ASC";
        break;
    case 'highest':
        $query .= " ORDER BY o.total DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY o.total ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY o.created_at DESC";
        break;
}

// Count total orders for pagination
$count_query = "SELECT COUNT(DISTINCT o.order_id) as total FROM orders o 
                LEFT JOIN users u ON o.user_id = u.user_id 
                WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (o.order_id LIKE '%$search%' OR u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

if (!empty($status)) {
    $count_query .= " AND o.status = '$status'";
}

if (!empty($payment_status)) {
    $count_query .= " AND o.payment_status = '$payment_status'";
}

if (!empty($date_from)) {
    $count_query .= " AND o.created_at >= '$date_from 00:00:00'";
}

if (!empty($date_to)) {
    $count_query .= " AND o.created_at <= '$date_to 23:59:59'";
}

$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_orders = $count_data['total'];
$total_pages = ceil($total_orders / $orders_per_page);

// Add pagination to the main query
$query .= " LIMIT $offset, $orders_per_page";

// Execute the query
$orders_result = mysqli_query($conn, $query);

$page_title = "View Orders";
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
        
        .filter-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn {
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #007bff;
            border: 1px solid #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        
        .btn-outline-secondary {
            background-color: transparent;
            border: 1px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
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
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-unpaid {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-refunded {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            color: #007bff;
            background-color: white;
            border: 1px solid #dee2e6;
            transition: background-color 0.2s;
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        
        .pagination .active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #495057;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .empty-state p {
            color: #6c757d;
            margin: 0;
            max-width: 500px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">View Orders</h1>
        </div>
        
        <div class="filter-container">
            <form class="filter-form" method="GET" action="view_orders.php">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Order ID, Customer" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Order Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_status">Payment Status</label>
                    <select id="payment_status" name="payment_status" class="form-control">
                        <option value="">All Payments</option>
                        <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="unpaid" <?php echo $payment_status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="refunded" <?php echo $payment_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort" class="form-control">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>>Highest Total</option>
                        <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>>Lowest Total</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="view_orders.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        
        <div class="orders-container">
            <?php if (mysqli_num_rows($orders_result) > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo $order['total_items'] ?: 0; ?></td>
                                <td>$<?php echo number_format($order['total'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
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
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <a href="process_orders.php?action=process&id=<?php echo $order['order_id']; ?>" class="btn-action btn-process">
                                                <i class="fas fa-box"></i> Process
                                            </a>
                                        <?php elseif ($order['status'] === 'processing'): ?>
                                            <a href="process_orders.php?action=ship&id=<?php echo $order['order_id']; ?>" class="btn-action btn-ship">
                                                <i class="fas fa-shipping-fast"></i> Ship
                                            </a>
                                        <?php elseif ($order['status'] === 'shipped'): ?>
                                            <a href="process_orders.php?action=deliver&id=<?php echo $order['order_id']; ?>" class="btn-action btn-deliver">
                                                <i class="fas fa-check"></i> Deliver
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status) . '&payment_status=' . urlencode($payment_status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort=' . urlencode($sort) . '">1</a>';
                            if ($start_page > 2) {
                                echo '<span>...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&payment_status=' . urlencode($payment_status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort=' . urlencode($sort) . '">' . $i . '</a>';
                            }
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span>...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&payment_status=' . urlencode($payment_status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort=' . urlencode($sort) . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Found</h3>
                    <p>
                        <?php
                        if (!empty($search) || !empty($status) || !empty($payment_status) || !empty($date_from) || !empty($date_to)) {
                            echo "No orders match your filter criteria. Try adjusting your filters or <a href='view_orders.php'>view all orders</a>.";
                        } else {
                            echo "There are no orders in the system yet.";
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Script to ensure that date_to is not earlier than date_from
        document.getElementById('date_from').addEventListener('change', function() {
            var dateFrom = this.value;
            var dateTo = document.getElementById('date_to');
            
            if (dateFrom && dateTo.value && dateFrom > dateTo.value) {
                dateTo.value = dateFrom;
            }
        });
        
        document.getElementById('date_to').addEventListener('change', function() {
            var dateTo = this.value;
            var dateFrom = document.getElementById('date_from');
            
            if (dateTo && dateFrom.value && dateTo < dateFrom.value) {
                dateFrom.value = dateTo;
            }
        });
    </script>
</body>
</html> 