<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ComparativeReports.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$conn = getConnection();

// Process filter parameters
$current_period = isset($_GET['period']) ? $_GET['period'] : 'month';
$comparison_type = isset($_GET['comparison']) ? $_GET['comparison'] : 'previous';
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$custom_start = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$custom_end = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Set up date ranges
$end_date = date('Y-m-d');

// Custom date range
if ($custom_start && $custom_end) {
    $start_date = $custom_start;
    $end_date = $custom_end;
    $period_label = date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
} else {
    // Predefined periods
    switch ($current_period) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $period_label = 'Last 7 Days';
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_label = 'Last 30 Days';
            break;
        case 'quarter':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            $period_label = 'Last 90 Days';
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            $period_label = 'Last 365 Days';
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_label = 'Last 30 Days';
    }
}

// Calculate comparison period
switch ($comparison_type) {
    case 'previous':
        // Previous period of same length
        $days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_end = date('Y-m-d', strtotime("$start_date - 1 day"));
        $previous_start = date('Y-m-d', strtotime("$previous_end - $days_diff days"));
        $comparison_label = 'vs Previous Period';
        break;
    case 'yoy':
        // Year over year (same period last year)
        $previous_start = date('Y-m-d', strtotime("$start_date - 1 year"));
        $previous_end = date('Y-m-d', strtotime("$end_date - 1 year"));
        $comparison_label = 'vs Same Period Last Year';
        break;
    default:
        // Previous period of same length
        $days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_end = date('Y-m-d', strtotime("$start_date - 1 day"));
        $previous_start = date('Y-m-d', strtotime("$previous_end - $days_diff days"));
        $comparison_label = 'vs Previous Period';
}

// Get list of vendors for filter
$vendors_query = "SELECT v.vendor_id, v.business_name as company_name FROM vendors v ORDER BY v.business_name";
$vendors_result = mysqli_query($conn, $vendors_query);
$vendors = [];
if ($vendors_result) {
    while ($row = mysqli_fetch_assoc($vendors_result)) {
        $vendors[$row['vendor_id']] = $row['company_name'];
    }
}

// Get list of categories for filter
$categories_query = "SELECT c.category_id, c.name FROM categories c ORDER BY c.name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[$row['category_id']] = $row['name'];
}

// Initialize comparative reports
$reports = new ComparativeReports($conn);

// Get sales comparison
$sales_comparison = $reports->getSalesComparison($start_date, $end_date, $previous_start, $previous_end, $vendor_id, $category_id);

// Get product comparison
$product_comparison = $reports->getProductComparison($start_date, $end_date, $previous_start, $previous_end, $vendor_id, $category_id);

// Get category comparison
$category_comparison = $reports->getCategoryComparison($start_date, $end_date, $previous_start, $previous_end, $vendor_id, $category_id);

// Traffic and user acquisition (from extended analytics)
$vendor_condition = $vendor_id ? "AND vendor_id = $vendor_id" : "";
$category_condition = $category_id ? "AND category_id = $category_id" : "";

// Get traffic data - current period
$traffic_query = "
    SELECT 
        device_type, 
        COUNT(DISTINCT session_id) as sessions
    FROM 
        analytics_extended
    WHERE 
        recorded_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        $vendor_condition
        $category_condition
    GROUP BY 
        device_type
";
$traffic_stmt = mysqli_prepare($conn, $traffic_query);
mysqli_stmt_bind_param($traffic_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($traffic_stmt);
$traffic_result = mysqli_stmt_get_result($traffic_stmt);

$device_traffic = [
    'desktop' => 0,
    'mobile' => 0,
    'tablet' => 0
];

while ($row = mysqli_fetch_assoc($traffic_result)) {
    $device_traffic[$row['device_type']] = (int)$row['sessions'];
}

// Get referrer data - current period
$referrer_query = "
    SELECT 
        SUBSTRING_INDEX(referrer, '/', 3) as source,
        COUNT(DISTINCT session_id) as sessions
    FROM 
        analytics_extended
    WHERE 
        recorded_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        AND referrer IS NOT NULL
        $vendor_condition
        $category_condition
    GROUP BY 
        source
    ORDER BY 
        sessions DESC
    LIMIT 5
";
$referrer_stmt = mysqli_prepare($conn, $referrer_query);
mysqli_stmt_bind_param($referrer_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($referrer_stmt);
$referrer_result = mysqli_stmt_get_result($referrer_stmt);

$referrers = [];
while ($row = mysqli_fetch_assoc($referrer_result)) {
    $referrers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Reports & Analytics - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        
        .filters-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            margin-bottom: 10px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .change-positive {
            color: #4CAF50;
        }
        
        .change-negative {
            color: #F44336;
        }
        
        .chart-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 500;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .data-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .comparison-table th,
        .comparison-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .comparison-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .comparison-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .period-label {
            background-color: #f0f0f0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            color: #666;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
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
            <h1>Advanced Reports & Analytics</h1>
            <span class="period-label"><?php echo htmlspecialchars($period_label); ?> <?php echo htmlspecialchars($comparison_label); ?></span>
        </div>
        
        <div class="filters-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="period">Time Period:</label>
                    <select name="period" id="period">
                        <option value="week" <?php echo $current_period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $current_period == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="quarter" <?php echo $current_period == 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="year" <?php echo $current_period == 'year' ? 'selected' : ''; ?>>Last 365 Days</option>
                        <option value="custom" <?php echo ($custom_start && $custom_end) ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="comparison">Compare With:</label>
                    <select name="comparison" id="comparison">
                        <option value="previous" <?php echo $comparison_type == 'previous' ? 'selected' : ''; ?>>Previous Period</option>
                        <option value="yoy" <?php echo $comparison_type == 'yoy' ? 'selected' : ''; ?>>Year-over-Year</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="vendor_id">Vendor:</label>
                    <select name="vendor_id" id="vendor_id">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $vendor_id == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="category_id">Category:</label>
                    <select name="category_id" id="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $category_id == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group custom-date-range" style="display: <?php echo ($custom_start && $custom_end) ? 'block' : 'none'; ?>;">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo $custom_start ?? ''; ?>">
                </div>
                
                <div class="filter-group custom-date-range" style="display: <?php echo ($custom_start && $custom_end) ? 'block' : 'none'; ?>;">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $custom_end ?? ''; ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Total Sales</div>
                <div class="stat-value">$<?php echo number_format($sales_comparison['current_period']['sales'], 2); ?></div>
                <div class="stat-change <?php echo $sales_comparison['changes']['sales'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <i class="fas fa-<?php echo $sales_comparison['changes']['sales'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($sales_comparison['changes']['sales']); ?>%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Orders</div>
                <div class="stat-value"><?php echo number_format($sales_comparison['current_period']['orders']); ?></div>
                <div class="stat-change <?php echo $sales_comparison['changes']['orders'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <i class="fas fa-<?php echo $sales_comparison['changes']['orders'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($sales_comparison['changes']['orders']); ?>%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Average Order Value</div>
                <div class="stat-value">$<?php echo number_format($sales_comparison['current_period']['avg_order'], 2); ?></div>
                <div class="stat-change <?php echo $sales_comparison['changes']['avg_order'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <i class="fas fa-<?php echo $sales_comparison['changes']['avg_order'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($sales_comparison['changes']['avg_order']); ?>%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Device Traffic</div>
                <div style="flex-grow: 1;">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Products Comparison -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Top Products Comparison</h2>
            </div>
            
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Sales</th>
                        <th>Previous Sales</th>
                        <th>Change</th>
                        <th>Current Units</th>
                        <th>Previous Units</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 0; ?>
                    <?php foreach ($product_comparison as $product_id => $product): ?>
                        <?php if ($counter++ < 10): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>$<?php echo number_format($product['current']['sales'], 2); ?></td>
                                <td>$<?php echo number_format($product['previous']['sales'], 2); ?></td>
                                <td class="<?php echo $product['changes']['sales'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                    <?php if ($product['changes']['sales'] >= 0): ?>
                                        <i class="fas fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fas fa-arrow-down"></i>
                                    <?php endif; ?>
                                    <?php echo abs($product['changes']['sales']); ?>%
                                </td>
                                <td><?php echo number_format($product['current']['quantity']); ?></td>
                                <td><?php echo number_format($product['previous']['quantity']); ?></td>
                                <td class="<?php echo $product['changes']['quantity'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                    <?php if ($product['changes']['quantity'] >= 0): ?>
                                        <i class="fas fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fas fa-arrow-down"></i>
                                    <?php endif; ?>
                                    <?php echo abs($product['changes']['quantity']); ?>%
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Category Comparison -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Category Performance</h2>
            </div>
            
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Current Sales</th>
                        <th>Previous Sales</th>
                        <th>Change</th>
                        <th>Current Units</th>
                        <th>Previous Units</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_comparison as $category_id => $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td>$<?php echo number_format($category['current']['sales'], 2); ?></td>
                            <td>$<?php echo number_format($category['previous']['sales'], 2); ?></td>
                            <td class="<?php echo $category['changes']['sales'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                <?php if ($category['changes']['sales'] >= 0): ?>
                                    <i class="fas fa-arrow-up"></i>
                                <?php else: ?>
                                    <i class="fas fa-arrow-down"></i>
                                <?php endif; ?>
                                <?php echo abs($category['changes']['sales']); ?>%
                            </td>
                            <td><?php echo number_format($category['current']['quantity']); ?></td>
                            <td><?php echo number_format($category['previous']['quantity']); ?></td>
                            <td class="<?php echo $category['changes']['quantity'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                <?php if ($category['changes']['quantity'] >= 0): ?>
                                    <i class="fas fa-arrow-up"></i>
                                <?php else: ?>
                                    <i class="fas fa-arrow-down"></i>
                                <?php endif; ?>
                                <?php echo abs($category['changes']['quantity']); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Traffic Sources -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Traffic Sources</h2>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Sessions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($referrers)): ?>
                        <tr>
                            <td colspan="2">No traffic data available for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($referrers as $referrer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($referrer['source']); ?></td>
                                <td><?php echo number_format($referrer['sessions']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Initialize device traffic chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        const deviceChart = new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Desktop', 'Mobile', 'Tablet'],
                datasets: [{
                    data: [
                        <?php echo $device_traffic['desktop']; ?>,
                        <?php echo $device_traffic['mobile']; ?>,
                        <?php echo $device_traffic['tablet']; ?>
                    ],
                    backgroundColor: [
                        '#4CAF50',
                        '#2196F3',
                        '#FFC107'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });
        
        // Toggle custom date range fields
        document.getElementById('period').addEventListener('change', function() {
            const customDateFields = document.querySelectorAll('.custom-date-range');
            if (this.value === 'custom') {
                customDateFields.forEach(field => field.style.display = 'block');
            } else {
                customDateFields.forEach(field => field.style.display = 'none');
            }
        });
    </script>
</body>
</html> 