<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query based on user type
if ($role === 'vendor') {
    $base_query = "
        SELECT o.*, u.name as customer_name, u.email as customer_email,
               COUNT(oi.item_id) as items_count,
               GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE p.vendor_id = $user_id
    ";
} else {
    $base_query = "
        SELECT o.*, 
               COUNT(oi.item_id) as items_count,
               GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.user_id = $user_id
    ";
}

// Add status filter
if ($status !== 'all') {
    $status = mysqli_real_escape_string($conn, $status);
    $base_query .= " AND o.status = '$status'";
}

$base_query .= " GROUP BY o.order_id";

// Add sorting
switch ($sort) {
    case 'oldest':
        $base_query .= " ORDER BY o.created_at ASC";
        break;
    case 'highest':
        $base_query .= " ORDER BY o.total_amount DESC";
        break;
    case 'lowest':
        $base_query .= " ORDER BY o.total_amount ASC";
        break;
    default: // newest
        $base_query .= " ORDER BY o.created_at DESC";
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT o.order_id) as total FROM (" . $base_query . ") as subquery";
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'];
$total_pages = ceil($total_orders / $per_page);

// Get orders for current page
$orders_query = $base_query . " LIMIT $offset, $per_page";
$orders = mysqli_query($conn, $orders_query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orders - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
        }

        .filters {
            display: flex;
            gap: 1rem;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            color: var(--dark-gray);
            background: white;
            cursor: pointer;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        .orders-table th {
            background: var(--light-bg);
            font-weight: 500;
            color: var(--dark-gray);
        }

        .order-id {
            font-family: monospace;
            font-weight: 500;
        }

        .order-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            color: var(--dark-gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover,
        .page-link.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .order-products {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .btn-view {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .btn-view:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 992px) {
            .orders-container {
                margin: 1rem;
                padding: 1rem;
            }

            .orders-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .filters {
                flex-wrap: wrap;
            }

            .filter-select {
                flex: 1;
            }
        }

        @media (max-width: 768px) {
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="orders-container">
            <div class="orders-header">
                <h1 class="page-title">
                    <?php echo $role === 'vendor' ? 'Manage Orders' : 'My Orders'; ?>
                </h1>

                <div class="filters">
                    <select class="filter-select" onchange="updateFilters('status', this.value)">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>

                    <select class="filter-select" onchange="updateFilters('sort', this.value)">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>>Lowest Amount</option>
                    </select>
                </div>
            </div>

            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <?php if ($role === 'vendor'): ?>
                            <th>Customer</th>
                        <?php endif; ?>
                        <th>Products</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                        <tr>
                            <td class="order-id">#<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></td>
                            <?php if ($role === 'vendor'): ?>
                                <td>
                                    <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="order-products"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                </td>
                            <?php endif; ?>
                            <td>
                                <div class="order-products">
                                    <?php echo htmlspecialchars($order['product_names']); ?>
                                </div>
                            </td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-view">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <a href="?page=1&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                       class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    
                    <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                       class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                           class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                       class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>

                    <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                       class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function updateFilters(type, value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set(type, value);
            urlParams.set('page', '1'); // Reset to first page when filters change
            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>