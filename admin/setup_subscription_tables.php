<?php
$root_path = dirname(dirname(__FILE__));
require_once $root_path . '/includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return mysqli_num_rows($result) > 0;
}

// Create subscription_tier_limits table if it doesn't exist
if (!tableExists($conn, 'subscription_tier_limits')) {
    $create_tier_limits = "CREATE TABLE subscription_tier_limits (
        tier VARCHAR(50) PRIMARY KEY,
        product_limit INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_tier_limits)) {
        echo "Created subscription_tier_limits table successfully.<br>";
        
        // Insert default tiers
        $insert_tiers = "INSERT INTO subscription_tier_limits (tier, product_limit) VALUES
            ('basic', 10),
            ('standard', 50),
            ('premium', 200),
            ('enterprise', 999999)";
        
        if (mysqli_query($conn, $insert_tiers)) {
            echo "Inserted default subscription tiers successfully.<br>";
        } else {
            echo "Error inserting default tiers: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Error creating subscription_tier_limits table: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "subscription_tier_limits table already exists.<br>";
}

// Create subscription_change_requests table if it doesn't exist
if (!tableExists($conn, 'subscription_change_requests')) {
    $create_requests = "CREATE TABLE subscription_change_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        current_tier VARCHAR(50) NOT NULL,
        requested_tier VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
        FOREIGN KEY (current_tier) REFERENCES subscription_tier_limits(tier),
        FOREIGN KEY (requested_tier) REFERENCES subscription_tier_limits(tier)
    )";
    
    if (mysqli_query($conn, $create_requests)) {
        echo "Created subscription_change_requests table successfully.<br>";
    } else {
        echo "Error creating subscription_change_requests table: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "subscription_change_requests table already exists.<br>";
}

// Add subscription_tier column to vendors table if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM vendors LIKE 'subscription_tier'");
if (mysqli_num_rows($check_column) == 0) {
    $add_column = "ALTER TABLE vendors 
                   ADD COLUMN subscription_tier VARCHAR(50) DEFAULT 'basic',
                   ADD FOREIGN KEY (subscription_tier) REFERENCES subscription_tier_limits(tier)";
    
    if (mysqli_query($conn, $add_column)) {
        echo "Added subscription_tier column to vendors table successfully.<br>";
    } else {
        echo "Error adding subscription_tier column: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "subscription_tier column already exists in vendors table.<br>";
}

// Display current table structures
echo "<br>Current table structures:<br>";
$tables = ['subscription_tier_limits', 'subscription_change_requests', 'vendors'];
foreach ($tables as $table) {
    echo "<br>Table: $table<br>";
    $result = mysqli_query($conn, "DESCRIBE $table");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            print_r($row);
            echo "<br>";
        }
    } else {
        echo "Error getting table structure: " . mysqli_error($conn) . "<br>";
    }
}

mysqli_close($conn);
?> 