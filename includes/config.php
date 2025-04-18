<?php
// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'agrimarket');
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("An error occurred. Please try again later.");
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour
        'path' => '/',
        'domain' => '', // Set to your domain if needed
        'secure' => false, // Set to true for HTTPS in production
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// CSRF token (generate if not set)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Remove the function to avoid redeclaration
// Note: getConnection() is already defined in db_connection.php
?> 