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

// First, modify the subscription_tier column to be VARCHAR instead of ENUM
$modify_column = "ALTER TABLE vendors 
                 MODIFY COLUMN subscription_tier VARCHAR(50) NOT NULL DEFAULT 'basic'";

if (mysqli_query($conn, $modify_column)) {
    echo "Modified subscription_tier column to VARCHAR(50) successfully.<br>";
    
    // Update any existing vendors to ensure they have valid tiers
    $update_tiers = "UPDATE vendors 
                     SET subscription_tier = 'basic' 
                     WHERE subscription_tier NOT IN ('basic', 'premium', 'enterprise')";
    
    if (mysqli_query($conn, $update_tiers)) {
        echo "Updated existing vendors with invalid tiers to 'basic'.<br>";
    } else {
        echo "Error updating existing vendors: " . mysqli_error($conn) . "<br>";
    }
    
    // Add foreign key constraint
    $add_constraint = "ALTER TABLE vendors 
                      ADD CONSTRAINT fk_vendor_subscription_tier 
                      FOREIGN KEY (subscription_tier) 
                      REFERENCES subscription_tier_limits(tier)";
    
    if (mysqli_query($conn, $add_constraint)) {
        echo "Added foreign key constraint successfully.<br>";
    } else {
        echo "Error adding foreign key constraint: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Error modifying subscription_tier column: " . mysqli_error($conn) . "<br>";
}

// Display current vendors table structure
echo "<br>Current vendors table structure:<br>";
$result = mysqli_query($conn, "DESCRIBE vendors");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
        echo "<br>";
    }
} else {
    echo "Error getting table structure: " . mysqli_error($conn) . "<br>";
}

mysqli_close($conn);
?> 