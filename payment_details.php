<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$order_query = "SELECT o.* FROM orders o WHERE o.order_id = ? AND o.user_id = ?";
                
$order_stmt = mysqli_prepare($conn, $order_query);

if ($order_stmt === false) {
    // Handle SQL preparation error
    die("Error preparing order query: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);

if (mysqli_num_rows($order_result) === 0) {
    // Order not found or doesn't belong to the user
    header('Location: orders.php');
    exit();
}

$order = mysqli_fetch_assoc($order_result);

// Parse shipping address (stored as JSON)
$shipping_address = json_decode($order['shipping_address'], true);

// Get payment method from shipping address JSON
$payment_method = $shipping_address['payment_method'] ?? 'Not specified';

// Get payment logs if available
$payment_logs = [];
$payment_query = "SELECT * FROM payment_logs WHERE order_id = ? ORDER BY created_at DESC";
$payment_stmt = mysqli_prepare($conn, $payment_query);

if ($payment_stmt === false) {
    // Just skip payment logs if the table doesn't exist
    $payment_logs = [];
} else {
    mysqli_stmt_bind_param($payment_stmt, "i", $order_id);
    mysqli_stmt_execute($payment_stmt);
    $payment_result = mysqli_stmt_get_result($payment_stmt);

    while ($log = mysqli_fetch_assoc($payment_result)) {
        $payment_logs[] = $log;
    }
}

// Try to get payment date and status from payment logs if available
$payment_date = null;
$payment_status = 'pending';
$transaction_id = null;
if (!empty($payment_logs)) {
    $latest_log = $payment_logs[0]; // First log is the most recent
    $payment_date = $latest_log['created_at'];
    $payment_status = $latest_log['status'];
    // Use log_id as transaction ID if no transaction_id in order
    $transaction_id = $order['transaction_id'] ?? ('LOG-' . $latest_log['log_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Details - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .payment-header {
            margin-bottom: 2rem;
        }
        
        .payment-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .payment-subtitle {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .payment-box {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .box-header {
            background: var(--light-gray);
            padding: 1.25rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .box-title {
            font-size: 1.25rem;
            margin: 0;
            color: #333;
        }
        
        .box-content {
            padding: 1.5rem;
        }
        
        .info-row {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            flex: 0 0 30%;
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            flex: 0 0 70%;
            color: var(--dark-gray);
        }
        
        .status-pending {
            color: #ff9800;
        }
        
        .status-completed {
            color: #4caf50;
        }
        
        .status-failed {
            color: #f44336;
        }
        
        .status-refunded {
            color: #2196f3;
        }
        
        .status-processing {
            color: #9c27b0;
        }
        
        .payment-history {
            margin-top: 1rem;
        }
        
        .history-title {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .history-item {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
        }
        
        .history-date {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }
        
        .history-details {
            display: flex;
            justify-content: space-between;
        }
        
        .history-method {
            font-weight: 500;
        }
        
        .history-amount {
            font-weight: 600;
        }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            background: #f0f7ff;
        }
        
        .receipt-section {
            display: none;
            margin-top: 2rem;
        }
        
        .receipt-content {
            background: white;
            padding: 2rem;
            border: 1px solid #ddd;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .receipt-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .receipt-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .receipt-date {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .receipt-details {
            margin-bottom: 2rem;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-section, .receipt-section * {
                visibility: visible;
            }
            .receipt-section {
                display: block;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
            }
            
            .info-label, .info-value {
                flex: 100%;
            }
            
            .info-label {
                margin-bottom: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="payment-container">
        <div class="payment-header">
            <h1 class="payment-title">Payment Details</h1>
            <p class="payment-subtitle">
                Order #<?php echo $order_id; ?> placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
            </p>
        </div>
        
        <div class="payment-box">
            <div class="box-header">
                <h3 class="box-title">Payment Summary</h3>
                <span class="status-<?php echo strtolower($payment_status); ?>">
                    <?php echo ucfirst($payment_status); ?>
                </span>
            </div>
            <div class="box-content">
                <div class="info-row">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">
                        <?php echo ucwords(str_replace('_', ' ', $payment_method)); ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Transaction ID</div>
                    <div class="info-value">
                        <?php echo $transaction_id ?? 'Not available'; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Date</div>
                    <div class="info-value">
                        <?php 
                        echo isset($payment_date) ? 
                            date('F j, Y, g:i a', strtotime($payment_date)) : 
                            'Not processed yet'; 
                        ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Total</div>
                    <div class="info-value">
                        $<?php echo number_format($order['total'], 2); ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Billing Name</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($shipping_address['full_name'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Billing Address</div>
                    <div class="info-value">
                        <?php 
                        echo htmlspecialchars(
                            ($shipping_address['address'] ?? 'N/A') . ', ' .
                            ($shipping_address['city'] ?? '') . ', ' . 
                            ($shipping_address['state'] ?? '') . ' ' . 
                            ($shipping_address['zip'] ?? '')
                        ); 
                        ?>
                    </div>
                </div>
                
                <?php if (!empty($payment_logs)): ?>
                <div class="payment-history">
                    <h4 class="history-title">Payment History</h4>
                    <?php foreach($payment_logs as $log): ?>
                    <div class="history-item">
                        <div class="history-date">
                            <?php echo date('F j, Y, g:i a', strtotime($log['created_at'])); ?>
                        </div>
                        <div class="history-details">
                            <div class="history-method">
                                <?php echo ucwords(str_replace('_', ' ', $log['payment_method'])); ?>
                            </div>
                            <div class="history-amount">
                                $<?php echo number_format($log['amount'], 2); ?>
                            </div>
                        </div>
                        <div class="status-<?php echo strtolower($log['status']); ?>">
                            Status: <?php echo ucfirst($log['status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <button id="print-receipt-btn" class="btn-primary">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="order_confirmation.php?order_id=<?php echo $order_id; ?>" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Order Details
            </a>
            <a href="orders.php" class="btn-secondary">
                <i class="fas fa-list-alt"></i> View All Orders
            </a>
        </div>
        
        <!-- Receipt Section (Hidden by default, shown when printing) -->
        <div id="receipt-section" class="receipt-section">
            <div class="receipt-content">
                <div class="receipt-header">
                    <div class="receipt-logo">AgriMarket</div>
                    <h2 class="receipt-title">Payment Receipt</h2>
                    <div class="receipt-date">
                        <?php echo date('F j, Y'); ?>
                    </div>
                </div>
                
                <div class="receipt-details">
                    <div class="receipt-info">
                        <strong>Order Number:</strong>
                        <span><?php echo $order_id; ?></span>
                    </div>
                    <div class="receipt-info">
                        <strong>Payment Date:</strong>
                        <span>
                            <?php 
                            echo isset($payment_date) ? 
                                date('F j, Y, g:i a', strtotime($payment_date)) : 
                                'Not processed yet'; 
                            ?>
                        </span>
                    </div>
                    <div class="receipt-info">
                        <strong>Payment Method:</strong>
                        <span>
                            <?php echo ucwords(str_replace('_', ' ', $payment_method)); ?>
                        </span>
                    </div>
                    <div class="receipt-info">
                        <strong>Transaction ID:</strong>
                        <span><?php echo $transaction_id ?? 'Not available'; ?></span>
                    </div>
                    <div class="receipt-info">
                        <strong>Customer:</strong>
                        <span><?php echo htmlspecialchars($shipping_address['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="receipt-info">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($shipping_address['email'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <div class="receipt-details">
                    <div class="receipt-info">
                        <strong>Subtotal:</strong>
                        <span>$<?php echo number_format($order['total'] * 0.95 - 5, 2); ?></span>
                    </div>
                    <div class="receipt-info">
                        <strong>Shipping:</strong>
                        <span>$5.00</span>
                    </div>
                    <div class="receipt-info">
                        <strong>Tax (5%):</strong>
                        <span>$<?php echo number_format($order['total'] * 0.05, 2); ?></span>
                    </div>
                    <div class="receipt-info" style="font-weight: bold; margin-top: 10px;">
                        <strong>Total:</strong>
                        <span>$<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for shopping with AgriMarket!</p>
                    <p>This receipt was generated on <?php echo date('F j, Y, g:i a'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        document.getElementById('print-receipt-btn').addEventListener('click', function() {
            window.print();
        });
    </script>
</body>
</html> 