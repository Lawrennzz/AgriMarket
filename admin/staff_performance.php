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

// Process filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get all staff members with performance metrics
$staff_query = "
    SELECT 
        u.user_id,
        u.name,
        u.email,
        COUNT(st.task_id) as total_tasks,
        SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN st.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN st.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN st.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        SUM(CASE WHEN st.due_date < CURDATE() AND st.status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks,
        AVG(CASE 
            WHEN st.status = 'completed' AND st.completion_date IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, st.created_at, st.completion_date) 
            ELSE NULL 
        END) as avg_completion_time,
        MAX(st.created_at) as last_task_date
    FROM 
        users u
    LEFT JOIN 
        staff_tasks st ON u.user_id = st.staff_id AND st.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    WHERE 
        u.role = 'staff' AND u.deleted_at IS NULL
    GROUP BY 
        u.user_id
    ORDER BY 
        u.name
";

$staff_stmt = mysqli_prepare($conn, $staff_query);
mysqli_stmt_bind_param($staff_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($staff_stmt);
$staff_result = mysqli_stmt_get_result($staff_stmt);

// Get overall performance summary
$summary_query = "
    SELECT 
        COUNT(DISTINCT st.staff_id) as active_staff,
        COUNT(st.task_id) as total_tasks,
        SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN st.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN st.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN st.due_date < CURDATE() AND st.status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks,
        AVG(CASE 
            WHEN st.status = 'completed' AND st.completion_date IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, st.created_at, st.completion_date) 
            ELSE NULL 
        END) as avg_completion_time
    FROM 
        staff_tasks st
    JOIN
        users u ON st.staff_id = u.user_id
    WHERE 
        u.deleted_at IS NULL AND
        st.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";

$summary_stmt = mysqli_prepare($conn, $summary_query);
mysqli_stmt_bind_param($summary_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($summary_stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summary_stmt));

// Get task distribution by priority
$priority_query = "
    SELECT 
        st.priority,
        COUNT(st.task_id) as task_count
    FROM 
        staff_tasks st
    JOIN
        users u ON st.staff_id = u.user_id
    WHERE 
        u.deleted_at IS NULL AND
        st.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY 
        st.priority
";

$priority_stmt = mysqli_prepare($conn, $priority_query);
mysqli_stmt_bind_param($priority_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($priority_stmt);
$priority_result = mysqli_stmt_get_result($priority_stmt);

$priority_data = [
    'high' => 0,
    'medium' => 0,
    'low' => 0
];

while ($priority = mysqli_fetch_assoc($priority_result)) {
    $priority_data[$priority['priority']] = $priority['task_count'];
}

// Get task completion history for chart
$completion_query = "
    SELECT 
        DATE(completion_date) as date,
        COUNT(task_id) as completed
    FROM 
        staff_tasks
    WHERE 
        status = 'completed' AND
        completion_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY 
        DATE(completion_date)
    ORDER BY 
        date
";

$completion_stmt = mysqli_prepare($conn, $completion_query);
mysqli_stmt_bind_param($completion_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($completion_stmt);
$completion_result = mysqli_stmt_get_result($completion_stmt);

$completion_data = [];
$completion_labels = [];

while ($day = mysqli_fetch_assoc($completion_result)) {
    $completion_labels[] = date('M d', strtotime($day['date']));
    $completion_data[] = $day['completed'];
}

// Calculate completion rate
$overall_completion_rate = ($summary['total_tasks'] > 0) ? 
    ($summary['completed_tasks'] / $summary['total_tasks']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance - AgriMarket</title>
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
            display: flex;
            align-items: flex-end;
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
        
        .filter-group input, .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .chart-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .staff-table th, .staff-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .staff-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .staff-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 4px;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #666;
        }
        
        .action-links a {
            margin-right: 10px;
            color: #4CAF50;
            text-decoration: none;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1>Staff Performance Dashboard</h1>
            <span><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></span>
        </div>
        
        <div class="filters-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="reset" class="btn btn-secondary" onclick="window.location.href='staff_performance.php'">Reset</button>
            </form>
        </div>
        
        <!-- Performance Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Active Staff</div>
                <div class="stat-value"><?php echo $summary['active_staff']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Total Tasks</div>
                <div class="stat-value"><?php echo $summary['total_tasks'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Completion Rate</div>
                <div class="stat-value"><?php echo number_format($overall_completion_rate, 1); ?>%</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Avg. Completion Time</div>
                <div class="stat-value">
                    <?php 
                    if ($summary['avg_completion_time']) {
                        $hours = floor($summary['avg_completion_time']);
                        echo $hours . 'h';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Pending Tasks</div>
                <div class="stat-value"><?php echo $summary['pending_tasks'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">In Progress</div>
                <div class="stat-value"><?php echo $summary['in_progress_tasks'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Completed Tasks</div>
                <div class="stat-value"><?php echo $summary['completed_tasks'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Overdue Tasks</div>
                <div class="stat-value" style="color: <?php echo ($summary['overdue_tasks'] > 0) ? '#f44336' : 'inherit'; ?>">
                    <?php echo $summary['overdue_tasks'] ?? 0; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-section">
                <h2>Task Completion History</h2>
                <div class="chart-container">
                    <canvas id="completionChart"></canvas>
                </div>
            </div>
            
            <div class="chart-section">
                <h2>Task Distribution by Priority</h2>
                <div class="chart-container">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Staff Performance Table -->
        <div class="chart-section">
            <h2>Staff Performance Overview</h2>
            
            <?php if (mysqli_num_rows($staff_result) > 0): ?>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Staff Name</th>
                            <th>Total Tasks</th>
                            <th>Completion Rate</th>
                            <th>Avg Time</th>
                            <th>Overdue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($staff = mysqli_fetch_assoc($staff_result)): ?>
                            <?php 
                                $completion_rate = ($staff['total_tasks'] > 0) ? 
                                    ($staff['completed_tasks'] / $staff['total_tasks']) * 100 : 0;
                                    
                                $avg_time = $staff['avg_completion_time'];
                                $avg_time_display = $avg_time ? floor($avg_time) . 'h' : 'N/A';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                <td><?php echo $staff['total_tasks']; ?></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        <span><?php echo number_format($completion_rate, 1); ?>%</span>
                                        <span><?php echo $staff['completed_tasks']; ?>/<?php echo $staff['total_tasks']; ?></span>
                                    </div>
                                </td>
                                <td><?php echo $avg_time_display; ?></td>
                                <td style="color: <?php echo ($staff['overdue_tasks'] > 0) ? '#f44336' : 'inherit'; ?>">
                                    <?php echo $staff['overdue_tasks']; ?>
                                </td>
                                <td class="action-links">
                                    <a href="../view_staff_tasks.php?id=<?php echo $staff['user_id']; ?>">
                                        <i class="fas fa-tasks"></i> View Tasks
                                    </a>
                                    <a href="../manage_staff.php">
                                        <i class="fas fa-user-cog"></i> Manage
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No staff performance data available for the selected period.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Task Completion History Chart
        const completionCtx = document.getElementById('completionChart').getContext('2d');
        const completionChart = new Chart(completionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($completion_labels); ?>,
                datasets: [{
                    label: 'Completed Tasks',
                    data: <?php echo json_encode($completion_data); ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    borderColor: '#4CAF50',
                    borderWidth: 2,
                    tension: 0.1,
                    pointRadius: 3,
                    pointBackgroundColor: '#4CAF50'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                        position: 'top',
                    }
                }
            }
        });
        
        // Task Priority Distribution Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        const priorityChart = new Chart(priorityCtx, {
            type: 'doughnut',
            data: {
                labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                datasets: [{
                    data: [
                        <?php echo $priority_data['high']; ?>,
                        <?php echo $priority_data['medium']; ?>,
                        <?php echo $priority_data['low']; ?>
                    ],
                    backgroundColor: [
                        '#f44336',
                        '#ffc107',
                        '#4CAF50'
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
                    }
                }
            }
        });
    </script>
</body>
</html> 