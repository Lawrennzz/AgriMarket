<?php
require_once 'config.php';
require_once 'classes/Database.php';

// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and is an admin or set a test user_id
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // For testing with a specific user, uncomment the line below
    // $user_id = 1; // Replace with the user ID you want to test
    
    // Or redirect if not admin
    if (!isset($user_id)) {
        echo "You must be an admin to access this page.";
        exit;
    }
} else {
    $user_id = $_SESSION['user_id'];
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all delivered orders for this user
$orders_query = "SELECT o.order_id, o.created_at, o.status 
                FROM orders o 
                WHERE o.user_id = ? AND o.status = 'delivered'";
$orders_stmt = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($orders_stmt, "i", $user_id);
mysqli_stmt_execute($orders_stmt);
$orders_result = mysqli_stmt_get_result($orders_stmt);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Review System Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .status-reviewed { color: green; }
        .status-not-reviewed { color: red; }
    </style>
</head>
<body>
    <h1>Review System Diagnostic</h1>
    <h2>User ID: <?php echo $user_id; ?></h2>
    
    <h3>Delivered Orders</h3>
    <?php if (mysqli_num_rows($orders_result) > 0): ?>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Products</th>
            </tr>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <tr>
                    <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <?php
                        // Get all products in this order
                        $items_query = "SELECT oi.product_id, p.name, oi.quantity
                                      FROM order_items oi
                                      JOIN products p ON oi.product_id = p.product_id
                                      WHERE oi.order_id = ?";
                        $items_stmt = mysqli_prepare($conn, $items_query);
                        mysqli_stmt_bind_param($items_stmt, "i", $order['order_id']);
                        mysqli_stmt_execute($items_stmt);
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        
                        echo "<ul>";
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            // Check if product has been reviewed for this specific order
                            $review_check_sql = "SELECT * FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?";
                            $review_stmt = mysqli_prepare($conn, $review_check_sql);
                            mysqli_stmt_bind_param($review_stmt, "iii", $user_id, $item['product_id'], $order['order_id']);
                            mysqli_stmt_execute($review_stmt);
                            $review_result = mysqli_stmt_get_result($review_stmt);
                            $has_reviewed = (mysqli_num_rows($review_result) > 0);
                            
                            $review_status = $has_reviewed ? 
                                "<span class='status-reviewed'>✓ Reviewed</span>" : 
                                "<span class='status-not-reviewed'>✗ Not Reviewed</span>";
                                
                            echo "<li>" . htmlspecialchars($item['name']) . " (Qty: " . $item['quantity'] . ") - " . $review_status;
                            
                            if (!$has_reviewed) {
                                echo " <a href='add_review.php?order_id=" . $order['order_id'] . "&product_id=" . $item['product_id'] . "'>Write Review</a>";
                            }
                            
                            echo "</li>";
                        }
                        echo "</ul>";
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No delivered orders found for this user.</p>
    <?php endif; ?>
    
    <h3>All Reviews by This User</h3>
    <?php
    $reviews_query = "SELECT r.*, p.name as product_name 
                     FROM reviews r 
                     JOIN products p ON r.product_id = p.product_id 
                     WHERE r.user_id = ?";
    $reviews_stmt = mysqli_prepare($conn, $reviews_query);
    mysqli_stmt_bind_param($reviews_stmt, "i", $user_id);
    mysqli_stmt_execute($reviews_stmt);
    $reviews_result = mysqli_stmt_get_result($reviews_stmt);
    ?>
    
    <?php if (mysqli_num_rows($reviews_result) > 0): ?>
        <table>
            <tr>
                <th>Product</th>
                <th>Rating</th>
                <th>Review Date</th>
                <th>Comment</th>
            </tr>
            <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                    <td><?php echo $review['rating']; ?> / 5</td>
                    <td><?php echo date('M j, Y', strtotime($review['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($review['comment']); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No reviews found for this user.</p>
    <?php endif; ?>
    
</body>
</html> 