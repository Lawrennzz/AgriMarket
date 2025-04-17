<?php
// Include database connection
include '../config.php';
require_once '../classes/AuditLog.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize audit logger
$auditLogger = new AuditLog();

// Initialize variables
$success_message = '';
$error_message = '';
$orders = [];
$updated_count = 0;
$failed_count = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check which action was requested
    if (isset($_POST['update_from_json'])) {
        // Update orders by extracting payment_method from JSON shipping_address
        $query = "SELECT order_id, 
                  JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.payment_method')) AS json_payment_method
                  FROM orders 
                  WHERE (payment_method IS NULL OR payment_method = '') 
                  AND JSON_EXTRACT(shipping_address, '$.payment_method') IS NOT NULL";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($order = mysqli_fetch_assoc($result)) {
                if (!empty($order['json_payment_method'])) {
                    $order_id = $order['order_id'];
                    $payment_method = $order['json_payment_method'];
                    
                    // Update the order payment_method
                    $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "si", $payment_method, $order_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Log the update in audit logs
                            $auditLogger->log(
                                $_SESSION['user_id'],
                                'update',
                                'orders',
                                $order_id,
                                [
                                    'payment_method' => [
                                        'from' => 'Not specified',
                                        'to' => $payment_method,
                                        'source' => 'json_extract'
                                    ]
                                ]
                            );
                            
                            $updated_count++;
                        } else {
                            $failed_count++;
                        }
                        
                        mysqli_stmt_close($stmt);
                    } else {
                        $failed_count++;
                    }
                }
            }
            
            if ($updated_count > 0) {
                $success_message = "Successfully updated payment method for {$updated_count} order(s)";
                
                if ($failed_count > 0) {
                    $error_message = "Failed to update {$failed_count} order(s)";
                }
            } else {
                $error_message = "No orders were updated. Either all orders already have payment methods or no payment methods found in shipping_address.";
            }
        } else {
            $error_message = "Failed to query orders: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['update_from_logs'])) {
        // Update orders using the most recent payment log for each order
        $query = "SELECT o.order_id, pl.payment_method
                  FROM orders o
                  JOIN payment_logs pl ON o.order_id = pl.order_id
                  WHERE (o.payment_method IS NULL OR o.payment_method = '')
                  AND pl.payment_method IS NOT NULL
                  AND pl.payment_method != ''
                  GROUP BY o.order_id
                  HAVING pl.created_at = MAX(pl.created_at)";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($order = mysqli_fetch_assoc($result)) {
                $order_id = $order['order_id'];
                $payment_method = $order['payment_method'];
                
                // Update the order payment_method
                $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $payment_method, $order_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Log the update in audit logs
                        $auditLogger->log(
                            $_SESSION['user_id'],
                            'update',
                            'orders',
                            $order_id,
                            [
                                'payment_method' => [
                                    'from' => 'Not specified',
                                    'to' => $payment_method,
                                    'source' => 'payment_logs'
                                ]
                            ]
                        );
                        
                        $updated_count++;
                    } else {
                        $failed_count++;
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $failed_count++;
                }
            }
            
            if ($updated_count > 0) {
                $success_message = "Successfully updated payment method for {$updated_count} order(s) from payment logs";
                
                if ($failed_count > 0) {
                    $error_message = "Failed to update {$failed_count} order(s)";
                }
            } else {
                $error_message = "No orders were updated from payment logs";
            }
        } else {
            $error_message = "Failed to query orders: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['bulk_update'])) {
        // Handle bulk updates where payment method is set to a specific value
        if (isset($_POST['order_ids']) && isset($_POST['payment_method'])) {
            $order_ids = $_POST['order_ids'];
            $payment_method = $_POST['payment_method'];
            
            if (!empty($payment_method)) {
                foreach ($order_ids as $order_id) {
                    // Update the order payment_method
                    $update_query = "UPDATE orders SET payment_method = ? WHERE order_id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "si", $payment_method, $order_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Log the update in audit logs
                            $auditLogger->log(
                                $_SESSION['user_id'],
                                'update',
                                'orders',
                                $order_id,
                                [
                                    'payment_method' => [
                                        'from' => 'Not specified',
                                        'to' => $payment_method,
                                        'source' => 'bulk_update'
                                    ]
                                ]
                            );
                            
                            $updated_count++;
                        } else {
                            $failed_count++;
                        }
                        
                        mysqli_stmt_close($stmt);
                    } else {
                        $failed_count++;
                    }
                }
                
                if ($updated_count > 0) {
                    $success_message = "Successfully updated payment method for {$updated_count} order(s)";
                    
                    if ($failed_count > 0) {
                        $error_message = "Failed to update {$failed_count} order(s)";
                    }
                } else {
                    $error_message = "No orders were updated";
                }
            } else {
                $error_message = "Payment method cannot be empty";
            }
        } else {
            $error_message = "Missing required parameters";
        }
    }
}

// Get all orders with missing payment methods
$query = "SELECT o.order_id, 
           o.user_id, 
           o.total, 
           o.status,
           o.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.full_name')) AS customer_name,
           JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method')) AS json_payment_method,
           u.name AS user_name,
           u.email AS user_email,
           (SELECT COUNT(*) FROM payment_logs pl WHERE pl.order_id = o.order_id) AS has_payment_logs
           FROM orders o
           LEFT JOIN users u ON o.user_id = u.user_id
           WHERE (o.payment_method IS NULL OR o.payment_method = '')
           ORDER BY o.created_at DESC";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
}

// Get stats
$total_orders = count($orders);
$orders_with_json_payment = 0;
$orders_with_payment_logs = 0;

foreach ($orders as $order) {
    if (!empty($order['json_payment_method'])) {
        $orders_with_json_payment++;
    }
    if ($order['has_payment_logs'] > 0) {
        $orders_with_payment_logs++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Order Payment Methods - AgriMarket Admin</title>
    <link rel="stylesheet" href="../styles.css">
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
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
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
        
        .card {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border: 1px solid #e5e5e5;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--medium-gray);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        
        table th, table td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .highlight {
            background-color: #fff3cd;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Update Order Payment Methods</h1>
            <p class="form-subtitle">Fix missing payment methods for orders in bulk</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="section-title">Order Payment Stats</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($orders); ?></div>
                    <div class="stat-label">Orders with Missing Payment Method</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $orders_with_json_payment; ?></div>
                    <div class="stat-label">Orders with Payment Method in JSON</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $orders_with_payment_logs; ?></div>
                    <div class="stat-label">Orders with Payment Logs</div>
                </div>
            </div>
            
            <div class="action-buttons">
                <form method="POST" action="">
                    <button type="submit" name="update_from_json" class="btn btn-primary" <?php echo $orders_with_json_payment == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-magic"></i> Auto-Extract from JSON
                    </button>
                </form>
                
                <form method="POST" action="">
                    <button type="submit" name="update_from_logs" class="btn btn-primary" <?php echo $orders_with_payment_logs == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-history"></i> Update from Payment Logs
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (count($orders) > 0): ?>
            <div class="card">
                <h2 class="section-title">Bulk Update Payment Methods</h2>
                
                <form method="POST" action="" id="bulkUpdateForm">
                    <div class="form-group">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash_on_delivery">Cash on Delivery</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="mobile_payment">Mobile Payment</option>
                            <option value="crypto">Cryptocurrency</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="bulk_update" class="btn btn-warning" id="bulkUpdateBtn" disabled>
                        <i class="fas fa-edit"></i> Update Selected Orders
                    </button>
                </form>
                
                <div style="margin-top: 1.5rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>JSON Payment Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="<?php echo !empty($order['json_payment_method']) ? 'highlight' : ''; ?>">
                                    <td>
                                        <input type="checkbox" class="order-checkbox" name="order_ids[]" value="<?php echo $order['order_id']; ?>" form="bulkUpdateForm">
                                    </td>
                                    <td><?php echo $order['order_id']; ?></td>
                                    <td>
                                        <?php echo !empty($order['customer_name']) ? htmlspecialchars($order['customer_name']) : htmlspecialchars($order['user_name']); ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($order['user_email']); ?></small>
                                    </td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><?php echo ucfirst($order['status']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                                    <td><?php echo !empty($order['json_payment_method']) ? htmlspecialchars($order['json_payment_method']) : 'Not found'; ?></td>
                                    <td>
                                        <a href="update_order_payment.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">Fix</a>
                                        <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2 class="section-title">No Orders with Missing Payment Methods</h2>
                <p>All orders have their payment methods properly set.</p>
            </div>
        <?php endif; ?>
        
        <div class="button-group" style="margin-top: 2rem; text-align: center;">
            <a href="manage_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Order Management
            </a>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const orderCheckboxes = document.querySelectorAll('.order-checkbox');
            const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
            
            // Handle "Select All" checkbox
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    orderCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    
                    updateBulkUpdateButton();
                });
            }
            
            // Handle individual checkboxes
            orderCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    updateBulkUpdateButton();
                    
                    // Update "Select All" checkbox based on individual checkboxes
                    let allChecked = true;
                    orderCheckboxes.forEach(function(cb) {
                        if (!cb.checked) {
                            allChecked = false;
                        }
                    });
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
            
            // Enable/disable bulk update button based on checkboxes
            function updateBulkUpdateButton() {
                let anyChecked = false;
                orderCheckboxes.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        anyChecked = true;
                    }
                });
                
                bulkUpdateBtn.disabled = !anyChecked;
            }
        });
    </script>
</body>
</html> 