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
    global $conn;
    
    // Start transaction if not already in one
    mysqli_begin_transaction($conn);
    
    try {
        // Get any payment-specific details from the form
        $payment_details = $_POST[$payment_method . '_details'] ?? [];
        
        $result = null;
        
        switch ($payment_method) {
            case 'cash_on_delivery':
                $result = process_cod_payment($amount, $order_data, $user_data);
                break;
            
            case 'bank_transfer':
                $result = process_bank_transfer($amount, $order_data, $user_data, $payment_details);
                break;
            
            case 'credit_card':
                $result = process_credit_card_payment($amount, $order_data, $user_data, $payment_details);
                break;
            
            case 'paypal':
                $result = process_paypal_payment($amount, $order_data, $user_data, $payment_details);
                break;
                
            case 'mobile_payment':
                $result = process_mobile_payment($amount, $order_data, $user_data, $payment_details);
                break;
                
            case 'crypto':
                $result = process_crypto_payment($amount, $order_data, $user_data, $payment_details);
                break;
                
            default:
                throw new Exception('Invalid payment method');
        }
        
        if ($result['success']) {
            // Log successful payment attempt
            log_payment_attempt($order_data['order_id'], $payment_method, $amount, $result);
            mysqli_commit($conn);
            return $result;
        } else {
            throw new Exception($result['message'] ?? 'Payment processing failed');
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        
        // Log failed payment attempt
        $error_result = [
            'success' => false,
            'message' => $e->getMessage(),
            'transaction_id' => null,
            'status' => 'failed'
        ];
        log_payment_attempt($order_data['order_id'], $payment_method, $amount, $error_result);
        return $error_result;
    }
}

/**
 * Process Cash on Delivery Payment
 */
function process_cod_payment($amount, $order_data, $user_data) {
    // For COD, we simply mark the payment as pending
    // No actual payment processing happens at this stage
    
    $result = [
        'success' => true,
        'message' => 'Cash on Delivery payment scheduled',
        'transaction_id' => 'COD-' . $order_data['order_id'] . '-' . time(),
        'status' => 'pending'
    ];
    
    // Log the COD payment request
    log_payment_attempt($order_data['order_id'], 'cash_on_delivery', $amount, $result);
    
    return $result;
}

/**
 * Process Bank Transfer Payment
 */
function process_bank_transfer($amount, $order_data, $user_data, $payment_details = []) {
    // Generate a unique reference number for the transfer
    $reference_number = 'BT-' . $order_data['order_id'] . '-' . substr(md5(uniqid()), 0, 8);
    
    // Store additional details if provided
    $bank_name = $payment_details['bank_name'] ?? 'Not provided';
    $account_name = $payment_details['account_name'] ?? 'Not provided';
    $transfer_date = $payment_details['transfer_date'] ?? date('Y-m-d');
    
    $result = [
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
    
    // Log the bank transfer request
    log_payment_attempt($order_data['order_id'], 'bank_transfer', $amount, $result);
    
    return $result;
}

/**
 * Process Credit Card Payment
 */
function process_credit_card_payment($amount, $order_data, $user_data, $payment_details = []) {
    // Get card details
    $card_number = isset($payment_details['card_number']) ? mask_card_number($payment_details['card_number']) : 'Not provided';
    $card_name = $payment_details['card_name'] ?? 'Not provided';
    $card_expiry = $payment_details['card_expiry'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'CC-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    $result = [
        'success' => $success,
        'message' => $success ? 'Credit card payment processed successfully' : 'Credit card payment failed',
        'transaction_id' => $success ? $transaction_id : null,
        'status' => $success ? 'completed' : 'failed',
        'card_details' => [
            'last4' => substr($card_number, -4),
            'card_type' => detect_card_type($card_number)
        ]
    ];
    
    // Log the credit card payment attempt
    log_payment_attempt($order_data['order_id'], 'credit_card', $amount, $result);
    
    return $result;
}

/**
 * Process PayPal Payment
 */
function process_paypal_payment($amount, $order_data, $user_data, $payment_details = []) {
    // Get PayPal email
    $paypal_email = $payment_details['email'] ?? $user_data['email'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'PP-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    $result = [
        'success' => $success,
        'message' => $success ? 'PayPal payment initiated' : 'Failed to initialize PayPal payment',
        'transaction_id' => $success ? $transaction_id : null,
        'status' => 'pending',
        'redirect_url' => 'https://www.paypal.com/checkoutnow', // This would be provided by PayPal API
        'paypal_email' => $paypal_email
    ];
    
    // Log the PayPal payment attempt
    log_payment_attempt($order_data['order_id'], 'paypal', $amount, $result);
    
    return $result;
}

/**
 * Process Mobile Payment
 */
function process_mobile_payment($amount, $order_data, $user_data, $payment_details = []) {
    // Get mobile payment details
    $provider = $payment_details['provider'] ?? 'Not specified';
    $mobile_number = $payment_details['number'] ?? 'Not provided';
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment
    $transaction_id = 'MP-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    
    $result = [
        'success' => $success,
        'message' => $success ? 'Mobile payment initiated' : 'Failed to initialize mobile payment',
        'transaction_id' => $success ? $transaction_id : null,
        'status' => 'pending',
        'qr_code' => 'https://example.com/qr/' . $transaction_id, // This would be generated by the mobile payment API
        'provider' => $provider,
        'mobile' => $mobile_number
    ];
    
    // Log the mobile payment attempt
    log_payment_attempt($order_data['order_id'], 'mobile_payment', $amount, $result);
    
    return $result;
}

/**
 * Process Cryptocurrency Payment
 */
function process_crypto_payment($amount, $order_data, $user_data, $payment_details = []) {
    // Get crypto payment details
    $currency = $payment_details['currency'] ?? 'btc';
    $wallet = $payment_details['wallet'] ?? 'Not provided';
    
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
    
    // This is a simulation for demonstration purposes
    $success = true; // Simulate successful payment initiation
    $transaction_id = 'CRYPTO-' . time() . '-' . substr(md5(uniqid()), 0, 8);
    $wallet_address = '0x' . substr(md5(uniqid()), 0, 32); // Simulated crypto wallet address
    
    $result = [
        'success' => $success,
        'message' => $success ? 'Cryptocurrency payment initiated' : 'Failed to initialize cryptocurrency payment',
        'transaction_id' => $success ? $transaction_id : null,
        'status' => 'pending',
        'wallet_address' => $wallet_address,
        'crypto_amount' => $crypto_amount,
        'currency' => $currency,
        'customer_wallet' => $wallet
    ];
    
    // Log the crypto payment attempt
    log_payment_attempt($order_data['order_id'], 'crypto', $amount, $result);
    
    return $result;
}

/**
 * Log payment attempts for auditing and troubleshooting
 * @return bool True if logging was successful, false otherwise
 */
function log_payment_attempt($order_id, $payment_method, $amount, $result) {
    global $conn;
    
    $query = "INSERT INTO payment_logs (
        order_id, 
        payment_method, 
        amount, 
        transaction_id, 
        status, 
        response_data, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt === false) {
        error_log("Error preparing payment log statement: " . mysqli_error($conn));
        return false;
    }
    
    $transaction_id = $result['transaction_id'] ?? null;
    $status = $result['status'] ?? ($result['success'] ? 'completed' : 'failed');
    $response_data = json_encode($result);
    
    mysqli_stmt_bind_param(
        $stmt, 
        "isdsss", 
        $order_id, 
        $payment_method, 
        $amount, 
        $transaction_id, 
        $status, 
        $response_data
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error logging payment attempt: " . mysqli_stmt_error($stmt));
        return false;
    }
    
    return true;
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