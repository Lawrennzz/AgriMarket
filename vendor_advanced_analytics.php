<?php
session_start();
include 'config.php';
require_once 'includes/ComparativeReports.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get vendor ID
$vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ? AND deleted_at IS NULL";
$vendor_stmt = mysqli_prepare($conn, $vendor_query);
mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
mysqli_stmt_execute($vendor_stmt);
$vendor_result = mysqli_stmt_get_result($vendor_stmt);

if ($vendor_row = mysqli_fetch_assoc($vendor_result)) {
    $vendor_id = $vendor_row['vendor_id'];
} else {
    // Handle case where user is not a vendor
    $_SESSION['error'] = "Vendor profile not found.";
    header("Location: dashboard.php");
    exit();
}

// Process filter parameters
$current_period = isset($_GET['period']) ? $_GET['period'] : 'month';
$comparison_type = isset($_GET['comparison']) ? $_GET['comparison'] : 'previous';
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

// Get categories for filter
$categories_query = "SELECT DISTINCT c.category_id, c.name 
                    FROM products p
                    JOIN categories c ON p.category_id = c.category_id
                    WHERE p.vendor_id = ? AND p.deleted_at IS NULL
                    ORDER BY c.name";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $vendor_id);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[$row['category_id']] = $row['name'];
}

// Initialize comparative reports
$reports = new ComparativeReports($conn);

// Apply category filter for vendor
$filtered_vendor_id = $vendor_id;

// Get sales comparison
$sales_comparison = $reports->getSalesComparison($start_date, $end_date, $previous_start, $previous_end, $filtered_vendor_id);

// Get product comparison
$product_comparison = $reports->getProductComparison($start_date, $end_date, $previous_start, $previous_end, $filtered_vendor_id);

// Get category comparison
$category_comparison = $reports->getCategoryComparison($start_date, $end_date, $previous_start, $previous_end, $filtered_vendor_id);

// Get daily sales data for chart
$daily_sales_query = "
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m-%d') as sale_date,
        SUM(oi.quantity * oi.price) as daily_sales
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE p.vendor_id = ?
    AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    AND o.status != 'cancelled'
    GROUP BY sale_date
    ORDER BY sale_date ASC
";

$daily_sales_stmt = mysqli_prepare($conn, $daily_sales_query);
mysqli_stmt_bind_param($daily_sales_stmt, "iss", $vendor_id, $start_date, $end_date);
mysqli_stmt_execute($daily_sales_stmt);
$daily_sales_result = mysqli_stmt_get_result($daily_sales_stmt);

$sales_dates = [];
$sales_values = [];

while ($row = mysqli_fetch_assoc($daily_sales_result)) {
    $sales_dates[] = date('M d', strtotime($row['sale_date']));
    $sales_values[] = (float)$row['daily_sales'];
}

// Get customer demographics
$customer_demo_query = "
    SELECT 
        COUNT(DISTINCT o.user_id) as total_customers,
        COUNT(DISTINCT CASE WHEN o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) THEN o.user_id END) as current_customers,
        COUNT(DISTINCT CASE WHEN o.created_at < ? THEN o.user_id END) as existing_customers
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE p.vendor_id = ?
";

$customer_demo_stmt = mysqli_prepare($conn, $customer_demo_query);
mysqli_stmt_bind_param($customer_demo_stmt, "sssi", $start_date, $end_date, $start_date, $vendor_id);
mysqli_stmt_execute($customer_demo_stmt);
$customer_demo = mysqli_fetch_assoc(mysqli_stmt_get_result($customer_demo_stmt));

// Calculate new customers in current period
$new_customers = $customer_demo['current_customers'] - $customer_demo['existing_customers'];
$new_customers = max(0, $new_customers); // Ensure non-negative value
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - AgriMarket Vendor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .filters-section {
            background-color: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-actions {
            grid-column: 1/-1;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-change {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .change-positive {
            color: #4CAF50;
        }
        
        .change-negative {
            color: #F44336;
        }
        
        .chart-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 500;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .data-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .period-label {
            background-color: #f0f0f0;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #555;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-chart-line"></i> Vendor Analytics</h1>
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
                    <label for="category_id">Product Category:</label>
                    <select name="category_id" id="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $category_id == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group custom-date" style="display: <?php echo ($custom_start && $custom_end) ? 'block' : 'none'; ?>;">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo $custom_start ?? ''; ?>">
                </div>
                
                <div class="filter-group custom-date" style="display: <?php echo ($custom_start && $custom_end) ? 'block' : 'none'; ?>;">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $custom_end ?? ''; ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($sales_comparison['current_period']['sales'], 2); ?></div>
                <div class="stat-label">Total Sales</div>
                <div class="stat-change <?php echo $sales_comparison['changes']['sales'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <?php if ($sales_comparison['changes']['sales'] >= 0): ?>
                        <i class="fas fa-arrow-up"></i>
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i>
                    <?php endif; ?>
                    <?php echo abs($sales_comparison['changes']['sales']); ?>% compared to previous period
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo number_format($sales_comparison['current_period']['orders']); ?></div>
                <div class="stat-label">Orders</div>
                <div class="stat-change <?php echo $sales_comparison['changes']['orders'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <?php if ($sales_comparison['changes']['orders'] >= 0): ?>
                        <i class="fas fa-arrow-up"></i>
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i>
                    <?php endif; ?>
                    <?php echo abs($sales_comparison['changes']['orders']); ?>% compared to previous period
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($sales_comparison['current_period']['avg_order'], 2); ?></div>
                <div class="stat-label">Average Order Value</div>
                <div class="stat-change <?php echo $sales_comparison['changes']['avg_order'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                    <?php if ($sales_comparison['changes']['avg_order'] >= 0): ?>
                        <i class="fas fa-arrow-up"></i>
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i>
                    <?php endif; ?>
                    <?php echo abs($sales_comparison['changes']['avg_order']); ?>% compared to previous period
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($customer_demo['current_customers']); ?></div>
                <div class="stat-label">Customers</div>
                <div class="stat-change">
                    <?php echo number_format($new_customers); ?> new customers this period
                </div>
            </div>
        </div>
        
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Sales Trend</h2>
            </div>
            <div class="chart-container">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
        
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Top Products Performance</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Sales</th>
                            <th>Previous</th>
                            <th>Change</th>
                            <th>Units Sold</th>
                            <th>Previous</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 0;
                        foreach ($product_comparison as $product_id => $product): 
                            if ($counter++ < 10):
                        ?>
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
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Category Performance</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Current Sales</th>
                            <th>Previous</th>
                            <th>Change</th>
                            <th>Units Sold</th>
                            <th>Previous</th>
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
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Sales trend chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($sales_dates); ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?php echo json_encode($sales_values); ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
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
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Toggle custom date fields
        document.getElementById('period').addEventListener('change', function() {
            const customDateFields = document.querySelectorAll('.custom-date');
            if (this.value === 'custom') {
                customDateFields.forEach(field => field.style.display = 'block');
            } else {
                customDateFields.forEach(field => field.style.display = 'none');
            }
        });
    </script>
</body>
</html> 