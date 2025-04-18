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

// Process task status update
if (isset($_POST['update_task']) && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
    
    // Additional fields for completion
    $completed_at = null;
    if ($new_status === 'completed') {
        $completed_at = date('Y-m-d H:i:s');
    }
    
    // Update task status
    $update_query = "
        UPDATE staff_tasks
        SET status = ?, 
            notes = CONCAT(IFNULL(notes, ''), '\n" . date('Y-m-d H:i:s') . " - Status updated to " . $new_status . ":\n" . $notes . "\n'),
            updated_at = NOW(),
            completed_at = " . ($completed_at ? "'$completed_at'" : "completed_at") . "
        WHERE task_id = ? AND staff_id = ?
    ";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    if ($update_stmt === false) {
        $error_message = "Failed to prepare update statement: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $task_id, $staff_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Task status updated successfully.";
            
            // Log the action
            $log_query = "
                INSERT INTO audit_log (user_id, action, details, timestamp)
                VALUES (?, 'update_task', ?, NOW())
            ";
            $log_details = "Updated task #$task_id status to $new_status";
            $log_stmt = mysqli_prepare($conn, $log_query);
            if ($log_stmt !== false) {
                mysqli_stmt_bind_param($log_stmt, "is", $staff_id, $log_details);
                mysqli_stmt_execute($log_stmt);
            }
        } else {
            $error_message = "Failed to update task status: " . mysqli_error($conn);
        }
    }
}

// Get tasks for this staff member with filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'due_date';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$tasks_query = "
    SELECT t.*, u.name as assigned_by_name 
    FROM staff_tasks t
    JOIN users u ON t.assigned_by = u.user_id
    WHERE t.staff_id = ?
";

// Add filters
if ($status_filter !== 'all') {
    $tasks_query .= " AND t.status = '$status_filter'";
}

if ($priority_filter !== 'all') {
    $tasks_query .= " AND t.priority = '$priority_filter'";
}

if ($search) {
    $tasks_query .= " AND (t.title LIKE '%$search%' OR t.description LIKE '%$search%')";
}

// Add sorting
switch ($sort_by) {
    case 'due_date':
        $tasks_query .= " ORDER BY t.due_date ASC";
        break;
    case 'priority':
        $tasks_query .= " ORDER BY 
            CASE 
                WHEN t.priority = 'high' THEN 1 
                WHEN t.priority = 'medium' THEN 2 
                WHEN t.priority = 'low' THEN 3 
            END ASC";
        break;
    case 'status':
        $tasks_query .= " ORDER BY t.status ASC";
        break;
    case 'created_at':
        $tasks_query .= " ORDER BY t.created_at DESC";
        break;
    default:
        $tasks_query .= " ORDER BY t.due_date ASC";
}

$tasks_stmt = mysqli_prepare($conn, $tasks_query);
if ($tasks_stmt === false) {
    $error_message = "Failed to prepare tasks query: " . mysqli_error($conn);
    $tasks_result = false;
} else {
    mysqli_stmt_bind_param($tasks_stmt, "i", $staff_id);
    mysqli_stmt_execute($tasks_stmt);
    $tasks_result = mysqli_stmt_get_result($tasks_stmt);
}

// Get task statistics
$task_stats_query = "
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) AS overdue_tasks
    FROM staff_tasks
    WHERE staff_id = ?
";
$task_stats_stmt = mysqli_prepare($conn, $task_stats_query);
if ($task_stats_stmt === false) {
    // If prepare fails, create a default stats array
    $task_stats = [
        'total_tasks' => 0,
        'pending_tasks' => 0,
        'in_progress_tasks' => 0,
        'completed_tasks' => 0,
        'overdue_tasks' => 0
    ];
} else {
    mysqli_stmt_bind_param($task_stats_stmt, "i", $staff_id);
    mysqli_stmt_execute($task_stats_stmt);
    $task_stats_result = mysqli_stmt_get_result($task_stats_stmt);
    $task_stats = mysqli_fetch_assoc($task_stats_result);
}

$page_title = "My Tasks";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .filter-label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .filter-select, .filter-search {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-search {
            min-width: 250px;
        }
        
        .filter-button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: auto;
        }
        
        .filter-button:hover {
            background-color: #2980b9;
        }
        
        .tasks-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tasks-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tasks-table th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .tasks-table th a:hover {
            color: #3498db;
        }
        
        .tasks-table th i {
            margin-left: 5px;
            font-size: 12px;
        }
        
        .tasks-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .tasks-table tr:last-child td {
            border-bottom: none;
        }
        
        .tasks-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .priority-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .priority-high {
            background-color: #e74c3c;
        }
        
        .priority-medium {
            background-color: #f39c12;
        }
        
        .priority-low {
            background-color: #27ae60;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .task-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-button {
            background-color: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .action-button:hover {
            color: #3498db;
        }
        
        .view-button:hover {
            color: #3498db;
        }
        
        .update-button:hover {
            color: #27ae60;
        }
        
        .delete-button:hover {
            color: #e74c3c;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .modal-footer {
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .task-description {
            white-space: pre-line;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-state p {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .tasks-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Tasks</h1>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $task_stats['total_tasks']; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $task_stats['pending_tasks']; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $task_stats['in_progress_tasks']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $task_stats['completed_tasks']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $task_stats['overdue_tasks']; ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
        
        <form action="" method="GET" class="filter-form">
            <div class="filter-controls">
                <div class="filter-group">
                    <label class="filter-label" for="status">Status</label>
                    <select class="filter-select" name="status" id="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="priority">Priority</label>
                    <select class="filter-select" name="priority" id="priority">
                        <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="sort">Sort By</label>
                    <select class="filter-select" name="sort" id="sort">
                        <option value="due_date" <?php echo $sort_by === 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                        <option value="priority" <?php echo $sort_by === 'priority' ? 'selected' : ''; ?>>Priority</option>
                        <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="search">Search</label>
                    <input type="text" class="filter-search" name="search" id="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="filter-button">Apply Filters</button>
                </div>
            </div>
        </form>
        
        <div class="tasks-container">
            <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th><a href="?sort=priority&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">Priority <?php echo $sort_by === 'priority' ? '<i class="fas fa-sort-down"></i>' : ''; ?></a></th>
                            <th>Title</th>
                            <th>Description</th>
                            <th><a href="?sort=due_date&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">Due Date <?php echo $sort_by === 'due_date' ? '<i class="fas fa-sort-down"></i>' : ''; ?></a></th>
                            <th><a href="?sort=status&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">Status <?php echo $sort_by === 'status' ? '<i class="fas fa-sort-down"></i>' : ''; ?></a></th>
                            <th>Assigned By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
                            <?php
                            // Determine if task is overdue
                            $is_overdue = ($task['status'] !== 'completed' && strtotime($task['due_date']) < time());
                            $status_class = $is_overdue ? 'status-overdue' : 'status-' . $task['status'];
                            $status_text = $is_overdue ? 'Overdue' : ucfirst($task['status']);
                            
                            // If title doesn't exist, use the first part of description as title
                            $task_title = isset($task['title']) ? $task['title'] : (substr($task['description'], 0, 30) . '...');
                            ?>
                            <tr>
                                <td>
                                    <span class="priority-indicator priority-<?php echo $task['priority']; ?>"></span>
                                    <?php echo ucfirst($task['priority']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($task_title); ?></td>
                                <td>
                                    <?php 
                                    // Truncate description for display
                                    $description = htmlspecialchars($task['description']);
                                    echo (strlen($description) > 50) ? substr($description, 0, 50) . '...' : $description; 
                                    ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($task['due_date'])); ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td><?php echo htmlspecialchars($task['assigned_by_name']); ?></td>
                                <td class="task-actions">
                                    <button type="button" class="action-button view-button" onclick="viewTask(<?php echo $task['task_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="action-button update-button" onclick="updateTask(<?php echo $task['task_id']; ?>, '<?php echo $task['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No tasks found</h3>
                    <p>There are no tasks matching your current filters. Try adjusting your filters or check back later for new assignments.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- View Task Modal -->
    <div id="viewTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Task Details</h2>
                <button type="button" class="modal-close" onclick="closeModal('viewTaskModal')">&times;</button>
            </div>
            <div class="modal-body" id="taskDetails">
                <!-- Task details will be loaded here via AJAX -->
                <div id="taskDetailsContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewTaskModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Update Task Modal -->
    <div id="updateTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Task Status</h2>
                <button type="button" class="modal-close" onclick="closeModal('updateTaskModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="updateTaskForm" method="POST" action="">
                    <input type="hidden" name="task_id" id="update_task_id">
                    <input type="hidden" name="update_task" value="1">
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-control" name="status" id="update_status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" name="notes" id="update_notes" rows="4" placeholder="Add notes about this status update..."></textarea>
                        <small class="form-text">These notes will be added to the task history.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateTaskModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitUpdateForm()">Update Status</button>
            </div>
        </div>
    </div>
    
    <script>
        // View task details
        function viewTask(taskId) {
            // In a real implementation, this would fetch task details via AJAX
            // For now, we'll just show the modal with a placeholder message
            document.getElementById('taskDetailsContent').innerHTML = 
                '<div style="text-align: center; padding: 20px;">' +
                '<p>Loading task details for task #' + taskId + '...</p>' +
                '<p>In a real implementation, this would load the full task details</p>' +
                '</div>';
            
            document.getElementById('viewTaskModal').style.display = 'block';
        }
        
        // Update task status
        function updateTask(taskId, currentStatus) {
            document.getElementById('update_task_id').value = taskId;
            document.getElementById('update_status').value = currentStatus;
            document.getElementById('update_notes').value = '';
            
            document.getElementById('updateTaskModal').style.display = 'block';
        }
        
        // Submit the update form
        function submitUpdateForm() {
            document.getElementById('updateTaskForm').submit();
        }
        
        // Close any modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 