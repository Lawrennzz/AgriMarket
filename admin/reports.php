<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/track_analytics.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$conn = getConnection();

// Define time periods for filtering
$period = isset($_GET['period']) ? $_GET['period'] : 'last30days';

// Set date range based on selected period
$end_date = date('Y-m-d');
switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = $start_date;
        break;
    case 'last7days':
        $start_date = date('Y-m-d', strtotime('-6 days'));
        break;
    case 'last30days':
        $start_date = date('Y-m-d', strtotime('-29 days'));
        break;
    case 'thismonth':
        $start_date = date('Y-m-01');
        break;
    case 'lastmonth':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-29 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-29 days'));
        break;
}

// Get top searched products
function getTopSearchedProducts($conn, $start_date, $end_date, $limit = 10) {
    $query = "SELECT p.name, p.product_id as id, COUNT(psl.id) as search_count 
              FROM product_search_logs psl
              JOIN products p ON JSON_CONTAINS(psl.product_ids, CAST(p.product_id AS JSON))
              WHERE psl.created_at BETWEEN ? AND ? 
              GROUP BY p.product_id
              ORDER BY search_count DESC
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $start_date_with_time = $start_date . ' 00:00:00';
        $end_date_with_time = $end_date . ' 23:59:59';
        mysqli_stmt_bind_param($stmt, "ssi", $start_date_with_time, $end_date_with_time, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    return [];
}

// Get top visited products
function getTopVisitedProducts($conn, $start_date, $end_date, $limit = 10) {
    $query = "SELECT p.name, p.product_id as id, COUNT(a.id) as visit_count 
              FROM analytics a
              JOIN products p ON a.product_id = p.product_id
              WHERE a.type = 'visit' 
              AND a.created_at BETWEEN ? AND ? 
              GROUP BY p.product_id
              ORDER BY visit_count DESC
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $start_date_with_time = $start_date . ' 00:00:00';
        $end_date_with_time = $end_date . ' 23:59:59';
        mysqli_stmt_bind_param($stmt, "ssi", $start_date_with_time, $end_date_with_time, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    return [];
}

// Get most popular search terms
function getPopularSearchTerms($conn, $start_date, $end_date, $limit = 10) {
    $query = "SELECT search_term, COUNT(*) as count 
              FROM product_search_logs 
              WHERE created_at BETWEEN ? AND ? 
              GROUP BY search_term 
              ORDER BY count DESC 
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $start_date_with_time = $start_date . ' 00:00:00';
        $end_date_with_time = $end_date . ' 23:59:59';
        mysqli_stmt_bind_param($stmt, "ssi", $start_date_with_time, $end_date_with_time, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $terms = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['search_term'])) {
                $terms[] = $row;
            }
        }
        
        return $terms;
    }
    
    return [];
}

// Get daily activity data for charting
function getDailyActivityData($conn, $start_date, $end_date) {
    $query = "SELECT DATE(created_at) as date,
              SUM(CASE WHEN type = 'visit' THEN 1 ELSE 0 END) as views,
              SUM(CASE WHEN type = 'search' THEN 1 ELSE 0 END) as searches,
              SUM(CASE WHEN type = 'order' THEN 1 ELSE 0 END) as orders
              FROM analytics
              WHERE created_at BETWEEN ? AND ?
              GROUP BY DATE(created_at)
              ORDER BY date";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $start_date_with_time = $start_date . ' 00:00:00';
        $end_date_with_time = $end_date . ' 23:59:59';
        mysqli_stmt_bind_param($stmt, "ss", $start_date_with_time, $end_date_with_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $daily_data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $daily_data[] = $row;
        }
        
        return $daily_data;
    }
    
    return [];
}

// Fetch data for reports
$top_searched_products = getTopSearchedProducts($conn, $start_date, $end_date);
$top_visited_products = getTopVisitedProducts($conn, $start_date, $end_date);
$popular_search_terms = getPopularSearchTerms($conn, $start_date, $end_date);
$daily_activity = getDailyActivityData($conn, $start_date, $end_date);

// Format data for charts
$dates = [];
$views = [];
$searches = [];
$orders = [];

foreach ($daily_activity as $day) {
    $dates[] = $day['date'];
    $views[] = intval($day['views']);
    $searches[] = intval($day['searches']);
    $orders[] = intval($day['orders']);
}

// Convert to JSON for JavaScript
$chart_data = [
    'dates' => $dates,
    'views' => $views,
    'searches' => $searches,
    'orders' => $orders
];

$chart_data_json = json_encode($chart_data);

// Prepare data for the product charts
$search_product_names = [];
$search_product_counts = [];
foreach ($top_searched_products as $product) {
    $search_product_names[] = $product['name'];
    $search_product_counts[] = $product['search_count'];
}

$visit_product_names = [];
$visit_product_counts = [];
foreach ($top_visited_products as $product) {
    $visit_product_names[] = $product['name'];
    $visit_product_counts[] = $product['visit_count'];
}

$search_terms = [];
$search_term_counts = [];
foreach ($popular_search_terms as $term) {
    $search_terms[] = $term['search_term'];
    $search_term_counts[] = $term['count'];
}

// Convert to JSON for JavaScript
$search_products_data = json_encode([
    'labels' => $search_product_names,
    'data' => $search_product_counts
]);

$visit_products_data = json_encode([
    'labels' => $visit_product_names,
    'data' => $visit_product_counts
]);

$search_terms_data = json_encode([
    'labels' => $search_terms,
    'data' => $search_term_counts
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Reports - AgriMarket Admin</title>
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
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .period-selector form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .period-selector label {
            margin-right: 5px;
            font-weight: bold;
        }
        
        .period-selector select,
        .period-selector input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .period-selector button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .period-selector button:hover {
            background-color: #45a049;
        }
        
        .chart-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
        
        .data-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .data-card h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table th {
            background-color: #f7f7f7;
            font-weight: bold;
        }
        
        .data-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .info-text {
            font-style: italic;
            color: #666;
            margin-top: 20px;
        }
        
        .action-links {
            text-align: right;
            margin-top: 20px;
        }
        
        .action-links a {
            color: #007bff;
            text-decoration: none;
            margin-left: 15px;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        .custom-date-range {
            display: none;
            margin-top: 10px;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1>Analytics Reports</h1>
            <div class="action-links">
                <a href="clean_analytics.php"><i class="fas fa-trash-alt"></i> Clean Analytics Data</a>
            </div>
        </div>
        
        <div class="period-selector">
            <form method="GET" action="reports.php">
                <div>
                    <label for="period">Time Period:</label>
                    <select name="period" id="period" onchange="toggleCustomDateRange()">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="last7days" <?php echo $period === 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="last30days" <?php echo $period === 'last30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="thismonth" <?php echo $period === 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                        <option value="lastmonth" <?php echo $period === 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div id="customDateRange" class="custom-date-range" style="<?php echo $period === 'custom' ? 'display: flex;' : ''; ?>">
                    <label for="start_date">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                    
                    <label for="end_date">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <button type="submit">Apply</button>
            </form>
        </div>
        
        <div class="chart-card">
            <h2>User Activity Overview (<?php echo $start_date; ?> to <?php echo $end_date; ?>)</h2>
            <div class="chart-container">
                <canvas id="activityChart"></canvas>
            </div>
            <?php if (empty($daily_activity)): ?>
                <p class="info-text">No activity data available for the selected period.</p>
            <?php endif; ?>
        </div>
        
        <div class="grid-container">
            <div class="chart-card">
                <h2>Top Searched Products</h2>
                <div class="chart-container">
                    <canvas id="searchedProductsChart"></canvas>
                </div>
                <?php if (empty($top_searched_products)): ?>
                    <p class="info-text">No search data available for the selected period.</p>
                <?php endif; ?>
            </div>
            
            <div class="chart-card">
                <h2>Most Visited Products</h2>
                <div class="chart-container">
                    <canvas id="visitedProductsChart"></canvas>
                </div>
                <?php if (empty($top_visited_products)): ?>
                    <p class="info-text">No visit data available for the selected period.</p>
                <?php endif; ?>
            </div>
            
            <div class="data-card">
                <h2>Popular Search Terms</h2>
                <?php if (!empty($popular_search_terms)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Search Term</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popular_search_terms as $term): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($term['search_term']); ?></td>
                                    <td><?php echo $term['count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="info-text">No search data available for the selected period.</p>
                <?php endif; ?>
            </div>
            
            <div class="chart-card">
                <h2>Popular Search Terms</h2>
                <div class="chart-container">
                    <canvas id="searchTermsChart"></canvas>
                </div>
                <?php if (empty($popular_search_terms)): ?>
                    <p class="info-text">No search term data available for the selected period.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid-container">
            <div class="data-card">
                <h2>Top Searched Products</h2>
                <?php if (!empty($top_searched_products)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Search Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_searched_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['search_count']; ?></td>
                                    <td>
                                        <a href="../product_details.php?id=<?php echo $product['id']; ?>" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="info-text">No search data available for the selected period.</p>
                <?php endif; ?>
            </div>
            
            <div class="data-card">
                <h2>Most Visited Products</h2>
                <?php if (!empty($top_visited_products)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Visit Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_visited_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['visit_count']; ?></td>
                                    <td>
                                        <a href="../product_details.php?id=<?php echo $product['id']; ?>" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="info-text">No visit data available for the selected period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle custom date range display
        function toggleCustomDateRange() {
            const periodSelect = document.getElementById('period');
            const customDateRange = document.getElementById('customDateRange');
            
            if (periodSelect.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Activity chart
            const activityData = <?php echo $chart_data_json; ?>;
            if (activityData.dates.length > 0) {
                const activityCtx = document.getElementById('activityChart').getContext('2d');
                new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: activityData.dates,
                        datasets: [
                            {
                                label: 'Page Views',
                                data: activityData.views,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Searches',
                                data: activityData.searches,
                                borderColor: 'rgba(255, 159, 64, 1)',
                                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Orders',
                                data: activityData.orders,
                                borderColor: 'rgba(75, 192, 192, 1)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Searched products chart
            const searchProductsData = <?php echo $search_products_data; ?>;
            if (searchProductsData.labels.length > 0) {
                const searchProductsCtx = document.getElementById('searchedProductsChart').getContext('2d');
                new Chart(searchProductsCtx, {
                    type: 'bar',
                    data: {
                        labels: searchProductsData.labels,
                        datasets: [{
                            label: 'Search Count',
                            data: searchProductsData.data,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Visited products chart
            const visitProductsData = <?php echo $visit_products_data; ?>;
            if (visitProductsData.labels.length > 0) {
                const visitProductsCtx = document.getElementById('visitedProductsChart').getContext('2d');
                new Chart(visitProductsCtx, {
                    type: 'bar',
                    data: {
                        labels: visitProductsData.labels,
                        datasets: [{
                            label: 'Visit Count',
                            data: visitProductsData.data,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Search terms chart
            const searchTermsData = <?php echo $search_terms_data; ?>;
            if (searchTermsData.labels.length > 0) {
                const searchTermsCtx = document.getElementById('searchTermsChart').getContext('2d');
                new Chart(searchTermsCtx, {
                    type: 'pie',
                    data: {
                        labels: searchTermsData.labels,
                        datasets: [{
                            data: searchTermsData.data,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(199, 199, 199, 0.7)',
                                'rgba(83, 102, 255, 0.7)',
                                'rgba(40, 159, 64, 0.7)',
                                'rgba(210, 99, 132, 0.7)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(199, 199, 199, 1)',
                                'rgba(83, 102, 255, 1)',
                                'rgba(40, 159, 64, 1)',
                                'rgba(210, 99, 132, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html> 