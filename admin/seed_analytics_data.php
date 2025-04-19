<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();

// Function to get all product IDs
function getProductIds($conn) {
    $query = "SELECT product_id FROM products";
    $result = mysqli_query($conn, $query);
    $ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = $row['product_id'];
    }
    return $ids;
}

// Function to generate random dates within a range
function randomDate($start_date, $end_date) {
    $min = strtotime($start_date);
    $max = strtotime($end_date);
    $random_timestamp = rand($min, $max);
    return date('Y-m-d H:i:s', $random_timestamp);
}

// Function to generate basic analytics data
function generateBasicAnalytics($conn, $product_ids, $num_records = 200) {
    $types = ['search', 'visit', 'order'];
    $values = [];
    $now = date('Y-m-d H:i:s');
    $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    for ($i = 0; $i < $num_records; $i++) {
        $type = $types[array_rand($types)];
        $product_id = $product_ids[array_rand($product_ids)];
        $count = rand(1, 5);
        $recorded_at = randomDate($start_date, $now);
        
        $values[] = "('$type', $product_id, $count, '$recorded_at')";
    }
    
    if (!empty($values)) {
        $query = "INSERT INTO analytics (type, product_id, count, recorded_at) VALUES " . implode(',', $values);
        return mysqli_query($conn, $query);
    }
    return false;
}

// Function to generate extended analytics data
function generateExtendedAnalytics($conn, $product_ids, $num_records = 200) {
    $types = ['search', 'visit', 'order', 'wishlist', 'cart', 'compare'];
    $devices = ['desktop', 'mobile', 'tablet'];
    $values = [];
    $now = date('Y-m-d H:i:s');
    $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    // Get user IDs
    $user_query = "SELECT user_id FROM users LIMIT 10";
    $user_result = mysqli_query($conn, $user_query);
    $user_ids = [];
    while ($row = mysqli_fetch_assoc($user_result)) {
        $user_ids[] = $row['user_id'];
    }
    
    // Get vendor IDs
    $vendor_query = "SELECT vendor_id FROM vendors LIMIT 5";
    $vendor_result = mysqli_query($conn, $vendor_query);
    $vendor_ids = [];
    while ($row = mysqli_fetch_assoc($vendor_result)) {
        $vendor_ids[] = $row['vendor_id'];
    }
    
    // Get category IDs
    $category_query = "SELECT category_id FROM categories";
    $category_result = mysqli_query($conn, $category_query);
    $category_ids = [];
    while ($row = mysqli_fetch_assoc($category_result)) {
        $category_ids[] = $row['category_id'];
    }
    
    for ($i = 0; $i < $num_records; $i++) {
        $type = $types[array_rand($types)];
        $user_id = !empty($user_ids) ? $user_ids[array_rand($user_ids)] : 'NULL';
        $vendor_id = !empty($vendor_ids) ? $vendor_ids[array_rand($vendor_ids)] : 'NULL';
        $product_id = $product_ids[array_rand($product_ids)];
        $category_id = !empty($category_ids) ? $category_ids[array_rand($category_ids)] : 'NULL';
        $quantity = rand(1, 5);
        $device = $devices[array_rand($devices)];
        $recorded_at = randomDate($start_date, $now);
        
        $values[] = "($user_id, $vendor_id, '$type', $product_id, $category_id, $quantity, UUID(), '$device', 'direct', NULL, '$recorded_at')";
    }
    
    if (!empty($values)) {
        $query = "INSERT INTO analytics_extended (user_id, vendor_id, type, product_id, category_id, quantity, session_id, device_type, referrer, details, recorded_at) VALUES " . implode(',', $values);
        return mysqli_query($conn, $query);
    }
    return false;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        // Get product IDs
        $product_ids = getProductIds($conn);
        
        if (!empty($product_ids)) {
            // Clear existing data if requested
            if (isset($_POST['reset']) && $_POST['reset'] === 'yes') {
                mysqli_query($conn, "TRUNCATE TABLE analytics");
                mysqli_query($conn, "TRUNCATE TABLE analytics_extended");
            }
            
            // Generate new data
            $basic_success = generateBasicAnalytics($conn, $product_ids);
            $extended_success = generateExtendedAnalytics($conn, $product_ids);
            
            if ($basic_success && $extended_success) {
                $message = "Sample analytics data generated successfully!";
            } else {
                $error = "Error generating analytics data: " . mysqli_error($conn);
            }
        } else {
            $error = "No products found in the database. Please add some products first.";
        }
    }
}

// Get current record counts
$basic_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM analytics"))['count'];
$extended_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM analytics_extended"))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Analytics Data - AgriMarket</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            border: none;
            margin: 5px;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <h1>Seed Analytics Data</h1>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="stats-card">
                <div class="stats-value"><?php echo number_format($basic_count); ?></div>
                <div class="stats-label">Basic Analytics Records</div>
            </div>
            
            <div class="stats-card">
                <div class="stats-value"><?php echo number_format($extended_count); ?></div>
                <div class="stats-label">Extended Analytics Records</div>
            </div>
        </div>
        
        <div class="about-section">
            <h2>About Analytics Data Seeding</h2>
            <p>This tool will generate sample analytics data for your products to populate the reporting and analytics dashboards. Use this if you want to see how the dashboards would look with actual data.</p>
            
            <div class="warning-box">
                <strong>Note:</strong> This will add sample product views, searches, and orders to your analytics tables. It will not affect any actual product, order, or customer data.
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" style="margin-top: 20px;">
            <button type="submit" name="generate" class="btn btn-primary">Generate Sample Analytics Data</button>
            <a href="reports.php" class="btn btn-secondary">View Reports</a>
        </form>
        
        <div style="margin-top: 40px;">
            <h2>Advanced Options</h2>
            <div class="warning-box">
                <strong>Warning:</strong> The following actions will delete existing analytics data. Use with caution.
            </div>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="reset" value="yes">
                <button type="submit" name="generate" class="btn btn-danger">Reset & Regenerate Analytics Data</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html> 