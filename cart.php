<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

// Process cart actions
if (isset($_POST['action'])) {
    // Add item (non-AJAX)
    if ($_POST['action'] == 'add' && isset($_POST['product_id'])) {
        $product_id = (int)$_POST['product_id'];
        
        // Check if product already in cart
        $check_query = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $product_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                // Update quantity
                mysqli_stmt_bind_result($check_stmt, $cart_id, $quantity);
                mysqli_stmt_fetch($check_stmt);
                
                $new_quantity = $quantity + 1;
                $update_query = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "ii", $new_quantity, $cart_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                // Add new item
                $add_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
                $add_stmt = mysqli_prepare($conn, $add_query);
                if ($add_stmt) {
                    mysqli_stmt_bind_param($add_stmt, "ii", $user_id, $product_id);
                    mysqli_stmt_execute($add_stmt);
                    mysqli_stmt_close($add_stmt);
                }
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    // Update quantity
    if ($_POST['action'] == 'update' && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $update_query = "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "iii", $quantity, $cart_id, $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            } else {
                $error_message = "Error updating cart: " . mysqli_error($conn);
            }
        }
    }
    
    // Remove item
    if ($_POST['action'] == 'remove' && isset($_POST['cart_id'])) {
        $cart_id = (int)$_POST['cart_id'];
        
        $remove_query = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
        $remove_stmt = mysqli_prepare($conn, $remove_query);
        if ($remove_stmt) {
            mysqli_stmt_bind_param($remove_stmt, "ii", $cart_id, $user_id);
            mysqli_stmt_execute($remove_stmt);
            mysqli_stmt_close($remove_stmt);
        } else {
            $error_message = "Error removing item: " . mysqli_error($conn);
        }
    }
    
    // Clear cart
    if ($_POST['action'] == 'clear') {
        $clear_query = "DELETE FROM cart WHERE user_id = ?";
        $clear_stmt = mysqli_prepare($conn, $clear_query);
        if ($clear_stmt) {
            mysqli_stmt_bind_param($clear_stmt, "i", $user_id);
            mysqli_stmt_execute($clear_stmt);
            mysqli_stmt_close($clear_stmt);
        } else {
            $error_message = "Error clearing cart: " . mysqli_error($conn);
        }
    }
    
    // Redirect to remove form submission data
    header('Location: cart.php');
    exit();
}

// AJAX add to cart
if (isset($_POST['action']) && $_POST['action'] == 'add' && isset($_POST['product_id']) && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    $response = ['success' => false, 'message' => 'Error adding to cart'];
    $product_id = (int)$_POST['product_id'];
    
    // Check if product exists
    $product_query = "SELECT product_id FROM products WHERE product_id = ?";
    $product_stmt = mysqli_prepare($conn, $product_query);
    if ($product_stmt) {
        mysqli_stmt_bind_param($product_stmt, "i", $product_id);
        mysqli_stmt_execute($product_stmt);
        mysqli_stmt_store_result($product_stmt);
        
        if (mysqli_stmt_num_rows($product_stmt) > 0) {
            // Check if product is in cart
            $check_query = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $product_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    // Update quantity
                    mysqli_stmt_bind_result($check_stmt, $cart_id, $current_qty);
                    mysqli_stmt_fetch($check_stmt);
                    
                    $new_qty = $current_qty + 1;
                    $update_query = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "ii", $new_qty, $cart_id);
                        if (mysqli_stmt_execute($update_stmt)) {
                            $response['success'] = true;
                            $response['message'] = 'Cart updated successfully';
                        }
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    // Add new item
                    $add_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
                    $add_stmt = mysqli_prepare($conn, $add_query);
                    if ($add_stmt) {
                        mysqli_stmt_bind_param($add_stmt, "ii", $user_id, $product_id);
                        if (mysqli_stmt_execute($add_stmt)) {
                            $response['success'] = true;
                            $response['message'] = 'Product added to cart';
                        }
                        mysqli_stmt_close($add_stmt);
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        } else {
            $response['message'] = 'Product not found';
        }
        mysqli_stmt_close($product_stmt);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Fetch cart items with product details
$cart_query = "SELECT c.cart_id, c.product_id, c.quantity, c.created_at,
               p.name, p.price, p.image_url, p.description, p.stock
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = ?
               ORDER BY c.created_at DESC";

// Initialize variables
$cart_items = [];
$subtotal = 0;
$item_count = 0;
$shipping = 0;
$tax = 0;
$total = 0;

// Fetch cart items directly to make sure we have the latest data
$cart_stmt = mysqli_prepare($conn, $cart_query);
if ($cart_stmt) {
    mysqli_stmt_bind_param($cart_stmt, "i", $user_id);
    if (mysqli_stmt_execute($cart_stmt)) {
        $result = mysqli_stmt_get_result($cart_stmt);
        while ($item = mysqli_fetch_assoc($result)) {
            $cart_items[] = $item;
            $subtotal += $item['price'] * $item['quantity'];
            $item_count += $item['quantity'];
        }
    } else {
        $error_message = "Error executing cart query: " . mysqli_error($conn);
    }
    mysqli_stmt_close($cart_stmt);
} else {
    $error_message = "Error preparing cart query: " . mysqli_error($conn);
}

// Calculate totals
$shipping = $item_count > 0 ? 5.00 : 0; // Fixed shipping cost
$tax = $subtotal * 0.05; // 5% tax
$total = $subtotal + $shipping + $tax;

// Debug information (uncomment for troubleshooting)
// echo "<pre>";
// print_r($cart_items);
// print_r($errors);
// echo "</pre>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart - AgriMarket</title>
    <!-- Debug info (uncomment for troubleshooting) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
    <div style="background:#f8f8f8; border:1px solid #ccc; padding:10px; margin:10px; font-family:monospace;">
        <h3>Debug Information</h3>
        <p>User ID: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set'; ?></p>
        <p>Cart Items Count: <?php echo count($cart_items); ?></p>
        <p>Last SQL Error: <?php echo mysqli_error($conn); ?></p>
        <pre><?php print_r($cart_items); ?></pre>
    </div>
    <?php endif; ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid #c62828;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid #2e7d32;
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .cart-title {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .cart-count {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 2rem;
        }
        
        .cart-items {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .cart-summary {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            align-self: start;
        }
        
        .cart-item {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            padding: 1.5rem;
            position: relative;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            border-radius: var(--border-radius);
            object-fit: cover;
            margin-right: 1.5rem;
        }
        
        .item-details {
            flex-grow: 1;
        }
        
        .item-name {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .item-description {
            font-size: 0.9rem;
            color: var(--medium-gray);
            margin-bottom: 1rem;
            max-width: 600px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .quantity-btn {
            background: var(--light-gray);
            border: none;
            width: 36px;
            height: 36px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .quantity-btn:hover {
            background: #e0e0e0;
        }
        
        .quantity-input {
            width: 40px;
            height: 36px;
            text-align: center;
            border: none;
            border-left: 1px solid var(--light-gray);
            border-right: 1px solid var(--light-gray);
        }
        
        .quantity-input:focus {
            outline: none;
        }
        
        .remove-btn {
            color: var(--danger-color);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: color 0.2s;
        }
        
        .remove-btn:hover {
            color: #d32f2f;
        }
        
        .summary-title {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
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
        
        .checkout-btn {
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
        }
        
        .checkout-btn:hover {
            background: var(--primary-dark);
        }
        
        .checkout-btn:disabled {
            background: var(--light-gray);
            cursor: not-allowed;
        }
        
        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }
        
        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .empty-cart p {
            color: var(--medium-gray);
            margin-bottom: 1.5rem;
        }
        
        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .shop-now-btn:hover {
            background: var(--primary-dark);
        }
        
        @media (max-width: 992px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .item-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="cart-container">
        <!-- Display session messages if any -->
        <?php if (isset($_SESSION['cart_message']) && !empty($_SESSION['cart_message'])): ?>
            <div class="<?php echo isset($_SESSION['cart_success']) && $_SESSION['cart_success'] ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
            </div>
            <?php 
            // Clear the messages
            unset($_SESSION['cart_message']);
            unset($_SESSION['cart_success']);
            ?>
        <?php endif; ?>
        
        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="cart-header">
            <h1 class="cart-title">
                <i class="fas fa-shopping-cart"></i> Shopping Cart
                <?php if ($item_count > 0): ?>
                <span class="cart-count"><?php echo $item_count; ?></span>
                <?php endif; ?>
            </h1>
            
            <?php if ($item_count > 0): ?>
            <form method="post" onsubmit="return confirm('Are you sure you want to clear your cart?');">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <?php if ($item_count > 0): ?>
        <div class="cart-content">
            <div class="cart-items">
                <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                    
                    <div class="item-details">
                        <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="item-price">$<?php echo number_format($item['price'], 2); ?></p>
                        <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                        
                        <div class="item-actions">
                            <form method="post" class="quantity-form">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn minus" onclick="updateQuantity(this.parentNode, -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="quantity-input" onchange="this.form.submit()">
                                    
                                    <button type="button" class="quantity-btn plus" onclick="updateQuantity(this.parentNode, 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                            
                            <form method="post" onsubmit="return confirm('Remove this item from cart?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                <button type="submit" class="remove-btn">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-summary">
                <h2 class="summary-title">Order Summary</h2>
                
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
                
                <?php if ($item_count > 0): ?>
                <a href="checkout.php" class="checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </a>
                <?php else: ?>
                <button disabled class="checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
                <p style="text-align: center; color: var(--medium-gray); margin-top: 10px;">Add items to your cart to checkout</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="cart-items">
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any products to your cart yet.</p>
                <a href="products.php" class="shop-now-btn">
                    <i class="fas fa-shopping-basket"></i> Shop Now
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
    function updateQuantity(container, change) {
        const input = container.querySelector('.quantity-input');
        let value = parseInt(input.value, 10) + change;
        const max = parseInt(input.getAttribute('max'), 10);
        
        if (value < 1) value = 1;
        if (value > max) value = max;
        
        if (input.value != value) {
            input.value = value;
            input.form.submit();
        }
    }
    </script>
</body>
</html>