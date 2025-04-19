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
$success_message = '';
$error_message = '';

// Get all active staff members
$staff_query = "SELECT user_id, name, email FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name";
$staff_result = mysqli_query($conn, $staff_query);
$staff_members = [];

while ($staff = mysqli_fetch_assoc($staff_result)) {
    $staff_members[] = $staff;
}

// Process task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tasks'])) {
    $staff_ids = isset($_POST['staff_ids']) ? $_POST['staff_ids'] : [];
    $description = htmlspecialchars($_POST['task_description'] ?? '', ENT_QUOTES, 'UTF-8');
    $due_date = htmlspecialchars($_POST['due_date'] ?? '', ENT_QUOTES, 'UTF-8');
    $priority = htmlspecialchars($_POST['priority'] ?? 'medium', ENT_QUOTES, 'UTF-8');
    
    if (empty($staff_ids)) {
        $error_message = "Please select at least one staff member.";
    } elseif (empty($description)) {
        $error_message = "Task description cannot be empty.";
    } elseif (empty($due_date)) {
        $error_message = "Due date is required.";
    } else {
        $success_count = 0;
        $error_count = 0;
        
        // Insert task for each selected staff member
        foreach ($staff_ids as $staff_id) {
            $task_query = "INSERT INTO staff_tasks (staff_id, description, status, due_date, assigned_by, priority) 
                          VALUES (?, ?, 'pending', ?, ?, ?)";
            $task_stmt = mysqli_prepare($conn, $task_query);
            
            if ($task_stmt) {
                mysqli_stmt_bind_param($task_stmt, "issss", $staff_id, $description, $due_date, $_SESSION['user_id'], $priority);
                
                if (mysqli_stmt_execute($task_stmt)) {
                    $success_count++;
                    
                    // Log action
                    $task_id = mysqli_insert_id($conn);
                    $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
                    $audit_stmt = mysqli_prepare($conn, $audit_query);
                    
                    if ($audit_stmt) {
                        $action = "insert";
                        $table = "staff_tasks";
                        $details = "Assigned task to staff member ID: " . $staff_id;
                        
                        mysqli_stmt_bind_param($audit_stmt, "issss", $_SESSION['user_id'], $action, $table, $task_id, $details);
                        mysqli_stmt_execute($audit_stmt);
                        mysqli_stmt_close($audit_stmt);
                    }
                } else {
                    $error_count++;
                }
                
                mysqli_stmt_close($task_stmt);
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $success_message = "Successfully assigned task to $success_count staff member(s).";
            
            if ($error_count > 0) {
                $error_message = "Failed to assign task to $error_count staff member(s).";
            }
        } else {
            $error_message = "Failed to assign any tasks. Please try again.";
        }
    }
}

// Get recent tasks
$recent_tasks_query = "
    SELECT st.task_id, st.description, st.due_date, st.priority, st.status, 
           u.name as staff_name, a.name as assigned_by_name, 
           COUNT(*) OVER() as total_count
    FROM staff_tasks st
    JOIN users u ON st.staff_id = u.user_id
    JOIN users a ON st.assigned_by = a.user_id
    ORDER BY st.created_at DESC
    LIMIT 10";
$recent_tasks_result = mysqli_query($conn, $recent_tasks_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Tasks - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .task-form-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .btn {
            padding: 10px 15px;
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
            margin-right: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f44336;
        }
        
        .staff-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        
        .staff-selection-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .staff-selection-item:last-child {
            border-bottom: none;
        }
        
        .staff-selection-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .staff-email {
            color: #666;
            font-size: 0.85em;
            margin-left: 10px;
        }
        
        .selection-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .task-list {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .task-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .task-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .task-table th,
        .task-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .task-table th {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            text-transform: capitalize;
        }
        
        .priority-high {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .priority-medium {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .priority-low {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            text-transform: capitalize;
        }
        
        .status-pending {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .status-in_progress {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-completed {
            background-color: #f5f5f5;
            color: #616161;
        }
        
        .status-cancelled {
            background-color: #fafafa;
            color: #9e9e9e;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1><i class="fas fa-tasks"></i> Assign Tasks to Staff</h1>
            <a href="staff_performance.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> View Performance
            </a>
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
        
        <div class="task-form-container">
            <h2>Assign New Task</h2>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label for="task_description">Task Description:</label>
                            <textarea id="task_description" name="task_description" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date:</label>
                            <input type="date" id="due_date" name="due_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority:</label>
                            <select id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label>Select Staff Members:</label>
                            
                            <div class="selection-actions">
                                <button type="button" id="selectAll" class="btn btn-secondary">Select All</button>
                                <button type="button" id="deselectAll" class="btn btn-secondary">Deselect All</button>
                            </div>
                            
                            <div class="staff-selection">
                                <?php if (count($staff_members) > 0): ?>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <div class="staff-selection-item">
                                            <input type="checkbox" id="staff_<?php echo $staff['user_id']; ?>" name="staff_ids[]" value="<?php echo $staff['user_id']; ?>">
                                            <label for="staff_<?php echo $staff['user_id']; ?>"><?php echo htmlspecialchars($staff['name']); ?></label>
                                            <span class="staff-email">(<?php echo htmlspecialchars($staff['email']); ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No staff members available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" name="assign_tasks" class="btn btn-primary">Assign Tasks</button>
                </div>
            </form>
        </div>
        
        <div class="task-list">
            <div class="task-list-header">
                <h2>Recently Assigned Tasks</h2>
                <a href="../view_staff_tasks.php" class="btn btn-secondary">View All Tasks</a>
            </div>
            
            <?php if (mysqli_num_rows($recent_tasks_result) > 0): ?>
                <table class="task-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($task = mysqli_fetch_assoc($recent_tasks_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($task['description'], 0, 50) . (strlen($task['description']) > 50 ? '...' : '')); ?></td>
                                <td><?php echo htmlspecialchars($task['staff_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $task['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($task['assigned_by_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No tasks have been assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('due_date').min = today;
            
            // Select/Deselect All functionality
            const selectAllBtn = document.getElementById('selectAll');
            const deselectAllBtn = document.getElementById('deselectAll');
            const checkboxes = document.querySelectorAll('input[name="staff_ids[]"]');
            
            selectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
            
            deselectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            });
        });
    </script>
</body>
</html> 