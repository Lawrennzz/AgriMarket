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
?> 