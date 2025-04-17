<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Validate order exists and belongs to user
if ($order_id <= 0) {
    $error_message = "Invalid order ID.";
} else {
    $check_query = "SELECT o.* FROM orders o WHERE o.order_id = ? AND o.user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    
    if (!$check_stmt) {
        $error_message = "Database error: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) === 0) {
            $error_message = "Order not found or you don't have permission to view it.";
        } else {
            $order = mysqli_fetch_assoc($result);
            
            // Get order items
            $items_query = "
                SELECT oi.*, p.name, p.image_url, v.vendor_id, u.name AS vendor_name 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                JOIN vendors v ON p.vendor_id = v.vendor_id
                JOIN users u ON v.user_id = u.user_id
                WHERE oi.order_id = ?";
                
            $items_stmt = mysqli_prepare($conn, $items_query);
            
            if (!$items_stmt) {
                $error_message = "Database error: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                mysqli_stmt_execute($items_stmt);
                $items_result = mysqli_stmt_get_result($items_stmt);
                
                // Get payment records
                $payment_query = "
                    SELECT pl.* 
                    FROM payment_logs pl
                    WHERE pl.order_id = ?
                    ORDER BY pl.created_at DESC";
                    
                $payment_stmt = mysqli_prepare($conn, $payment_query);
                
                if (!$payment_stmt) {
                    $error_message = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($payment_stmt, "i", $order_id);
                    mysqli_stmt_execute($payment_stmt);
                    $payment_result = mysqli_stmt_get_result($payment_stmt);
                }
            }
        }
    }
}

// Handle payment verification or update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment']) && !empty($order)) {
    require_once 'payment_processor.php';
    
    if (!empty($order['transaction_id'])) {
        $payment_status = verify_payment_status($order['transaction_id']);
        
        if ($payment_status['status'] !== $order['payment_status']) {
            // Update payment status
            update_payment_status($order_id, $order['transaction_id'], $payment_status['status']);
            $success_message = "Payment status updated to: " . ucfirst($payment_status['status']);
            
            // Refresh order data
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $order = mysqli_fetch_assoc($result);
        } else {
            $success_message = "Payment status is current: " . ucfirst($payment_status['status']);
        }
    } else {
        $error_message = "No transaction ID found for this order.";
    }
}

// Parse shipping address JSON
$shipping_address = isset($order['shipping_address']) ? json_decode($order['shipping_address'], true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Payment Details - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .payment-details {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        
        .payment-status {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-processing {
            background-color: #b8daff;
            color: #004085;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-refunded {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 1px solid #f5f5f5;
            padding-bottom: 1rem;
        }
        
        .detail-label {
            font-weight: bold;
            width: 200px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .payment-actions {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .payment-instructions {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }
        
        .payment-logs {
            margin-top: 2rem;
        }
        
        .payment-logs h3 {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .payment-log-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .payment-log-item .log-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .btn-verify {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-verify:hover {
            background: #0069d9;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="payment-container">
        <h1>Order Payment Details</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div>
                <a href="orders.php" class="btn">Return to Orders</a>
            </div>
        <?php elseif (!empty($order)): ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <div class="payment-details">
                <div class="order-header">
                    <div>
                        <h2>Order #<?php echo htmlspecialchars($order['order_id']); ?></h2>
                        <p>Placed on <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($order['created_at']))); ?></p>
                    </div>
                    <div class="payment-status status-<?php echo htmlspecialchars(strtolower($order['payment_status'] ?? 'pending')); ?>">
                        <?php echo htmlspecialchars(ucfirst($order['payment_status'] ?? 'pending')); ?>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Order Total:</div>
                    <div class="detail-value">$<?php echo htmlspecialchars(number_format($order['total'], 2)); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Payment Method:</div>
                    <div class="detail-value">
                        <?php 
                        if (!empty($order['payment_method'])) {
                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'])));
                        } elseif (!empty($shipping_address['payment_method'])) {
                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $shipping_address['payment_method'])));
                        } else {
                            echo 'Not specified';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Transaction ID:</div>
                    <div class="detail-value">
                        <?php echo !empty($order['transaction_id']) ? htmlspecialchars($order['transaction_id']) : 'Not available'; ?>
                    </div>
                </div>
                
                <?php if (!empty($order['payment_method'])): ?>
                    <?php if ($order['payment_method'] === 'bank_transfer'): ?>
                        <div class="payment-instructions">
                            <h3>Bank Transfer Instructions</h3>
                            <p>Please transfer the exact amount of $<?php echo htmlspecialchars(number_format($order['total'], 2)); ?> to:</p>
                            <p><strong>Account Name:</strong> AgriMarket Inc.</p>
                            <p><strong>Account Number:</strong> 1234567890</p>
                            <p><strong>Bank Name:</strong> Agricultural Bank</p>
                            <p><strong>Reference:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?></p>
                            <p class="note">Please include the reference number in your transfer to help us identify your payment.</p>
                        </div>
                    <?php elseif ($order['payment_method'] === 'cash_on_delivery'): ?>
                        <div class="payment-instructions">
                            <h3>Cash on Delivery</h3>
                            <p>Please prepare the exact amount of $<?php echo htmlspecialchars(number_format($order['total'], 2)); ?> to be paid upon delivery.</p>
                            <p class="note">Our delivery personnel will provide a receipt for your payment.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="payment-actions">
                    <form method="post">
                        <button type="submit" name="verify_payment" class="btn-verify">
                            <i class="fas fa-sync-alt"></i> Verify Payment Status
                        </button>
                    </form>
                </div>
                
                <?php if (mysqli_num_rows($payment_result) > 0): ?>
                    <div class="payment-logs">
                        <h3>Payment History</h3>
                        <?php while ($log = mysqli_fetch_assoc($payment_result)): ?>
                            <div class="payment-log-item">
                                <div class="log-date"><?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($log['created_at']))); ?></div>
                                <div><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($log['status'])); ?></div>
                                <div><strong>Amount:</strong> $<?php echo htmlspecialchars(number_format($log['amount'], 2)); ?></div>
                                <?php if (!empty($log['details'])): ?>
                                    <div><strong>Details:</strong> <?php echo htmlspecialchars($log['details']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <a href="order_details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn">View Complete Order Details</a>
                <a href="orders.php" class="btn">Return to Orders</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 