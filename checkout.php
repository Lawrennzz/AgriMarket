<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
$user_query = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);

// Initialize variables
$user = [];
$cart_items = [];
$subtotal = 0;
$item_count = 0;
$shipping = 0;
$tax = 0;
$total = 0;

if ($user_stmt) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
} else {
    $errors[] = "Error loading user details: " . mysqli_error($conn);
}

// Fetch cart items
$cart_query = "SELECT c.cart_id, c.product_id, c.quantity, 
               p.name, p.price, p.image_url, p.stock
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = ?
               ORDER BY c.created_at DESC";
$cart_stmt = mysqli_prepare($conn, $cart_query);

if ($cart_stmt) {
    mysqli_stmt_bind_param($cart_stmt, "i", $user_id);
    mysqli_stmt_execute($cart_stmt);
    $cart_result = mysqli_stmt_get_result($cart_stmt);

    // Calculate cart totals
    while ($item = mysqli_fetch_assoc($cart_result)) {
        $cart_items[] = $item;
    $subtotal += $item['price'] * $item['quantity'];
        $item_count += $item['quantity'];
}

    $shipping = $item_count > 0 ? 5.00 : 0; // Fixed shipping cost
    $tax = $subtotal * 0.05; // 5% tax
$total = $subtotal + $shipping + $tax;

    mysqli_stmt_close($cart_stmt);
} else {
    $errors[] = "Error loading cart: " . mysqli_error($conn);
}

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($city)) {
        $errors[] = "City is required";
    }
    
    if (empty($state)) {
        $errors[] = "State is required";
    }
    
    if (empty($zip)) {
        $errors[] = "ZIP code is required";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Payment method is required";
    }
    
    // If no errors, process the order
    if (empty($errors) && $item_count > 0) {
        // Create the order
        mysqli_begin_transaction($conn);
        
        try {
            // Include payment processor
            require_once 'payment_processor.php';
            
            // Insert order
            $order_query = "INSERT INTO orders (user_id, total, subtotal, shipping, tax, shipping_address, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $order_stmt = mysqli_prepare($conn, $order_query);
            
            if ($order_stmt === false) {
                throw new Exception("Error preparing order query: " . mysqli_error($conn));
            }
            
            $shipping_address = json_encode([
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'payment_method' => $payment_method
            ]);
            mysqli_stmt_bind_param($order_stmt, "idddss", $user_id, $total, $subtotal, $shipping, $tax, $shipping_address);
            
            if (!mysqli_stmt_execute($order_stmt)) {
                throw new Exception("Error inserting order: " . mysqli_stmt_error($order_stmt));
            }
            
            $order_id = mysqli_insert_id($conn);
            
            // Insert order items
            foreach ($cart_items as $item) {
                $order_item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                                    VALUES (?, ?, ?, ?)";
                $order_item_stmt = mysqli_prepare($conn, $order_item_query);
                
                if ($order_item_stmt === false) {
                    throw new Exception("Error preparing order item query: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($order_item_stmt, "iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                
                if (!mysqli_stmt_execute($order_item_stmt)) {
                    throw new Exception("Error inserting order item: " . mysqli_stmt_error($order_item_stmt));
                }
                
                // Update product stock
                $update_product_query = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
                $update_product_stmt = mysqli_prepare($conn, $update_product_query);
                
                if ($update_product_stmt === false) {
                    throw new Exception("Error preparing stock update query: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($update_product_stmt, "ii", $item['quantity'], $item['product_id']);
                
                if (!mysqli_stmt_execute($update_product_stmt)) {
                    throw new Exception("Error updating product stock: " . mysqli_stmt_error($update_product_stmt));
                }
                
                mysqli_stmt_close($update_product_stmt);
                mysqli_stmt_close($order_item_stmt);
            }
            
            // Process payment
            $order_data = [
                'order_id' => $order_id,
                'total' => $total,
                'items' => $cart_items
            ];
            
            $user_data = [
                'user_id' => $user_id,
                'name' => $full_name,
                'email' => $email,
                'phone' => $phone
            ];
            
            $payment_result = process_payment($payment_method, $total, $order_data, $user_data);
            
            // Update order with payment information
            if ($payment_result['success']) {
                $payment_status = $payment_result['status'] ?? 'pending';
                $transaction_id = $payment_result['transaction_id'] ?? null;
                
                // Update payment status
                update_payment_status($order_id, $transaction_id, $payment_status, $payment_method);
                
                // Clear cart
                $clear_cart_query = "DELETE FROM cart WHERE user_id = ?";
                $clear_cart_stmt = mysqli_prepare($conn, $clear_cart_query);
                mysqli_stmt_bind_param($clear_cart_stmt, "i", $user_id);
                mysqli_stmt_execute($clear_cart_stmt);
                
                // Create notification for user
                $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                                      VALUES (?, ?, 'order', NOW())";
                $notification_stmt = mysqli_prepare($conn, $notification_query);
                $message = "Your order #$order_id has been placed successfully and is being processed.";
                mysqli_stmt_bind_param($notification_stmt, "is", $user_id, $message);
                mysqli_stmt_execute($notification_stmt);
                
                // Send order confirmation email
                try {
                    require_once 'includes/Mailer.php';
                    
                    // Get user details
                    $user_query = "SELECT name, email FROM users WHERE user_id = ?";
                    $user_stmt = mysqli_prepare($conn, $user_query);
                    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    $user_data = mysqli_fetch_assoc($user_result);
                    
                    // Get order details
                    $order_query = "SELECT * FROM orders WHERE order_id = ?";
                    $order_stmt = mysqli_prepare($conn, $order_query);
                    mysqli_stmt_bind_param($order_stmt, "i", $order_id);
                    mysqli_stmt_execute($order_stmt);
                    $order_result = mysqli_stmt_get_result($order_stmt);
                    $order_data = mysqli_fetch_assoc($order_result);
                    
                    // Get order items
                    $items_query = "SELECT oi.*, p.name FROM order_items oi 
                                   JOIN products p ON oi.product_id = p.product_id
                                   WHERE oi.order_id = ?";
                    $items_stmt = mysqli_prepare($conn, $items_query);
                    mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    
                    $order_items = [];
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    
                    // Send the confirmation email
                    $mailer = new Mailer();
                    $mailer->sendOrderConfirmation(
                        $order_id,
                        $user_data['email'],
                        $user_data['name'],
                        $order_data,
                        $order_items
                    );
                } catch (Exception $e) {
                    // Log the error but continue with the checkout process
                    error_log("Failed to send order confirmation email: " . $e->getMessage());
                }
                
                mysqli_commit($conn);
                
                // Handle special payment methods that need redirects
                if ($payment_method == 'paypal' && isset($payment_result['redirect_url'])) {
                    // Store redirect info in session
                    $_SESSION['payment_redirect'] = [
                        'order_id' => $order_id,
                        'url' => $payment_result['redirect_url']
                    ];
                    
                    // Redirect to PayPal
                    header("Location: " . $payment_result['redirect_url']);
                    exit();
                }
                
                // For all other payment methods, go to confirmation
            header("Location: order_confirmation.php?order_id=$order_id");
            exit();
        } else {
                // Payment failed
                mysqli_rollback($conn);
                $errors[] = $payment_result['message'] ?? "Payment processing failed. Please try again.";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "An error occurred while processing your order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .checkout-header {
            margin-bottom: 2rem;
        }
        
        .checkout-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .checkout-subtitle {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 2rem;
        }

        .checkout-form {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            background: #f9f9f9;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }
        
        .payment-methods {
            display: grid;
            gap: 1rem;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: border-color 0.2s;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background: #f0f7ff;
        }
        
        .payment-radio {
            margin-right: 1rem;
        }
        
        .payment-icon {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            color: var(--primary-color);
            width: 24px;
            text-align: center;
        }
        
        .payment-details {
            flex-grow: 1;
        }
        
        .payment-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .payment-description {
            font-size: 0.875rem;
            color: var(--medium-gray);
        }
        
        .order-summary {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            align-self: start;
        }
        
        .summary-title {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .summary-items {
            margin-bottom: 1.5rem;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .summary-item {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .summary-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .item-image-small {
            width: 60px;
            height: 60px;
            border-radius: var(--border-radius);
            object-fit: cover;
            margin-right: 1rem;
        }

        .item-info {
            flex-grow: 1;
        }

        .item-name-small {
            font-weight: 500;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .item-price-quantity {
            display: flex;
            justify-content: space-between;
            color: var(--medium-gray);
            font-size: 0.875rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .summary-label {
            color: var(--medium-gray);
        }
        
        .summary-value {
            font-weight: 500;
        }
        
        .summary-total {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }

        .place-order-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem;
            width: 100%;
            font-weight: 500;
            margin-top: 1.5rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        
        .place-order-btn:hover {
            background: var(--primary-dark);
        }

        .place-order-btn:disabled {
            background: var(--light-gray);
            cursor: not-allowed;
        }
        
        .error-box {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid #c62828;
        }
        
        .error-box ul {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
        
        .success-box {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid #2e7d32;
        }

        @media (max-width: 992px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }

            .order-summary {
                margin-top: 2rem;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

        <div class="checkout-container">
        <div class="checkout-header">
            <h1 class="checkout-title">Checkout</h1>
            <p class="checkout-subtitle">Please fill in your details to complete your order</p>
        </div>
        
        <?php if ($item_count == 0): ?>
        <div class="error-box">
            <p>Your cart is empty. Please add products to your cart before checkout.</p>
            <p><a href="products.php">Continue shopping</a></p>
        </div>
        <?php else: ?>
        
        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
                    </div>
                <?php endif; ?>

        <div class="checkout-content">
            <form class="checkout-form" method="post" action="">
                    <div class="form-section">
                    <h2 class="section-title">Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                        <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                        </div>

                <div class="form-section">
                    <h2 class="section-title">Shipping Address</h2>
                        <div class="form-group">
                        <label class="form-label" for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="state">State</label>
                                <input type="text" id="state" name="state" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="zip">ZIP Code</label>
                            <input type="text" id="zip" name="zip" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-section">
                    <h2 class="section-title">Payment Method</h2>
                    <div class="payment-methods">
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="cash_on_delivery" class="payment-radio" data-payment-type="cod" required>
                            <div class="payment-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">Cash on Delivery</div>
                                <div class="payment-description">Pay cash when you receive your order</div>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="bank_transfer" class="payment-radio" data-payment-type="bank" required>
                            <div class="payment-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">Bank Transfer</div>
                                <div class="payment-description">Pay via bank transfer</div>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="credit_card" class="payment-radio" data-payment-type="card" required>
                            <div class="payment-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">Credit Card</div>
                                <div class="payment-description">Pay securely with your credit card</div>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="paypal" class="payment-radio" data-payment-type="paypal" required>
                            <div class="payment-icon">
                                <i class="fab fa-paypal"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">PayPal</div>
                                <div class="payment-description">Fast and secure payment via PayPal</div>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="mobile_payment" class="payment-radio" data-payment-type="mobile" required>
                            <div class="payment-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">Mobile Payment</div>
                                <div class="payment-description">Pay using your mobile wallet or banking app</div>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="crypto" class="payment-radio" data-payment-type="crypto" required>
                            <div class="payment-icon">
                                <i class="fab fa-bitcoin"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-title">Cryptocurrency</div>
                                <div class="payment-description">Pay with Bitcoin, Ethereum or other cryptocurrencies</div>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Payment Details Forms - These will show/hide based on selection -->
                    <div id="payment-details-container" class="payment-details-container" style="margin-top: 20px;">
                        <!-- Cash on Delivery Form -->
                        <div id="cod-form" class="payment-form" style="display: none;">
                            <p class="payment-note">No additional information is required for Cash on Delivery. The delivery person will collect the payment when your order arrives.</p>
                        </div>
                        
                        <!-- Bank Transfer Form -->
                        <div id="bank-form" class="payment-form" style="display: none;">
                            <p class="payment-note">Please complete your bank transfer within 24 hours. Use your order number as the reference.</p>
                            <div class="form-group">
                                <label class="form-label" for="bank_name">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_details[bank_name]" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="account_name">Account Holder Name</label>
                                <input type="text" id="account_name" name="bank_details[account_name]" class="form-control">
                            </div>
                        <div class="form-group">
                                <label class="form-label" for="transfer_date">Expected Transfer Date</label>
                                <input type="date" id="transfer_date" name="bank_details[transfer_date]" class="form-control">
                            </div>
                        </div>

                        <!-- Credit Card Form -->
                        <div id="card-form" class="payment-form" style="display: none;">
                            <div class="form-group">
                                <label class="form-label" for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_details[card_number]" class="form-control" placeholder="1234 5678 9012 3456">
                            </div>
                        <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="card_expiry">Expiry Date</label>
                                    <input type="text" id="card_expiry" name="card_details[card_expiry]" class="form-control" placeholder="MM/YY">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="card_cvv">CVV</label>
                                    <input type="text" id="card_cvv" name="card_details[card_cvv]" class="form-control" placeholder="123">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="card_name">Name on Card</label>
                                <input type="text" id="card_name" name="card_details[card_name]" class="form-control">
                            </div>
                        </div>
                        
                        <!-- PayPal Form -->
                        <div id="paypal-form" class="payment-form" style="display: none;">
                            <p class="payment-note">You will be redirected to PayPal to complete your payment after placing the order.</p>
                            <div class="form-group">
                                <label class="form-label" for="paypal_email">PayPal Email (Optional)</label>
                                <input type="email" id="paypal_email" name="paypal_details[email]" class="form-control" placeholder="your-email@example.com">
                            </div>
                        </div>
                        
                        <!-- Mobile Payment Form -->
                        <div id="mobile-form" class="payment-form" style="display: none;">
                            <div class="form-group">
                                <label class="form-label" for="mobile_provider">Payment Provider</label>
                                <select id="mobile_provider" name="mobile_details[provider]" class="form-control">
                                    <option value="">Select Provider</option>
                                    <option value="apple_pay">Apple Pay</option>
                                    <option value="google_pay">Google Pay</option>
                                    <option value="samsung_pay">Samsung Pay</option>
                                    <option value="alipay">Alipay</option>
                                    <option value="wechat_pay">WeChat Pay</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="mobile_number">Mobile Number</label>
                                <input type="tel" id="mobile_number" name="mobile_details[number]" class="form-control" placeholder="+1 123 456 7890">
                            </div>
                        </div>
                        
                        <!-- Cryptocurrency Form -->
                        <div id="crypto-form" class="payment-form" style="display: none;">
                            <div class="form-group">
                                <label class="form-label" for="crypto_currency">Cryptocurrency</label>
                                <select id="crypto_currency" name="crypto_details[currency]" class="form-control">
                                    <option value="">Select Cryptocurrency</option>
                                    <option value="btc">Bitcoin (BTC)</option>
                                    <option value="eth">Ethereum (ETH)</option>
                                    <option value="ltc">Litecoin (LTC)</option>
                                    <option value="usdt">Tether (USDT)</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="crypto_wallet">Wallet Address (Optional)</label>
                                <input type="text" id="crypto_wallet" name="crypto_details[wallet]" class="form-control" placeholder="Your wallet address for refunds if needed">
                            </div>
                            </div>
                        </div>
                    </div>

                <button type="submit" class="place-order-btn">
                    <i class="fas fa-check-circle"></i> Place Order
                    </button>
                </form>

            <div class="order-summary">
                <h2 class="summary-title">Order Summary</h2>
                
                <div class="summary-items">
                <?php foreach ($cart_items as $item): ?>
                    <div class="summary-item">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image-small">
                        <div class="item-info">
                            <h4 class="item-name-small"><?php echo htmlspecialchars($item['name']); ?></h4>
                            <div class="item-price-quantity">
                                <span>$<?php echo number_format($item['price'], 2); ?></span>
                                <span>Qty: <?php echo $item['quantity']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Subtotal (<?php echo $item_count; ?> items)</span>
                    <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Shipping</span>
                    <span class="summary-value">$<?php echo number_format($shipping, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Tax (5%)</span>
                    <span class="summary-value">$<?php echo number_format($tax, 2); ?></span>
                </div>
                
                <div class="summary-row summary-total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('.payment-method');
        const paymentForms = document.querySelectorAll('.payment-form');
        
        function showPaymentForm(paymentType) {
            // Hide all payment forms first
            paymentForms.forEach(form => {
                form.style.display = 'none';
            });
            
            // Show the selected payment form
            const selectedForm = document.getElementById(paymentType + '-form');
            if (selectedForm) {
                selectedForm.style.display = 'block';
            }
        }
        
        paymentMethods.forEach(method => {
            const radio = method.querySelector('input[type="radio"]');
            
            method.addEventListener('click', function() {
                // Remove selected class from all methods
                paymentMethods.forEach(m => m.classList.remove('selected'));
                
                // Add selected class to clicked method
                method.classList.add('selected');
                
                // Check the radio button
                radio.checked = true;
                
                // Show the corresponding payment form
                const paymentType = radio.getAttribute('data-payment-type');
                showPaymentForm(paymentType);
            });
            
            // Set initial selected state based on checked radio
            if (radio.checked) {
                method.classList.add('selected');
                const paymentType = radio.getAttribute('data-payment-type');
                showPaymentForm(paymentType);
            }
        });
        });
    </script>
</body>
</html>