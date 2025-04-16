<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Location: products.php');
    exit();
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$order_query = "SELECT * FROM orders WHERE order_id = ? AND user_id = ?";
$order_stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);

if (mysqli_num_rows($order_result) === 0) {
    // Order not found or doesn't belong to the user
    header('Location: products.php');
    exit();
}

$order = mysqli_fetch_assoc($order_result);

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
$item_count = 0;
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
    $item_count += $item['quantity'];
}

// Parse shipping address (stored as JSON)
$shipping_address = json_decode($order['shipping_address'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #4caf50;
            margin-bottom: 1rem;
        }
        
        .confirmation-title {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .confirmation-message {
            color: var(--medium-gray);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .confirmation-box {
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
        }
        
        .box-title {
            font-size: 1.25rem;
            margin: 0;
            color: #333;
        }
        
        .box-content {
            padding: 1.5rem;
        }
        
        .order-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }
        
        .meta-item {
            flex: 1;
            min-width: 200px;
        }
        
        .meta-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .meta-value {
            color: var(--dark-gray);
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .order-items-table th {
            text-align: left;
            padding: 1rem;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .order-items-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .order-items-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-cell {
            display: flex;
            align-items: center;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            object-fit: cover;
            margin-right: 1rem;
        }
        
        .product-name {
            font-weight: 500;
        }
        
        .price {
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .order-summary {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .summary-label {
            color: var(--medium-gray);
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .total-row {
            font-size: 1.2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .address-info {
            margin-bottom: 0.5rem;
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
        }
        
        .btn-secondary:hover {
            background: #f0f7ff;
        }
        
        @media (max-width: 768px) {
            .meta-item {
                flex: 100%;
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
    
    <div class="confirmation-container">
        <div class="confirmation-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="confirmation-title">Thank you for your order!</h1>
            <p class="confirmation-message">
                Your order <span class="order-id">#<?php echo $order_id; ?></span> has been placed successfully.
                A confirmation email has been sent to your registered email address.
            </p>
        </div>
        
        <div class="confirmation-box">
            <div class="box-header">
                <h3 class="box-title">Order Details</h3>
            </div>
            <div class="box-content">
                <div class="order-meta">
                    <div class="meta-item">
                        <div class="meta-label">Order Date</div>
                        <div class="meta-value">
                            <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Order Status</div>
                        <div class="meta-value">
                            <?php
                            $status_class = '';
                            switch($order['status']) {
                                case 'pending':
                                    $status_class = 'text-warning';
                                    break;
                                case 'processing':
                                    $status_class = 'text-info';
                                    break;
                                case 'shipped':
                                    $status_class = 'text-primary';
                                    break;
                                case 'delivered':
                                    $status_class = 'text-success';
                                    break;
                                case 'cancelled':
                                    $status_class = 'text-danger';
                                    break;
                            }
                            ?>
                            <span class="<?php echo $status_class; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Payment Method</div>
                        <div class="meta-value">
                            <?php 
                            $payment_method = $shipping_address['payment_method'] ?? 'Not specified';
                            echo ucwords(str_replace('_', ' ', $payment_method)); 
                            ?>
                        </div>
                    </div>
                </div>
                
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image">
                                    <span class="product-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                            </td>
                            <td class="price">$<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td class="price">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="order-summary">
                    <?php
                    // Calculate order subtotal
                    $subtotal = 0;
                    foreach ($order_items as $item) {
                        $subtotal += $item['price'] * $item['quantity'];
                    }
                    
                    // Estimate tax and shipping based on total
                    $shipping = 5.00; // Fixed shipping
                    $tax = $subtotal * 0.05; // 5% tax
                    ?>
                    
                    <div class="summary-row">
                        <div class="summary-label">Subtotal (<?php echo $item_count; ?> items)</div>
                        <div class="summary-value">$<?php echo number_format($subtotal, 2); ?></div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Shipping</div>
                        <div class="summary-value">$<?php echo number_format($shipping, 2); ?></div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Tax (5%)</div>
                        <div class="summary-value">$<?php echo number_format($tax, 2); ?></div>
                    </div>
                    <div class="summary-row total-row">
                        <div class="summary-label">Total</div>
                        <div class="summary-value">$<?php echo number_format($order['total'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-box">
            <div class="box-header">
                <h3 class="box-title">Shipping Information</h3>
            </div>
            <div class="box-content">
                <div class="address-info">
                    <strong><?php echo htmlspecialchars($shipping_address['full_name'] ?? 'N/A'); ?></strong>
                </div>
                <div class="address-info">
                    <?php echo htmlspecialchars($shipping_address['address'] ?? 'N/A'); ?>
                </div>
                <div class="address-info">
                    <?php echo htmlspecialchars(
                        ($shipping_address['city'] ?? 'N/A') . ', ' . 
                        ($shipping_address['state'] ?? '') . ' ' . 
                        ($shipping_address['zip'] ?? '')
                    ); ?>
                </div>
                <div class="address-info">
                    Phone: <?php echo htmlspecialchars($shipping_address['phone'] ?? 'N/A'); ?>
                </div>
                <div class="address-info">
                    Email: <?php echo htmlspecialchars($shipping_address['email'] ?? 'N/A'); ?>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="payment_details.php?order_id=<?php echo $order_id; ?>" class="btn-primary">
                <i class="fas fa-credit-card"></i> View Payment Details
            </a>
            <a href="products.php" class="btn-primary">
                <i class="fas fa-shopping-basket"></i> Continue Shopping
            </a>
            <a href="orders.php" class="btn-secondary">
                <i class="fas fa-list-alt"></i> View My Orders
            </a>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 