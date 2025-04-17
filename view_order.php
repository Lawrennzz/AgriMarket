<?php
include 'config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_orders.php");
    exit();
}

$order_id = (int)$_GET['id'];

// Get order details
$order_query = "
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone_number AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ? AND o.deleted_at IS NULL";

$order_stmt = mysqli_prepare($conn, $order_query);

if ($order_stmt === false) {
    die('MySQL prepare error: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($order_stmt, "i", $order_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);

if (mysqli_num_rows($order_result) == 0) {
    header("Location: manage_orders.php");
    exit();
}

$order = mysqli_fetch_assoc($order_result);
mysqli_stmt_close($order_stmt);

// Get order items
$items_query = "
    SELECT oi.*, p.name AS product_name, p.image_url, v.vendor_id, u.name AS vendor_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN users u ON v.user_id = u.user_id
    WHERE oi.order_id = ?";

$items_stmt = mysqli_prepare($conn, $items_query);

if ($items_stmt === false) {
    die('MySQL prepare error: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($items_stmt, "i", $order_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

// Get order status history
$history_query = "
    SELECT osh.*
    FROM order_status_history osh
    WHERE osh.order_id = ?
    ORDER BY osh.created_at DESC";

$history_stmt = mysqli_prepare($conn, $history_query);

if ($history_stmt === false) {
    die('MySQL prepare error: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($history_stmt, "i", $order_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    if (!in_array($new_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $error = "Invalid status selected.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update order status
            $update_query = "UPDATE orders SET status = ? WHERE order_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            
            if ($update_stmt === false) {
                throw new Exception("Error preparing statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($update_stmt, "si", $new_status, $order_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Error updating order: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($update_stmt);
            
            // Log status change in order_status_history
            $history_insert = "INSERT INTO order_status_history (order_id, status) VALUES (?, ?)";
            $history_insert_stmt = mysqli_prepare($conn, $history_insert);
            
            if ($history_insert_stmt === false) {
                throw new Exception("Error preparing statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($history_insert_stmt, "is", $order_id, $new_status);
            
            if (!mysqli_stmt_execute($history_insert_stmt)) {
                throw new Exception("Error logging status change: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($history_insert_stmt);
            
            // Log action
            $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = mysqli_prepare($conn, $audit_query);
            
            if ($audit_stmt === false) {
                throw new Exception("Error preparing audit log statement: " . mysqli_error($conn));
            }
            
            $action = "update";
            $table = "orders";
            $details = "Updated order status to " . $new_status;
            
            mysqli_stmt_bind_param($audit_stmt, "issss", $_SESSION['user_id'], $action, $table, $order_id, $details);
            mysqli_stmt_execute($audit_stmt);
            mysqli_stmt_close($audit_stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success = "Order status updated successfully!";
            
            // Send order status update email to the customer
            try {
                require_once 'includes/Mailer.php';
                
                // Get user details
                $user_query = "SELECT u.name, u.email FROM users u 
                              JOIN orders o ON u.user_id = o.user_id 
                              WHERE o.order_id = ?";
                $user_stmt = mysqli_prepare($conn, $user_query);
                mysqli_stmt_bind_param($user_stmt, "i", $order_id);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                $user_data = mysqli_fetch_assoc($user_result);
                
                if ($user_data) {
                    // Get order details
                    $order_query_for_email = "SELECT * FROM orders WHERE order_id = ?";
                    $order_stmt_for_email = mysqli_prepare($conn, $order_query_for_email);
                    mysqli_stmt_bind_param($order_stmt_for_email, "i", $order_id);
                    mysqli_stmt_execute($order_stmt_for_email);
                    $order_result_for_email = mysqli_stmt_get_result($order_stmt_for_email);
                    $order_data = mysqli_fetch_assoc($order_result_for_email);
                    
                    // Send the status update email
                    $mailer = new Mailer();
                    $mailer->sendOrderStatusUpdate(
                        $order_id,
                        $user_data['email'],
                        $user_data['name'],
                        $new_status,
                        $order_data
                    );
                }
            } catch (Exception $e) {
                // Log the error but don't disrupt the status update process
                error_log("Failed to send order status update email: " . $e->getMessage());
            }
            
            // Refresh order data - reopen the statement since it was already closed
            $order_stmt = mysqli_prepare($conn, $order_query);
            mysqli_stmt_bind_param($order_stmt, "i", $order_id);
            mysqli_stmt_execute($order_stmt);
            $order_result = mysqli_stmt_get_result($order_stmt);
            $order = mysqli_fetch_assoc($order_result);
            mysqli_stmt_close($order_stmt);
            
            // Refresh status history - reopen the statement since it was already closed
            $history_stmt = mysqli_prepare($conn, $history_query);
            mysqli_stmt_bind_param($history_stmt, "i", $order_id);
            mysqli_stmt_execute($history_stmt);
            $history_result = mysqli_stmt_get_result($history_stmt);
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - AgriMarket Admin</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        
        .order-id {
            font-size: 1.2rem;
            color: #555;
        }
        
        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-processing {
            background-color: #b8daff;
            color: #004085;
        }
        
        .status-shipped {
            background-color: #c3e6cb;
            color: #155724;
        }
        
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        
        .detail-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .items-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .order-totals {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            margin-bottom: 2rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 2px solid #333;
            border-bottom: none;
            padding-top: 1rem;
        }
        
        .btn-container {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
        }
        
        .status-form {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .status-select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        
        .history-table th,
        .history-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .history-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <h1>Order Details</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <div class="order-container">
            <div class="order-header">
                <div>
                    <h2>Order #<?php echo htmlspecialchars($order['order_id']); ?></h2>
                    <p class="order-id">Placed on <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($order['created_at']))); ?></p>
                </div>
                <div class="order-status status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>">
                    <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                </div>
            </div>
            
            <!-- Debug information -->
            <div style="background: #f5f5f5; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                <h4>Available Order Fields</h4>
                <pre><?php echo htmlspecialchars(print_r(array_keys($order), true)); ?></pre>
            </div>
            
            <div class="status-form">
                <form method="post" action="">
                    <label for="status">Update Status:</label>
                    <select name="status" id="status" class="status-select">
                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                </form>
            </div>
            
            <div class="order-details">
                <div class="detail-card">
                    <h3>Customer Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="detail-card">
                    <h3>Shipping Address</h3>
                    <?php
                    $shipping_address = json_decode($order['shipping_address'], true);
                    if ($shipping_address) {
                        echo '<p>' . htmlspecialchars($shipping_address['street'] ?? '') . '</p>';
                        echo '<p>' . htmlspecialchars($shipping_address['city'] ?? '') . ', ' . 
                                     htmlspecialchars($shipping_address['state'] ?? '') . ' ' . 
                                     htmlspecialchars($shipping_address['zip'] ?? '') . '</p>';
                        echo '<p>' . htmlspecialchars($shipping_address['country'] ?? '') . '</p>';
                    } else {
                        echo '<p>No shipping address available</p>';
                    }
                    ?>
                </div>
                
                <div class="detail-card">
                    <h3>Payment Information</h3>
                    <p>
                        <strong>Payment Method:</strong> 
                        <?php if (!isset($order['payment_method']) || empty($order['payment_method'])): ?>
                            Not specified 
                            <a href="update_payment_methods.php?id=<?php echo $order_id; ?>" class="btn-sm" style="display: inline-block; background: #4CAF50; color: white; border-radius: 3px; padding: 3px 8px; font-size: 12px; text-decoration: none; margin-left: 10px;">
                                <i class="fas fa-edit"></i> Fix
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?>
                        <?php endif; ?>
                    </p>
                    <p><strong>Payment Status:</strong> <?php echo isset($order['payment_status']) && $order['payment_status'] ? htmlspecialchars(ucfirst($order['payment_status'])) : 'Not specified'; ?></p>
                    <?php if (isset($order['payment_details']) && !empty($order['payment_details'])): ?>
                        <p><strong>Payment Details:</strong> <?php echo htmlspecialchars($order['payment_details']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <h3>Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Vendor</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-image">
                                    <?php else: ?>
                                        <div class="product-image" style="background: #eee; display: flex; align-items: center; justify-content: center;">No Image</div>
                                    <?php endif; ?>
                                    <div style="margin-left: 1rem;">
                                        <p style="margin: 0;"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($item['vendor_name']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="order-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>$<?php echo htmlspecialchars(number_format($order['subtotal'] ?? 0, 2)); ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping:</span>
                    <span>$<?php echo htmlspecialchars(number_format($order['shipping'] ?? 0, 2)); ?></span>
                </div>
                <div class="total-row">
                    <span>Tax:</span>
                    <span>$<?php echo htmlspecialchars(number_format($order['tax'] ?? 0, 2)); ?></span>
                </div>
                <div class="total-row final">
                    <span>Total:</span>
                    <span>$<?php echo htmlspecialchars(number_format($order['total'], 2)); ?></span>
                </div>
            </div>
            
            <h3>Status History</h3>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($history_result) > 0): ?>
                        <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                            <tr>
                                <td>
                                    <span class="order-status status-<?php echo htmlspecialchars(strtolower($history['status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst($history['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($history['created_at']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">No status history available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="btn-container">
                <a href="manage_orders.php" class="btn btn-secondary">Back to Orders</a>
                
                <?php if ($order['status'] !== 'cancelled'): ?>
                    <form method="post" action="" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" name="update_status" class="btn btn-danger">Cancel Order</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 