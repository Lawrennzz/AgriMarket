<?php
// Include required files
require_once 'includes/env.php';
require_once 'includes/Mailer.php';
require_once 'vendor/autoload.php';

// Load environment variables
Env::load();

echo "<h1>Order Email Test</h1>";
echo "<p>This script tests sending order confirmation and status update emails.</p>";

// Create sample order data
$order_id = 12345;
$sample_order = [
    'order_id' => $order_id,
    'user_id' => 1,
    'total' => 129.95,
    'subtotal' => 119.95,
    'shipping' => 5.00,
    'tax' => 5.00,
    'status' => 'pending',
    'payment_status' => 'pending',
    'payment_method' => 'credit_card',
    'created_at' => date('Y-m-d H:i:s'),
    'shipping_address' => json_encode([
        'full_name' => 'John Doe',
        'email' => 'johndoe@example.com',
        'phone' => '555-123-4567',
        'address' => '123 Main St',
        'city' => 'Anytown',
        'state' => 'CA',
        'zip' => '12345',
        'country' => 'USA'
    ])
];

// Create sample order items
$sample_items = [
    [
        'product_id' => 1,
        'name' => 'Organic Apples',
        'quantity' => 2,
        'price' => 4.99
    ],
    [
        'product_id' => 2,
        'name' => 'Fresh Farm Eggs (Dozen)',
        'quantity' => 1,
        'price' => 6.99
    ],
    [
        'product_id' => 3,
        'name' => 'Premium Grass-Fed Beef (1lb)',
        'quantity' => 2,
        'price' => 12.99
    ],
    [
        'product_id' => 4,
        'name' => 'Organic Vegetable Basket',
        'quantity' => 1,
        'price' => 24.99
    ],
    [
        'product_id' => 5,
        'name' => 'Local Honey (16oz)',
        'quantity' => 1,
        'price' => 8.99
    ]
];

// Function to display order details
function displayOrderDetails($order, $items) {
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    echo "<div style='background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<h3>Sample Order #" . $order['order_id'] . "</h3>";
    echo "<p><strong>Date:</strong> " . date('F j, Y', strtotime($order['created_at'])) . "</p>";
    echo "<p><strong>Status:</strong> " . ucfirst($order['status']) . "</p>";
    echo "<p><strong>Payment Method:</strong> " . ucwords(str_replace('_', ' ', $order['payment_method'])) . "</p>";
    echo "<p><strong>Payment Status:</strong> " . ucfirst($order['payment_status']) . "</p>";
    
    echo "<h4>Items:</h4>";
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<thead style='background-color: #f1f1f1;'>";
    echo "<tr>";
    echo "<th style='padding: 10px; text-align: left; border-bottom: 1px solid #ddd;'>Item</th>";
    echo "<th style='padding: 10px; text-align: center; border-bottom: 1px solid #ddd;'>Qty</th>";
    echo "<th style='padding: 10px; text-align: right; border-bottom: 1px solid #ddd;'>Price</th>";
    echo "<th style='padding: 10px; text-align: right; border-bottom: 1px solid #ddd;'>Total</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        echo "<tr>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>" . $item['quantity'] . "</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($item['price'], 2) . "</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($item_total, 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    
    echo "<div style='margin-top: 20px; text-align: right;'>";
    echo "<p><strong>Subtotal:</strong> $" . number_format($subtotal, 2) . "</p>";
    echo "<p><strong>Shipping:</strong> $" . number_format($order['shipping'], 2) . "</p>";
    echo "<p><strong>Tax:</strong> $" . number_format($order['tax'], 2) . "</p>";
    echo "<p style='font-size: 1.2em;'><strong>Total:</strong> $" . number_format($order['total'], 2) . "</p>";
    echo "</div>";
    
    echo "</div>";
}

// Display the sample order
displayOrderDetails($sample_order, $sample_items);

// Test confirmation email
if (isset($_GET['confirm']) && $_GET['confirm'] == '1' && isset($_GET['email']) && !empty($_GET['email'])) {
    $test_email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$test_email) {
        echo "<p style='color: red;'>Invalid email address provided.</p>";
    } else {
        echo "<h2>Order Confirmation Email Test Results</h2>";
        
        try {
            $mailer = new Mailer();
            $result = $mailer->sendOrderConfirmation(
                $order_id,
                $test_email,
                'Test Customer',
                $sample_order,
                $sample_items
            );
            
            if ($result) {
                echo "<p style='color: green;'>✓ Order confirmation email sent successfully to {$test_email}!</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to send order confirmation email.</p>";
                echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
                foreach ($mailer->getErrors() as $error) {
                    echo htmlspecialchars($error) . "<br>";
                }
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Test status update email
if (isset($_GET['status']) && $_GET['status'] == '1' && isset($_GET['email']) && !empty($_GET['email'])) {
    $test_email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
    $new_status = isset($_GET['new_status']) ? $_GET['new_status'] : 'processing';
    
    if (!$test_email) {
        echo "<p style='color: red;'>Invalid email address provided.</p>";
    } else {
        echo "<h2>Order Status Update Email Test Results</h2>";
        
        try {
            $mailer = new Mailer();
            $result = $mailer->sendOrderStatusUpdate(
                $order_id,
                $test_email,
                'Test Customer',
                $new_status,
                $sample_order
            );
            
            if ($result) {
                echo "<p style='color: green;'>✓ Order status update email sent successfully to {$test_email}!</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to send order status update email.</p>";
                echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
                foreach ($mailer->getErrors() as $error) {
                    echo htmlspecialchars($error) . "<br>";
                }
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Test forms
echo "<h2>Test Order Emails</h2>";
echo "<div style='display: flex; gap: 20px;'>";

// Order confirmation test form
echo "<div style='flex: 1;'>";
echo "<h3>Send Order Confirmation Email</h3>";
echo "<p>Enter an email address to send a test order confirmation:</p>";
echo "<form method='get'>";
echo "<input type='hidden' name='confirm' value='1'>";
echo "<input type='email' name='email' placeholder='recipient@example.com' required style='padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;'>";
echo "<button type='submit' style='padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Send Confirmation Email</button>";
echo "</form>";
echo "</div>";

// Order status update test form
echo "<div style='flex: 1;'>";
echo "<h3>Send Order Status Update Email</h3>";
echo "<p>Enter an email address and select a status to send a test status update:</p>";
echo "<form method='get'>";
echo "<input type='hidden' name='status' value='1'>";
echo "<input type='email' name='email' placeholder='recipient@example.com' required style='padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;'>";
echo "<select name='new_status' style='padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;'>";
echo "<option value='processing'>Processing</option>";
echo "<option value='shipped'>Shipped</option>";
echo "<option value='delivered'>Delivered</option>";
echo "<option value='cancelled'>Cancelled</option>";
echo "</select>";
echo "<button type='submit' style='padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Send Status Update Email</button>";
echo "</form>";
echo "</div>";

echo "</div>";

echo "<p style='margin-top: 20px;'><a href='test_email_config.php'>&laquo; Back to Email Configuration Test</a> | <a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
?> 