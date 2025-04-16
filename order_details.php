<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'customer'; // Default to customer if role is unset
$order_id = intval($_GET['id']);

// Get order details
$order = null;
try {
    if ($role === 'vendor') {
        // Fetch vendor_id from vendors table
        $vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ?";
        $vendor_stmt = mysqli_prepare($conn, $vendor_query);
        if (!$vendor_stmt) {
            throw new Exception("Vendor query prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
        mysqli_stmt_execute($vendor_stmt);
        $vendor_result = mysqli_stmt_get_result($vendor_stmt);
        if (!$vendor_row = mysqli_fetch_assoc($vendor_result)) {
            $_SESSION['error_message'] = "Vendor profile not found.";
            header("Location: orders.php");
            exit();
        }
        $vendor_id = $vendor_row['vendor_id'];
        mysqli_stmt_close($vendor_stmt);

        // Vendor order query
        $order_query = "
            SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone_number as customer_phone
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            WHERE o.order_id = ? 
            AND EXISTS (
                SELECT 1 FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = o.order_id AND p.vendor_id = ?
            )";
        $stmt = mysqli_prepare($conn, $order_query);
        if (!$stmt) {
            throw new Exception("Order query prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $vendor_id);
    } else {
        // Customer order query
        $order_query = "
            SELECT o.* FROM orders o
            WHERE o.order_id = ? AND o.user_id = ?";
        $stmt = mysqli_prepare($conn, $order_query);
        if (!$stmt) {
            throw new Exception("Order query prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$order) {
        $_SESSION['error_message'] = "Order not found or you lack permission to view it.";
        header("Location: orders.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Order details error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to load order details. Please try again.";
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "
    SELECT oi.*, p.name, p.image_url, u.name as vendor_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN vendors v ON p.vendor_id = v.vendor_id 
    JOIN users u ON v.user_id = u.user_id 
    WHERE oi.order_id = ?";
$stmt = mysqli_prepare($conn, $items_query);
if (!$stmt) {
    error_log("Items query prepare failed: " . mysqli_error($conn));
    $_SESSION['error_message'] = "Failed to load order items.";
    header("Location: orders.php");
    exit();
}
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Handle status update for vendors
if ($role === 'vendor' && isset($_POST['status'])) {
    $new_status = $_POST['status'];
    if (!in_array($new_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $_SESSION['error_message'] = "Invalid status selected.";
        header("Location: order_details.php?id=$order_id");
        exit();
    }

    // Start transaction
    mysqli_begin_transaction($conn);
    try {
        // Update order status
        $update_query = "UPDATE orders SET status = ? WHERE order_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        if (!$update_stmt) {
            throw new Exception("Update query prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $order_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Failed to update order status: " . mysqli_error($conn));
        }
        mysqli_stmt_close($update_stmt);

        // Log status change in order_status_history
        $history_query = "INSERT INTO order_status_history (order_id, status) VALUES (?, ?)";
        $history_stmt = mysqli_prepare($conn, $history_query);
        if (!$history_stmt) {
            throw new Exception("History query prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($history_stmt, "is", $order_id, $new_status);
        if (!mysqli_stmt_execute($history_stmt)) {
            throw new Exception("Failed to log status history: " . mysqli_error($conn));
        }
        mysqli_stmt_close($history_stmt);

        // Log to audit_logs
        $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        if (!$log_stmt) {
            throw new Exception("Audit log query prepare failed: " . mysqli_error($conn));
        }
        $action = "update_order_status";
        $table_name = "orders";
        $details = json_encode(['order_id' => $order_id, 'new_status' => $new_status]);
        mysqli_stmt_bind_param($log_stmt, "issis", $user_id, $action, $table_name, $order_id, $details);
        if (!mysqli_stmt_execute($log_stmt)) {
            throw new Exception("Failed to log audit: " . mysqli_error($conn));
        }
        mysqli_stmt_close($log_stmt);

        mysqli_commit($conn);
        $_SESSION['success_message'] = "Order status updated successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Status update error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update order status: " . htmlspecialchars($e->getMessage());
    }
    header("Location: order_details.php?id=$order_id");
    exit();
}

// Get order timeline
$timeline_query = "
    SELECT * FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY created_at DESC";
$timeline_stmt = mysqli_prepare($conn, $timeline_query);
if (!$timeline_stmt) {
    error_log("Timeline query prepare failed: " . mysqli_error($conn));
    $_SESSION['error_message'] = "Failed to load order timeline.";
    header("Location: orders.php");
    exit();
}
mysqli_stmt_bind_param($timeline_stmt, "i", $order_id);
mysqli_stmt_execute($timeline_stmt);
$timeline_result = mysqli_stmt_get_result($timeline_stmt);
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
            padding-bottom: 0;
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

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
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
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

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

                <?php if ($role === 'vendor'): ?>
                    <div class="customer-info">
                        <h2 class="section-title">Customer Information</h2>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-items">
                    <h2 class="section-title">Order Items</h2>
                    <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
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
                    <?php while ($status = mysqli_fetch_assoc($timeline_result)): ?>
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
                        <span>$<?php echo number_format($order['total'] - ($order['total'] * 0.1) - 10, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>$10.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (10%)</span>
                        <span>$<?php echo number_format($order['total'] * 0.1, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>$<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                </div>

                <?php if ($role === 'vendor' && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
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