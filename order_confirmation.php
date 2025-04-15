<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id']);

// Get order details
$order_query = mysqli_query($conn, "
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.user_id 
    WHERE o.order_id = $order_id AND o.user_id = $user_id
");

if (!$order = mysqli_fetch_assoc($order_query)) {
    header("Location: dashboard.php");
    exit();
}

// Get order items
$items_query = mysqli_query($conn, "
    SELECT oi.*, p.name, p.image_url, u.name as vendor_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN users u ON p.vendor_id = u.user_id 
    WHERE oi.order_id = $order_id
");
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
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .success-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .success-icon i {
            font-size: 2.5rem;
        }

        .success-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .success-subtitle {
            color: var(--medium-gray);
        }

        .order-details {
            margin-bottom: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .detail-label {
            color: var(--medium-gray);
            font-weight: 500;
        }

        .detail-value {
            color: var(--dark-gray);
            font-weight: 500;
        }

        .items-list {
            margin-bottom: 2rem;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .item-vendor {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .item-price {
            text-align: right;
            font-weight: 500;
        }

        .next-steps {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .next-steps-title {
            font-size: 1.2rem;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }

        .steps-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .step-item i {
            color: var(--primary-color);
            margin-top: 0.25rem;
        }

        .step-text {
            flex: 1;
            color: var(--medium-gray);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .btn-track {
            background: var(--primary-color);
        }

        .btn-continue {
            background: var(--dark-gray);
        }

        @media (max-width: 768px) {
            .confirmation-container {
                margin: 1rem;
                padding: 1rem;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="confirmation-container">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="success-title">Order Confirmed!</h1>
                <p class="success-subtitle">Thank you for your purchase. Your order has been received.</p>
            </div>

            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Order Number</span>
                    <span class="detail-value">#<?php echo str_pad($order_id, 8, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Date</span>
                    <span class="detail-value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Shipping Address</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount</span>
                    <span class="detail-value">$<?php echo number_format($order['total'], 2); ?></span>
                </div>
            </div>

            <h2 class="section-title">Order Items</h2>
            <div class="items-list">
                <?php while ($item = mysqli_fetch_assoc($items_query)): ?>
                    <div class="order-item">
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="item-image">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-vendor">Sold by <?php echo htmlspecialchars($item['vendor_name']); ?></div>
                            <div>Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                        <div class="item-price">
                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="next-steps">
                <h3 class="next-steps-title">What's Next?</h3>
                <ul class="steps-list">
                    <li class="step-item">
                        <i class="fas fa-envelope"></i>
                        <span class="step-text">
                            You will receive an order confirmation email with order details and tracking information.
                        </span>
                    </li>
                    <li class="step-item">
                        <i class="fas fa-box"></i>
                        <span class="step-text">
                            Vendors will process your order and prepare it for shipping within 1-2 business days.
                        </span>
                    </li>
                    <li class="step-item">
                        <i class="fas fa-truck"></i>
                        <span class="step-text">
                            Once shipped, you can track your order status in your account dashboard.
                        </span>
                    </li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="orders.php" class="btn btn-track">
                    Track Order
                </a>
                <a href="products.php" class="btn btn-continue">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 