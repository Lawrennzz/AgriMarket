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

$steps = [];
$errors = [];

// Step 1: Check if we need to add the order_id column
$check_column_sql = "SHOW COLUMNS FROM reviews LIKE 'order_id'";
$check_column_result = mysqli_query($conn, $check_column_sql);

if (mysqli_num_rows($check_column_result) == 0) {
    // Need to add order_id column
    $steps[] = "Adding order_id column to reviews table...";
    
    // First, back up existing reviews
    $backup_sql = "CREATE TABLE reviews_backup LIKE reviews";
    if (mysqli_query($conn, $backup_sql)) {
        $steps[] = "Created backup table reviews_backup";
        
        $copy_sql = "INSERT INTO reviews_backup SELECT * FROM reviews";
        if (mysqli_query($conn, $copy_sql)) {
            $steps[] = "Copied all review data to backup table";
            
            // Find appropriate order_id values for existing reviews
            $steps[] = "Preparing to add order_id column...";
            
            // Add order_id column
            $add_column_sql = "ALTER TABLE reviews ADD COLUMN order_id INT DEFAULT NULL AFTER product_id";
            if (mysqli_query($conn, $add_column_sql)) {
                $steps[] = "Added order_id column to reviews table";
                
                // Get all current reviews
                $get_reviews_sql = "SELECT review_id, user_id, product_id FROM reviews";
                $reviews_result = mysqli_query($conn, $get_reviews_sql);
                
                if ($reviews_result) {
                    $steps[] = "Updating order_id for existing reviews...";
                    $updated_count = 0;
                    $error_count = 0;
                    
                    while ($review = mysqli_fetch_assoc($reviews_result)) {
                        // Find the most recent delivered order containing this product for this user
                        $find_order_sql = "SELECT o.order_id 
                                          FROM orders o 
                                          JOIN order_items oi ON o.order_id = oi.order_id 
                                          WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered' 
                                          ORDER BY o.created_at DESC 
                                          LIMIT 1";
                        $find_order_stmt = mysqli_prepare($conn, $find_order_sql);
                        mysqli_stmt_bind_param($find_order_stmt, "ii", $review['user_id'], $review['product_id']);
                        mysqli_stmt_execute($find_order_stmt);
                        $order_result = mysqli_stmt_get_result($find_order_stmt);
                        
                        if ($order = mysqli_fetch_assoc($order_result)) {
                            // Found an order, update the review
                            $update_review_sql = "UPDATE reviews SET order_id = ? WHERE review_id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_review_sql);
                            mysqli_stmt_bind_param($update_stmt, "ii", $order['order_id'], $review['review_id']);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $updated_count++;
                            } else {
                                $error_count++;
                            }
                        } else {
                            // No order found, assign a default order (this shouldn't happen in a normal system)
                            $steps[] = "Warning: No order found for review ID {$review['review_id']} (User: {$review['user_id']}, Product: {$review['product_id']})";
                        }
                    }
                    
                    $steps[] = "Updated order_id for $updated_count reviews. $error_count errors.";
                    
                    // Make order_id NOT NULL
                    $make_notnull_sql = "ALTER TABLE reviews MODIFY COLUMN order_id INT NOT NULL";
                    if (mysqli_query($conn, $make_notnull_sql)) {
                        $steps[] = "Changed order_id column to NOT NULL";
                    } else {
                        $errors[] = "Failed to change order_id to NOT NULL: " . mysqli_error($conn);
                    }
                    
                    // Add foreign key
                    $add_fk_sql = "ALTER TABLE reviews ADD CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(order_id)";
                    if (mysqli_query($conn, $add_fk_sql)) {
                        $steps[] = "Added foreign key constraint for order_id";
                    } else {
                        $errors[] = "Failed to add foreign key constraint: " . mysqli_error($conn);
                    }
                } else {
                    $errors[] = "Failed to retrieve existing reviews: " . mysqli_error($conn);
                }
            } else {
                $errors[] = "Failed to add order_id column: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Failed to copy review data to backup: " . mysqli_error($conn);
        }
    } else {
        $errors[] = "Failed to create backup table: " . mysqli_error($conn);
    }
} else {
    $steps[] = "order_id column already exists in reviews table.";
}

// Step 2: Check if we need to remove the unique constraint
$check_constraint_sql = "SHOW CREATE TABLE reviews";
$check_constraint_result = mysqli_query($conn, $check_constraint_sql);
$table_info = mysqli_fetch_array($check_constraint_result);
$create_table_statement = $table_info[1];

if (strpos($create_table_statement, 'UNIQUE KEY `unique_user_product`') !== false) {
    $steps[] = "Removing unique constraint on user_id and product_id...";
    
    $remove_constraint_sql = "ALTER TABLE reviews DROP INDEX unique_user_product";
    if (mysqli_query($conn, $remove_constraint_sql)) {
        $steps[] = "Removed unique constraint successfully";
    } else {
        $errors[] = "Failed to remove unique constraint: " . mysqli_error($conn);
    }
} else {
    $steps[] = "Unique constraint has already been removed.";
}

// Step 3: Add a new index on all three fields
$check_index_sql = "SHOW INDEX FROM reviews WHERE Column_name IN ('user_id', 'product_id', 'order_id') GROUP BY Key_name HAVING COUNT(*) = 3";
$check_index_result = mysqli_query($conn, $check_index_sql);

if (mysqli_num_rows($check_index_result) == 0) {
    $steps[] = "Adding new index on user_id, product_id, and order_id...";
    
    $add_index_sql = "ALTER TABLE reviews ADD INDEX user_product_order_idx (user_id, product_id, order_id)";
    if (mysqli_query($conn, $add_index_sql)) {
        $steps[] = "Added new index successfully";
    } else {
        $errors[] = "Failed to add new index: " . mysqli_error($conn);
    }
} else {
    $steps[] = "Index on user_id, product_id, and order_id already exists.";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Reviews Table</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { color: #333; }
        .step { margin-bottom: 5px; padding: 5px; }
        .error { color: #dc3545; background-color: #f8d7da; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .success { color: #28a745; background-color: #d4edda; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Update Reviews Table Structure</h1>
    
    <h2>Steps Completed:</h2>
    <div class="steps">
        <?php foreach ($steps as $step): ?>
            <div class="step"><?php echo htmlspecialchars($step); ?></div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($errors)): ?>
        <div class="success">
            <h3>✓ Update Completed Successfully</h3>
            <p>The reviews table has been successfully updated to allow multiple reviews per product.</p>
            <p>Users can now write reviews for the same product across different orders!</p>
        </div>
    <?php else: ?>
        <h2>Errors:</h2>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <h2>Next Steps:</h2>
    <ol>
        <li>Update add_review.php to store the order_id with each review</li>
        <li>Update the review checking logic in orders.php to check by order_id</li>
        <li>Test the system by writing reviews for the same product from different orders</li>
    </ol>
    
    <p><a href="fix_order_status.php">Return to Order Status Management</a></p>
</body>
</html> 