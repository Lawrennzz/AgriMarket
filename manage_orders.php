<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Fetch orders
$orders_query = "SELECT o.order_id, u.name AS customer_name, o.total, o.status, o.created_at 
                 FROM orders o 
                 JOIN users u ON o.user_id = u.user_id 
                 WHERE o.deleted_at IS NULL 
                 ORDER BY o.created_at DESC";

$orders_stmt = mysqli_prepare($conn, $orders_query);

if ($orders_stmt === false) {
    die('MySQL prepare error: ' . mysqli_error($conn));
}

mysqli_stmt_execute($orders_stmt);
$orders_result = mysqli_stmt_get_result($orders_stmt);

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_order'])) {
    $order_id_to_delete = (int)$_POST['order_id'];
    $delete_query = "UPDATE orders SET deleted_at = NOW() WHERE order_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $order_id_to_delete);
    if (mysqli_stmt_execute($delete_stmt)) {
        $success = "Order deleted successfully!";
    } else {
        $error = "Failed to delete order.";
    }
    mysqli_stmt_close($delete_stmt);
    header("Location: manage_orders.php"); // Refresh page
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Orders - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
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

        .form-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: var(--medium-gray);
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .order-table th, .order-table td {
            padding: 1rem;
            border: 1px solid var(--light-gray);
            text-align: left;
        }

        .order-table th {
            background: var(--primary-color);
            color: white;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Manage Orders</h1>
            <p class="form-subtitle">Manage customer orders</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <table class="order-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Name</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['total']); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">View</a>
                            <form method="POST" action="manage_orders.php" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <button type="submit" name="delete_order" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this order?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
