<?php
include 'config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Process staff actions
$success_message = '';
$error_message = '';

// Handle staff deletion
if (isset($_POST['delete_staff']) && isset($_POST['staff_id'])) {
    $staff_id = (int)$_POST['staff_id'];
    
    // Update user record (soft delete)
    $delete_query = "UPDATE users SET deleted_at = NOW() WHERE user_id = ? AND role = 'staff'";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "i", $staff_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Staff member deleted successfully.";
            
            // Log action
            $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = mysqli_prepare($conn, $audit_query);
            
            if ($audit_stmt) {
                $action = "delete";
                $table = "users";
                $details = "Deleted staff member";
                mysqli_stmt_bind_param($audit_stmt, "issss", $_SESSION['user_id'], $action, $table, $staff_id, $details);
                mysqli_stmt_execute($audit_stmt);
                mysqli_stmt_close($audit_stmt);
            } else {
                $error_message = "Warning: Could not log deletion action: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Error deleting staff member: " . mysqli_error($conn);
        }
        mysqli_stmt_close($delete_stmt);
    } else {
        $error_message = "Error preparing delete statement: " . mysqli_error($conn);
    }
}

// Handle task assignment
if (isset($_POST['assign_task']) && isset($_POST['staff_id']) && isset($_POST['task_description'])) {
    $staff_id = (int)$_POST['staff_id'];
    $task_description = htmlspecialchars($_POST['task_description'] ?? '', ENT_QUOTES, 'UTF-8');
    $due_date = htmlspecialchars($_POST['due_date'] ?? NULL, ENT_QUOTES, 'UTF-8');
    
    if (!empty($task_description)) {
        $task_query = "INSERT INTO staff_tasks (staff_id, description, status, due_date, assigned_by, priority) VALUES (?, ?, 'pending', ?, ?, 'medium')";
        $task_stmt = mysqli_prepare($conn, $task_query);
        
        if ($task_stmt) {
            mysqli_stmt_bind_param($task_stmt, "issi", $staff_id, $task_description, $due_date, $_SESSION['user_id']);
            
            if (mysqli_stmt_execute($task_stmt)) {
                $success_message = "Task assigned successfully.";
                
                // Log action
                $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, details) VALUES (?, ?, ?, ?)";
                $audit_stmt = mysqli_prepare($conn, $audit_query);
                
                if ($audit_stmt) {
                    $action = "insert";
                    $table = "staff_tasks";
                    $details = "Assigned task to staff member ID: " . $staff_id;
                    mysqli_stmt_bind_param($audit_stmt, "isss", $_SESSION['user_id'], $action, $table, $details);
                    mysqli_stmt_execute($audit_stmt);
                    mysqli_stmt_close($audit_stmt);
                } else {
                    // Error preparing audit statement
                    $error_message = "Warning: Could not log action: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Error assigning task: " . mysqli_error($conn);
            }
            mysqli_stmt_close($task_stmt);
        } else {
            $error_message = "Error preparing task statement: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Task description cannot be empty.";
    }
}

// Fetch staff members
$staff_query = "SELECT u.user_id, u.name, u.email, u.created_at, 
                COUNT(st.task_id) as total_tasks,
                SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
               FROM users u
               LEFT JOIN staff_tasks st ON u.user_id = st.staff_id
               WHERE u.role = 'staff' AND u.deleted_at IS NULL
               GROUP BY u.user_id
               ORDER BY u.created_at DESC";

// First check if we have any staff members at all
$check_query = "SELECT COUNT(*) as staff_count FROM users WHERE role = 'staff'";
$check_result = mysqli_query($conn, $check_query);
$staff_count = 0;
if ($check_result) {
    $row = mysqli_fetch_assoc($check_result);
    $staff_count = $row['staff_count'];
}

$staff_stmt = mysqli_prepare($conn, $staff_query);
if ($staff_stmt) {
    mysqli_stmt_execute($staff_stmt);
    $staff_result = mysqli_stmt_get_result($staff_stmt);
    
    // Debug information
    $debug_info = [
        'query' => $staff_query,
        'num_rows' => $staff_result ? mysqli_num_rows($staff_result) : 0,
        'error' => mysqli_error($conn),
        'total_staff_in_db' => $staff_count
    ];
    
    // Comment out or remove the line that adds debug info to error_message
    // $error_message .= "<pre>" . print_r($debug_info, true) . "</pre>";
} else {
    $error_message = "Error preparing staff query: " . mysqli_error($conn);
    $staff_result = false;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Staff - AgriMarket</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .staff-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .staff-table th, .staff-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .staff-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .staff-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .btn-task {
            background-color: #2196F3;
            color: white;
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
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .modal-form input, .modal-form textarea, .modal-form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .modal-form textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .modal-form button {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .performance-metrics {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .task-progress {
            width: 100px;
            height: 10px;
            background-color: #e0e0e0;
            border-radius: 5px;
            position: relative;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 5px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            border-left: 5px solid #4CAF50;
            color: #3c763d;
        }
        
        .alert-danger {
            background-color: #f2dede;
            border-left: 5px solid #f44336;
            color: #a94442;
        }
        
        .add-staff-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .staff-table {
                display: block;
                overflow-x: auto;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .modal-content {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Manage Staff</h1>
            <button class="add-staff-btn" onclick="window.location.href='add_staff.php'">
                <i class="fas fa-user-plus"></i> Add New Staff
            </button>
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
        
        <table class="staff-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($staff_result) > 0): ?>
                    <?php while ($staff = mysqli_fetch_assoc($staff_result)): ?>
                        <?php 
                            $completion_rate = $staff['total_tasks'] > 0 ? 
                                ($staff['completed_tasks'] / $staff['total_tasks']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo $staff['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($staff['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                            <td>
                                <div class="performance-metrics">
                                    <div class="task-progress">
                                        <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                    <span><?php echo number_format($completion_rate, 0); ?>%</span>
                                    <span>(<?php echo $staff['completed_tasks']; ?>/<?php echo $staff['total_tasks']; ?>)</span>
                                </div>
                            </td>
                            <td class="action-buttons">
                                <button class="btn-action btn-task" onclick="openTaskModal(<?php echo $staff['user_id']; ?>, '<?php echo htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8'); ?>')">
                                    <i class="fas fa-tasks"></i> Assign Task
                                </button>
                                <button class="btn-action btn-edit" onclick="window.location.href='edit_staff.php?id=<?php echo $staff['user_id']; ?>'">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['user_id']; ?>">
                                    <button type="submit" name="delete_staff" class="btn-action btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px;">No staff members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Task Assignment Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Task to <span id="staffName"></span></h2>
                <span class="close">&times;</span>
            </div>
            <form method="post" class="modal-form">
                <input type="hidden" id="modalStaffId" name="staff_id">
                
                <label for="task_description">Task Description:</label>
                <textarea id="task_description" name="task_description" required></textarea>
                
                <label for="due_date">Due Date:</label>
                <input type="date" id="due_date" name="due_date" required>
                
                <button type="submit" name="assign_task">Assign Task</button>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Task Modal Functionality
        var modal = document.getElementById("taskModal");
        var span = document.getElementsByClassName("close")[0];
        
        function openTaskModal(staffId, staffName) {
            document.getElementById("modalStaffId").value = staffId;
            document.getElementById("staffName").textContent = staffName;
            modal.style.display = "block";
            
            // Set minimum date to today
            var today = new Date().toISOString().split('T')[0];
            document.getElementById("due_date").min = today;
        }
        
        span.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html> 