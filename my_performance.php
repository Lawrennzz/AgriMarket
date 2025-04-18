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
$page_title = "My Performance";
$error_message = "";

// Get date range from query parameters
$time_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$current_year = date('Y');
$current_month = date('m');

// Prepare date range based on time period
switch ($time_period) {
    case 'weekly':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        $end_date = date('Y-m-d');
        $date_label = "Past Week";
        break;
    case 'monthly':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $date_label = "Current Month";
        break;
    case 'quarterly':
        $current_quarter = ceil($current_month / 3);
        $start_month = (($current_quarter - 1) * 3) + 1;
        $start_date = "$current_year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
        $end_month = $start_month + 2;
        $end_date = "$current_year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$current_year-$end_month-01"));
        $date_label = "Current Quarter";
        break;
    case 'yearly':
        $start_date = "$current_year-01-01";
        $end_date = "$current_year-12-31";
        $date_label = "Year $current_year";
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $date_label = "Current Month";
}

// Initialize default metrics in case of query failures
$orders_metrics = [
    'total_orders' => 0,
    'processed_by_me' => 0,
    'avg_processing_time' => 0
];

$messages_metrics = [
    'total_messages' => 0,
    'handled_by_me' => 0,
    'avg_response_time' => 0
];

// Get staff performance metrics
// 1. Orders processed
$orders_query = "
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(CASE WHEN o.processed_by = ? THEN 1 ELSE 0 END) as processed_by_me,
        AVG(TIMESTAMPDIFF(HOUR, o.created_at, COALESCE(
            (SELECT MIN(h.changed_at) FROM order_status_history h 
             WHERE h.order_id = o.order_id AND h.status IN ('processing', 'shipped', 'delivered')), 
            o.created_at
        ))) as avg_processing_time
    FROM 
        orders o
    WHERE 
        o.created_at BETWEEN ? AND ?
";

$orders_stmt = mysqli_prepare($conn, $orders_query);
if ($orders_stmt) {
    mysqli_stmt_bind_param($orders_stmt, "iss", $staff_id, $start_date, $end_date);
    mysqli_stmt_execute($orders_stmt);
    $orders_result = mysqli_stmt_get_result($orders_stmt);
    $orders_metrics = mysqli_fetch_assoc($orders_result);
} else {
    $error_message = "Error preparing orders query: " . mysqli_error($conn);
}

// 2. Customer messages handled
$messages_query = "
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN staff_id = ? THEN 1 ELSE 0 END) as handled_by_me,
        AVG(CASE WHEN staff_id = ? THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as avg_response_time
    FROM 
        customer_messages
    WHERE 
        created_at BETWEEN ? AND ?
        AND status != 'pending'
";

$messages_stmt = mysqli_prepare($conn, $messages_query);
if ($messages_stmt) {
    mysqli_stmt_bind_param($messages_stmt, "iiss", $staff_id, $staff_id, $start_date, $end_date);
    mysqli_stmt_execute($messages_stmt);
    $messages_result = mysqli_stmt_get_result($messages_stmt);
    $messages_metrics = mysqli_fetch_assoc($messages_result);
} else {
    if (empty($error_message)) {
        $error_message = "Error preparing messages query: " . mysqli_error($conn);
    }
}

// 3. Audit log activities
$audit_query = "
    SELECT 
        action,
        COUNT(*) as action_count
    FROM 
        audit_logs
    WHERE 
        user_id = ?
        AND created_at BETWEEN ? AND ?
    GROUP BY 
        action
    ORDER BY 
        action_count DESC
";

$audit_stmt = mysqli_prepare($conn, $audit_query);
$has_audit_data = false;
if ($audit_stmt) {
    mysqli_stmt_bind_param($audit_stmt, "iss", $staff_id, $start_date, $end_date);
    mysqli_stmt_execute($audit_stmt);
    $audit_result = mysqli_stmt_get_result($audit_stmt);
    $has_audit_data = true;
} else {
    if (empty($error_message)) {
        $error_message = "Error preparing audit log query: " . mysqli_error($conn);
    }
}

// 4. Daily activity trend
$activity_query = "
    SELECT 
        DATE(created_at) as activity_date,
        COUNT(*) as activity_count
    FROM 
        audit_logs
    WHERE 
        user_id = ?
        AND created_at BETWEEN ? AND ?
    GROUP BY 
        activity_date
    ORDER BY 
        activity_date
";

$activity_stmt = mysqli_prepare($conn, $activity_query);
$activity_dates = [];
$activity_counts = [];

if ($activity_stmt) {
    mysqli_stmt_bind_param($activity_stmt, "iss", $staff_id, $start_date, $end_date);
    mysqli_stmt_execute($activity_stmt);
    $activity_result = mysqli_stmt_get_result($activity_stmt);
    
    while ($row = mysqli_fetch_assoc($activity_result)) {
        $activity_dates[] = date('M d', strtotime($row['activity_date']));
        $activity_counts[] = $row['activity_count'];
    }
} else {
    if (empty($error_message)) {
        $error_message = "Error preparing activity trend query: " . mysqli_error($conn);
    }
}

// Calculate performance score (simplified example)
$performance_score = 0;
$total_metrics = 0;

// Order processing score (0-100)
if (isset($orders_metrics['total_orders']) && $orders_metrics['total_orders'] > 0) {
    $order_score = min(100, ($orders_metrics['processed_by_me'] / max(1, $orders_metrics['total_orders'])) * 100);
    $performance_score += $order_score;
    $total_metrics++;
}

// Message handling score (0-100)
if (isset($messages_metrics['total_messages']) && $messages_metrics['total_messages'] > 0) {
    $message_score = min(100, ($messages_metrics['handled_by_me'] / max(1, $messages_metrics['total_messages'])) * 100);
    $performance_score += $message_score;
    $total_metrics++;
}

// Response time score (0-100, lower is better)
if (!empty($messages_metrics['avg_response_time'])) {
    // Assuming 24 hours is acceptable, less is better
    $response_time_score = min(100, (24 / max(1, $messages_metrics['avg_response_time'])) * 100);
    $performance_score += $response_time_score;
    $total_metrics++;
}

// Activity level score (0-100)
if (count($activity_counts) > 0) {
    $activity_score = min(100, (array_sum($activity_counts) / count($activity_counts)) * 10);
    $performance_score += $activity_score;
    $total_metrics++;
}

// Calculate final performance score
$performance_score = $total_metrics > 0 ? round($performance_score / $total_metrics) : 0;

// Get performance rating
$rating = "Not Rated";
if ($performance_score >= 90) {
    $rating = "Excellent";
    $rating_color = "#28a745";
} elseif ($performance_score >= 75) {
    $rating = "Good";
    $rating_color = "#17a2b8";
} elseif ($performance_score >= 60) {
    $rating = "Satisfactory";
    $rating_color = "#ffc107";
} elseif ($performance_score >= 40) {
    $rating = "Needs Improvement";
    $rating_color = "#fd7e14";
} else {
    $rating = "Unsatisfactory";
    $rating_color = "#dc3545";
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
        
        .period-selector {
            display: flex;
            background-color: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .period-selector a {
            padding: 8px 15px;
            color: #666;
            text-decoration: none;
            border-right: 1px solid #eee;
        }
        
        .period-selector a:last-child {
            border-right: none;
        }
        
        .period-selector a.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .metric-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .metric-header {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .metric-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .metric-icon i {
            color: #4CAF50;
            font-size: 20px;
        }
        
        .metric-title {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 500;
            color: #333;
            margin: 10px 0;
        }
        
        .metric-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .performance-score {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 10px solid #eee;
            margin: 20px auto;
            position: relative;
            background-color: white;
        }
        
        .score-fill {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            clip: rect(0px, 150px, 150px, 75px);
            background-color: transparent;
            transform: rotate(0deg);
        }
        
        .score-value {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .score-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .score-text {
            font-size: 1rem;
        }
        
        .activity-chart {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            height: auto;
            min-height: 350px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1.2rem;
            color: #333;
            margin: 0;
        }
        
        .activity-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .activity-item {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        .activity-name {
            font-weight: 500;
            color: #333;
        }
        
        .activity-count {
            font-weight: 700;
            color: #4CAF50;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 992px) {
            .metrics-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><?php echo $page_title; ?></h1>
            
            <div class="period-selector">
                <a href="?period=weekly" <?php echo $time_period === 'weekly' ? 'class="active"' : ''; ?>>Weekly</a>
                <a href="?period=monthly" <?php echo $time_period === 'monthly' ? 'class="active"' : ''; ?>>Monthly</a>
                <a href="?period=quarterly" <?php echo $time_period === 'quarterly' ? 'class="active"' : ''; ?>>Quarterly</a>
                <a href="?period=yearly" <?php echo $time_period === 'yearly' ? 'class="active"' : ''; ?>>Yearly</a>
            </div>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="performance-score">
            <h2>Overall Performance: <?php echo $date_label; ?></h2>
            
            <div class="score-circle">
                <div class="score-value">
                    <div class="score-number"><?php echo $performance_score; ?></div>
                    <div class="score-text" style="color: <?php echo $rating_color; ?>"><?php echo $rating; ?></div>
                </div>
            </div>
        </div>
        
        <div class="metrics-container">
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3 class="metric-title">Orders Processed</h3>
                </div>
                <div class="metric-value"><?php echo number_format($orders_metrics['processed_by_me']); ?></div>
                <p class="metric-subtitle">
                    Out of <?php echo number_format($orders_metrics['total_orders']); ?> total orders
                    (<?php echo $orders_metrics['total_orders'] > 0 ? round(($orders_metrics['processed_by_me'] / $orders_metrics['total_orders']) * 100) : 0; ?>%)
                </p>
                <p class="metric-subtitle">
                    Avg. processing time: 
                    <?php echo !empty($orders_metrics['avg_processing_time']) ? round($orders_metrics['avg_processing_time'], 1) . ' hours' : 'N/A'; ?>
                </p>
            </div>
            
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="metric-title">Customer Support</h3>
                </div>
                <div class="metric-value"><?php echo number_format($messages_metrics['handled_by_me']); ?></div>
                <p class="metric-subtitle">
                    Messages handled out of <?php echo number_format($messages_metrics['total_messages']); ?> total
                    (<?php echo $messages_metrics['total_messages'] > 0 ? round(($messages_metrics['handled_by_me'] / $messages_metrics['total_messages']) * 100) : 0; ?>%)
                </p>
                <p class="metric-subtitle">
                    Avg. response time: 
                    <?php echo !empty($messages_metrics['avg_response_time']) ? round($messages_metrics['avg_response_time'], 1) . ' hours' : 'N/A'; ?>
                </p>
            </div>
        </div>
        
        <div class="activity-chart">
            <div class="chart-header">
                <h3 class="chart-title">Daily Activity Trend</h3>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
        
        <div class="activity-chart">
            <div class="chart-header">
                <h3 class="chart-title">Activity Breakdown</h3>
            </div>
            
            <div class="activity-breakdown">
                <?php 
                if ($has_audit_data && mysqli_num_rows($audit_result) > 0) {
                    while ($row = mysqli_fetch_assoc($audit_result)) {
                        echo '<div class="activity-item">';
                        echo '<span class="activity-name">' . ucwords(str_replace('_', ' ', $row['action'])) . '</span>';
                        echo '<span class="activity-count">' . $row['action_count'] . '</span>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No activities recorded for this period.</p>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configure the score circle animation
            const performanceScore = <?php echo $performance_score; ?>;
            const scoreCircle = document.querySelector('.score-circle');
            
            // Set the color of the circle based on the score
            const scoreColor = '<?php echo $rating_color; ?>';
            scoreCircle.style.borderColor = scoreColor;
            
            // Daily activity trend chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($activity_dates); ?>,
                    datasets: [{
                        label: 'Activities',
                        data: <?php echo json_encode($activity_counts); ?>,
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
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 