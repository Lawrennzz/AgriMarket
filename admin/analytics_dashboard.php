<?php
require_once('../includes/db_connect.php');
require_once('../includes/track_analytics.php');
require_once('../includes/product_view_tracker.php');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get analytics data
$most_viewed_products = get_most_viewed_products_analytics(10);
$period = isset($_GET['period']) ? $_GET['period'] : 'total';
$trending_products = get_most_viewed_products_analytics(10, $period);

// Get total views
$total_views_query = "SELECT COUNT(*) as total FROM product_views";
$total_views_result = $conn->query($total_views_query);
$total_views = $total_views_result->fetch_assoc()['total'];

// Get recent analytics activity
$recent_activity_query = "SELECT * FROM analytics ORDER BY timestamp DESC LIMIT 20";
$recent_activity_result = $conn->query($recent_activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - AgriMarket Admin</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <h1>Analytics Dashboard</h1>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Product Views</h5>
                        <h2 class="display-4"><?php echo $total_views; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Most Viewed Products</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Total Views</th>
                                    <th>Daily Views</th>
                                    <th>Weekly Views</th>
                                    <th>Monthly Views</th>
                                    <th>Last Viewed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($most_viewed_products as $product): ?>
                                <tr>
                                    <td>
                                        <a href="../product_details.php?id=<?php echo $product['product_id']; ?>">
                                            <?php echo $product['product_name']; ?>
                                        </a>
                                    </td>
                                    <td><strong><?php echo $product['total_views']; ?></strong></td>
                                    <td><?php echo $product['daily_views']; ?></td>
                                    <td><?php echo $product['weekly_views']; ?></td>
                                    <td><?php echo $product['monthly_views']; ?></td>
                                    <td><?php echo $product['last_view_click_date'] ? date('M j, Y', strtotime($product['last_view_click_date'])) : 'Never'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Trending Products</h5>
                        <div class="btn-group">
                            <a href="?period=day" class="btn btn-sm btn-outline-primary <?php echo $period == 'day' ? 'active' : ''; ?>">Day</a>
                            <a href="?period=week" class="btn btn-sm btn-outline-primary <?php echo $period == 'week' ? 'active' : ''; ?>">Week</a>
                            <a href="?period=month" class="btn btn-sm btn-outline-primary <?php echo $period == 'month' ? 'active' : ''; ?>">Month</a>
                            <a href="?period=total" class="btn btn-sm btn-outline-primary <?php echo $period == 'total' ? 'active' : ''; ?>">All Time</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="trendingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Activity Type</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($activity = $recent_activity_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($activity['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['activity_type']); ?></td>
                                    <td>
                                        <?php 
                                        $data = json_decode($activity['data'], true);
                                        if (is_array($data)) {
                                            foreach ($data as $key => $value) {
                                                if (is_array($value)) {
                                                    echo htmlspecialchars($key) . ": " . htmlspecialchars(json_encode($value)) . "<br>";
                                                } else {
                                                    echo htmlspecialchars($key) . ": " . htmlspecialchars($value) . "<br>";
                                                }
                                            }
                                        } else {
                                            echo htmlspecialchars($activity['data']);
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare chart data
        const ctx = document.getElementById('trendingChart').getContext('2d');
        
        const productNames = <?php echo json_encode(array_column($trending_products, 'product_name')); ?>;
        const productViews = <?php echo json_encode(array_column($trending_products, 'total_views')); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: productNames,
                datasets: [{
                    label: 'Views',
                    data: productViews,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 