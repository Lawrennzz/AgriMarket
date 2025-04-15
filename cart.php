<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle quantity updates and item removal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['product_id']) && isset($_POST['action'])) {
        $product_id = (int)$_POST['product_id'];
        
        switch($_POST['action']) {
            case 'add':
                $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;
                break;
            case 'reduce':
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]--;
                    if ($_SESSION['cart'][$product_id] <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                    }
                }
                break;
            case 'remove':
                unset($_SESSION['cart'][$product_id]);
                break;
        }

        // Sync with cart table
        // First, clear existing cart entries for this user
        mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
        
        // Insert updated cart items
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            if ($quantity > 0) {
                $stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $quantity);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        // If AJAX request, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Load cart items from the cart table instead of session
$cart_items = [];
$total = 0;
$result = mysqli_query($conn, "
    SELECT c.*, p.name, p.price, p.image_url 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    WHERE c.user_id = $user_id
");
if ($result) {
    $cart_items = $result;
    // Update session cart to match database
    $_SESSION['cart'] = [];
    while ($item = mysqli_fetch_assoc($result)) {
        $_SESSION['cart'][$item['product_id']] = $item['quantity'];
    }
    mysqli_data_seek($result, 0); // Reset result pointer for later use
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cart - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        .cart-items {
            display: grid;
            gap: 1.5rem;
        }
        .cart-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .item-price {
            color: #666;
            font-size: 1.1rem;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .quantity-btn {
            background: #f0f0f0;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        .quantity-btn:hover {
            background: #e0e0e0;
        }
        .quantity {
            font-size: 1.1rem;
            min-width: 40px;
            text-align: center;
        }
        .remove-btn {
            color: #ff4444;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.5rem;
            transition: color 0.2s;
        }
        .remove-btn:hover {
            color: #cc0000;
        }
        .cart-summary {
            margin-top: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .checkout-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: #4CAF50;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            font-size: 1.1rem;
            transition: background-color 0.2s;
        }
        .checkout-btn:hover {
            background: #45a049;
        }
        .empty-cart {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .empty-cart i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        .empty-cart p {
            color: #666;
            margin-bottom: 1rem;
        }
        .continue-shopping {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .continue-shopping:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="cart-container">
            <div class="cart-header">
                <h1>Shopping Cart</h1>
            </div>

            <?php 
            if (mysqli_num_rows($cart_items) == 0) { ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <a href="products.php" class="continue-shopping">Continue Shopping</a>
                </div>
            <?php } else { ?>
                <div class="cart-items">
                    <?php 
                    $total = 0;
                    while ($item = mysqli_fetch_assoc($cart_items)) { 
                        $subtotal = $item['price'] * $item['quantity'];
                        $total += $subtotal;
                    ?>
                        <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default-product.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="item-details">
                                <div>
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="item-price">$<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="quantity-controls">
                                    <button class="quantity-btn reduce-quantity" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 'reduce')">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity"><?php echo $item['quantity']; ?></span>
                                    <button class="quantity-btn add-quantity" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 'add')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="remove-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 'remove')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="item-subtotal">
                                <p>$<?php echo number_format($subtotal, 2); ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="cart-summary">
                    <div class="cart-total">
                        <span>Total</span>
                        <span>$<?php echo number_format($total, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
    function updateQuantity(productId, action) {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `product_id=${productId}&action=${action}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>