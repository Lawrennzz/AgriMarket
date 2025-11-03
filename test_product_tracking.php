<?php
/**
 * Test script for product view tracking
 * This script simulates a few product views and then displays statistics
 */

// Include necessary files
require_once 'includes/db_connection.php';
require_once 'includes/product_view_tracker.php';

// Get database connection
$conn = getConnection();

// Check if we're testing the tracking system
if (isset($_GET['test']) && $_GET['test'] === 'true') {
    // Get the first few product IDs for testing
    $product_query = "SELECT product_id FROM products LIMIT 3";
    $products_result = $conn->query($product_query);
    $products = [];
    
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row['product_id'];
    }
    
    // Track a view for each product
    $tracked = [];
    foreach ($products as $product_id) {
        $success = track_product_view($product_id, 'test_script');
        $tracked[] = [
            'product_id' => $product_id,
            'success' => $success ? 'Success' : 'Failed'
        ];
    }
    
    // Display test results
    $test_results = true;
} else {
    $test_results = false;
    $tracked = [];
}

// Get current stats for all products that have views
$stats_query = "
    SELECT 
        p.product_id, p.name, ps.total_views, ps.daily_views, ps.weekly_views, ps.monthly_views, ps.last_view_date
    FROM 
        products p
    JOIN 
        product_stats ps ON p.product_id = ps.product_id
    ORDER BY 
        ps.total_views DESC
    LIMIT 10
";
$stats_result = $conn->query($stats_query);
$product_stats = [];

if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $product_stats[] = $row;
    }
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Tracking Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 2rem;
        }
        .header {
            margin-bottom: 2rem;
        }
        .card {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Product View Tracking Test</h1>
            <p>This page tests if the product tracking system is working correctly.</p>
            
            <?php if (!$test_results): ?>
                <a href="?test=true" class="btn btn-primary">Run Test (Generate Some Views)</a>
            <?php endif; ?>
        </div>
        
        <?php if ($test_results): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Test Results</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        Test completed. Attempted to track views for <?php echo count($tracked); ?> products.
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracked as $item): ?>
                                <tr>
                                    <td><?php echo $item['product_id']; ?></td>
                                    <td>
                                        <?php if ($item['success'] === 'Success'): ?>
                                            <span class="badge bg-success">Success</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Current Product View Statistics</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($product_stats)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Total Views</th>
                                <th>Today's Views</th>
                                <th>This Week</th>
                                <th>This Month</th>
                                <th>Last Viewed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product_stats as $stat): ?>
                                <tr>
                                    <td><?php echo $stat['product_id']; ?></td>
                                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                    <td><?php echo $stat['total_views']; ?></td>
                                    <td><?php echo $stat['daily_views']; ?></td>
                                    <td><?php echo $stat['weekly_views']; ?></td>
                                    <td><?php echo $stat['monthly_views']; ?></td>
                                    <td><?php echo $stat['last_view_date']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No product view statistics found. Run the test to generate some views.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="admin/update_product_tracking.php" class="btn btn-secondary">Go to Admin Tracking Dashboard</a>
            <a href="index.php" class="btn btn-link">Back to Home</a>
        </div>
    </div>
</body>
</html> 