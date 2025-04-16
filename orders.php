<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all orders for the user
$orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$orders_stmt = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($orders_stmt, "i", $user_id);
mysqli_stmt_execute($orders_stmt);
$orders_result = mysqli_stmt_get_result($orders_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders - AgriMarket</title>
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
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            border-radius: var(--border-radius);
            object-fit: cover;
            margin-right: 1rem;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--dark-gray);
        }
        
        .item-meta {
            font-size: 0.875rem;
            color: var(--medium-gray);
        }
        
        .item-price {
            font-weight: 500;
            color: var(--dark-gray);
            text-align: right;
            min-width: 80px;
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
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-view-order {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-view-order:hover {
            background: var(--primary-dark);
        }
        
        .btn-track-order {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-track-order:hover {
            background: #f0f7ff;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-meta {
                margin-bottom: 1rem;
            }
            
            .order-summary {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="orders-container">
        <h1 class="page-title">My Orders</h1>
        
        <?php if (mysqli_num_rows($orders_result) === 0): ?>
        <div class="orders-empty">
            <div class="empty-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h2 class="empty-message">You haven't placed any orders yet.</h2>
            <a href="products.php" class="btn-primary">Start Shopping</a>
        </div>
        <?php else: ?>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <?php
                // Fetch order items
                $items_query = "SELECT oi.*, p.name, p.image_url 
                                FROM order_items oi
                                JOIN products p ON oi.product_id = p.product_id
                                WHERE oi.order_id = ?";
                $items_stmt = mysqli_prepare($conn, $items_query);
                mysqli_stmt_bind_param($items_stmt, "i", $order['order_id']);
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
                            <div class="meta-group">
                                <div class="meta-label">Items</div>
                                <div class="meta-value"><?php echo $item_count; ?> items</div>
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
                                Total: $<?php echo number_format($order['total'], 2); ?>
                            </div>
                            <div class="order-actions">
                                <a href="order_confirmation.php?order_id=<?php echo $order['order_id']; ?>" class="btn-view-order">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <a href="#" class="btn-track-order">
                                    <i class="fas fa-truck"></i> Track Order
                                </a>
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