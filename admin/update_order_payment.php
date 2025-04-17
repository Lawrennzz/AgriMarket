<?php
include '../config.php';
require_once '../classes/AuditLog.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize audit logger
$auditLogger = new AuditLog();

// Initialize variables
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';
$order = null;
$payment_logs = [];

// Get order details
if ($order_id > 0) {
    $order_query = "SELECT o.*, 
                   JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method')) AS json_payment_method
                   FROM orders o WHERE o.order_id = ?";
    $stmt = mysqli_prepare($conn, $order_query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    
    // Get payment logs for this order
    $logs_query = "SELECT * FROM payment_logs WHERE order_id = ? ORDER BY created_at DESC";
    $logs_stmt = mysqli_prepare($conn, $logs_query);
    mysqli_stmt_bind_param($logs_stmt, "i", $order_id);
    mysqli_stmt_execute($logs_stmt);
    $logs_result = mysqli_stmt_get_result($logs_stmt);
    
    while ($log = mysqli_fetch_assoc($logs_result)) {
        $payment_logs[] = $log;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_from_logs'])) {
        // Update from selected payment log
        $log_id = intval($_POST['log_id']);
        
        // Get payment method from log
        $log_query = "SELECT payment_method FROM payment_logs WHERE log_id = ?";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "i", $log_id);
        mysqli_stmt_execute($log_stmt);
        $log_result = mysqli_stmt_get_result($log_stmt);
        $log_data = mysqli_fetch_assoc($log_result);
        
        if ($log_data) {
            $payment_method = $log_data['payment_method'];
            
            // Update order payment method
            $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $order_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Log the update in audit logs
                $auditLogger->log(
                    $_SESSION['user_id'],
                    'update',
                    'orders',
                    $order_id,
                    [
                        'payment_method' => [
                            'from' => $order['payment_method'] ?? 'Not specified',
                            'to' => $payment_method,
                            'source' => 'payment_logs',
                            'log_id' => $log_id
                        ]
                    ]
                );
                
                $success_message = "Payment method updated successfully to '{$payment_method}'!";
                
                // Refresh order data
                $stmt = mysqli_prepare($conn, $order_query);
                mysqli_stmt_bind_param($stmt, "i", $order_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $order = mysqli_fetch_assoc($result);
            } else {
                $error_message = "Failed to update payment method: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Payment log not found";
        }
    } elseif (isset($_POST['update_manual'])) {
        // Update with manually selected payment method
        $payment_method = $_POST['payment_method'];
        
        // Update order payment method
        $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $order_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log the update in audit logs
            $auditLogger->log(
                $_SESSION['user_id'],
                'update',
                'orders',
                $order_id,
                [
                    'payment_method' => [
                        'from' => $order['payment_method'] ?? 'Not specified',
                        'to' => $payment_method,
                        'source' => 'manual'
                    ]
                ]
            );
            
            $success_message = "Payment method updated successfully to '{$payment_method}'!";
            
            // Refresh order data
            $stmt = mysqli_prepare($conn, $order_query);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($result);
        } else {
            $error_message = "Failed to update payment method: " . mysqli_error($conn);
        }
    }
}

// Get all orders with missing payment methods
$missing_payments_query = "SELECT o.order_id, 
                         JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.full_name')) AS customer_name,
                         JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method')) AS json_payment_method,
                         o.created_at, o.total
                         FROM orders o 
                         WHERE (o.payment_method IS NULL OR o.payment_method = '')
                         ORDER BY o.created_at DESC";
$missing_stmt = mysqli_prepare($conn, $missing_payments_query);
mysqli_stmt_execute($missing_stmt);
$missing_result = mysqli_stmt_get_result($missing_stmt);
$orders_missing_payment = [];
while ($missing = mysqli_fetch_assoc($missing_result)) {
    $orders_missing_payment[] = $missing;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Order Payment Method - AgriMarket Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: var(--medium-gray);
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-section {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        
        .info-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .info-item {
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 500;
            display: inline-block;
            width: 180px;
            color: var(--medium-gray);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        
        table th, table td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .highlight {
            background-color: #fff3cd;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Update Order Payment Method</h1>
            <p class="form-subtitle">Fix missing payment methods for orders</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($order): ?>
            <div class="section">
                <h2 class="section-title">Order #<?php echo htmlspecialchars($order_id); ?> Information</h2>
                
                <div class="info-section">
                    <div class="info-item">
                        <span class="info-label">Order ID:</span>
                        <span><?php echo htmlspecialchars($order_id); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Payment Method:</span>
                        <span><?php echo !empty($order['payment_method']) ? htmlspecialchars($order['payment_method']) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method in JSON:</span>
                        <span><?php echo !empty($order['json_payment_method']) ? htmlspecialchars($order['json_payment_method']) : 'Not found'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Status:</span>
                        <span><?php echo !empty($order['payment_status']) ? htmlspecialchars($order['payment_status']) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Created At:</span>
                        <span><?php echo htmlspecialchars($order['created_at']); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($payment_logs)): ?>
                    <h3 class="section-title">Payment Logs</h3>
                    <form method="POST" action="">
                        <table>
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Log ID</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_logs as $log): ?>
                                    <tr class="<?php echo ($log['payment_method'] == $order['json_payment_method']) ? 'highlight' : ''; ?>">
                                        <td>
                                            <input type="radio" name="log_id" value="<?php echo htmlspecialchars($log['log_id']); ?>" 
                                                   <?php echo ($log['payment_method'] == $order['json_payment_method']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['payment_method']); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($log['amount'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($log['status']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($log['details'], 0, 50) . (strlen($log['details']) > 50 ? '...' : '')); ?></td>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" name="update_from_logs" class="btn btn-primary">Update from Selected Log</button>
                    </form>
                <?php else: ?>
                    <p>No payment logs found for this order.</p>
                <?php endif; ?>
                
                <h3 class="section-title">Manual Update</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="cash_on_delivery" <?php echo ($order['payment_method'] == 'cash_on_delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                            <option value="bank_transfer" <?php echo ($order['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="credit_card" <?php echo ($order['payment_method'] == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="paypal" <?php echo ($order['payment_method'] == 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                            <option value="mobile_payment" <?php echo ($order['payment_method'] == 'mobile_payment') ? 'selected' : ''; ?>>Mobile Payment</option>
                            <option value="crypto" <?php echo ($order['payment_method'] == 'crypto') ? 'selected' : ''; ?>>Cryptocurrency</option>
                        </select>
                    </div>
                    <button type="submit" name="update_manual" class="btn btn-primary">Update Payment Method</button>
                </form>
                
                <div class="button-group">
                    <a href="view_order.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">Back to Order</a>
                    <a href="manage_orders.php" class="btn btn-secondary">Back to Orders</a>
                </div>
            </div>
        <?php else: ?>
            <div class="section">
                <h2 class="section-title">Orders with Missing Payment Methods</h2>
                
                <?php if (!empty($orders_missing_payment)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>JSON Payment Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders_missing_payment as $missing_order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($missing_order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($missing_order['customer_name']); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format($missing_order['total'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($missing_order['created_at']); ?></td>
                                    <td><?php echo !empty($missing_order['json_payment_method']) ? htmlspecialchars($missing_order['json_payment_method']) : 'Not found'; ?></td>
                                    <td>
                                        <a href="update_order_payment.php?id=<?php echo $missing_order['order_id']; ?>" class="btn btn-primary">Fix</a>
                                        <a href="view_order.php?id=<?php echo $missing_order['order_id']; ?>" class="btn btn-secondary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No orders with missing payment methods found.</p>
                <?php endif; ?>
                
                <div class="button-group">
                    <a href="manage_orders.php" class="btn btn-secondary">Back to Orders</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 