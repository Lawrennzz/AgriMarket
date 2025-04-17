<?php
// Include database connection
include '../config.php';

// Check session and admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo "<p>Error: No order ID specified</p>";
    echo "<p><a href='manage_orders.php'>Return to Order Management</a></p>";
    exit();
}

$order_id = intval($_GET['order_id']);
$success_message = '';
$error_message = '';

// Handle form submission to update payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_method = $_POST['payment_method'];
    
    // Update the order
    $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $payment_method, $order_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Payment method updated successfully!";
        } else {
            $error_message = "Error updating payment method: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Error preparing statement: " . mysqli_error($conn);
    }
}

// Get order details
$query = "SELECT o.*, 
          JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.full_name')) AS customer_name,
          JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method')) AS json_payment_method
          FROM orders o WHERE o.order_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    echo "<p>Error: Order not found</p>";
    echo "<p><a href='manage_orders.php'>Return to Order Management</a></p>";
    exit();
}

// Get shipping address details
$shipping_address = json_decode($order['shipping_address'], true);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details & Payment Method Fix - AgriMarket Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .field {
            margin-bottom: 10px;
        }
        .label {
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
        }
        .value {
            color: #555;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
            color: #3c763d;
        }
        .alert-danger {
            background-color: #f2dede;
            border-color: #ebccd1;
            color: #a94442;
        }
        .form-group {
            margin-bottom: 15px;
        }
        select, button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .json-data {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Order #<?php echo $order_id; ?> Details</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2 class="section-title">Current Payment Information</h2>
            <div class="order-info">
                <div class="field">
                    <span class="label">Payment Method (DB):</span>
                    <span class="value"><?php echo $order['payment_method'] ? htmlspecialchars($order['payment_method']) : 'Not specified'; ?></span>
                </div>
                <div class="field">
                    <span class="label">Payment Method (JSON):</span>
                    <span class="value"><?php echo isset($shipping_address['payment_method']) ? htmlspecialchars($shipping_address['payment_method']) : 'Not found in JSON'; ?></span>
                </div>
                <div class="field">
                    <span class="label">Payment Status:</span>
                    <span class="value"><?php echo htmlspecialchars($order['payment_status'] ?? 'Not specified'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Update Payment Method</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select id="payment_method" name="payment_method">
                        <option value="cash_on_delivery" <?php echo ($order['payment_method'] == 'cash_on_delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="bank_transfer" <?php echo ($order['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="credit_card" <?php echo ($order['payment_method'] == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="paypal" <?php echo ($order['payment_method'] == 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                        <option value="mobile_payment" <?php echo ($order['payment_method'] == 'mobile_payment') ? 'selected' : ''; ?>>Mobile Payment</option>
                        <option value="crypto" <?php echo ($order['payment_method'] == 'crypto') ? 'selected' : ''; ?>>Cryptocurrency</option>
                    </select>
                </div>
                <button type="submit" name="update_payment">Update Payment Method</button>
            </form>
        </div>
        
        <div class="section">
            <h2 class="section-title">Order Details</h2>
            <div class="order-info">
                <div class="field">
                    <span class="label">Order ID:</span>
                    <span class="value"><?php echo $order_id; ?></span>
                </div>
                <div class="field">
                    <span class="label">Customer:</span>
                    <span class="value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <div class="field">
                    <span class="label">Total:</span>
                    <span class="value">$<?php echo number_format($order['total'], 2); ?></span>
                </div>
                <div class="field">
                    <span class="label">Status:</span>
                    <span class="value"><?php echo htmlspecialchars($order['status']); ?></span>
                </div>
                <div class="field">
                    <span class="label">Created At:</span>
                    <span class="value"><?php echo htmlspecialchars($order['created_at']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Full Shipping Address JSON</h2>
            <div class="json-data"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
        </div>
        
        <p><a href="manage_orders.php">Return to Order Management</a> | <a href="view_order.php?id=<?php echo $order_id; ?>">View Order</a></p>
    </div>
</body>
</html> 