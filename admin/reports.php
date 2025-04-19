<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
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

// Query 1: Most searched products - real-time data
$search_query = "
    SELECT 
        p.product_id,
        p.name AS product_name,
        COUNT(*) AS search_count
    FROM analytics a
    JOIN products p ON a.product_id = p.product_id
    WHERE a.type = 'search' $date_clause
    GROUP BY p.product_id, p.name
    ORDER BY search_count DESC
    LIMIT 5
";

$search_result = mysqli_query($conn, $search_query);
if (!$search_result) {
    error_log("Search query failed: " . mysqli_error($conn));
    // If analytics table doesn't have data, check product_search_logs table
    $search_query_alt = "
        SELECT 
            p.product_id,
            p.name AS product_name,
            COUNT(*) AS search_count
        FROM product_search_logs psl
        JOIN products p ON JSON_CONTAINS(psl.product_ids, CAST(p.product_id AS JSON))
        WHERE psl.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY p.product_id, p.name
        ORDER BY search_count DESC
        LIMIT 5
    ";
    $search_result = mysqli_query($conn, $search_query_alt);
    
    if (!$search_result) {
        // If both fail, create a simpler query for search term counts
        $search_query_simple = "
            SELECT 
                p.product_id,
                p.name AS product_name,
                (SELECT COUNT(*) FROM product_search_logs WHERE search_term LIKE CONCAT('%', p.name, '%')) AS search_count
            FROM 
                products p
            ORDER BY 
                search_count DESC
            LIMIT 5
        ";
        $search_result = mysqli_query($conn, $search_query_simple);
    }
}

// Query 2: Most visited product pages - real-time data
$visit_query = "
    SELECT 
        p.product_id,
        p.name AS product_name,
        COUNT(*) AS visit_count
    FROM analytics a
    JOIN products p ON a.product_id = p.product_id
    WHERE a.type = 'visit' $date_clause
    GROUP BY p.product_id, p.name
    ORDER BY visit_count DESC
    LIMIT 5
";

$visit_result = mysqli_query($conn, $visit_query);
if (!$visit_result || mysqli_num_rows($visit_result) === 0) {
    // If analytics table doesn't have visit data, try product_visits table
    $visit_query_alt = "
        SELECT 
            p.product_id,
            p.name AS product_name,
            COUNT(*) AS visit_count
        FROM product_visits pv
        JOIN products p ON pv.product_id = p.product_id
        WHERE pv.visit_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY p.product_id, p.name
        ORDER BY visit_count DESC
        LIMIT 5
    ";
    $visit_result = mysqli_query($conn, $visit_query_alt);
    
    if (!$visit_result || mysqli_num_rows($visit_result) === 0) {
        // If both fail, create a basic query from order_items as a proxy for popularity
        $visit_query_simple = "
            SELECT 
                p.product_id,
                p.name AS product_name,
                COUNT(DISTINCT oi.order_id) AS visit_count
            FROM 
                products p
            LEFT JOIN
                order_items oi ON p.product_id = oi.product_id
            GROUP BY 
                p.product_id, p.name
            ORDER BY 
                visit_count DESC
            LIMIT 5
        ";
        $visit_result = mysqli_query($conn, $visit_query_simple);
    }
}

// Query 3: Most ordered products - real-time data
$order_query = "
    SELECT 
        p.product_id,
        p.name AS product_name, 
        SUM(oi.quantity) AS order_count,
        SUM(oi.quantity * oi.price) AS total_revenue
    FROM products p
    JOIN order_items oi ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status IN ('processing', 'shipped', 'delivered') 
    AND o.deleted_at IS NULL $date_clause
    GROUP BY p.product_id, p.name
    ORDER BY order_count DESC
    LIMIT 5
";
$order_result = mysqli_query($conn, $order_query);
if (!$order_result || mysqli_num_rows($order_result) === 0) {
    // If order query fails, try a simpler query
    $order_query_simple = "
        SELECT 
            p.product_id,
            p.name AS product_name, 
            COUNT(oi.order_item_id) AS order_count
        FROM 
            products p
        LEFT JOIN 
            order_items oi ON p.product_id = oi.product_id
        GROUP BY 
            p.product_id, p.name
        ORDER BY 
            order_count DESC
        LIMIT 5
    ";
    $order_result = mysqli_query($conn, $order_query_simple);
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
        COALESCE(SUM(o.total), 0) AS sales
    FROM orders o
    WHERE o.status IN ('completed', 'shipped', 'delivered') 
    AND o.deleted_at IS NULL
    $date_clause
    GROUP BY date
    ORDER BY date ASC
";
$sales_trend_result = mysqli_query($conn, $sales_trend_query);
if (!$sales_trend_result) {
    error_log("Sales trend query failed: " . mysqli_error($conn));
    $sales_trend_result = false;
}

// Query 6: Total statistics - real-time data
$stats_query = "
    SELECT
        COUNT(DISTINCT o.order_id) AS total_orders,
        COALESCE(SUM(o.total), 0) AS total_revenue,
        COUNT(DISTINCT o.user_id) AS unique_customers,
        COALESCE(AVG(o.total), 0) AS average_order_value
    FROM orders o
    WHERE o.status IN ('processing', 'shipped', 'delivered')
    AND o.deleted_at IS NULL
    $date_clause
";
$stats_result = mysqli_query($conn, $stats_query);

// Initialize default values for stats
$stats = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'unique_customers' => 0,
    'average_order_value' => 0
];

// Check if query was successful before fetching
if ($stats_result && mysqli_num_rows($stats_result) > 0) {
    $stats = mysqli_fetch_assoc($stats_result);
} else {
    // Log the error
    error_log("Stats query failed: " . mysqli_error($conn));
    
    // Run individual queries for each statistic
    $total_orders_query = "SELECT COUNT(DISTINCT order_id) AS total_orders FROM orders WHERE status IN ('processing', 'shipped', 'delivered') $date_clause";
    $total_revenue_query = "SELECT COALESCE(SUM(total), 0) AS total_revenue FROM orders WHERE status IN ('processing', 'shipped', 'delivered') $date_clause";
    $unique_customers_query = "SELECT COUNT(DISTINCT user_id) AS unique_customers FROM orders WHERE status IN ('processing', 'shipped', 'delivered') $date_clause";
    $avg_order_query = "SELECT COALESCE(AVG(total), 0) AS average_order_value FROM orders WHERE status IN ('processing', 'shipped', 'delivered') $date_clause";
    
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
    SELECT 
        c.name, 
        COALESCE(SUM(oi.quantity * oi.price), 0) AS total_sales,
        COUNT(DISTINCT o.order_id) as order_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON oi.product_id = p.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status IN ('completed', 'shipped', 'delivered') 
        AND o.deleted_at IS NULL $date_clause
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
            height: auto;
        }
        
        .chart-card, .ranking-card, .data-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .chart-title, .ranking-title, .card-title {
            font-size: 1.2rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .ranking-item:last-child {
            border-bottom: none;
        }
        
        .rank-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #eee;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .rank-1 {
            background-color: gold;
            color: #333;
        }
        
        .rank-2 {
            background-color: silver;
            color: #333;
        }
        
        .rank-3 {
            background-color: #cd7f32; /* Bronze */
            color: white;
        }
        
        .item-name {
            flex: 1;
            font-weight: 500;
        }
        
        .item-value {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .no-data-message {
            text-align: center;
            padding: 30px 20px;
            color: #777;
        }
        
        .no-data-message i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .no-data-message p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Responsive styling */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 10px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .period-selector {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 5px;
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
            <div class="chart-container sales-trend-container">
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
                <div class="data-card">
                    <?php if ($search_result && mysqli_num_rows($search_result) > 0): ?>
                        <ul class="ranking-list">
                            <?php $rank = 1; while ($row = mysqli_fetch_assoc($search_result)): ?>
                                <li class="ranking-item">
                                    <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <span class="item-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                    <span class="item-value"><?php echo number_format($row['search_count']); ?> searches</span>
                                </li>
                            <?php $rank++; endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-search"></i>
                            <p>No search data available for this period. Search data is collected when users search for products.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ranking-card">
                <h2 class="ranking-title">Most Visited Products</h2>
                <div class="data-card">
                    <?php if ($visit_result && mysqli_num_rows($visit_result) > 0): ?>
                        <ul class="ranking-list">
                            <?php $rank = 1; while ($row = mysqli_fetch_assoc($visit_result)): ?>
                                <li class="ranking-item">
                                    <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <span class="item-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                    <span class="item-value"><?php echo number_format($row['visit_count']); ?> visits</span>
                                </li>
                            <?php $rank++; endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-eye"></i>
                            <p>No visit data available for this period. Visit data is collected when users view product pages.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ranking-card">
                <h2 class="ranking-title">Most Ordered Products</h2>
                <div class="data-card">
                    <?php if ($order_result && mysqli_num_rows($order_result) > 0): ?>
                        <ul class="ranking-list">
                            <?php $rank = 1; while ($row = mysqli_fetch_assoc($order_result)): ?>
                                <li class="ranking-item">
                                    <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <span class="item-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                    <span class="item-value"><?php echo number_format($row['order_count']); ?> ordered</span>
                                </li>
                            <?php $rank++; endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-shopping-cart"></i>
                            <p>No order data available for this period. Order data is collected when customers make purchases.</p>
                        </div>
                    <?php endif; ?>
                </div>
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
        // Create charts using Chart.js
        window.onload = function() {
            // Get chart data for search analysis
            const searchChartData = {
                labels: [
                    <?php 
                    $search_labels = [];
                    if ($search_result) {
                        while ($row = mysqli_fetch_assoc($search_result)) {
                            $search_labels[] = "'" . addslashes($row['product_name']) . "'";
                        }
                        mysqli_data_seek($search_result, 0); // Reset result pointer
                        echo implode(', ', $search_labels);
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Search Appearances',
                        data: [
                            <?php 
                            $search_data = [];
                            if ($search_result) {
                                while ($row = mysqli_fetch_assoc($search_result)) {
                                    // Use search_impressions if available, otherwise fall back to search_count
                                    $count = isset($row['search_impressions']) ? $row['search_impressions'] : $row['search_count'];
                                    $search_data[] = $count;
                                }
                                echo implode(', ', $search_data);
                                mysqli_data_seek($search_result, 0); // Reset result pointer
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Actual Visits',
                        data: [
                            <?php 
                            $visit_data = [];
                            if ($search_result) {
                                while ($row = mysqli_fetch_assoc($search_result)) {
                                    // Use visits if available, otherwise use 0
                                    $visits = isset($row['visits']) ? $row['visits'] : 0;
                                    $visit_data[] = $visits;
                                }
                                echo implode(', ', $visit_data);
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            };

            // Get chart data for visits
            const visitChartData = {
                labels: [
                    <?php 
                    $visit_labels = [];
                    if ($visit_result) {
                        while ($row = mysqli_fetch_assoc($visit_result)) {
                            $visit_labels[] = "'" . addslashes($row['product_name']) . "'";
                        }
                        mysqli_data_seek($visit_result, 0); // Reset result pointer
                        echo implode(', ', $visit_labels);
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Unique Visits',
                        data: [
                            <?php 
                            $unique_visit_data = [];
                            if ($visit_result) {
                                while ($row = mysqli_fetch_assoc($visit_result)) {
                                    // Use unique_visits if available, otherwise fall back to visit_count
                                    $count = isset($row['unique_visits']) ? $row['unique_visits'] : $row['visit_count'];
                                    $unique_visit_data[] = $count;
                                }
                                echo implode(', ', $unique_visit_data);
                                mysqli_data_seek($visit_result, 0); // Reset result pointer
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Visits',
                        data: [
                            <?php 
                            $total_visit_data = [];
                            if ($visit_result) {
                                while ($row = mysqli_fetch_assoc($visit_result)) {
                                    // Use total_visits if available, otherwise fall back to visit_count
                                    $count = isset($row['total_visits']) ? $row['total_visits'] : $row['visit_count'];
                                    $total_visit_data[] = $count;
                                }
                                echo implode(', ', $total_visit_data);
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(153, 102, 255, 0.5)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }
                ]
            };

            // Render the search chart
            const searchChart = new Chart(
                document.getElementById('searchChart').getContext('2d'),
                {
                    type: 'bar',
                    data: searchChartData,
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Most Searched Products'
                            },
                            tooltip: {
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return tooltipItems[0].label;
                                    },
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y;
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                }
            );

            // Render the visits chart
            const visitChart = new Chart(
                document.getElementById('visitChart').getContext('2d'),
                {
                    type: 'bar',
                    data: visitChartData,
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Most Visited Products'
                            },
                            tooltip: {
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return tooltipItems[0].label;
                                    },
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y;
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                }
            );
            
            // Rest of your chart code...
        }
    </script>
</body>
</html> 