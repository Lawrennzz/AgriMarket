<?php
/**
 * Product Tracking Update Script
 * 
 * Admin script to update the product view tracking system.
 * This fixes issues with the tracking system after database structure changes.
 */

session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_product_stats'])) {
        // Calculate date ranges for different time periods
        $current_date = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('last sunday'));
        $month_start = date('Y-m-01');
        
        try {
            // First, clear the existing product_stats table
            $conn->query("TRUNCATE TABLE product_stats");
            
            // Get all products that have views
            $query = "
                SELECT 
                    product_id, 
                    COUNT(*) as total_views,
                    SUM(DATE(view_date) = '$current_date') as daily_views,
                    SUM(DATE(view_date) >= '$week_start') as weekly_views,
                    SUM(DATE(view_date) >= '$month_start') as monthly_views,
                    MAX(view_date) as last_view_date
                FROM 
                    product_views
                GROUP BY 
                    product_id
            ";
            
            $result = $conn->query($query);
            
            if ($result) {
                // Insert updated stats into product_stats table
                while ($row = $result->fetch_assoc()) {
                    $insert_query = "
                        INSERT INTO product_stats 
                            (product_id, total_views, daily_views, weekly_views, monthly_views, last_view_date)
                        VALUES 
                            (?, ?, ?, ?, ?, ?)
                    ";
                    
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param('iiiiss', 
                        $row['product_id'], 
                        $row['total_views'], 
                        $row['daily_views'], 
                        $row['weekly_views'], 
                        $row['monthly_views'], 
                        $row['last_view_date']
                    );
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Also add entries for products with no views
                $products_query = "
                    SELECT 
                        p.product_id 
                    FROM 
                        products p
                    LEFT JOIN 
                        product_stats ps ON p.product_id = ps.product_id
                    WHERE 
                        ps.product_id IS NULL
                ";
                
                $products_result = $conn->query($products_query);
                
                if ($products_result) {
                    while ($product = $products_result->fetch_assoc()) {
                        $insert_query = "
                            INSERT INTO product_stats 
                                (product_id, total_views, daily_views, weekly_views, monthly_views)
                            VALUES 
                                (?, 0, 0, 0, 0)
                        ";
                        
                        $stmt = $conn->prepare($insert_query);
                        $stmt->bind_param('i', $product['product_id']);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                // Check if the trigger exists
                $trigger_check = $conn->query("SHOW TRIGGERS LIKE 'after_product_view_insert'");
                
                // If trigger doesn't exist, create it
                if ($trigger_check->num_rows === 0) {
                    $create_trigger = "
                        CREATE TRIGGER after_product_view_insert
                        AFTER INSERT ON product_views
                        FOR EACH ROW
                        BEGIN
                            DECLARE current_date DATE;
                            DECLARE week_start DATE;
                            DECLARE month_start DATE;
                            
                            SET current_date = CURDATE();
                            SET week_start = DATE_SUB(current_date, INTERVAL WEEKDAY(current_date) DAY);
                            SET month_start = DATE_FORMAT(current_date, '%Y-%m-01');
                            
                            -- Update or insert a record in product_stats table
                            INSERT INTO product_stats 
                                (product_id, total_views, daily_views, weekly_views, monthly_views, last_view_date) 
                            VALUES 
                                (NEW.product_id, 1, 
                                (NEW.view_date >= current_date), 
                                (NEW.view_date >= week_start), 
                                (NEW.view_date >= month_start), 
                                NOW()) 
                            ON DUPLICATE KEY UPDATE 
                                total_views = total_views + 1,
                                daily_views = daily_views + (NEW.view_date >= current_date),
                                weekly_views = weekly_views + (NEW.view_date >= week_start),
                                monthly_views = monthly_views + (NEW.view_date >= month_start),
                                last_view_date = NOW();
                        END
                    ";
                    
                    if ($conn->multi_query($create_trigger)) {
                        // Wait for all results to be processed
                        while ($conn->more_results() && $conn->next_result()) {
                            // Consume remaining results
                        }
                    }
                }
                
                $success_message = "Product view tracking system has been successfully updated!";
            } else {
                $error_message = "Error updating product stats: " . $conn->error;
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get current stats from product_views table
$stats_query = "SELECT COUNT(*) as total_views, COUNT(DISTINCT product_id) as viewed_products FROM product_views";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get total products count
$products_query = "SELECT COUNT(*) as total FROM products";
$products_result = $conn->query($products_query);
$total_products = $products_result->fetch_assoc()['total'];

// Check if product_stats table exists
$table_check = $conn->query("SHOW TABLES LIKE 'product_stats'");
$product_stats_exists = $table_check->num_rows > 0;

// Check if trigger exists
$trigger_check = $conn->query("SHOW TRIGGERS LIKE 'after_product_view_insert'");
$trigger_exists = $trigger_check->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Product Tracking - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 1.25rem 1.5rem;
        }
        .card-body {
            padding: 1.5rem;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok {
            background-color: #28a745;
        }
        .status-warning {
            background-color: #ffc107;
        }
        .status-error {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include_once '../sidebar.php'; ?>

    <div class="main-content">
        <h2 class="mb-4">Update Product Tracking System</h2>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Current System Status</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="status-indicator <?php echo $product_stats_exists ? 'status-ok' : 'status-error'; ?>"></span>
                                    Product Stats Table
                                </span>
                                <span class="badge bg-<?php echo $product_stats_exists ? 'success' : 'danger'; ?>">
                                    <?php echo $product_stats_exists ? 'Exists' : 'Missing'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="status-indicator <?php echo $trigger_exists ? 'status-ok' : 'status-error'; ?>"></span>
                                    Update Trigger
                                </span>
                                <span class="badge bg-<?php echo $trigger_exists ? 'success' : 'danger'; ?>">
                                    <?php echo $trigger_exists ? 'Active' : 'Missing'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>View Statistics</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($stats['total_views']); ?></h3>
                                        <p class="text-muted mb-0">Total Views</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($stats['viewed_products']); ?> / <?php echo number_format($total_products); ?></h3>
                                        <p class="text-muted mb-0">Products Viewed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h4>Update Tracking System</h4>
            </div>
            <div class="card-body">
                <p>This tool will update the product view tracking system to ensure all analytics are working correctly. It will:</p>
                <ul>
                    <li>Create the product_stats table if missing</li>
                    <li>Create the update trigger if missing</li>
                    <li>Recalculate all product view statistics</li>
                </ul>
                
                <form method="post" action="">
                    <button type="submit" name="update_product_stats" class="btn btn-primary">
                        <i class="fas fa-sync-alt me-2"></i> Update Tracking System
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 