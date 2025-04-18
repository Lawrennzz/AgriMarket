<?php
/**
 * Database Connection File
 * Establishes a connection to the MySQL database
 */

// Include environment variable handler
require_once __DIR__ . '/env.php';

// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'agrimarket';

// Create a connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check the connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character set to ensure proper encoding
mysqli_set_charset($conn, "utf8mb4");

/**
 * Get a new database connection
 * 
 * @return mysqli Database connection object
 */
function getConnection() {
    global $conn;
    
    // If the connection is already established, return it
    if ($conn && mysqli_ping($conn)) {
        return $conn;
    }
    
    // Otherwise, create a new connection
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'agrimarket';
    
    $new_conn = mysqli_connect($host, $username, $password, $database);
    
    if (!$new_conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($new_conn, "utf8mb4");
    return $new_conn;
}

/**
 * Close the database connection
 */
function closeConnection() {
    global $conn;
    
    if ($conn) {
        mysqli_close($conn);
    }
}

// Register a shutdown function to close the connection when the script ends
register_shutdown_function('closeConnection'); 