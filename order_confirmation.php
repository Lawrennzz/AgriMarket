<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

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

// Fetch order items
$items_query = "SELECT oi.*, p.name, p.image_url 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?";
$items_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($items_stmt, "i", $order_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
}

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);

// Get payment information
$payment_query = "SELECT * FROM payment_logs 
                 WHERE order_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 1";
$payment_stmt = mysqli_prepare($conn, $payment_query);
mysqli_stmt_bind_param($payment_stmt, "i", $order_id);
mysqli_stmt_execute($payment_stmt);
$payment_result = mysqli_stmt_get_result($payment_stmt);
$payment_info = mysqli_fetch_assoc($payment_result);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .success-icon {
            color: #4CAF50;
            font-size: 48px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .thank-you {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .thank-you h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .thank-you p {
            color: #666;
        }
        
        .order-details {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        
        .product-list {
            margin-top: 1rem;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
        }
        
        .product-info {
            flex-grow: 1;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .product-price {
            color: #666;
        }
        
        .order-summary {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .shipping-info {
            margin-top: 2rem;
        }
        
        .shipping-info h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="thank-you">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Thank you for your order!</h1>
            <p>Your order #<?php echo $order_id; ?> has been placed successfully. A confirmation email has been sent to your registered email address.</p>
        </div>
        
        <div class="order-details">
            <h2>Order Details</h2>
            
            <div class="detail-row">
                <span class="detail-label">Order Date</span>
                <span><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Status</span>
                <span><?php echo ucfirst($order['status']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                <span><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
            </div>
            
            <div class="product-list">
                <?php foreach ($order_items as $item): ?>
                <div class="product-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="product-image">
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="product-price">
                            <span>$<?php echo number_format($item['price'], 2); ?></span>
                            <span>Quantity: <?php echo $item['quantity']; ?></span>
                        </div>
                    </div>
                    <div class="product-total">
                        $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-summary">
                <div class="summary-row">
                    <span>Subtotal (<?php echo count($order_items); ?> items)</span>
                    <span>$<?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>$<?php echo number_format($order['shipping'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (5%)</span>
                    <span>$<?php echo number_format($order['tax'], 2); ?></span>
                </div>
                <div class="summary-row" style="font-weight: bold; margin-top: 0.5rem;">
                    <span>Total</span>
                    <span>$<?php echo number_format($order['total'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="button-group">
            <a href="view_payment_details.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                <i class="fas fa-credit-card"></i> View Payment Details
            </a>
            <a href="my_orders.php" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i> View My Orders
            </a>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-shopping-cart"></i> Continue Shopping
            </a>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 