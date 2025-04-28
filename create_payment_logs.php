<?php
// Include config file
include 'config.php';

// SQL to create payment_logs table
$payment_logs_table = "
CREATE TABLE IF NOT EXISTS payment_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    subtotal DECIMAL(10,2),
    tax DECIMAL(10,2),
    shipping DECIMAL(10,2),
    details TEXT,
    payment_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_payment_logs_order_id (order_id),
    INDEX idx_payment_logs_status (status),
    INDEX idx_payment_logs_transaction_id (transaction_id)
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
        // Insert from orders with payment info
        "INSERT INTO payment_logs (
            order_id, 
            payment_method, 
            amount, 
            status, 
            transaction_id,
            subtotal,
            tax,
            shipping,
            details,
            payment_details
        ) 
        SELECT 
            order_id, 
            payment_method, 
            total as amount, 
            COALESCE(payment_status, 'pending') as status,
            transaction_id,
            subtotal,
            tax,
            shipping,
            'Initial payment record',
            JSON_OBJECT(
                'payment_method', payment_method,
                'shipping_address', shipping_address
            ) as payment_details
        FROM orders 
        WHERE payment_method IS NOT NULL",
         
        // Insert from orders with payment method in shipping_address
        "INSERT INTO payment_logs (
            order_id, 
            payment_method, 
            amount, 
            status,
            subtotal,
            tax,
            shipping,
            details,
            payment_details
        )
        SELECT 
            o.order_id, 
            JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.payment_method')) as payment_method, 
            o.total as amount, 
            COALESCE(o.payment_status, 'pending') as status,
            o.subtotal,
            o.tax,
            o.shipping,
            'Generated from order shipping_address data',
            o.shipping_address as payment_details
        FROM orders o
        WHERE JSON_EXTRACT(o.shipping_address, '$.payment_method') IS NOT NULL
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

// Create function to log payment attempts if it doesn't exist
$create_log_function = "
CREATE FUNCTION IF NOT EXISTS log_payment_attempt(
    p_order_id INT,
    p_payment_method VARCHAR(50),
    p_amount DECIMAL(10,2),
    p_status VARCHAR(20),
    p_transaction_id VARCHAR(100),
    p_details TEXT,
    p_payment_details JSON
) RETURNS INT
DETERMINISTIC
BEGIN
    INSERT INTO payment_logs (
        order_id,
        payment_method,
        amount,
        status,
        transaction_id,
        details,
        payment_details,
        created_at
    ) VALUES (
        p_order_id,
        p_payment_method,
        p_amount,
        p_status,
        p_transaction_id,
        p_details,
        p_payment_details,
        CURRENT_TIMESTAMP
    );
    
    RETURN LAST_INSERT_ID();
END;
";

if (mysqli_multi_query($conn, $create_log_function)) {
    echo "<br>Log payment attempt function created or updated successfully";
} else {
    echo "<br>Error creating log function: " . mysqli_error($conn);
}

// Close the connection
mysqli_close($conn);
?> 