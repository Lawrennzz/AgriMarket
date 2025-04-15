<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$order_id = intval($_GET['id']);

// Get order details
if ($user_type === 'vendor') {
    $order_query = mysqli_query($conn, "
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = $order_id
        AND EXISTS (
            SELECT 1 FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = o.order_id AND p.vendor_id = $user_id
        )
    ");
} else {
    $order_query = mysqli_query($conn, "
        SELECT o.* FROM orders o
        WHERE o.order_id = $order_id AND o.user_id = $user_id
    ");
}

if (!$order = mysqli_fetch_assoc($order_query)) {
    header("Location: orders.php");
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

// Handle status update for vendors
if ($user_type === 'vendor' && isset($_POST['status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    mysqli_query($conn, "
        UPDATE orders 
        SET status = '$new_status', 
            updated_at = NOW() 
        WHERE order_id = $order_id
    ");
    
    // Redirect to refresh page
    header("Location: order_details.php?id=$order_id");
    exit();
}

// Get order timeline
$timeline_query = mysqli_query($conn, "
    SELECT * FROM order_status_history 
    WHERE order_id = $order_id 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .details-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .order-info {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .order-sidebar {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .order-id {
            font-size: 1.2rem;
            color: var(--medium-gray);
        }

        .order-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .section-title {
            font-size: 1.2rem;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }

        .customer-info {
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: var(--border-radius);
        }

        .info-row {
            display: flex;
            margin-bottom: 0.5rem;
        }

        .info-label {
            width: 120px;
            color: var(--medium-gray);
            font-weight: 500;
        }

        .info-value {
            color: var(--dark-gray);
        }

        .order-items {
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
        }

        .timeline {
            margin-bottom: 2rem;
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
            border-left: 2px solid var(--light-gray);
        }

        .timeline-item:last-child {
            border-left: none;
        }

        .timeline-dot {
            position: absolute;
            left: -8px;
            width: 14px;
            height: 14px;
            background: var(--primary-color);
            border-radius: 50%;
        }

        .timeline-content {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-left: 1rem;
        }

        .timeline-date {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .order-summary {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--light-gray);
        }

        .status-form {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }

        .status-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .btn-update {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-update:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 992px) {
            .details-container {
                grid-template-columns: 1fr;
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="details-container">
            <div class="order-info">
                <div class="order-header">
                    <div>
                        <div class="order-id">Order #<?php echo str_pad($order_id, 8, '0', STR_PAD_LEFT); ?></div>
                        <div>Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                    </div>
                    <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>

                <?php if ($user_type === 'vendor'): ?>
                    <div class="customer-info">
                        <h2 class="section-title">Customer Information</h2>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-items">
                    <h2 class="section-title">Order Items</h2>
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

                <div class="timeline">
                    <h2 class="section-title">Order Timeline</h2>
                    <?php while ($status = mysqli_fetch_assoc($timeline_query)): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-status">
                                    Order <?php echo ucfirst($status['status']); ?>
                                </div>
                                <div class="timeline-date">
                                    <?php echo date('F j, Y g:i A', strtotime($status['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="order-sidebar">
                <h2 class="section-title">Order Summary</h2>
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($order['total_amount'] - ($order['total_amount'] * 0.1) - 10, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>$10.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (10%)</span>
                        <span>$<?php echo number_format($order['total_amount'] * 0.1, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <?php if ($user_type === 'vendor' && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                    <form method="POST" class="status-form">
                        <h2 class="section-title">Update Status</h2>
                        <select name="status" class="status-select">
                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn-update">Update Status</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 