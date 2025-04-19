<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/track_analytics.php';

// Check admin permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();
$message = '';

// Get product IDs for testing
$product_ids = [];
$query = "SELECT p.product_id, p.category_id, p.vendor_id, p.name FROM products p LIMIT 20";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $product_ids[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
    $searches_per_day = isset($_POST['searches']) ? (int)$_POST['searches'] : 10;
    $views_per_day = isset($_POST['views']) ? (int)$_POST['views'] : 30;
    $orders_per_day = isset($_POST['orders']) ? (int)$_POST['orders'] : 5;
    
    $total_records = 0;
    
    // Generate data for each day
    for ($day = 0; $day < $days; $day++) {
        $date = date('Y-m-d', strtotime("-$day days"));
        
        // Generate searches
        $search_terms = ['organic', 'fresh', 'local', 'vegetables', 'fruits', 'dairy', 'farm', 'green', 'natural', 'eco'];
        for ($i = 0; $i < $searches_per_day; $i++) {
            $term = $search_terms[array_rand($search_terms)];
            
            // Get random products for results
            $results = [];
            $result_count = rand(0, min(count($product_ids), 8));
            $product_keys = array_rand($product_ids, max(1, $result_count));
            
            if (!is_array($product_keys)) {
                $product_keys = [$product_keys];
            }
            
            foreach ($product_keys as $key) {
                $results[] = $product_ids[$key];
            }
            
            // Backdate the activity
            $timestamp = $date . ' ' . rand(0, 23) . ':' . rand(0, 59) . ':' . rand(0, 59);
            
            // Log the search
            track_product_search($term, null, $results);
            
            // Update the timestamp directly in the database
            $update_query = "UPDATE product_search_logs SET created_at = ? ORDER BY id DESC LIMIT 1";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "s", $timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $update_query = "UPDATE analytics_extended SET created_at = ? ORDER BY id DESC LIMIT ?";
            $stmt = mysqli_prepare($conn, $update_query);
            $limit = 1 + count($results); // The search + impressions
            mysqli_stmt_bind_param($stmt, "si", $timestamp, $limit);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $update_query = "UPDATE analytics SET created_at = ? WHERE type = 'search' ORDER BY id DESC LIMIT ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $timestamp, $limit);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $total_records += 1 + count($results);
        }
        
        // Generate product views
        for ($i = 0; $i < $views_per_day; $i++) {
            if (empty($product_ids)) continue;
            $product = $product_ids[array_rand($product_ids)];
            
            // Backdate the activity
            $timestamp = $date . ' ' . rand(0, 23) . ':' . rand(0, 59) . ':' . rand(0, 59);
            
            // Log the product view
            track_product_view($product['product_id'], [
                'vendor_id' => $product['vendor_id'],
                'category_id' => $product['category_id'],
                'name' => $product['name']
            ]);
            
            // Update the timestamps
            $update_query = "UPDATE analytics SET created_at = ? WHERE type = 'visit' ORDER BY id DESC LIMIT 1";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "s", $timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $update_query = "UPDATE analytics_extended SET created_at = ? WHERE type = 'visit' ORDER BY id DESC LIMIT 1";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "s", $timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $update_query = "UPDATE product_visits SET visit_date = ? ORDER BY id DESC LIMIT 1";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "s", $timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $total_records++;
        }
        
        // Generate orders
        for ($i = 0; $i < $orders_per_day; $i++) {
            $order_id = 'TEST-' . rand(1000, 9999);
            $order_items = [];
            $order_total = 0;
            
            // Add 1-5 items to order
            $item_count = rand(1, 5);
            for ($j = 0; $j < $item_count; $j++) {
                if (empty($product_ids)) continue;
                $product = $product_ids[array_rand($product_ids)];
                $price = rand(5, 100);
                $quantity = rand(1, 3);
                $subtotal = $price * $quantity;
                $order_total += $subtotal;
                
                $order_items[] = [
                    'product_id' => $product['product_id'],
                    'vendor_id' => $product['vendor_id'],
                    'category_id' => $product['category_id'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'name' => $product['name']
                ];
            }
            
            // Backdate the activity
            $timestamp = $date . ' ' . rand(0, 23) . ':' . rand(0, 59) . ':' . rand(0, 59);
            
            // Log the order
            track_order_placement($order_id, $order_items, $order_total);
            
            // Update the timestamps for each order item
            foreach ($order_items as $item) {
                $update_query = "UPDATE analytics SET created_at = ? WHERE type = 'order' AND product_id = ? ORDER BY id DESC LIMIT 1";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $timestamp, $item['product_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                $update_query = "UPDATE analytics_extended SET created_at = ? WHERE type = 'order' AND product_id = ? ORDER BY id DESC LIMIT 1";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $timestamp, $item['product_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            $total_records += count($order_items);
        }
    }
    
    $message = "Successfully generated $total_records analytics records over $days days.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Test Analytics Data - AgriMarket Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        h1, h2 {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .product-list {
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        
        .product-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <h1>Generate Test Analytics Data</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Generate Sample Data</h2>
            <p>This tool will generate sample analytics data for testing purposes. The data will be backdated to create a realistic history.</p>
            
            <form method="POST" action="generate_test_data.php">
                <div class="form-group">
                    <label for="days">Number of Days to Generate:</label>
                    <input type="number" id="days" name="days" value="30" min="1" max="90">
                    <small>How many days back should we generate data for?</small>
                </div>
                
                <div class="form-group">
                    <label for="searches">Searches Per Day:</label>
                    <input type="number" id="searches" name="searches" value="15" min="1" max="100">
                    <small>How many product searches should be simulated each day?</small>
                </div>
                
                <div class="form-group">
                    <label for="views">Product Views Per Day:</label>
                    <input type="number" id="views" name="views" value="40" min="1" max="200">
                    <small>How many product views should be simulated each day?</small>
                </div>
                
                <div class="form-group">
                    <label for="orders">Orders Per Day:</label>
                    <input type="number" id="orders" name="orders" value="8" min="1" max="50">
                    <small>How many orders should be simulated each day?</small>
                </div>
                
                <button type="submit">Generate Data</button>
            </form>
            
            <?php if (!empty($product_ids)): ?>
                <div class="product-list">
                    <h3>Available Products for Testing (<?php echo count($product_ids); ?>)</h3>
                    <?php foreach ($product_ids as $product): ?>
                        <div class="product-item">
                            ID: <?php echo $product['product_id']; ?> - 
                            <?php echo htmlspecialchars($product['name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No products found in the database. Please add some products first.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Next Steps</h2>
            <p>After generating test data:</p>
            <ol>
                <li>Go to the <a href="reports.php">Analytics Reports</a> page to view the generated data.</li>
                <li>Use the date filters to explore different time periods.</li>
                <li>If you need to clear the test data, use the <a href="clean_analytics.php">Clean Analytics Data</a> tool.</li>
            </ol>
        </div>
    </div>
</body>
</html> 