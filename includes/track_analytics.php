<?php
/**
 * Enhanced Analytics Tracking System for AgriMarket
 * 
 * This script provides functions to track various user activities
 * in a more detailed and comprehensive way.
 */

require_once 'config.php';
require_once 'db_connection.php';

/**
 * Track user activity in the extended analytics system
 * 
 * @param string $type Type of activity (search, visit, order, cart, wishlist, compare)
 * @param array $data Associated data with the activity
 * @return bool Success status
 */
function track_activity($type, $data = []) {
    $conn = getConnection();
    
    // Common data
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id() ?: uniqid('sess_');
    $device_type = detect_device();
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Extract specific data
    $product_id = isset($data['product_id']) ? $data['product_id'] : null;
    $vendor_id = isset($data['vendor_id']) ? $data['vendor_id'] : null;
    $category_id = isset($data['category_id']) ? $data['category_id'] : null;
    $quantity = isset($data['quantity']) ? $data['quantity'] : 1;
    $details = isset($data['details']) ? json_encode($data['details']) : null;
    
    // Ensure analytics_extended table exists
    ensure_analytics_tables($conn);
    
    // Insert into analytics_extended
    $query = "INSERT INTO analytics_extended 
              (user_id, vendor_id, type, product_id, category_id, quantity, session_id, device_type, referrer, user_ip, details, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare analytics statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param(
        $stmt, 
        "iisisissss", 
        $user_id, 
        $vendor_id, 
        $type, 
        $product_id, 
        $category_id, 
        $quantity, 
        $session_id, 
        $device_type, 
        $referrer, 
        $user_ip, 
        $details
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        error_log("Failed to track analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    
    // For backward compatibility, also track in the original analytics table
    track_legacy_analytics($conn, $type, $product_id, $quantity);
    
    return $success;
}

/**
 * Track analytics in the original analytics table for backward compatibility
 * 
 * @param mysqli $conn Database connection
 * @param string $type Activity type
 * @param int $product_id Product ID
 * @param int $quantity Count/quantity
 * @return bool Success status
 */
function track_legacy_analytics($conn, $type, $product_id = null, $quantity = 1) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id() ?: uniqid('sess_');
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Map to legacy types if needed
    $legacy_type = $type;
    if (!in_array($type, ['search', 'visit', 'order'])) {
        // For backward compatibility, new activity types are logged as 'visit'
        $legacy_type = 'visit';
    }
    
    $query = "INSERT INTO analytics 
              (type, product_id, session_id, user_id, user_ip, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Failed to prepare legacy analytics statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "sisis", $legacy_type, $product_id, $session_id, $user_id, $user_ip);
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        error_log("Failed to track legacy analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Helper function to detect device type
 * 
 * @return string Device type (desktop, mobile, tablet)
 */
function detect_device() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/mobile|android|touch|iphone|ipod|blackberry|palm/i', $user_agent)) {
        return 'mobile';
    }
    
    if (preg_match('/ipad|tablet|playbook|silk/i', $user_agent)) {
        return 'tablet';
    }
    
    return 'desktop';
}

/**
 * Ensure all needed analytics tables exist
 * 
 * @param mysqli $conn Database connection
 */
function ensure_analytics_tables($conn) {
    // Check if analytics_extended table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'analytics_extended'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create the table if it doesn't exist
        $create_table = "CREATE TABLE analytics_extended (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            vendor_id INT,
            type VARCHAR(50) NOT NULL,
            product_id INT,
            category_id INT,
            quantity INT DEFAULT 1,
            session_id VARCHAR(100) NOT NULL,
            device_type VARCHAR(20),
            referrer VARCHAR(255),
            user_ip VARCHAR(45),
            details JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (type),
            INDEX (product_id),
            INDEX (user_id),
            INDEX (vendor_id),
            INDEX (category_id),
            INDEX (created_at)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            error_log("Failed to create analytics_extended table: " . mysqli_error($conn));
        }
    }
    
    // Check if analytics table exists
    $check_legacy = mysqli_query($conn, "SHOW TABLES LIKE 'analytics'");
    if (mysqli_num_rows($check_legacy) == 0) {
        // Create the original analytics table
        $create_legacy = "CREATE TABLE analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            product_id INT,
            session_id VARCHAR(100),
            user_id INT,
            user_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (type),
            INDEX (product_id),
            INDEX (user_id),
            INDEX (created_at)
        )";
        
        if (!mysqli_query($conn, $create_legacy)) {
            error_log("Failed to create analytics table: " . mysqli_error($conn));
        }
    }
    
    // Check if product_search_logs table exists
    $check_search = mysqli_query($conn, "SHOW TABLES LIKE 'product_search_logs'");
    if (mysqli_num_rows($check_search) == 0) {
        // Create the product search logs table
        $create_search = "CREATE TABLE product_search_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            search_term VARCHAR(255) NOT NULL,
            product_ids JSON,
            session_id VARCHAR(100),
            user_id INT,
            user_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (search_term),
            INDEX (user_id),
            INDEX (created_at)
        )";
        
        if (!mysqli_query($conn, $create_search)) {
            error_log("Failed to create product_search_logs table: " . mysqli_error($conn));
        }
    }
    
    // Check if product_visits table exists
    $check_visits = mysqli_query($conn, "SHOW TABLES LIKE 'product_visits'");
    if (mysqli_num_rows($check_visits) == 0) {
        // Create the product visits table
        $create_visits = "CREATE TABLE product_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT,
            session_id VARCHAR(100) NOT NULL,
            user_ip VARCHAR(45),
            visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (product_id),
            INDEX (user_id),
            INDEX (visit_date)
        )";
        
        if (!mysqli_query($conn, $create_visits)) {
            error_log("Failed to create product_visits table: " . mysqli_error($conn));
        }
    }
}

/**
 * Track product view
 * 
 * @param int $product_id Product ID
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_product_view($product_id, $product_data = []) {
    $conn = getConnection();
    
    // Track in detailed analytics
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'details' => $product_data
    ];
    
    // Record in product_visits for specific view tracking
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id() ?: uniqid('sess_');
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    ensure_analytics_tables($conn);
    
    $query = "INSERT INTO product_visits 
              (product_id, user_id, session_id, user_ip, visit_date) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiss", $product_id, $user_id, $session_id, $user_ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    return track_activity('visit', $data);
}

/**
 * Track product search
 * 
 * @param string $search_query Search query
 * @param int $category_id Category ID if filtered
 * @param array $results_data Data about search results
 * @return bool Success status
 */
function track_product_search($search_query, $category_id = null, $results_data = []) {
    $conn = getConnection();
    
    // Generate a session ID if one doesn't exist
    $session_id = session_id() ?: uniqid('sess_');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Extract product IDs from results
    $product_ids = [];
    if (!empty($results_data)) {
        foreach ($results_data as $product) {
            if (isset($product['product_id'])) {
                $product_ids[] = $product['product_id'];
            }
        }
    }
    
    // First, log the search term to product_search_logs
    ensure_analytics_tables($conn);
    
    $search_sql = "INSERT INTO product_search_logs 
                  (search_term, product_ids, session_id, user_id, user_ip, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $search_sql);
    if (!$stmt) {
        error_log("Failed to prepare search log query: " . mysqli_error($conn));
        return false;
    }
    
    $product_ids_json = json_encode($product_ids);
    mysqli_stmt_bind_param($stmt, "sssis", $search_query, $product_ids_json, $session_id, $user_id, $user_ip);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Now track this search in the enhanced analytics system
    $data = [
        'category_id' => $category_id,
        'details' => [
            'query' => $search_query,
            'results_count' => count($product_ids),
            'products_found' => $product_ids,
            'filters' => $_GET ?? null
        ]
    ];
    
    track_activity('search', $data);
    
    // For each product in the search results, record an impression
    if (!empty($product_ids)) {
        foreach ($results_data as $product) {
            $product_data = [
                'product_id' => $product['product_id'],
                'vendor_id' => $product['vendor_id'] ?? null,
                'category_id' => $product['category_id'] ?? null,
                'details' => [
                    'source' => 'search',
                    'query' => $search_query
                ]
            ];
            track_activity('impression', $product_data);
        }
    }
    
    return $result;
}

/**
 * Track order placement
 * 
 * @param int $order_id Order ID
 * @param array $order_items Order items
 * @param float $total_amount Total order amount
 * @return bool Success status
 */
function track_order_placement($order_id, $order_items = [], $total_amount = 0) {
    foreach ($order_items as $item) {
        $data = [
            'product_id' => $item['product_id'],
            'vendor_id' => $item['vendor_id'] ?? null,
            'category_id' => $item['category_id'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
            'details' => [
                'order_id' => $order_id,
                'price' => $item['price'] ?? 0,
                'subtotal' => ($item['quantity'] ?? 1) * ($item['price'] ?? 0),
                'total_order_amount' => $total_amount
            ]
        ];
        
        track_activity('order', $data);
    }
    
    return true;
}

/**
 * Track cart actions
 * 
 * @param string $action Action (add, remove, update)
 * @param int $product_id Product ID
 * @param int $quantity Quantity
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_cart_action($action, $product_id, $quantity = 1, $product_data = []) {
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'quantity' => $quantity,
        'details' => [
            'action' => $action,
            'price' => $product_data['price'] ?? null
        ]
    ];
    
    return track_activity('cart_' . $action, $data);
}

/**
 * Track wishlist actions
 * 
 * @param string $action Action (add, remove)
 * @param int $product_id Product ID
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_wishlist_action($action, $product_id, $product_data = []) {
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'details' => [
            'action' => $action
        ]
    ];
    
    return track_activity('wishlist_' . $action, $data);
}

/**
 * Purge fake/test analytics data
 * 
 * @param string $table Table to purge (analytics, analytics_extended, product_search_logs, product_visits)
 * @return bool Success status
 */
function purge_analytics_data($table = 'all') {
    $conn = getConnection();
    $success = true;
    
    // Ensure user is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        return false;
    }
    
    // Determine which tables to purge
    $tables = [];
    if ($table === 'all') {
        $tables = ['analytics', 'analytics_extended', 'product_search_logs', 'product_visits'];
    } else {
        $tables = [$table];
    }
    
    // Purge each table
    foreach ($tables as $t) {
        $query = "TRUNCATE TABLE $t";
        if (!mysqli_query($conn, $query)) {
            error_log("Failed to purge $t: " . mysqli_error($conn));
            $success = false;
        }
    }
    
    return $success;
} 