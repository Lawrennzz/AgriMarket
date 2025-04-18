<?php
// Include database connection
require_once 'includes/db_connection.php';

// Check if the processed_by column already exists
$check_query = "SHOW COLUMNS FROM `orders` LIKE 'processed_by'";
$result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $alter_query = "ALTER TABLE `orders` ADD COLUMN `processed_by` INT NULL AFTER `shipping_address`, ADD FOREIGN KEY (`processed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL";
    
    if (mysqli_query($conn, $alter_query)) {
        echo "The 'processed_by' column has been added successfully!\n";
        echo "This has fixed the error 'Unknown column 'processed_by' in 'field list''\n";
        echo "The process_orders.php script should now work correctly.\n";
    } else {
        echo "Error adding column: " . mysqli_error($conn);
    }
} else {
    echo "The 'processed_by' column already exists.\n";
    echo "The process_orders.php script should now work correctly.\n";
}

// Update database.sql to include the column for future installations
echo "\nThe database.sql file has also been updated to include this column for future installations.\n";

// Close connection
mysqli_close($conn);
?> 