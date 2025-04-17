<?php
// Include database connection
include 'config.php';

// Check if the user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Only administrators can run this script.");
}

echo "<h2>Updating Order Totals</h2>";

// Start the process
$count = 0;
$errors = 0;

// Get all orders where subtotal, shipping or tax is 0 or NULL
$query = "SELECT o.order_id, o.total, o.subtotal, o.shipping, o.tax, 
          (SELECT SUM(oi.price * oi.quantity) FROM order_items oi WHERE oi.order_id = o.order_id) AS calculated_subtotal
          FROM orders o 
          WHERE o.subtotal IS NULL OR o.subtotal = 0 
          OR o.shipping IS NULL OR o.shipping = 0 
          OR o.tax IS NULL OR o.tax = 0";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error fetching orders: " . mysqli_error($conn));
}

echo "<p>Found " . mysqli_num_rows($result) . " orders with missing total information.</p>";

// Process each order
while ($order = mysqli_fetch_assoc($result)) {
    $order_id = $order['order_id'];
    $total = $order['total'];
    
    // If we have a calculated subtotal from order items, use it
    if ($order['calculated_subtotal']) {
        $subtotal = $order['calculated_subtotal'];
        
        // Standard shipping is $5.00
        $shipping = 5.00;
        
        // Tax is 5% of subtotal
        $tax = $subtotal * 0.05;
        
        // Check if our calculated values are reasonably close to the total
        $calculated_total = $subtotal + $shipping + $tax;
        $total_diff = abs($calculated_total - $total);
        
        // If more than $1 difference, adjust shipping to match the total
        if ($total_diff > 1) {
            $shipping = $total - $subtotal - $tax;
            // If shipping is negative, adjust tax instead
            if ($shipping < 0) {
                $shipping = 5.00;
                $tax = $total - $subtotal - $shipping;
            }
        }
    } else {
        // If we don't have calculated subtotal, estimate based on total
        $shipping = 5.00;
        $tax = $total * 0.05;
        $subtotal = $total - $shipping - $tax;
    }
    
    // Update the order
    $update_query = "UPDATE orders SET subtotal = ?, shipping = ?, tax = ? WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "dddi", $subtotal, $shipping, $tax, $order_id);
        if (mysqli_stmt_execute($stmt)) {
            $count++;
            echo "<p>Updated order #$order_id: Set subtotal=$subtotal, shipping=$shipping, tax=$tax</p>";
        } else {
            $errors++;
            echo "<p>Error updating order #$order_id: " . mysqli_error($conn) . "</p>";
        }
        mysqli_stmt_close($stmt);
    } else {
        $errors++;
        echo "<p>Error preparing statement for order #$order_id: " . mysqli_error($conn) . "</p>";
    }
}

echo "<h3>Summary</h3>";
echo "<p>Successfully updated: $count orders</p>";
echo "<p>Errors: $errors</p>";
echo "<p><a href='manage_orders.php'>Return to Order Management</a></p>";
?> 