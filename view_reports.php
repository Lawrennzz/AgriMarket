<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$page_title = "View Reports";
$success_message = "";
$error_message = "";

// Get report type from query string
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';
$time_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$current_year = date('Y');
$current_month = date('m');

// Prepare date range based on time period
switch ($time_period) {
    case 'weekly':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        $end_date = date('Y-m-d');
        $group_by = "DATE(o.created_at)";
        $date_format = "%Y-%m-%d";
        break;
    case 'monthly':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $group_by = "DATE(o.created_at)";
        $date_format = "%Y-%m-%d";
        break;
    case 'quarterly':
        $current_quarter = ceil($current_month / 3);
        $start_month = (($current_quarter - 1) * 3) + 1;
        $start_date = "$current_year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
        $end_month = $start_month + 2;
        $end_date = "$current_year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$current_year-$end_month-01"));
        $group_by = "MONTH(o.created_at)";
        $date_format = "%Y-%m";
        break;
    case 'yearly':
        $start_date = "$current_year-01-01";
        $end_date = "$current_year-12-31";
        $group_by = "MONTH(o.created_at)";
        $date_format = "%Y-%m";
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $group_by = "DATE(o.created_at)";
        $date_format = "%Y-%m-%d";
}

// Handle different report types
switch ($report_type) {
    case 'sales':
        // Sales report query
        $report_query = "
            SELECT 
                DATE_FORMAT(o.created_at, '$date_format') as date,
                COUNT(o.order_id) as order_count,
                SUM(o.total) as total_sales
            FROM 
                orders o
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                $group_by
            ORDER BY 
                o.created_at ASC
        ";
        $report_title = "Sales Report";
        $report_description = "Overview of sales from $start_date to $end_date";
        $chart_type = "line";
        $y_axis_label = "Revenue ($)";
        break;
        
    case 'products':
        // Top products report query
        $report_query = "
            SELECT 
                p.name as product_name,
                COUNT(oi.item_id) as order_count,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.price * oi.quantity) as total_revenue
            FROM 
                order_items oi
            JOIN 
                products p ON oi.product_id = p.product_id
            JOIN 
                orders o ON oi.order_id = o.order_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                oi.product_id
            ORDER BY 
                quantity_sold DESC
            LIMIT 10
        ";
        $report_title = "Top Products Report";
        $report_description = "Best selling products from $start_date to $end_date";
        $chart_type = "bar";
        $y_axis_label = "Units Sold";
        break;
        
    case 'categories':
        // Category performance report
        $report_query = "
            SELECT 
                c.name as category_name,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.price * oi.quantity) as total_revenue
            FROM 
                order_items oi
            JOIN 
                products p ON oi.product_id = p.product_id
            JOIN 
                categories c ON p.category_id = c.category_id
            JOIN 
                orders o ON oi.order_id = o.order_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                p.category_id
            ORDER BY 
                total_revenue DESC
        ";
        $report_title = "Category Performance Report";
        $report_description = "Sales by product category from $start_date to $end_date";
        $chart_type = "pie";
        $y_axis_label = "Revenue ($)";
        break;
        
    case 'vendors':
        // Vendor performance report
        $report_query = "
            SELECT 
                v.business_name as vendor_name,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.price * oi.quantity) as total_revenue
            FROM 
                order_items oi
            JOIN 
                products p ON oi.product_id = p.product_id
            JOIN 
                vendors v ON p.vendor_id = v.vendor_id
            JOIN 
                orders o ON oi.order_id = o.order_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                p.vendor_id
            ORDER BY 
                total_revenue DESC
            LIMIT 10
        ";
        $report_title = "Vendor Performance Report";
        $report_description = "Top performing vendors from $start_date to $end_date";
        $chart_type = "bar";
        $y_axis_label = "Revenue ($)";
        break;
        
    case 'customers':
        // Customer activity report
        $report_query = "
            SELECT 
                u.name as customer_name,
                COUNT(o.order_id) as order_count,
                SUM(o.total) as total_spent,
                MAX(o.created_at) as last_order_date
            FROM 
                orders o
            JOIN 
                users u ON o.user_id = u.user_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                o.user_id
            ORDER BY 
                total_spent DESC
            LIMIT 10
        ";
        $report_title = "Top Customers Report";
        $report_description = "Most valuable customers from $start_date to $end_date";
        $chart_type = "bar";
        $y_axis_label = "Amount Spent ($)";
        break;
        
    default:
        // Default to sales report
        $report_query = "
            SELECT 
                DATE_FORMAT(o.created_at, '$date_format') as date,
                COUNT(o.order_id) as order_count,
                SUM(o.total) as total_sales
            FROM 
                orders o
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                $group_by
            ORDER BY 
                o.created_at ASC
        ";
        $report_title = "Sales Report";
        $report_description = "Overview of sales from $start_date to $end_date";
        $chart_type = "line";
        $y_axis_label = "Revenue ($)";
}

// Execute report query
$stmt = mysqli_prepare($conn, $report_query);
if ($stmt === false) {
    $error_message = "Error preparing report query: " . mysqli_error($conn);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $report_result = mysqli_stmt_get_result($stmt);
    
    // Check if query returned results
    if (mysqli_num_rows($report_result) === 0) {
        $error_message = "No data available for the selected time period.";
    }
}

// Get summary statistics
$summary_query = "
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        COUNT(DISTINCT o.user_id) as unique_customers,
        SUM(o.total) as total_revenue,
        AVG(o.total) as average_order_value
    FROM 
        orders o
    WHERE 
        o.created_at BETWEEN ? AND ?
        AND o.status != 'cancelled'
";

$summary_stmt = mysqli_prepare($conn, $summary_query);
if ($summary_stmt === false) {
    $summary_stats = [
        'total_orders' => 0,
        'unique_customers' => 0,
        'total_revenue' => 0,
        'average_order_value' => 0
    ];
} else {
    mysqli_stmt_bind_param($summary_stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($summary_stmt);
    $summary_result = mysqli_stmt_get_result($summary_stmt);
    $summary_stats = mysqli_fetch_assoc($summary_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }
        
        .report-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-header {
            margin-bottom: l5px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .report-title {
            font-size: 1.5rem;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .report-description {
            color: #666;
            margin: 0;
        }
        
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-section {
            display: flex;
            align-items: center;
        }
        
        .filter-label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        
        .apply-filters {
            margin-left: auto;
            padding: 8px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .apply-filters:hover {
            background-color: #45a049;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 500;
            color: #333;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        .data-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        
        .export-btn:hover {
            background-color: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .filter-section {
                flex-basis: 100%;
            }
            
            .apply-filters {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><?php echo $page_title; ?></h1>
            
            <button class="export-btn" onclick="exportReportCSV()">
                <i class="fas fa-download"></i> Export to CSV
            </button>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <form action="" method="GET" id="report-filter-form">
                <div class="filter-section">
                    <label class="filter-label" for="type">Report Type:</label>
                    <select class="filter-select" id="type" name="type" onchange="this.form.submit()">
                        <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                        <option value="products" <?php echo $report_type === 'products' ? 'selected' : ''; ?>>Top Products</option>
                        <option value="categories" <?php echo $report_type === 'categories' ? 'selected' : ''; ?>>Category Performance</option>
                        <option value="vendors" <?php echo $report_type === 'vendors' ? 'selected' : ''; ?>>Vendor Performance</option>
                        <option value="customers" <?php echo $report_type === 'customers' ? 'selected' : ''; ?>>Top Customers</option>
                    </select>
                </div>
                
                <div class="filter-section">
                    <label class="filter-label" for="period">Time Period:</label>
                    <select class="filter-select" id="period" name="period" onchange="this.form.submit()">
                        <option value="weekly" <?php echo $time_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $time_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $time_period === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo $time_period === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
            </form>
        </div>
        
        <div class="report-container">
            <div class="report-header">
                <h2 class="report-title"><?php echo $report_title; ?></h2>
                <p class="report-description"><?php echo $report_description; ?></p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($summary_stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($summary_stats['unique_customers']); ?></div>
                    <div class="stat-label">Unique Customers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($summary_stats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($summary_stats['average_order_value'], 2); ?></div>
                    <div class="stat-label">Avg. Order Value</div>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
            
            <?php if (isset($report_result) && mysqli_num_rows($report_result) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php 
                            // Dynamic table headers based on report type
                            $first_row = mysqli_fetch_assoc($report_result);
                            mysqli_data_seek($report_result, 0); // Reset pointer
                            
                            foreach ($first_row as $key => $value) {
                                echo "<th>" . ucfirst(str_replace('_', ' ', $key)) . "</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($report_result)): ?>
                            <tr>
                                <?php foreach ($row as $key => $value): ?>
                                    <td>
                                        <?php 
                                        // Format values based on column name
                                        if (strpos($key, 'total') !== false || strpos($key, 'revenue') !== false || strpos($key, 'spent') !== false || strpos($key, 'value') !== false) {
                                            echo '$' . number_format($value, 2);
                                        } elseif (strpos($key, 'date') !== false && strtotime($value)) {
                                            echo date('M d, Y', strtotime($value));
                                        } else {
                                            echo $value;
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($report_result) && mysqli_num_rows($report_result) > 0): ?>
                // Reset result pointer
                <?php mysqli_data_seek($report_result, 0); ?>
                
                // Prepare chart data
                const chartData = {
                    labels: [
                        <?php 
                        $labels = [];
                        $values = [];
                        $colors = [];
                        
                        // Different data preparation based on report type
                        switch($report_type) {
                            case 'sales':
                                while ($row = mysqli_fetch_assoc($report_result)) {
                                    if (strtotime($row['date'])) {
                                        $formatted_date = date('M d', strtotime($row['date']));
                                        $labels[] = "'{$formatted_date}'";
                                        $values[] = $row['total_sales'];
                                    }
                                }
                                break;
                                
                            case 'products':
                                while ($row = mysqli_fetch_assoc($report_result)) {
                                    $labels[] = "'" . addslashes($row['product_name']) . "'";
                                    $values[] = $row['quantity_sold'];
                                    $colors[] = "'rgba(" . rand(0, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 0.8)'";
                                }
                                break;
                                
                            case 'categories':
                                while ($row = mysqli_fetch_assoc($report_result)) {
                                    $labels[] = "'" . addslashes($row['category_name']) . "'";
                                    $values[] = $row['total_revenue'];
                                    $colors[] = "'rgba(" . rand(0, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 0.8)'";
                                }
                                break;
                                
                            case 'vendors':
                                while ($row = mysqli_fetch_assoc($report_result)) {
                                    $labels[] = "'" . addslashes($row['vendor_name']) . "'";
                                    $values[] = $row['total_revenue'];
                                    $colors[] = "'rgba(" . rand(0, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 0.8)'";
                                }
                                break;
                                
                            case 'customers':
                                while ($row = mysqli_fetch_assoc($report_result)) {
                                    $labels[] = "'" . addslashes($row['customer_name']) . "'";
                                    $values[] = $row['total_spent'];
                                    $colors[] = "'rgba(" . rand(0, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 0.8)'";
                                }
                                break;
                        }
                        
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: '<?php echo $y_axis_label; ?>',
                        data: [<?php echo implode(', ', $values); ?>],
                        <?php if ($chart_type === 'pie' || $chart_type === 'bar'): ?>
                        backgroundColor: [<?php echo implode(', ', $colors); ?>],
                        borderColor: [<?php echo implode(', ', $colors); ?>],
                        <?php else: ?>
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        <?php endif; ?>
                        borderWidth: 1
                    }]
                };
                
                // Create chart
                const ctx = document.getElementById('reportChart').getContext('2d');
                const reportChart = new Chart(ctx, {
                    type: '<?php echo $chart_type; ?>',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            <?php if ($chart_type !== 'pie'): ?>
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '<?php echo $y_axis_label; ?>'
                                }
                            }
                            <?php endif; ?>
                        },
                        plugins: {
                            legend: {
                                display: <?php echo $chart_type === 'pie' ? 'true' : 'false'; ?>,
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== undefined) {
                                            <?php if (strpos($y_axis_label, '$') !== false): ?>
                                            label += '$' + context.parsed.y.toFixed(2);
                                            <?php else: ?>
                                            label += context.parsed.y;
                                            <?php endif; ?>
                                        } else if (context.parsed !== undefined) {
                                            <?php if ($chart_type === 'pie'): ?>
                                            label += '$' + context.parsed.toFixed(2);
                                            <?php endif; ?>
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
        
        // Function to export table data to CSV
        function exportReportCSV() {
            const table = document.querySelector('.data-table');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Replace HTML entities and handle commas in the cell content
                    let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s)/gm, ' ');
                    text = text.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const reportType = document.getElementById('type').value;
            const filename = reportType + '_report_' + new Date().toISOString().slice(0,10) + '.csv';
            
            // Create a temporary link to download the CSV
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('target', '_blank');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html> 