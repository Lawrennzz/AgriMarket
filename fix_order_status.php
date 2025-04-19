<?php
require_once 'config.php';
require_once 'classes/Database.php';

// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "You must be an admin to access this page.";
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

if (isset($_POST['update_order'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    // Update order status
    $update_query = "UPDATE orders SET status = ? WHERE order_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "si", $status, $order_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " status updated to " . $status;
        
        // Also log in history
        $history_query = "INSERT INTO order_status_history (order_id, status) VALUES (?, ?)";
        $history_stmt = mysqli_prepare($conn, $history_query);
        mysqli_stmt_bind_param($history_stmt, "is", $order_id, $status);
        mysqli_stmt_execute($history_stmt);
    } else {
        $error_message = "Failed to update order status: " . mysqli_error($conn);
    }
}

// Get all recent orders
$orders_query = "SELECT o.order_id, o.user_id, o.status, o.created_at, o.total, u.name as customer_name 
                FROM orders o 
                JOIN users u ON o.user_id = u.user_id
                ORDER BY o.created_at DESC 
                LIMIT 50";
$orders_result = mysqli_query($conn, $orders_query);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Order Status</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: green; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .error { color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        form { display: inline; }
        button { padding: 5px 10px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Order Status Management</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <p>Use this page to update order statuses to ensure review buttons appear correctly.</p>
    
    <h2>Recent Orders</h2>
    <?php if (mysqli_num_rows($orders_result) > 0): ?>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Current Status</th>
                <th>Actions</th>
            </tr>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <tr>
                    <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?> (ID: <?php echo $order['user_id']; ?>)</td>
                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                    <td><?php echo ucfirst($order['status']); ?></td>
                    <td>
                        <form method="post" action="">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <select name="status">
                                <option value="pending" <?php echo ($order['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo ($order['status'] === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo ($order['status'] === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo ($order['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo ($order['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_order">Update</button>
                        </form>
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" target="_blank">View Details</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No orders found.</p>
    <?php endif; ?>
    
    <h2>Quick Actions</h2>
    <p>Set all orders for the current user to 'delivered' status so you can test reviews:</p>
    
    <form method="post" action="">
        <input type="text" name="user_id" placeholder="Enter user ID" value="<?php echo $_SESSION['user_id']; ?>">
        <button type="submit" name="set_all_delivered">Set All to Delivered</button>
    </form>
    
    <?php
    // Handle setting all user orders to delivered
    if (isset($_POST['set_all_delivered'])) {
        $user_id = (int)$_POST['user_id'];
        
        $update_all_query = "UPDATE orders SET status = 'delivered' WHERE user_id = ?";
        $update_all_stmt = mysqli_prepare($conn, $update_all_query);
        mysqli_stmt_bind_param($update_all_stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($update_all_stmt)) {
            $affected = mysqli_stmt_affected_rows($update_all_stmt);
            echo "<div class='success'>$affected orders updated to 'delivered' for user $user_id</div>";
            
            // Also log in history
            $orders_to_update = mysqli_query($conn, "SELECT order_id FROM orders WHERE user_id = $user_id");
            while ($order = mysqli_fetch_assoc($orders_to_update)) {
                $history_query = "INSERT INTO order_status_history (order_id, status) VALUES (?, 'delivered')";
                $history_stmt = mysqli_prepare($conn, $history_query);
                mysqli_stmt_bind_param($history_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($history_stmt);
            }
        } else {
            echo "<div class='error'>Failed to update orders: " . mysqli_error($conn) . "</div>";
        }
    }
    ?>
</body>
</html> 