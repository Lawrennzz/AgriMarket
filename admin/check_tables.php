<?php
include '../includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return mysqli_num_rows($result) > 0;
}

// Function to get table structure
function getTableStructure($conn, $tableName) {
    $result = mysqli_query($conn, "DESCRIBE $tableName");
    if (!$result) {
        return "Error getting structure: " . mysqli_error($conn);
    }
    $structure = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $structure[] = $row;
    }
    return $structure;
}

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
echo "Database connection successful.<br><br>";

// Check subscription_change_requests table
if (tableExists($conn, 'subscription_change_requests')) {
    echo "Table 'subscription_change_requests' exists.<br>";
    echo "Structure:<br><pre>";
    print_r(getTableStructure($conn, 'subscription_change_requests'));
    echo "</pre><br>";
} else {
    echo "Table 'subscription_change_requests' does not exist!<br>";
}

// Check vendors table
if (tableExists($conn, 'vendors')) {
    echo "Table 'vendors' exists.<br>";
    echo "Structure:<br><pre>";
    print_r(getTableStructure($conn, 'vendors'));
    echo "</pre><br>";
} else {
    echo "Table 'vendors' does not exist!<br>";
}

// Sample query to check for any data
$sample_query = "SELECT COUNT(*) as count FROM vendors";
$result = mysqli_query($conn, $sample_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Number of vendors in database: " . $row['count'] . "<br>";
} else {
    echo "Error checking vendor count: " . mysqli_error($conn) . "<br>";
}

mysqli_close($conn);
?> 