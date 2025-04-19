<?php
require_once 'config.php';
require_once 'classes/Database.php';

// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get user's orders with items
$sql = "SELECT o.*, oi.*, p.name as product_name, p.image_url, p.price 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC";

$stmt = $db->prepare($sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $order_id = $row['order_id'];
        if (!isset($orders[$order_id])) {
            $orders[$order_id] = [
                'order_id' => $order_id,
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'total' => $row['total'],
                'items' => []
            ];
        }
        $orders[$order_id]['items'][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'image_url' => $row['image_url']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .order-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .order-header {
            background: var(--light-gray);
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-items {
            padding: 1rem;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .item-details {
            flex: 1;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge-success {
            background-color: var(--success-color);
            color: white;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Fix for search input text visibility */
        input[type="text"], input[type="search"], .form-control {
            color: #333;
        }
        
        /* Improved alert styling */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            position: relative;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="orders-container">
        <h1>My Orders</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                You haven't placed any orders yet. <a href="products.php">Start shopping</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?php echo $order['order_id']; ?></strong>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        <div>
                            <span>Ordered on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                            <strong class="ml-3">Total: $<?php echo number_format($order['total'], 2); ?></strong>
                        </div>
                    </div>

                    <div class="order-items">
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default-product.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     class="item-image">
                                
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                    <p>Quantity: <?php echo $item['quantity']; ?></p>
                                    <p>Price: $<?php echo number_format($item['price'], 2); ?></p>
                                    
                                    <div class="item-actions">
                                        <?php if ($order['status'] === 'delivered'): ?>
                                            <?php
                                            // Check if user has already reviewed this product for this specific order
                                            $review_check_sql = "SELECT * FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?";
                                            $review_stmt = $db->prepare($review_check_sql);
                                            if ($review_stmt) {
                                                mysqli_stmt_bind_param($review_stmt, "iii", $_SESSION['user_id'], $item['product_id'], $order['order_id']);
                                                mysqli_stmt_execute($review_stmt);
                                                $review_result = mysqli_stmt_get_result($review_stmt);
                                                $has_reviewed = mysqli_num_rows($review_result) > 0;
                                                mysqli_stmt_close($review_stmt);
                                            } else {
                                                $has_reviewed = false; // Default if query fails
                                            }
                                            ?>
                                            
                                            <?php if (!$has_reviewed): ?>
                                                <a href="add_review.php?order_id=<?php echo $order['order_id']; ?>&product_id=<?php echo $item['product_id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-star"></i> Write Review
                                                </a>
                                            <?php else: ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Reviewed
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="product.php?id=<?php echo $item['product_id']; ?>" 
                                           class="btn btn-secondary btn-sm">
                                            View Product
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 