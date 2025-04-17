<?php
// Include database connection
include 'config.php';

// Check if the user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Only administrators can run this script.");
}

echo "<h2>Adding Required Columns to Orders Table</h2>";

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

// Start the process
$messages = [];
$errors = [];

// Check and add payment_method column
if (!columnExists($conn, 'orders', 'payment_method')) {
    $query = "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'payment_method' column to orders table.";
    } else {
        $errors[] = "Error adding 'payment_method' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'payment_method' column already exists.";
}

// Check and add subtotal column
if (!columnExists($conn, 'orders', 'subtotal')) {
    $query = "ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'subtotal' column to orders table.";
    } else {
        $errors[] = "Error adding 'subtotal' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'subtotal' column already exists.";
}

// Check and add shipping column
if (!columnExists($conn, 'orders', 'shipping')) {
    $query = "ALTER TABLE orders ADD COLUMN shipping DECIMAL(10,2) DEFAULT 0";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'shipping' column to orders table.";
    } else {
        $errors[] = "Error adding 'shipping' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'shipping' column already exists.";
}

// Check and add tax column
if (!columnExists($conn, 'orders', 'tax')) {
    $query = "ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'tax' column to orders table.";
    } else {
        $errors[] = "Error adding 'tax' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'tax' column already exists.";
}

// Check and add payment_status column if it doesn't exist
if (!columnExists($conn, 'orders', 'payment_status')) {
    $query = "ALTER TABLE orders ADD COLUMN payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending'";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'payment_status' column to orders table.";
    } else {
        $errors[] = "Error adding 'payment_status' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'payment_status' column already exists.";
}

// Check and add transaction_id column if it doesn't exist
if (!columnExists($conn, 'orders', 'transaction_id')) {
    $query = "ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL";
    if (mysqli_query($conn, $query)) {
        $messages[] = "Added 'transaction_id' column to orders table.";
    } else {
        $errors[] = "Error adding 'transaction_id' column: " . mysqli_error($conn);
    }
} else {
    $messages[] = "'transaction_id' column already exists.";
}

// Output results
echo "<h3>Results:</h3>";
echo "<ul>";
foreach ($messages as $message) {
    echo "<li>$message</li>";
}
echo "</ul>";

if (!empty($errors)) {
    echo "<h3>Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

// Add links to next steps
echo "<h3>Next Steps:</h3>";
echo "<p>Now that the database structure has been updated, you can:</p>";
echo "<ul>";
echo "<li><a href='update_existing_orders.php'>Update existing orders with correct subtotal, shipping, and tax values</a></li>";
echo "<li><a href='update_order_payment.php'>Update missing payment methods for existing orders</a></li>";
echo "<li><a href='manage_orders.php'>Return to Order Management</a></li>";
echo "</ul>";
?> 