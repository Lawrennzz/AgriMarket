<?php
// Include necessary files
require_once 'includes/db_connection.php';

// Get connection
$conn = getConnection();

// Check vendors table structure
echo "<h3>Vendors Table Structure:</h3>";
$columns_result = mysqli_query($conn, "SHOW COLUMNS FROM vendors");
if ($columns_result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($columns_result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Get a single vendor row to check field names
$vendor_result = mysqli_query($conn, "SELECT * FROM vendors LIMIT 1");
if ($vendor_result && mysqli_num_rows($vendor_result) > 0) {
    echo "<h3>Sample Vendor Data:</h3>";
    $vendor = mysqli_fetch_assoc($vendor_result);
    echo "<pre>";
    print_r($vendor);
    echo "</pre>";
} else {
    echo "<p>No vendors found in the database.</p>";
} 