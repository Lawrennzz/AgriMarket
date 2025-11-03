<?php
/**
 * Product View Tracking Functions
 * 
 * This file contains functions for tracking and analyzing product views.
 */

/**
 * Track a product view
 * 
 * @param int $product_id The product ID being viewed
 * @param string $source Optional: Source of the view (direct, search, etc.)
 * @return bool Success or failure
 */
function track_product_view($product_id, $source = 'direct') {
    global $conn;
    
    // Get user info if available
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Insert the view record
    $query = "INSERT INTO product_views (product_id, user_id, ip_address, user_agent, source) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("iisss", $product_id, $user_id, $ip_address, $user_agent, $source);
    $result = $stmt->execute();
    $stmt->close();
    
    // Record in analytics table
    $analytics_data = [
        'product_id' => $product_id,
        'source' => $source,
        'user_id' => $user_id,
        'ip' => $ip_address
    ];
    
    record_analytics('product_view', $analytics_data);
    
    return $result;
}

/**
 * Record analytics data in the database
 * 
 * @param string $action The type of action being recorded
 * @param array $data Additional data to record
 * @return bool Success or failure
 */
function record_analytics($action, $data = []) {
    global $conn;
    
    // Serialize data array for storage
    $data_json = json_encode($data);
    
    // Get timestamp
    $timestamp = date('Y-m-d H:i:s');
    
    // Create SQL query
    $query = "INSERT INTO analytics (action, data, timestamp) VALUES (?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sss", $action, $data_json, $timestamp);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get the most viewed products
 * 
 * @param int $limit Number of products to return
 * @param string $period Time period (day, week, month, total)
 * @return array Array of products with view counts
 */
function get_most_viewed_products($limit = 10, $period = 'total') {
    global $conn;
    
    $time_condition = '';
    
    switch ($period) {
        case 'day':
            $time_condition = "AND view_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $time_condition = "AND view_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $time_condition = "AND view_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        default:
            $time_condition = '';
    }
    
    $query = "SELECT p.product_id, p.name, COUNT(pv.id) as views 
              FROM product_views pv
              JOIN products p ON pv.product_id = p.product_id
              WHERE 1=1 $time_condition
              GROUP BY p.product_id
              ORDER BY views DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $stmt->close();
    return $products;
}

/**
 * Get view statistics for a specific product
 * 
 * @param int $product_id The product ID
 * @return array Statistics for the product
 */
function get_product_view_stats($product_id) {
    global $conn;
    
    $stats = [];
    
    // Total views
    $query = "SELECT COUNT(*) as total FROM product_views WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_views'] = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Views in last 24 hours
    $query = "SELECT COUNT(*) as count FROM product_views 
              WHERE product_id = ? AND view_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['views_24h'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Views in last 7 days
    $query = "SELECT COUNT(*) as count FROM product_views 
              WHERE product_id = ? AND view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['views_7days'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Views by source
    $query = "SELECT source, COUNT(*) as count FROM product_views 
              WHERE product_id = ? GROUP BY source ORDER BY count DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['views_by_source'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['views_by_source'][$row['source']] = $row['count'];
    }
    $stmt->close();
    
    return $stats;
}

/**
 * Get view trend data for charts
 * 
 * @param int $product_id The product ID
 * @param int $days Number of days to include
 * @return array Daily view counts
 */
function get_product_view_trend($product_id, $days = 30) {
    global $conn;
    
    $query = "SELECT DATE(view_date) as date, COUNT(*) as views 
              FROM product_views 
              WHERE product_id = ? 
              AND view_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(view_date)
              ORDER BY date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $product_id, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trend_data = [];
    while ($row = $result->fetch_assoc()) {
        $trend_data[$row['date']] = $row['views'];
    }
    
    // Fill in missing dates with zero views
    $end_date = new DateTime();
    $start_date = new DateTime();
    $start_date->modify("-$days days");
    
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start_date, $interval, $end_date);
    
    $complete_data = [];
    foreach ($date_range as $date) {
        $date_string = $date->format('Y-m-d');
        $complete_data[$date_string] = isset($trend_data[$date_string]) ? $trend_data[$date_string] : 0;
    }
    
    $stmt->close();
    return $complete_data;
}

/**
 * Get the client IP address
 * 
 * @return string The client IP address
 */
function get_client_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP passed from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        // Direct IP
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/**
 * Gets the total view count for a product
 * 
 * @param int $product_id The ID of the product
 * @return int The total number of views
 */
function get_product_view_count($product_id) {
    global $conn;
    
    // Make sure we have a connection
    if (!isset($conn) || !$conn) {
        // Connect to database if not already connected
        require_once 'db_connection.php';
        $conn = getConnection();
    }
    
    $sql = "SELECT COUNT(*) as view_count FROM product_views WHERE product_id = ?";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['view_count'];
    } catch (Exception $e) {
        error_log("Error getting product view count: " . $e->getMessage());
        return 0;
    }
} 