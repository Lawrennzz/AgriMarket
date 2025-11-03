<?php
/**
 * Basic Analytics Tracking System for AgriMarket
 */

require_once 'config.php';
require_once 'db_connection.php';

/**
 * Track basic user activity
 * 
 * @param string $type Type of activity (search, visit, order)
 * @param array $data Associated data with the activity
 * @return bool Success status
 */
function track_activity($type, $data = []) {
    global $conn;
    
    // Common data
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id() ?: uniqid('sess_');
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $product_id = isset($data['product_id']) ? $data['product_id'] : null;
    
    // Ensure analytics table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'analytics'");
    if (!$check_table || mysqli_num_rows($check_table) === 0) {
        $create_table = "CREATE TABLE analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            product_id INT,
            session_id VARCHAR(100),
            user_id INT,
            user_ip VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            count INT DEFAULT 1,
            INDEX (type),
            INDEX (product_id),
            INDEX (user_id),
            INDEX (created_at)
        )";
        mysqli_query($conn, $create_table);
    }
    
    $query = "INSERT INTO analytics (type, product_id, session_id, user_id, user_ip, created_at, count) 
              VALUES (?, ?, ?, ?, ?, NOW(), 1)";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare analytics statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "sisis", $type, $product_id, $session_id, $user_id, $user_ip);
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        error_log("Failed to track analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Update product statistics
 * 
 * @param int $product_id Product ID
 * @param array $data Stats to update (views, sales, revenue)
 * @return bool Success status
 */
function update_product_stats($product_id, $data = []) {
    global $conn;
    
    // First ensure a record exists
    $check_sql = "INSERT IGNORE INTO product_stats 
                  (product_id, total_sales, total_views, total_revenue, created_at) 
                  VALUES (?, 0, 0, 0, NOW())";
    $stmt = mysqli_prepare($conn, $check_sql);
    if (!$stmt) {
        error_log("Failed to prepare product stats check statement: " . mysqli_error($conn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Build update query based on provided data
    $updates = [];
    $types = "";
    $params = [];
    
    if (isset($data['views'])) {
        $updates[] = "total_views = total_views + ?";
        $types .= "i";
        $params[] = $data['views'];
        $updates[] = "last_view_date = NOW()";
    }
    
    if (isset($data['sales'])) {
        $updates[] = "total_sales = total_sales + ?";
        $types .= "i";
        $params[] = $data['sales'];
        $updates[] = "last_sale_date = NOW()";
    }
    
    if (isset($data['revenue'])) {
        $updates[] = "total_revenue = total_revenue + ?";
        $types .= "d";
        $params[] = $data['revenue'];
    }
    
    if (!empty($updates)) {
        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE product_stats SET " . implode(", ", $updates) . " WHERE product_id = ?";
        $types .= "i";
        $params[] = $product_id;
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log("Failed to prepare product stats update statement: " . mysqli_error($conn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$success) {
            error_log("Failed to update product stats: " . mysqli_error($conn));
            return false;
        }
    }
    
    return true;
}

/**
 * Track product view
 * 
 * @param int $product_id Product ID
 * @return bool Success status
 */
function track_product_view_analytics($product_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Track the view in analytics
        $type = 'product_view';
        $success = track_activity($type, ['product_id' => $product_id]);
        
        if (!$success) {
            throw new Exception("Failed to track activity");
        }
        
        // Update product stats
        $update_stats = "INSERT INTO product_stats 
                        (product_id, total_views, last_view_date) 
                        VALUES (?, 1, NOW())
                        ON DUPLICATE KEY UPDATE 
                            total_views = total_views + 1,
                            last_view_date = NOW()";
        
        $stmt = mysqli_prepare($conn, $update_stats);
        if (!$stmt) {
            throw new Exception("Failed to prepare stats update: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$success) {
            throw new Exception("Failed to update product stats");
        }
        
        // If everything succeeded, commit the transaction
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        // If anything failed, rollback the transaction
        mysqli_rollback($conn);
        error_log("Error tracking product view: " . $e->getMessage());
        return false;
    }
}

/**
 * Track order placement
 * 
 * @param int $order_id Order ID
 * @param array $order_items Order items with product_id, quantity, and price
 * @return bool Success status
 */
function track_order_placement($order_id, $order_items = []) {
    global $conn;
    
    // Track the order in analytics
    $type = 'order';
    track_activity($type, ['order_id' => $order_id]);
    
    // Update product stats for each item
    foreach ($order_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $subtotal = $item['price'] * $quantity;
        
        update_product_stats($product_id, [
            'sales' => $quantity,
            'revenue' => $subtotal
        ]);
    }
}

/**
 * Track product search
 * 
 * @param string $search_query Search query
 * @return bool Success status
 */
function track_product_search($search_query) {
    return track_activity('search', []);
}

/**
 * Track product view button click
 * 
 * @param int $product_id Product ID being viewed
 * @param string $source Page where the view button was clicked (e.g. 'products_list', 'search_results')
 * @return bool Success status
 */
function track_product_view_click($product_id, $source = '') {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Track the click in analytics
        $type = 'product_view_click';
        $success = track_activity($type, [
            'product_id' => $product_id,
            'source' => $source
        ]);
        
        if (!$success) {
            throw new Exception("Failed to track activity");
        }
        
        // Update product stats
        $update_stats = "UPDATE product_stats 
                        SET total_view_clicks = COALESCE(total_view_clicks, 0) + 1,
                            daily_views = COALESCE(daily_views, 0) + 1,
                            weekly_views = COALESCE(weekly_views, 0) + 1,
                            monthly_views = COALESCE(monthly_views, 0) + 1,
                            last_view_click_date = NOW()
                        WHERE product_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_stats);
        if (!$stmt) {
            throw new Exception("Failed to prepare stats update: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$success) {
            throw new Exception("Failed to update product stats");
        }
        
        // If no rows were updated, create a new stats record
        if (mysqli_affected_rows($conn) === 0) {
            $insert_stats = "INSERT INTO product_stats 
                            (product_id, total_view_clicks, daily_views, weekly_views, monthly_views, last_view_click_date)
                            VALUES (?, 1, 1, 1, 1, NOW())";
            
            $stmt = mysqli_prepare($conn, $insert_stats);
            if (!$stmt) {
                throw new Exception("Failed to prepare stats insert: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if (!$success) {
                throw new Exception("Failed to insert product stats");
            }
        }
        
        // Reset periodic views if needed
        reset_periodic_views();
        
        // Commit the transaction
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        // If anything failed, rollback the transaction
        mysqli_rollback($conn);
        error_log("Error tracking product view click: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset periodic view counters
 */
function reset_periodic_views() {
    global $conn;
    
    // Reset daily views
    $reset_daily = "UPDATE product_stats 
                   SET daily_views = 0 
                   WHERE DATE(last_view_click_date) < CURDATE()";
    mysqli_query($conn, $reset_daily);
    
    // Reset weekly views
    $reset_weekly = "UPDATE product_stats 
                    SET weekly_views = 0 
                    WHERE YEARWEEK(last_view_click_date) < YEARWEEK(NOW())";
    mysqli_query($conn, $reset_weekly);
    
    // Reset monthly views
    $reset_monthly = "UPDATE product_stats 
                     SET monthly_views = 0 
                     WHERE MONTH(last_view_click_date) < MONTH(NOW()) 
                        OR YEAR(last_view_click_date) < YEAR(NOW())";
    mysqli_query($conn, $reset_monthly);
}

/**
 * Get most viewed products
 * 
 * @param int $limit Number of products to return
 * @param string $period Period to consider ('daily', 'weekly', 'monthly', 'total')
 * @return array Array of most viewed products
 */
function get_most_viewed_products_analytics($limit = 10, $period = 'total') {
    global $conn;
    
    $view_column = match($period) {
        'daily' => 'daily_views',
        'weekly' => 'weekly_views',
        'monthly' => 'monthly_views',
        default => '(COALESCE(daily_views, 0) + COALESCE(weekly_views, 0) + COALESCE(monthly_views, 0))'
    };
    
    $query = "SELECT 
                p.product_id,
                p.name AS product_name,
                p.price,
                COALESCE(ps.daily_views, 0) as daily_views,
                COALESCE(ps.weekly_views, 0) as weekly_views,
                COALESCE(ps.monthly_views, 0) as monthly_views,
                (COALESCE(ps.daily_views, 0) + COALESCE(ps.weekly_views, 0) + COALESCE(ps.monthly_views, 0)) as total_views,
                ps.last_view_click_date
              FROM 
                products p
              LEFT JOIN 
                product_stats ps ON p.product_id = ps.product_id
              ORDER BY 
                $view_column DESC
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare most viewed products query: " . mysqli_error($conn));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $products;
}