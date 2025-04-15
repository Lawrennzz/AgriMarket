<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get cart items
$stmt = mysqli_prepare($conn, "
    SELECT c.*, p.name, p.price, p.image_url, p.stock, u.name as vendor_name 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    JOIN users u ON p.vendor_id = u.user_id 
    WHERE c.user_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$cart_query = mysqli_stmt_get_result($stmt);

// Debugging: Check query results
$cart_items = [];
while ($item = mysqli_fetch_assoc($cart_query)) {
    $cart_items[] = $item;
}
// var_dump($cart_items); // Uncomment to debug

// Calculate totals
$subtotal = 0;
$shipping = 10.00; // Fixed shipping rate
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax = $subtotal * 0.10; // 10% tax
$total = $subtotal + $shipping + $tax;

// Get user details
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_query = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate stock levels
    $stock_error = false;
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $error_message = "Sorry, {$item['name']} only has {$item['stock']} units available.";
            $stock_error = true;
            break;
        }
    }
    
    if (!$stock_error) {
        // Create order
        $status = 'pending';
        $shipping_address = mysqli_real_escape_string($conn, 
            $_POST['street'] . ', ' . 
            $_POST['city'] . ', ' . 
            $_POST['state'] . ' ' . 
            $_POST['zip']
        );
        $query = "INSERT INTO orders (user_id, total, status, shipping_address, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "idss", $user_id, $total, $status, $shipping_address);
        
        if (mysqli_stmt_execute($stmt)) {
            $order_id = mysqli_insert_id($conn);
            
            // Add order items and update stock
            foreach ($cart_items as $item) {
                $item_stmt = mysqli_prepare($conn, "
                    INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($item_stmt, "iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                mysqli_stmt_execute($item_stmt);
                mysqli_stmt_close($item_stmt);
                
                $update_stmt = mysqli_prepare($conn, "
                    UPDATE products 
                    SET stock = stock - ? 
                    WHERE product_id = ?
                ");
                mysqli_stmt_bind_param($update_stmt, "ii", $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            // Clear cart
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
            mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
            
            // Redirect to order confirmation
            header("Location: order_confirmation.php?order_id=$order_id");
            exit();
        } else {
            $error_message = "Failed to place order. Please try again.";
        }
    }
}
mysqli_stmt_close($stmt);
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
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .checkout-form {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .order-summary {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: sticky;
            top: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .form-section {
            margin-bottom: 2rem;
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
            color: var(--dark-gray);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
        }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .item-vendor {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .item-price {
            font-weight: 500;
            color: var(--dark-gray);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 600;
            padding-top: 1rem;
            border-top: 2px solid var(--light-gray);
        }

        .btn-checkout {
            width: 100%;
            padding: 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-checkout:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
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

    <div class="container">
        <div class="checkout-container">
            <div class="checkout-form">
                <h1 class="section-title">Shipping Information</h1>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="firstName">First Name</label>
                                <input type="text" id="firstName" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="last_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="street">Street Address</label>
                            <input type="text" id="street" name="street" class="form-control" required>
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
                        <h2 class="section-title">Payment Information</h2>
                        <div class="form-group">
                            <label class="form-label" for="cardNumber">Card Number</label>
                            <input type="text" id="cardNumber" name="card_number" class="form-control" 
                                   placeholder="1234 5678 9012 3456" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="expiry">Expiry Date</label>
                                <input type="text" id="expiry" name="expiry" class="form-control" 
                                       placeholder="MM/YY" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" class="form-control" 
                                       placeholder="123" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-checkout">
                        Place Order
                    </button>
                </form>
            </div>

            <div class="order-summary">
                <h2 class="section-title">Order Summary</h2>
                
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="item-image">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-vendor">Sold by <?php echo htmlspecialchars($item['vendor_name']); ?></div>
                            <div class="item-price">
                                $<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>$<?php echo number_format($shipping, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (10%)</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Simple card number formatting
        const cardInput = document.getElementById('cardNumber');
        cardInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(.{4})/g, '$1 ').trim();
            e.target.value = value;
        });

        // Expiry date formatting
        const expiryInput = document.getElementById('expiry');
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0,2) + '/' + value.slice(2);
            }
            e.target.value = value;
        });

        // CVV validation
        const cvvInput = document.getElementById('cvv');
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 3);
        });
    </script>
</body>
</html>