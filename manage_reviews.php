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

// Handle review deletion
if (isset($_POST['delete_review'])) {
    $review_id = (int)$_POST['review_id'];
    $delete_sql = "DELETE FROM reviews WHERE review_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $review_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        $success_message = "Review #$review_id deleted successfully.";
    } else {
        $error_message = "Failed to delete review: " . mysqli_error($conn);
    }
}

// Handle adding test review
if (isset($_POST['add_review'])) {
    $test_user_id = (int)$_POST['user_id'];
    $test_product_id = (int)$_POST['product_id'];
    $test_order_id = (int)$_POST['order_id'];
    $test_rating = (int)$_POST['rating'];
    $test_comment = $_POST['comment'];
    
    $add_sql = "INSERT INTO reviews (user_id, product_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
    $add_stmt = mysqli_prepare($conn, $add_sql);
    mysqli_stmt_bind_param($add_stmt, "iiiis", $test_user_id, $test_product_id, $test_order_id, $test_rating, $test_comment);
    
    if (mysqli_stmt_execute($add_stmt)) {
        $success_message = "Test review added successfully.";
    } else {
        $error_message = "Failed to add test review: " . mysqli_error($conn);
    }
}

// Get all reviews
$reviews_query = "SELECT r.*, u.name as user_name, p.name as product_name 
                 FROM reviews r
                 JOIN users u ON r.user_id = u.user_id
                 JOIN products p ON r.product_id = p.product_id
                 ORDER BY r.created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_query);

// Get users for dropdown
$users_query = "SELECT user_id, name FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_query);
$users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users[$user['user_id']] = $user['name'];
}

// Get products for dropdown
$products_query = "SELECT product_id, name FROM products ORDER BY name";
$products_result = mysqli_query($conn, $products_query);
$products = [];
while ($product = mysqli_fetch_assoc($products_result)) {
    $products[$product['product_id']] = $product['name'];
}

// Get orders for dropdown
$orders_query = "SELECT o.order_id, o.user_id, u.name as user_name 
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.status = 'delivered' 
                ORDER BY o.created_at DESC";
$orders_result = mysqli_query($conn, $orders_query);
$orders = [];
while ($order = mysqli_fetch_assoc($orders_result)) {
    $orders[$order['order_id']] = "Order #" . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) . " - " . $order['user_name'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Reviews</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: #28a745; background-color: #d4edda; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .error { color: #dc3545; background-color: #f8d7da; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        form { margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .delete-btn { background: #dc3545; }
        .delete-btn:hover { background: #c82333; }
        .review-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .actions { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <h1>Review Management System</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <h2>Add Test Review</h2>
    <form method="post" action="">
        <div class="review-form">
            <div>
                <div class="form-group">
                    <label for="user_id">User:</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="product_id">Product:</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="order_id">Order:</label>
                    <select name="order_id" id="order_id" required>
                        <option value="">Select Order</option>
                        <?php foreach ($orders as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div>
                <div class="form-group">
                    <label for="rating">Rating (1-5):</label>
                    <select name="rating" id="rating" required>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Very Good</option>
                        <option value="3">3 - Good</option>
                        <option value="2">2 - Fair</option>
                        <option value="1">1 - Poor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="comment">Comment:</label>
                    <textarea name="comment" id="comment" rows="4" required>This is a test review.</textarea>
                </div>
                
                <button type="submit" name="add_review">Add Test Review</button>
            </div>
        </div>
    </form>
    
    <h2>All Reviews</h2>
    <?php if (mysqli_num_rows($reviews_result) > 0): ?>
        <table>
            <tr>
                <th>Review ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Order ID</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                <tr>
                    <td><?php echo $review['review_id']; ?></td>
                    <td><?php echo htmlspecialchars($review['user_name']); ?> (ID: <?php echo $review['user_id']; ?>)</td>
                    <td><?php echo htmlspecialchars($review['product_name']); ?> (ID: <?php echo $review['product_id']; ?>)</td>
                    <td><?php echo $review['order_id']; ?></td>
                    <td><?php echo $review['rating']; ?>/5</td>
                    <td><?php echo htmlspecialchars($review['comment']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($review['created_at'])); ?></td>
                    <td>
                        <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this review?');">
                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                            <button type="submit" name="delete_review" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No reviews found.</p>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="diagnostic_review.php">View Review Diagnostic</a> | 
        <a href="fix_order_status.php">Manage Order Statuses</a> | 
        <a href="update_reviews_table.php">Update Reviews Table Structure</a>
    </div>
</body>
</html> 