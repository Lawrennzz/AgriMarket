<?php
// Include config file
include 'config.php';

// SQL to create payment_logs table
$payment_logs_table = "
CREATE TABLE IF NOT EXISTS payment_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
)";

// Execute the SQL
if (mysqli_query($conn, $payment_logs_table)) {
    echo "Payment logs table created successfully";
} else {
    echo "Error creating payment_logs table: " . mysqli_error($conn);
}

// Check if any records exist in the payment_logs table
$check_query = "SELECT COUNT(*) as count FROM payment_logs";
$result = mysqli_query($conn, $check_query);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    // Insert sample payment logs
    $sample_queries = [
        "INSERT INTO payment_logs (order_id, payment_method, amount, status, details) 
         SELECT order_id, payment_method, total, payment_status, 'Initial payment record' 
         FROM orders 
         WHERE payment_method IS NOT NULL AND payment_status IS NOT NULL",
         
        "INSERT INTO payment_logs (order_id, payment_method, amount, status, details)
         SELECT o.order_id, 
                COALESCE(o.payment_method, JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method'))), 
                o.total, 
                COALESCE(o.payment_status, 'pending'), 
                'Generated from order data'
         FROM orders o
         WHERE o.payment_method IS NULL AND JSON_EXTRACT(o.shipping_address, '$.payment_method') IS NOT NULL
         AND NOT EXISTS (SELECT 1 FROM payment_logs pl WHERE pl.order_id = o.order_id)"
    ];
    
    $success = true;
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($sample_queries as $query) {
            if (!mysqli_query($conn, $query)) {
                throw new Exception("Error inserting sample data: " . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        echo "<br>Sample payment logs created successfully";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<br>Error: " . $e->getMessage();
        $success = false;
    }
} else {
    echo "<br>Payment logs table already has data";
}

// Update payment_processor.php to ensure it logs to payment_logs
$log_function_check = "
SELECT COUNT(*) as count 
FROM information_schema.routines 
WHERE routine_name = 'log_payment_attempt' 
AND routine_type = 'FUNCTION'
AND routine_schema = DATABASE()";

$log_function_result = mysqli_query($conn, $log_function_check);
if (!$log_function_result) {
    echo "<br>Error checking log_payment_attempt function: " . mysqli_error($conn);
} else {
    $log_function_row = mysqli_fetch_assoc($log_function_result);
    if ($log_function_row['count'] == 0) {
        echo "<br>The log_payment_attempt function is handled in PHP code, not as a MySQL function. Make sure the payment_processor.php file includes code to log to payment_logs table.";
    }
}

echo "<br><br>Done! You can now <a href='orders.php'>go back to orders</a> or <a href='index.php'>return home</a>.";
?> 