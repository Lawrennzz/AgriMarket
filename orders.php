<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'customer';

// Different query based on role
if ($role === 'vendor') {
    // First get the vendor_id
    $vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_query);
    
    if (!$vendor_stmt) {
        die('MySQL prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
    mysqli_stmt_execute($vendor_stmt);
    $vendor_result = mysqli_stmt_get_result($vendor_stmt);
    
    if (mysqli_num_rows($vendor_result) === 0) {
        // Not a vendor yet
        $error_message = "Vendor profile not found. Please complete your vendor registration.";
    } else {
        $vendor_data = mysqli_fetch_assoc($vendor_result);
        $vendor_id = $vendor_data['vendor_id'];
        
        // Get orders that contain products from this vendor
        $orders_query = "
            SELECT DISTINCT o.order_id, o.created_at, o.status, o.total, u.name AS customer_name,
                   (SELECT COUNT(*) FROM order_items oi2 
                    JOIN products p2 ON oi2.product_id = p2.product_id 
                    WHERE oi2.order_id = o.order_id AND p2.vendor_id = ?) AS item_count
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            JOIN users u ON o.user_id = u.user_id
            WHERE p.vendor_id = ? AND o.deleted_at IS NULL
            ORDER BY o.created_at DESC";
        
        $orders_stmt = mysqli_prepare($conn, $orders_query);
        
        if (!$orders_stmt) {
            die('MySQL prepare error: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($orders_stmt, "ii", $vendor_id, $vendor_id);
        mysqli_stmt_execute($orders_stmt);
        $orders_result = mysqli_stmt_get_result($orders_stmt);
    }
} else {
    // Regular customer query - unchanged
    $orders_query = "SELECT * FROM orders WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC";
    $orders_stmt = mysqli_prepare($conn, $orders_query);
    
    if (!$orders_stmt) {
        die('MySQL prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($orders_stmt, "i", $user_id);
    mysqli_stmt_execute($orders_stmt);
    $orders_result = mysqli_stmt_get_result($orders_stmt);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo ($role === 'vendor') ? 'Customer Orders' : 'My Orders'; ?> - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .orders-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 1rem;
        }
        
        .orders-empty {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--medium-gray);
            margin-bottom: 1rem;
        }
        
        .empty-message {
            font-size: 1.2rem;
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
        }
        
        .order-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            background: var(--light-gray);
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .meta-group {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            font-weight: 500;
            color: var(--dark-gray);
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff8e1;
            color: #f57c00;
        }
        
        .status-processing {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-shipped {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-delivered {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-cancelled {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .order-content {
            padding: 1.5rem;
        }
        
        .order-items {
            margin-bottom: 1.5rem;
        }
        
        .item-row {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .item-meta {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .order-summary {
            background: var(--light-gray);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .summary-total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark-gray);
        }
        
        .order-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-payment {
            background: #28a745;
            color: white;
        }
        
        .review-btn {
            margin-top: 8px;
            display: inline-block;
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        .reviewed-badge {
            color: #4CAF50;
            font-size: 0.85rem;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .meta-group {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="orders-container">
        <h1 class="page-title"><?php echo ($role === 'vendor') ? 'Customer Orders' : 'My Orders'; ?></h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($orders_result) && mysqli_num_rows($orders_result) === 0): ?>
        <div class="orders-empty">
            <div class="empty-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <?php if ($role === 'vendor'): ?>
                <h2 class="empty-message">No orders for your products yet.</h2>
                <a href="product_upload.php" class="btn-primary">Upload Products</a>
            <?php else: ?>
                <h2 class="empty-message">You haven't placed any orders yet.</h2>
                <a href="products.php" class="btn-primary">Start Shopping</a>
            <?php endif; ?>
        </div>
        <?php elseif (isset($orders_result)): ?>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <?php
                if ($role === 'vendor') {
                    // For vendors, only show items from their products
                    $items_query = "SELECT oi.*, p.name, p.image_url 
                                   FROM order_items oi
                                   JOIN products p ON oi.product_id = p.product_id
                                   WHERE oi.order_id = ? AND p.vendor_id = ?";
                    $items_stmt = mysqli_prepare($conn, $items_query);
                    mysqli_stmt_bind_param($items_stmt, "ii", $order['order_id'], $vendor_id);
                } else {
                    // For customers, show all items in their order
                    $items_query = "SELECT oi.*, p.name, p.image_url 
                                   FROM order_items oi
                                   JOIN products p ON oi.product_id = p.product_id
                                   WHERE oi.order_id = ?";
                    $items_stmt = mysqli_prepare($conn, $items_query);
                    mysqli_stmt_bind_param($items_stmt, "i", $order['order_id']);
                }
                
                mysqli_stmt_execute($items_stmt);
                $items_result = mysqli_stmt_get_result($items_stmt);
                
                $items = [];
                $item_count = 0;
                while ($item = mysqli_fetch_assoc($items_result)) {
                    $items[] = $item;
                    $item_count += $item['quantity'];
                }
                
                // Get status class
                $status_class = '';
                switch($order['status']) {
                    case 'pending':
                        $status_class = 'status-pending';
                        break;
                    case 'processing':
                        $status_class = 'status-processing';
                        break;
                    case 'shipped':
                        $status_class = 'status-shipped';
                        break;
                    case 'delivered':
                        $status_class = 'status-delivered';
                        break;
                    case 'cancelled':
                        $status_class = 'status-cancelled';
                        break;
                }
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-meta">
                            <div class="meta-group">
                                <div class="meta-label">Order ID</div>
                                <div class="meta-value">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></div>
                            </div>
                            <div class="meta-group">
                                <div class="meta-label">Order Date</div>
                                <div class="meta-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                            </div>
                            <?php if ($role === 'vendor'): ?>
                            <div class="meta-group">
                                <div class="meta-label">Customer</div>
                                <div class="meta-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="meta-group">
                                <div class="meta-label">Items</div>
                                <div class="meta-value"><?php echo $role === 'vendor' ? $order['item_count'] : $item_count; ?> items</div>
                            </div>
                        </div>
                        <div class="order-status <?php echo $status_class; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </div>
                    </div>
                    <div class="order-content">
                        <div class="order-items">
                            <?php 
                            // Display up to 3 items
                            $display_items = array_slice($items, 0, 3);
                            foreach ($display_items as $item): 
                            ?>
                            <div class="item-row">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="item-image">
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-meta">Qty: <?php echo $item['quantity']; ?></div>
                                    
                                    <?php if ($role === 'customer'): ?>
                                        <?php
                                        // Check if user has already reviewed this product for this specific order
                                        $review_check_sql = "SELECT * FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?";
                                        $review_stmt = mysqli_prepare($conn, $review_check_sql);
                                        $has_reviewed = false;
                                        
                                        if ($review_stmt) {
                                            mysqli_stmt_bind_param($review_stmt, "iii", $user_id, $item['product_id'], $order['order_id']);
                                            mysqli_stmt_execute($review_stmt);
                                            $review_result = mysqli_stmt_get_result($review_stmt);
                                            $has_reviewed = (mysqli_num_rows($review_result) > 0);
                                            mysqli_stmt_close($review_stmt);
                                        }
                                        ?>
                                        
                                        <?php if (!$has_reviewed): ?>
                                            <a href="add_review.php?order_id=<?php echo $order['order_id']; ?>&product_id=<?php echo $item['product_id']; ?>" 
                                               class="btn btn-outline review-btn">
                                                <i class="fas fa-star"></i> Write Review
                                            </a>
                                        <?php else: ?>
                                            <span class="reviewed-badge">
                                                <i class="fas fa-check-circle"></i> Reviewed
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($items) > 3): ?>
                            <div class="item-meta" style="text-align: center; margin-top: 0.5rem;">
                                + <?php echo count($items) - 3; ?> more items
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-summary">
                            <div class="summary-total">
                                <?php if ($role === 'vendor'): ?>
                                    Total for Your Products: $<?php 
                                    $vendor_total = 0;
                                    foreach ($items as $item) {
                                        $vendor_total += $item['price'] * $item['quantity'];
                                    }
                                    echo number_format($vendor_total, 2); 
                                    ?>
                                <?php else: ?>
                                    Total: $<?php echo number_format($order['total'], 2); ?>
                                <?php endif; ?>
                            </div>
                            <div class="order-actions">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                                    View Details
                                </a>
                                <a href="check_order_payment.php?id=<?php echo $order['order_id']; ?>" class="btn btn-payment">
                                    Payment Details
                                </a>
                                <?php if ($role === 'customer' && $order['status'] !== 'cancelled'): ?>
                                    <form method="post" action="order_details.php?id=<?php echo $order['order_id']; ?>">
                                        <button type="submit" name="reorder" class="btn btn-outline">Reorder</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>