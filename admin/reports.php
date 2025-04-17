<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$conn = getConnection();

// Check for required tables
$requiredTables = ['orders', 'order_items', 'products', 'vendors', 'categories', 'analytics'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = mysqli_query($conn, $query);
    if (!$result || mysqli_num_rows($result) === 0) {
        $missingTables[] = $table;
    }
}

// Display error if tables are missing
if (!empty($missingTables)) {
    echo "<div style='color: red; padding: 20px; background-color: #ffeeee; border: 1px solid #ff0000; margin: 20px;'>";
    echo "<h3>Database Error: Missing Tables</h3>";
    echo "<p>The following tables are missing from the database:</p>";
    echo "<ul>";
    foreach ($missingTables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    echo "<p>Please make sure the database is properly set up before using this page.</p>";
    echo "</div>";
    // Log the error
    error_log("Missing tables for analytics: " . implode(", ", $missingTables));
}

// Set the time period for reports
$time_period = isset($_GET['period']) ? $_GET['period'] : 'month';
$date_clause = "";

switch ($time_period) {
    case 'week':
        $date_clause = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        $date_group = "DATE_FORMAT(created_at, '%Y-%m-%d')";
        $date_label = "DATE_FORMAT(created_at, '%a')";
        $date_subtitle = "Last 7 Days";
        break;
    case 'month':
        $date_clause = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_group = "DATE_FORMAT(created_at, '%Y-%m-%d')";
        $date_label = "DATE_FORMAT(created_at, '%b %d')";
        $date_subtitle = "Last 30 Days";
        break;
    case 'quarter':
        $date_clause = "AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_group = "DATE_FORMAT(created_at, '%Y-%m')";
        $date_label = "DATE_FORMAT(created_at, '%b %Y')";
        $date_subtitle = "Last 3 Months";
        break;
    case 'year':
        $date_clause = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_group = "DATE_FORMAT(created_at, '%Y-%m')";
        $date_label = "DATE_FORMAT(created_at, '%b %Y')";
        $date_subtitle = "Last 12 Months";
        break;
    default:
        $date_clause = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_group = "DATE_FORMAT(created_at, '%Y-%m-%d')";
        $date_label = "DATE_FORMAT(created_at, '%b %d')";
        $date_subtitle = "Last 30 Days";
}

// Query 1: Most searched products
$search_query = "
    SELECT p.name AS product_name, COUNT(*) AS search_count
    FROM analytics a
    JOIN products p ON a.product_id = p.product_id
    WHERE a.type = 'search' $date_clause
    GROUP BY p.product_id
    ORDER BY search_count DESC
    LIMIT 10
";
$search_result = mysqli_query($conn, $search_query);
if (!$search_result) {
    error_log("Search query failed: " . mysqli_error($conn));
    $search_result = false;
}

// Query 2: Most visited product pages
$visit_query = "
    SELECT p.name AS product_name, COUNT(*) AS visit_count
    FROM analytics a
    JOIN products p ON a.product_id = p.product_id
    WHERE a.type = 'visit' $date_clause
    GROUP BY p.product_id
    ORDER BY visit_count DESC
    LIMIT 10
";
$visit_result = mysqli_query($conn, $visit_query);
if (!$visit_result) {
    error_log("Visit query failed: " . mysqli_error($conn));
    $visit_result = false;
}

// Query 3: Most ordered products
$order_query = "
    SELECT p.name AS product_name, SUM(oi.quantity) AS order_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status != 'cancelled' $date_clause
    GROUP BY p.product_id
    ORDER BY order_count DESC
    LIMIT 10
";
$order_result = mysqli_query($conn, $order_query);
if (!$order_result) {
    error_log("Order query failed: " . mysqli_error($conn));
    $order_result = false;
}

// Query 4: Sales by vendor
$vendor_query = "
    SELECT v.company_name, SUM(oi.quantity * oi.price) AS total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status IN ('completed', 'shipped', 'delivered') $date_clause
    GROUP BY v.vendor_id
    ORDER BY total_sales DESC
    LIMIT 10
";
$vendor_result = mysqli_query($conn, $vendor_query);
if (!$vendor_result) {
    error_log("Vendor query failed: " . mysqli_error($conn));
    $vendor_result = false;
}

// Query 5: Sales over time
$sales_trend_query = "
    SELECT 
        $date_group AS date,
        $date_label AS label,
        SUM(total_amount) AS sales
    FROM orders
    WHERE status IN ('completed', 'shipped', 'delivered') $date_clause
    GROUP BY date
    ORDER BY date ASC
";
$sales_trend_result = mysqli_query($conn, $sales_trend_query);
if (!$sales_trend_result) {
    error_log("Sales trend query failed: " . mysqli_error($conn));
    $sales_trend_result = false;
}

// Query 6: Total statistics
$stats_query = "
    SELECT
        (SELECT COUNT(*) FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause) AS total_orders,
        (SELECT SUM(total_amount) FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause) AS total_revenue,
        (SELECT COUNT(DISTINCT user_id) FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause) AS unique_customers,
        (SELECT AVG(total_amount) FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause) AS average_order_value
";
$stats_result = mysqli_query($conn, $stats_query);

// Initialize default values for stats in case query fails
$stats = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'unique_customers' => 0,
    'average_order_value' => 0
];

// Check if query was successful before fetching
if ($stats_result) {
    $stats = mysqli_fetch_assoc($stats_result);
} else {
    // Log the error
    error_log("Stats query failed: " . mysqli_error($conn));
    
    // Fallback - run individual queries instead of subqueries
    $total_orders_query = "SELECT COUNT(*) AS total_orders FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause";
    $total_revenue_query = "SELECT SUM(total_amount) AS total_revenue FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause";
    $unique_customers_query = "SELECT COUNT(DISTINCT user_id) AS unique_customers FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause";
    $avg_order_query = "SELECT AVG(total_amount) AS average_order_value FROM orders WHERE status IN ('completed', 'shipped', 'delivered') $date_clause";
    
    $total_orders_result = mysqli_query($conn, $total_orders_query);
    if ($total_orders_result && $row = mysqli_fetch_assoc($total_orders_result)) {
        $stats['total_orders'] = $row['total_orders'];
    }
    
    $total_revenue_result = mysqli_query($conn, $total_revenue_query);
    if ($total_revenue_result && $row = mysqli_fetch_assoc($total_revenue_result)) {
        $stats['total_revenue'] = $row['total_revenue'];
    }
    
    $unique_customers_result = mysqli_query($conn, $unique_customers_query);
    if ($unique_customers_result && $row = mysqli_fetch_assoc($unique_customers_result)) {
        $stats['unique_customers'] = $row['unique_customers'];
    }
    
    $avg_order_result = mysqli_query($conn, $avg_order_query);
    if ($avg_order_result && $row = mysqli_fetch_assoc($avg_order_result)) {
        $stats['average_order_value'] = $row['average_order_value'];
    }
}

// Prepare chart data
$sales_trend_data = [];
$sales_trend_labels = [];
if ($sales_trend_result) {
    while ($row = mysqli_fetch_assoc($sales_trend_result)) {
        $sales_trend_labels[] = $row['label'];
        $sales_trend_data[] = (float)$row['sales'];
    }
}

// Query 7: Product categories by sales
$category_query = "
    SELECT c.name, SUM(oi.quantity * oi.price) AS total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status IN ('completed', 'shipped', 'delivered') $date_clause
    GROUP BY c.category_id
    ORDER BY total_sales DESC
";
$category_result = mysqli_query($conn, $category_query);
if (!$category_result) {
    error_log("Category query failed: " . mysqli_error($conn));
    $category_result = false;
}

$category_sales_data = [];
$category_labels = [];
if ($category_result) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $category_labels[] = $row['name'];
        $category_sales_data[] = (float)$row['total_sales'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .page-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }
        
        .period-selector {
            display: flex;
            gap: 10px;
        }
        
        .period-selector a {
            padding: 5px 10px;
            border-radius: 4px;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .period-selector a.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .stat-card .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .chart-title {
            font-size: 1.2rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .chart-subtitle {
            font-size: 0.9rem;
            color: #777;
            margin-top: -15px;
            margin-bottom: 20px;
        }
        
        .ranking-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .ranking-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .ranking-title {
            font-size: 1.2rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .ranking-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .ranking-item:last-child {
            border-bottom: none;
        }
        
        .ranking-name {
            font-weight: 500;
        }
        
        .ranking-value {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .ranking-position {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: #f0f0f0;
            color: #333;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 10px;
        }
        
        .top-3 {
            background-color: #4CAF50;
            color: white;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-grid, .ranking-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1>Reports & Analytics</h1>
            <div class="period-selector">
                <a href="?period=week" class="<?php echo $time_period === 'week' ? 'active' : ''; ?>">Weekly</a>
                <a href="?period=month" class="<?php echo $time_period === 'month' ? 'active' : ''; ?>">Monthly</a>
                <a href="?period=quarter" class="<?php echo $time_period === 'quarter' ? 'active' : ''; ?>">Quarterly</a>
                <a href="?period=year" class="<?php echo $time_period === 'year' ? 'active' : ''; ?>">Yearly</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div class="stat-value"><?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo number_format($stats['unique_customers']); ?></div>
                <div class="stat-label">Unique Customers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <div class="stat-value"><?php echo number_format($stats['average_order_value'], 2); ?></div>
                <div class="stat-label">Average Order Value</div>
            </div>
        </div>
        
        <div class="chart-grid">
            <div class="chart-container">
                <h2 class="chart-title">Sales Trend</h2>
                <p class="chart-subtitle"><?php echo $date_subtitle; ?></p>
                <canvas id="salesTrendChart"></canvas>
            </div>
            <div class="chart-container">
                <h2 class="chart-title">Sales by Category</h2>
                <p class="chart-subtitle"><?php echo $date_subtitle; ?></p>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        
        <div class="ranking-grid">
            <div class="ranking-card">
                <h2 class="ranking-title">Most Searched Products</h2>
                <?php if ($search_result && mysqli_num_rows($search_result) > 0): ?>
                    <ul class="ranking-list">
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($search_result)): ?>
                            <li class="ranking-item">
                                <span>
                                    <span class="ranking-position <?php echo $i <= 3 ? 'top-3' : ''; ?>"><?php echo $i; ?></span>
                                    <span class="ranking-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                </span>
                                <span class="ranking-value"><?php echo number_format($row['search_count']); ?> searches</span>
                            </li>
                        <?php $i++; endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No search data available for this period.</p>
                <?php endif; ?>
            </div>
            
            <div class="ranking-card">
                <h2 class="ranking-title">Most Visited Products</h2>
                <?php if ($visit_result && mysqli_num_rows($visit_result) > 0): ?>
                    <ul class="ranking-list">
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($visit_result)): ?>
                            <li class="ranking-item">
                                <span>
                                    <span class="ranking-position <?php echo $i <= 3 ? 'top-3' : ''; ?>"><?php echo $i; ?></span>
                                    <span class="ranking-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                </span>
                                <span class="ranking-value"><?php echo number_format($row['visit_count']); ?> visits</span>
                            </li>
                        <?php $i++; endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No visit data available for this period.</p>
                <?php endif; ?>
            </div>
            
            <div class="ranking-card">
                <h2 class="ranking-title">Most Ordered Products</h2>
                <?php if ($order_result && mysqli_num_rows($order_result) > 0): ?>
                    <ul class="ranking-list">
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($order_result)): ?>
                            <li class="ranking-item">
                                <span>
                                    <span class="ranking-position <?php echo $i <= 3 ? 'top-3' : ''; ?>"><?php echo $i; ?></span>
                                    <span class="ranking-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                </span>
                                <span class="ranking-value"><?php echo number_format($row['order_count']); ?> orders</span>
                            </li>
                        <?php $i++; endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No order data available for this period.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="ranking-card" style="margin-bottom: 30px;">
            <h2 class="ranking-title">Top Performing Vendors</h2>
            <?php if ($vendor_result && mysqli_num_rows($vendor_result) > 0): ?>
                <ul class="ranking-list">
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($vendor_result)): ?>
                        <li class="ranking-item">
                            <span>
                                <span class="ranking-position <?php echo $i <= 3 ? 'top-3' : ''; ?>"><?php echo $i; ?></span>
                                <span class="ranking-name"><?php echo htmlspecialchars($row['company_name']); ?></span>
                            </span>
                            <span class="ranking-value">$<?php echo number_format($row['total_sales'], 2); ?></span>
                        </li>
                    <?php $i++; endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No vendor sales data available for this period.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Default empty values
        const defaultLabels = ['No Data Available'];
        const defaultData = [0];
        
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendLabels = <?php echo !empty($sales_trend_labels) ? json_encode($sales_trend_labels) : 'defaultLabels'; ?>;
        const salesTrendData = <?php echo !empty($sales_trend_data) ? json_encode($sales_trend_data) : 'defaultData'; ?>;
        
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: salesTrendLabels,
                datasets: [{
                    label: 'Sales',
                    data: salesTrendData,
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryLabels = <?php echo !empty($category_labels) ? json_encode($category_labels) : 'defaultLabels'; ?>;
        const categorySalesData = <?php echo !empty($category_sales_data) ? json_encode($category_sales_data) : 'defaultData'; ?>;
        
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categorySalesData,
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.8)',
                        'rgba(33, 150, 243, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(156, 39, 176, 0.8)',
                        'rgba(233, 30, 99, 0.8)',
                        'rgba(0, 188, 212, 0.8)',
                        'rgba(255, 87, 34, 0.8)',
                        'rgba(63, 81, 181, 0.8)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return context.label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 