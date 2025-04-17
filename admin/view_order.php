<?php
include '../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize variables
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';
$order = null;

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
    
    if (!$order) {
        $error_message = "Order not found";
    }
}

// Get order items if order exists
$order_items = [];
if ($order) {
    $items_query = "SELECT oi.*, p.name, p.image_url 
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.product_id
                  WHERE oi.order_id = ?";
    $items_stmt = mysqli_prepare($conn, $items_query);
    mysqli_stmt_bind_param($items_stmt, "i", $order_id);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);
    
    while ($item = mysqli_fetch_assoc($items_result)) {
        $order_items[] = $item;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Order - AgriMarket Admin</title>
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
        
        .section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .section-header {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 500;
            display: block;
            margin-bottom: 0.25rem;
            color: var(--medium-gray);
        }
        
        .info-value {
            color: var(--dark-gray);
        }
        
        .order-totals {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 2px solid #333;
            border-bottom: none;
            padding-top: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: white;
        }
        
        .btn-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <div class="form-header">
            <h1>Order Details</h1>
            <?php if ($order): ?>
                <p>Order #<?php echo htmlspecialchars($order_id); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($order): ?>
            <div class="section">
                <h2 class="section-header">Payment Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Payment Method:</div>
                        <div class="info-value">
                            <?php if (empty($order['payment_method'])): ?>
                                Not specified 
                                <a href="update_order_payment.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-primary" style="margin-left: 10px; padding: 2px 8px; font-size: 12px;">
                                    <i class="fas fa-edit"></i> Fix
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($order['payment_method']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Payment Status:</div>
                        <div class="info-value">
                            <?php echo !empty($order['payment_status']) ? htmlspecialchars($order['payment_status']) : 'Not specified'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-header">Order Totals</h2>
                <div class="order-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>$<?php echo htmlspecialchars(number_format($order['subtotal'] ?? 0, 2)); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Shipping:</span>
                        <span>$<?php echo htmlspecialchars(number_format($order['shipping'] ?? 0, 2)); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Tax:</span>
                        <span>$<?php echo htmlspecialchars(number_format($order['tax'] ?? 0, 2)); ?></span>
                    </div>
                    <div class="total-row final">
                        <span>Total:</span>
                        <span>$<?php echo htmlspecialchars(number_format($order['total'] ?? 0, 2)); ?></span>
                    </div>
                    
                    <?php if (isset($order['subtotal']) && isset($order['shipping']) && isset($order['tax']) && 
                            $order['subtotal'] == 0 && $order['shipping'] == 0 && $order['tax'] == 0 && $order['total'] > 0): ?>
                    <div style="margin-top: 10px; padding: 5px; background-color: #fff3cd; color: #856404; border-radius: 4px;">
                        <small><i class="fas fa-exclamation-triangle"></i> Order total details are missing. <a href="../update_existing_orders.php">Click here to fix</a></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($order_items)): ?>
            <div class="section">
                <h2 class="section-header">Order Items</h2>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">Product</th>
                            <th style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #ddd;">Price</th>
                            <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">Quantity</th>
                            <th style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #ddd;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ddd;">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #ddd;">
                                    $<?php echo htmlspecialchars(number_format($item['price'], 2)); ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">
                                    <?php echo htmlspecialchars($item['quantity']); ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #ddd;">
                                    $<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="manage_orders.php" class="btn btn-secondary">Back to Orders</a>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                Order not found or invalid ID provided.
            </div>
            <div style="margin-top: 2rem; text-align: center;">
                <a href="manage_orders.php" class="btn btn-secondary">Back to Orders</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 