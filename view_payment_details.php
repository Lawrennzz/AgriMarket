<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error_message'] = "Invalid order ID provided.";
    header('Location: my_orders.php');
    exit();
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$order_query = "SELECT * FROM orders WHERE order_id = ? AND user_id = ?";
$order_stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    $_SESSION['error_message'] = "Order not found or access denied.";
    header('Location: my_orders.php');
    exit();
}

// Get payment information
$payment_query = "SELECT * FROM payment_logs 
                 WHERE order_id = ? 
                 ORDER BY created_at DESC";
$payment_stmt = mysqli_prepare($conn, $payment_query);
mysqli_stmt_bind_param($payment_stmt, "i", $order_id);
mysqli_stmt_execute($payment_stmt);
$payment_result = mysqli_stmt_get_result($payment_stmt);
$payment_history = [];
while ($payment = mysqli_fetch_assoc($payment_result)) {
    $payment_history[] = $payment;
}

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Details - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .payment-details {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .payment-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .payment-title {
            font-size: 1.25rem;
            margin: 0;
            color: #333;
        }
        
        .order-info {
            color: #666;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .payment-content {
            padding: 1.5rem;
        }
        
        .payment-summary {
            margin-bottom: 2rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        
        .summary-label {
            color: #666;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            float: right;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .billing-info {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .payment-history {
            margin-top: 2rem;
        }
        
        .history-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-date {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .history-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .payment-icon {
            color: #4CAF50;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .print-receipt {
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="payment-details">
            <div class="payment-header">
                <h2 class="payment-title">Payment Details</h2>
                <div class="order-info">Order #<?php echo $order_id; ?> placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
            </div>
            
            <div class="payment-content">
                <div class="payment-summary">
                    <div class="summary-row">
                        <span class="summary-label">Payment Method</span>
                        <span><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Transaction ID</span>
                        <span><?php echo htmlspecialchars($order['transaction_id'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Payment Date</span>
                        <span><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Order Total</span>
                        <span>$<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Billing Name</span>
                        <span><?php echo htmlspecialchars($shipping_address['full_name']); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Billing Address</span>
                        <span><?php echo htmlspecialchars($shipping_address['address'] . ', ' . 
                                    $shipping_address['city'] . ', ' . 
                                    $shipping_address['state'] . ' ' . 
                                    $shipping_address['zip']); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($payment_history)): ?>
                <div class="payment-history">
                    <h3 class="section-title">Payment History</h3>
                    <?php foreach ($payment_history as $payment): ?>
                    <div class="history-item">
                        <div class="history-date">
                            <?php echo date('F j, Y, g:i a', strtotime($payment['created_at'])); ?>
                        </div>
                        <div class="history-details">
                            <div class="payment-method">
                                <i class="fas fa-money-bill payment-icon"></i>
                                <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                            </div>
                            <div class="payment-amount">
                                $<?php echo number_format($payment['amount'], 2); ?>
                                <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="button-group">
            <a href="print_receipt.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="order_confirmation.php?order_id=<?php echo $order_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Order Details
            </a>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 