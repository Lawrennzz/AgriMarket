<?php
include 'config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$success_message = '';
$error_message = '';
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

// Check if viewing a specific staff member (admin only)
$staff_id = null;
if ($is_admin && isset($_GET['id'])) {
    $staff_id = (int)$_GET['id'];
    
    // Check if staff exists
    $check_query = "SELECT u.name FROM users u WHERE u.user_id = ? AND u.role = 'staff' AND u.deleted_at IS NULL";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $staff_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) === 0) {
        header("Location: manage_staff.php");
        exit();
    }
    
    $staff_name = mysqli_fetch_assoc($result)['name'];
    mysqli_stmt_close($check_stmt);
} else {
    // Staff viewing their own tasks
    $staff_id = $user_id;
}

// Process task updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_task']) && isset($_POST['task_id']) && isset($_POST['status'])) {
        $task_id = (int)$_POST['task_id'];
        $status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');
        $completion_date = ($status === 'completed') ? date('Y-m-d H:i:s') : NULL;
        
        // Check if task belongs to this staff member or admin can edit any task
        $check_task_query = "SELECT staff_id FROM staff_tasks WHERE task_id = ?";
        $check_task_stmt = mysqli_prepare($conn, $check_task_query);
        mysqli_stmt_bind_param($check_task_stmt, "i", $task_id);
        mysqli_stmt_execute($check_task_stmt);
        $task_result = mysqli_stmt_get_result($check_task_stmt);
        
        if (mysqli_num_rows($task_result) > 0) {
            $task_owner = mysqli_fetch_assoc($task_result)['staff_id'];
            
            if ($is_admin || $task_owner == $user_id) {
                $update_query = "UPDATE staff_tasks SET status = ?, completion_date = ? WHERE task_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "ssi", $status, $completion_date, $task_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_message = "Task status updated successfully.";
                    
                    // Log action
                    $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
                    $audit_stmt = mysqli_prepare($conn, $audit_query);
                    $action = "update";
                    $table = "staff_tasks";
                    $details = "Updated task status to " . $status;
                    
                    mysqli_stmt_bind_param($audit_stmt, "issss", $user_id, $action, $table, $task_id, $details);
                    mysqli_stmt_execute($audit_stmt);
                    mysqli_stmt_close($audit_stmt);
                } else {
                    $error_message = "Error updating task: " . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $error_message = "You do not have permission to update this task.";
            }
        } else {
            $error_message = "Task not found.";
        }
        mysqli_stmt_close($check_task_stmt);
    }
}

// Get staff performance metrics
$performance_query = "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
    FROM staff_tasks
    WHERE staff_id = ?
";
$performance_stmt = mysqli_prepare($conn, $performance_query);
mysqli_stmt_bind_param($performance_stmt, "i", $staff_id);
mysqli_stmt_execute($performance_stmt);
$performance = mysqli_fetch_assoc(mysqli_stmt_get_result($performance_stmt));
mysqli_stmt_close($performance_stmt);

// Calculate completion rate
$completion_rate = ($performance['total_tasks'] > 0) ? 
    ($performance['completed_tasks'] / $performance['total_tasks']) * 100 : 0;

// Get all tasks for the staff member
$tasks_query = "
    SELECT st.*, u.name as assigned_by_name
    FROM staff_tasks st
    JOIN users u ON st.assigned_by = u.user_id
    WHERE st.staff_id = ?
    ORDER BY 
        CASE 
            WHEN st.status = 'pending' AND st.due_date < CURDATE() THEN 0
            WHEN st.status = 'pending' THEN 1
            WHEN st.status = 'in_progress' THEN 2
            WHEN st.status = 'completed' THEN 3
            ELSE 4
        END,
        st.due_date ASC,
        st.created_at DESC
";
$tasks_stmt = mysqli_prepare($conn, $tasks_query);
mysqli_stmt_bind_param($tasks_stmt, "i", $staff_id);
mysqli_stmt_execute($tasks_stmt);
$tasks_result = mysqli_stmt_get_result($tasks_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Tasks - AgriMarket</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .performance-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            text-align: center;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tasks-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .tasks-title {
            font-size: 1.25rem;
            font-weight: 500;
        }
        
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tasks-table th, .tasks-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .tasks-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .task-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            text-transform: capitalize;
        }
        
        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status-in_progress {
            background-color: #CCE5FF;
            color: #004085;
        }
        
        .status-completed {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .task-priority {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .priority-high {
            background-color: #dc3545;
        }
        
        .priority-medium {
            background-color: #ffc107;
        }
        
        .priority-low {
            background-color: #28a745;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .task-actions form {
            margin: 0;
        }
        
        .btn-action {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .btn-action:hover {
            background-color: #f0f0f0;
        }
        
        .progress-container {
            margin-bottom: 2rem;
        }
        
        .progress-bar-container {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 10px;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #666;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: 500;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #fff;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1;
            border-radius: 4px;
        }
        
        .dropdown-content button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 15px;
            border: none;
            background: none;
            cursor: pointer;
        }
        
        .dropdown-content button:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <?php if ($is_admin && isset($staff_name)): ?>
                <h1><i class="fas fa-tasks"></i> Tasks for <?php echo htmlspecialchars($staff_name); ?></h1>
                <a href="manage_staff.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Staff List
                </a>
            <?php else: ?>
                <h1><i class="fas fa-tasks"></i> My Tasks</h1>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="progress-container">
            <h2>Task Completion Progress</h2>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo number_format($completion_rate, 0); ?>%"></div>
            </div>
            <div class="progress-text">
                <span><?php echo $performance['completed_tasks']; ?> of <?php echo $performance['total_tasks']; ?> tasks completed</span>
                <span><?php echo number_format($completion_rate, 0); ?>% complete</span>
            </div>
        </div>
        
        <div class="performance-metrics">
            <div class="metric-card">
                <div class="metric-value"><?php echo $performance['total_tasks']; ?></div>
                <div class="metric-label">Total Tasks</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $performance['pending_tasks']; ?></div>
                <div class="metric-label">Pending Tasks</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $performance['in_progress_tasks']; ?></div>
                <div class="metric-label">In Progress</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $performance['completed_tasks']; ?></div>
                <div class="metric-label">Completed</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $performance['overdue_tasks'] > 0 ? 'overdue' : ''; ?>">
                    <?php echo $performance['overdue_tasks']; ?>
                </div>
                <div class="metric-label">Overdue Tasks</div>
            </div>
        </div>
        
        <div class="tasks-container">
            <div class="tasks-header">
                <h2 class="tasks-title">Task List</h2>
            </div>
            
            <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Assigned By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
                            <?php 
                                $is_overdue = ($task['status'] != 'completed' && $task['due_date'] < date('Y-m-d'));
                                $status_class = 'status-' . $task['status'];
                                $priority_class = 'priority-' . $task['priority'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                <td>
                                    <span class="task-priority <?php echo $priority_class; ?>"></span>
                                    <?php echo ucfirst($task['priority']); ?>
                                </td>
                                <td <?php echo $is_overdue ? 'class="overdue"' : ''; ?>>
                                    <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                    <?php if ($is_overdue): ?>
                                        <span>(Overdue)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="task-status <?php echo $status_class; ?>">
                                        <?php echo str_replace('_', ' ', $task['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($task['assigned_by_name']); ?></td>
                                <td class="task-actions">
                                    <div class="dropdown">
                                        <button class="btn-action">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-content">
                                            <?php if ($task['status'] == 'pending'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" name="update_task">
                                                        <i class="fas fa-play"></i> Start Task
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['status'] == 'in_progress'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" name="update_task">
                                                        <i class="fas fa-check"></i> Mark Completed
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['status'] != 'completed' && $task['status'] != 'cancelled'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" name="update_task">
                                                        <i class="fas fa-times"></i> Cancel Task
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['status'] == 'completed' || $task['status'] == 'cancelled'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" name="update_task">
                                                        <i class="fas fa-redo"></i> Reopen Task
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No tasks found</h3>
                    <p>There are currently no tasks assigned to this staff member.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 