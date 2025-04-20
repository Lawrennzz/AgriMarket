<?php
/**
 * Utility functions for the AgriMarket website
 */

/**
 * Get database connection
 * 
 * @return mysqli Database connection object
 */
// Removed getConnection() function as it's already defined in config.php

/**
 * Log activity in the system
 * 
 * @param int $user_id User ID (0 for system)
 * @param string $action Action performed
 * @param string $description Description of the activity
 * @param string $ip_address IP address (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logActivity($user_id, $action, $description, $ip_address = '') {
    $conn = getConnection();
    
    // Get IP address if not provided
    if (empty($ip_address)) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // Prepare query
    $query = "INSERT INTO audit_logs (user_id, action, description, ip_address, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    // Check if audit_logs table exists
    $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'audit_logs'");
    if (mysqli_num_rows($table_exists) == 0) {
        // If table doesn't exist, just log to error_log and return
        error_log("Activity log: User $user_id performed $action - $description");
        return false;
    }
    
    // Execute query
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $action, $description, $ip_address);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    
    // Log to error_log as fallback
    error_log("Failed to log activity: User $user_id performed $action - $description");
    return false;
}

/**
 * Check if a user is an admin
 * 
 * @param int $user_id The user ID to check
 * @return bool True if the user is an admin, false otherwise
 */
function isAdmin($user_id) {
    global $conn;
    
    $query = "SELECT role FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        return $user['role'] === 'admin';
    }
    
    return false;
}

/**
 * Check if a user is a vendor
 * 
 * @param int $user_id The user ID to check
 * @return bool True if the user is a vendor, false otherwise
 */
function isVendor($user_id) {
    global $conn;
    
    $query = "SELECT v.vendor_id FROM vendors v 
              JOIN users u ON v.user_id = u.user_id 
              WHERE u.user_id = ? AND u.role = 'vendor'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_num_rows($result) > 0;
}

/**
 * Format price with currency symbol
 * 
 * @param float $price The price to format
 * @return string The formatted price
 */
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

/**
 * Get human-readable date/time
 * 
 * @param string $datetime MySQL datetime string
 * @param bool $include_time Whether to include the time
 * @return string Formatted date/time
 */
function formatDateTime($datetime, $include_time = true) {
    $format = 'F j, Y';
    if ($include_time) {
        $format .= ' g:i A';
    }
    return date($format, strtotime($datetime));
}

/**
 * Generate pagination links
 * 
 * @param int $total_records Total number of records
 * @param int $records_per_page Number of records per page
 * @param int $current_page Current page number
 * @param string $url Base URL for pagination links
 * @return string HTML for pagination links
 */
function generatePagination($total_records, $records_per_page, $current_page, $url) {
    $total_pages = ceil($total_records / $records_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $pagination = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $pagination .= '<a href="' . $url . '&page=' . ($current_page - 1) . '">&laquo; Previous</a>';
    } else {
        $pagination .= '<span class="disabled">&laquo; Previous</span>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $pagination .= '<a href="' . $url . '&page=1">1</a>';
        if ($start_page > 2) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $pagination .= '<span class="current">' . $i . '</span>';
        } else {
            $pagination .= '<a href="' . $url . '&page=' . $i . '">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        $pagination .= '<a href="' . $url . '&page=' . $total_pages . '">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . $url . '&page=' . ($current_page + 1) . '">Next &raquo;</a>';
    } else {
        $pagination .= '<span class="disabled">Next &raquo;</span>';
    }
    
    $pagination .= '</div>';
    
    return $pagination;
}

/**
 * Format date in a user-friendly way
 * 
 * @param string $date Date string in MySQL format
 * @param bool $include_time Whether to include time
 * @return string Formatted date
 */
function formatDate($date, $include_time = false) {
    if (empty($date)) {
        return 'N/A';
    }
    
    $format = 'F j, Y';
    if ($include_time) {
        $format .= ' g:i A';
    }
    
    return date($format, strtotime($date));
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted currency amount
 */
function formatCurrency($amount, $currency = '$') {
    return $currency . number_format($amount, 2);
}

/**
 * Clean and sanitize input
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate random string
 * 
 * @param int $length Length of the string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Check if user has specific role
 * 
 * @param string $role Role to check (admin, vendor, customer)
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if ($role === 'admin' && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        return true;
    }
    
    if ($role === 'vendor' && isset($_SESSION['is_vendor']) && $_SESSION['is_vendor']) {
        return true;
    }
    
    if ($role === 'customer') {
        return true; // All logged in users are customers
    }
    
    return false;
}

/**
 * Upload a file and return the path
 * 
 * @param array $file File from $_FILES
 * @param string $destination Destination directory
 * @param array $allowed_types Allowed file types
 * @param int $max_size Maximum file size in bytes
 * @return array Array with status and path/error message
 */
function uploadFile($file, $destination, $allowed_types = [], $max_size = 2097152) {
    // Create directory if it doesn't exist
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Check if file was uploaded properly
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['status' => false, 'message' => 'No file was uploaded'];
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file type
    if (!empty($allowed_types) && !in_array($file_extension, $allowed_types)) {
        return ['status' => false, 'message' => 'File type not allowed'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds limit'];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $destination . '/' . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['status' => true, 'path' => $upload_path];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Log a product search to the analytics table
 * 
 * @param object $conn Database connection
 * @param string $search_term The search term
 * @param array $product_ids Array of product IDs found in search
 * @return bool Whether the logging was successful
 */
function logProductSearch($conn, $search_term, $product_ids = []) {
    // Generate a session ID if one doesn't exist
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = session_id() ?: uniqid('sess_');
    }
    
    $session_id = $_SESSION['analytics_session_id'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // First, log the search term to product_search_logs
    $search_sql = "INSERT INTO product_search_logs 
                  (search_term, product_ids, session_id, user_id, user_ip, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $search_sql);
    if (!$stmt) {
        error_log("Failed to prepare search log query: " . mysqli_error($conn));
        return false;
    }
    
    $product_ids_json = json_encode($product_ids);
    mysqli_stmt_bind_param($stmt, "sssis", $search_term, $product_ids_json, $session_id, $user_id, $user_ip);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Now log individual product impressions in the analytics table
    if (!empty($product_ids)) {
        foreach ($product_ids as $product_id) {
            logAnalyticEvent($conn, 'search', $product_id);
        }
    }
    
    return $result;
}

/**
 * Log a product visit to the analytics table
 * 
 * @param object $conn Database connection
 * @param int $product_id Product ID being viewed
 * @return bool Whether the logging was successful
 */
function logProductView($conn, $product_id) {
    return logAnalyticEvent($conn, 'visit', $product_id);
}

/**
 * Log a vendor search to the analytics table
 * 
 * @param object $conn Database connection
 * @param int $vendor_id Vendor ID being searched
 * @return bool Whether the logging was successful
 */
function logVendorSearch($conn, $vendor_id) {
    // Generate a session ID if one doesn't exist
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = session_id() ?: uniqid('sess_');
    }
    
    $session_id = $_SESSION['analytics_session_id'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $sql = "INSERT INTO vendor_search_logs 
            (vendor_id, session_id, user_id, user_ip, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Failed to prepare vendor search log query: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "isis", $vendor_id, $session_id, $user_id, $user_ip);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Log an analytic event to the analytics table
 * 
 * @param object $conn Database connection
 * @param string $type Type of event (search, visit, etc.)
 * @param int $product_id Product ID
 * @return bool Whether the logging was successful
 */
function logAnalyticEvent($conn, $type, $product_id) {
    // Ensure tables exist
    createAnalyticsTables($conn);
    
    // Generate a session ID if one doesn't exist
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = session_id() ?: uniqid('sess_');
    }
    
    $session_id = $_SESSION['analytics_session_id'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Log to analytics table
    $sql = "INSERT INTO analytics 
            (type, product_id, session_id, user_id, user_ip, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Failed to prepare analytics query: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "sisis", $type, $product_id, $session_id, $user_id, $user_ip);
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        error_log("Failed to log analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Create analytics tables if they don't exist
 * 
 * @param object $conn Database connection
 */
function createAnalyticsTables($conn) {
    // Check if analytics table exists, if not create it
    $analytics_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'analytics'");
    if (mysqli_num_rows($analytics_table_check) == 0) {
        $create_analytics_table = "
            CREATE TABLE analytics (
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
            )
        ";
        mysqli_query($conn, $create_analytics_table);
    }
    
    // Check if product_search_logs table exists, if not create it
    $search_logs_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'product_search_logs'");
    if (mysqli_num_rows($search_logs_table_check) == 0) {
        $create_search_logs_table = "
            CREATE TABLE product_search_logs (
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
            )
        ";
        mysqli_query($conn, $create_search_logs_table);
    }
}
?> 