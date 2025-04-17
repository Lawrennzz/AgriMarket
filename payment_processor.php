<?php
/**
 * Payment Processor for AgriMarket
 * Handles multiple payment gateway integrations
 */

// Include config file
require_once 'config.php';

/**
 * Process a payment using the selected payment method
 * @param string $payment_method The payment method (cash_on_delivery, bank_transfer, credit_card, paypal, mobile_payment, crypto)
 * @param float $amount The payment amount
 * @param array $order_data Order information
 * @param array $user_data User information
 * @return array Result with success status and message
 */
function process_payment($payment_method, $amount, $order_data, $user_data) {
    // Get any payment-specific details from the form
    $payment_details = $_POST[$payment_method . '_details'] ?? [];
    
    switch ($payment_method) {
        case 'cash_on_delivery':
            return process_cod_payment($amount, $order_data, $user_data);
        
        case 'bank_transfer':
            return process_bank_transfer($amount, $order_data, $user_data, $payment_details);
        
        case 'credit_card':
            return process_credit_card_payment($amount, $order_data, $user_data, $payment_details);
        
        case 'paypal':
            return process_paypal_payment($amount, $order_data, $user_data, $payment_details);
            
        case 'mobile_payment':
            return process_mobile_payment($amount, $order_data, $user_data, $payment_details);
            
        case 'crypto':
            return process_crypto_payment($amount, $order_data, $user_data, $payment_details);
            
        default:
            return [
                'success' => false,
                'message' => 'Invalid payment method',
                'transaction_id' => null
            ];
    }
}

/**
 * Process Cash on Delivery Payment
 */
function process_cod_payment($amount, $order_data, $user_data) {
    // For COD, we simply mark the payment as pending
    // No actual payment processing happens at this stage
    
    // Log the COD payment request
    log_payment_attempt('cash_on_delivery', $amount, $order_data['order_id'], true);
    
    return [
        'success' => true,
        'message' => 'Cash on Delivery payment scheduled',
        'transaction_id' => 'COD-' . $order_data['order_id'] . '-' . time(),
        'status' => 'pending'
    ];
}

/**
 * Process Bank Transfer Payment
 */
function process_bank_transfer($amount, $order_data, $user_data, $payment_details = []) {
    // Generate a unique reference number for the transfer
    $reference_number = 'BT-' . $order_data['order_id'] . '-' . substr(md5(uniqid()), 0, 8);
    
    // In a real implementation, you would:
    // 1. Generate bank account details or payment instructions
    // 2. Send these to the customer
    // 3. Set up a notification system for when the payment is received
    
    // Store additional details if provided
    $bank_name = $payment_details['bank_name'] ?? 'Not provided';
    $account_name = $payment_details['account_name'] ?? 'Not provided';
    $transfer_date = $payment_details['transfer_date'] ?? date('Y-m-d');
    
    // Log additional details for reference
    $details = "Bank: $bank_name, Account: $account_name, Expected: $transfer_date";
    
    // Log the bank transfer request
    log_payment_attempt('bank_transfer', $amount, $order_data['order_id'], true, $details);
    
    return [
        'success' => true,
        'message' => 'Please use reference number ' . $reference_number . ' when making your bank transfer',
        'transaction_id' => $reference_number,
        'status' => 'pending',
        'bank_details' => [
            'account_name' => 'AgriMarket Inc.',
            'account_number' => '1234567890',
            'bank_name' => 'Agricultural Bank',
            'reference' => $reference_number,
            'customer_bank' => $bank_name,
            'transfer_date' => $transfer_date
        ]
    ];
}

/**
 * Process Credit Card Payment
 */
function process_credit_card_payment($amount, $order_data, $user_data, $payment_details = []) {
    // In a real implementation, you would:
    // 1. Integrate with a payment gateway like Stripe, PayPal, or Braintree
    // 2. Validate the credit card details
    // 3. Process the payment through the gateway
    // 4. Handle the response
    
    // Get card details
    $card_number = isset($payment_details['card_number']) ? mask_card_number($payment_details['card_number']) : 'Not provided';
    $card_name = $payment_details['card_name'] ?? 'Not provided';
    $card_expiry = $payment_details['card_expiry'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'CC-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    // Log additional details
    $details = "Card: $card_number, Name: $card_name, Exp: $card_expiry";
    
    // Log the credit card payment attempt
    log_payment_attempt('credit_card', $amount, $order_data['order_id'], $success, $details);
    
    if ($success) {
        return [
            'success' => true,
            'message' => 'Credit card payment processed successfully',
            'transaction_id' => $transaction_id,
            'status' => 'completed',
            'card_details' => [
                'last4' => substr($card_number, -4),
                'card_type' => detect_card_type($card_number)
            ]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Credit card payment failed. Please try again or use a different payment method.',
            'transaction_id' => null
        ];
    }
}

/**
 * Process PayPal Payment
 */
function process_paypal_payment($amount, $order_data, $user_data, $payment_details = []) {
    // In a real implementation, you would:
    // 1. Integrate with PayPal's API
    // 2. Create a payment and redirect the user to PayPal's site
    // 3. Handle the callback when the user completes payment
    
    // Get PayPal email
    $paypal_email = $payment_details['email'] ?? $user_data['email'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'PP-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    // Log additional details
    $details = "PayPal Email: $paypal_email";
    
    // Log the PayPal payment attempt
    log_payment_attempt('paypal', $amount, $order_data['order_id'], $success, $details);
    
    if ($success) {
        return [
            'success' => true,
            'message' => 'PayPal payment initiated',
            'transaction_id' => $transaction_id,
            'redirect_url' => 'https://www.paypal.com/checkoutnow', // This would be provided by PayPal API
            'status' => 'pending',
            'paypal_email' => $paypal_email
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to initialize PayPal payment. Please try again.',
            'transaction_id' => null
        ];
    }
}

/**
 * Process Mobile Payment
 */
function process_mobile_payment($amount, $order_data, $user_data, $payment_details = []) {
    // In a real implementation, you would:
    // 1. Integrate with mobile payment providers
    // 2. Generate a payment request for the mobile payment app
    // 3. Handle the callback when payment is completed
    
    // Get mobile payment details
    $provider = $payment_details['provider'] ?? 'Not specified';
    $mobile_number = $payment_details['number'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'MP-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    // Log additional details
    $details = "Provider: $provider, Mobile: $mobile_number";
    
    // Log the mobile payment attempt
    log_payment_attempt('mobile_payment', $amount, $order_data['order_id'], $success, $details);
    
    if ($success) {
        return [
            'success' => true,
            'message' => 'Mobile payment initiated',
            'transaction_id' => $transaction_id,
            'qr_code' => 'https://example.com/qr/' . $transaction_id, // This would be generated by the mobile payment API
            'status' => 'pending',
            'provider' => $provider,
            'mobile' => $mobile_number
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to initialize mobile payment. Please try again.',
            'transaction_id' => null
        ];
    }
}

/**
 * Process Cryptocurrency Payment
 */
function process_crypto_payment($amount, $order_data, $user_data, $payment_details = []) {
    // In a real implementation, you would:
    // 1. Integrate with a cryptocurrency payment processor
    // 2. Generate a wallet address for payment
    // 3. Monitor the blockchain for payment confirmation
    
    // Get crypto payment details
    $currency = $payment_details['currency'] ?? 'btc';
    $wallet = $payment_details['wallet'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment initiation
    $transaction_id = 'CRYPTO-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    $wallet_address = '0x' . substr(md5(uniqid()), 0, 32); // Simulated crypto wallet address
    
    // Calculate equivalent amount in cryptocurrency (example with Bitcoin)
    $crypto_amount = $amount;
    switch ($currency) {
        case 'btc':
            $crypto_amount = $amount / 50000; // Simplified conversion
            break;
        case 'eth':
            $crypto_amount = $amount / 2000; // Simplified conversion
            break;
        case 'ltc':
            $crypto_amount = $amount / 100; // Simplified conversion
            break;
        case 'usdt':
            $crypto_amount = $amount; // 1:1 conversion
            break;
    }
    
    // Log additional details
    $details = "Currency: $currency, Customer Wallet: $wallet, Amount: $crypto_amount $currency";
    
    // Log the crypto payment attempt
    log_payment_attempt('crypto', $amount, $order_data['order_id'], $success, $details);
    
    if ($success) {
        return [
            'success' => true,
            'message' => 'Cryptocurrency payment initiated',
            'transaction_id' => $transaction_id,
            'wallet_address' => $wallet_address,
            'crypto_amount' => $crypto_amount,
            'currency' => $currency,
            'status' => 'pending',
            'customer_wallet' => $wallet
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to initialize cryptocurrency payment. Please try again.',
            'transaction_id' => null
        ];
    }
}

/**
 * Log payment attempts for auditing and troubleshooting
 */
function log_payment_attempt($payment_method, $amount, $order_id, $success, $details = '') {
    global $conn;
    
    // First, check if payment_logs table exists, if not create it
    $check_table = "SHOW TABLES LIKE 'payment_logs'";
    $table_result = mysqli_query($conn, $check_table);
    
    if (!$table_result || mysqli_num_rows($table_result) === 0) {
        // Create payment_logs table
        $create_table = "CREATE TABLE IF NOT EXISTS payment_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            payment_method VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            order_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL
        )";
        mysqli_query($conn, $create_table);
    }
    
    // Check if payment_logs table has details column
    $check_column = "SHOW COLUMNS FROM payment_logs LIKE 'details'";
    $column_result = mysqli_query($conn, $check_column);
    
    // Only check mysqli_num_rows if query was successful
    if ($column_result && mysqli_num_rows($column_result) === 0) {
        // Add details column if it doesn't exist
        $add_column = "ALTER TABLE payment_logs ADD COLUMN details TEXT";
        mysqli_query($conn, $add_column);
    }
    
    $query = "INSERT INTO payment_logs (payment_method, amount, order_id, status, details, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    $status = $success ? 'success' : 'failed';
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sdiss", $payment_method, $amount, $order_id, $status, $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    } else {
        error_log("Failed to log payment attempt: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Helper function to mask a credit card number
 */
function mask_card_number($number) {
    // Remove any non-digit characters
    $number = preg_replace('/\D/', '', $number);
    
    // Keep first 6 and last 4 digits
    $length = strlen($number);
    if ($length > 10) {
        $masked = substr($number, 0, 6) . str_repeat('*', $length - 10) . substr($number, -4);
        return $masked;
    }
    
    return $number;
}

/**
 * Helper function to detect card type from card number
 */
function detect_card_type($number) {
    $number = preg_replace('/\D/', '', $number);
    
    if (empty($number)) {
        return 'Unknown';
    }
    
    // Check for card types based on prefix
    if (preg_match('/^4/', $number)) {
        return 'Visa';
    } elseif (preg_match('/^5[1-5]/', $number)) {
        return 'MasterCard';
    } elseif (preg_match('/^3[47]/', $number)) {
        return 'American Express';
    } elseif (preg_match('/^6(?:011|5)/', $number)) {
        return 'Discover';
    } else {
        return 'Other';
    }
}

/**
 * Update payment status for an order
 */
function update_payment_status($order_id, $transaction_id, $status, $payment_method = null) {
    global $conn;
    
    // First, check if orders table has payment_status column
    $check_status_column = "SHOW COLUMNS FROM orders LIKE 'payment_status'";
    $status_result = mysqli_query($conn, $check_status_column);
    
    if (!$status_result || mysqli_num_rows($status_result) === 0) {
        // Add payment_status column if it doesn't exist
        $add_status = "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending'";
        if (!mysqli_query($conn, $add_status)) {
            error_log("Failed to add payment_status column: " . mysqli_error($conn));
            return false;
        }
    }
    
    // Check if orders table has transaction_id column
    $check_trans_column = "SHOW COLUMNS FROM orders LIKE 'transaction_id'";
    $trans_result = mysqli_query($conn, $check_trans_column);
    
    if (!$trans_result || mysqli_num_rows($trans_result) === 0) {
        // Add transaction_id column if it doesn't exist
        $add_transaction = "ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL";
        if (!mysqli_query($conn, $add_transaction)) {
            error_log("Failed to add transaction_id column: " . mysqli_error($conn));
            return false;
        }
    }
    
    // Now update the order with payment information
    if ($payment_method) {
        $query = "UPDATE orders SET payment_status = ?, transaction_id = ?, payment_method = ? WHERE order_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            error_log("Failed to prepare update payment status query: " . mysqli_error($conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "sssi", $status, $transaction_id, $payment_method, $order_id);
    } else {
        $query = "UPDATE orders SET payment_status = ?, transaction_id = ? WHERE order_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            error_log("Failed to prepare update payment status query: " . mysqli_error($conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "ssi", $status, $transaction_id, $order_id);
    }
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Verify payment status 
 * For use with asynchronous payment methods like bank transfers
 */
function verify_payment_status($transaction_id) {
    global $conn;
    
    // In a real implementation, you would:
    // 1. Check with the payment provider API for the latest status
    // 2. Update your local database with the status
    
    // For demonstration, just return the current status from the database
    $query = "SELECT o.payment_status, o.order_id 
              FROM orders o 
              WHERE o.transaction_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $transaction_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return [
                'status' => $row['payment_status'],
                'order_id' => $row['order_id']
            ];
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return [
        'status' => 'unknown',
        'order_id' => null
    ];
}
?> 