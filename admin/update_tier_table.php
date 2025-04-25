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

// Add price and features columns if they don't exist
$alter_table_query = "
    ALTER TABLE subscription_tier_limits 
    ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS features TEXT DEFAULT NULL
";

if (mysqli_query($conn, $alter_table_query)) {
    echo "Table structure updated successfully.<br>";
    
    // Update default tier features
    $update_features = [
        'basic' => "Up to 10 products\nBasic product listings\nStandard support\nMonthly analytics",
        'standard' => "Up to 50 products\nEnhanced product listings\nPriority support\nWeekly analytics\nProduct variations",
        'premium' => "Up to 200 products\nPremium product listings\n24/7 support\nDaily analytics\nProduct variations\nFeatured listings\nBulk upload",
        'enterprise' => "Unlimited products\nCustom product features\nDedicated support\nReal-time analytics\nAdvanced API access\nPriority placement\nCustom integrations"
    ];
    
    $update_prices = [
        'basic' => 0.00,
        'standard' => 29.99,
        'premium' => 99.99,
        'enterprise' => 499.99
    ];
    
    foreach ($update_features as $tier => $features) {
        $price = $update_prices[$tier];
        $update_query = "UPDATE subscription_tier_limits 
                        SET features = ?, price = ?
                        WHERE tier = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sds", $features, $price, $tier);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "Updated features and price for $tier tier.<br>";
        } else {
            echo "Error updating $tier tier: " . mysqli_error($conn) . "<br>";
        }
        mysqli_stmt_close($stmt);
    }
} else {
    echo "Error updating table structure: " . mysqli_error($conn) . "<br>";
}

// Display current table structure
echo "<br>Current table structure:<br>";
$result = mysqli_query($conn, "DESCRIBE subscription_tier_limits");
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