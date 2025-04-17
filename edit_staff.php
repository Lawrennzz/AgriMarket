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

$success_message = '';
$error_message = '';

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_staff.php");
    exit();
}

$staff_id = (int)$_GET['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff'])) {
    // Get and sanitize input
    $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $position = htmlspecialchars($_POST['position'] ?? '', ENT_QUOTES, 'UTF-8');
    $phone_number = htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    } else {
        // Check if email already exists (except for this user)
        $check_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ? AND deleted_at IS NULL";
        $check_stmt = mysqli_prepare($conn, $check_query);
        
        if ($check_stmt === false) {
            $errors[] = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($check_stmt, "si", $email, $staff_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $errors[] = "Email already exists";
            }
            
            mysqli_stmt_close($check_stmt);
        }
    }
    
    if (!empty($password) && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (empty($errors)) {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update user record
            if (!empty($password)) {
                // Hash password if provided
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user_query = "UPDATE users SET name = ?, email = ?, password = ?, phone_number = ? WHERE user_id = ? AND role = 'staff'";
                $user_stmt = mysqli_prepare($conn, $user_query);
                
                if ($user_stmt === false) {
                    throw new Exception("Error preparing statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($user_stmt, "ssssi", $name, $email, $password_hash, $phone_number, $staff_id);
            } else {
                // Don't update password if not provided
                $user_query = "UPDATE users SET name = ?, email = ?, phone_number = ? WHERE user_id = ? AND role = 'staff'";
                $user_stmt = mysqli_prepare($conn, $user_query);
                
                if ($user_stmt === false) {
                    throw new Exception("Error preparing statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($user_stmt, "sssi", $name, $email, $phone_number, $staff_id);
            }
            
            if (!mysqli_stmt_execute($user_stmt)) {
                throw new Exception("Error updating user: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($user_stmt);
            
            // Update staff details
            $check_details = "SELECT staff_detail_id FROM staff_details WHERE user_id = ?";
            $check_details_stmt = mysqli_prepare($conn, $check_details);
            
            if ($check_details_stmt === false) {
                throw new Exception("Error preparing statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_details_stmt, "i", $staff_id);
            mysqli_stmt_execute($check_details_stmt);
            mysqli_stmt_store_result($check_details_stmt);
            
            if (mysqli_stmt_num_rows($check_details_stmt) > 0) {
                // Update existing record
                $staff_query = "UPDATE staff_details SET position = ? WHERE user_id = ?";
                $staff_stmt = mysqli_prepare($conn, $staff_query);
                
                if ($staff_stmt === false) {
                    mysqli_stmt_close($check_details_stmt);
                    throw new Exception("Error preparing statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($staff_stmt, "si", $position, $staff_id);
            } else {
                // Insert new record
                $staff_query = "INSERT INTO staff_details (user_id, position) VALUES (?, ?)";
                $staff_stmt = mysqli_prepare($conn, $staff_query);
                
                if ($staff_stmt === false) {
                    mysqli_stmt_close($check_details_stmt);
                    throw new Exception("Error preparing statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($staff_stmt, "is", $staff_id, $position);
            }
            
            mysqli_stmt_close($check_details_stmt);
            
            if (!mysqli_stmt_execute($staff_stmt)) {
                throw new Exception("Error updating staff details: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($staff_stmt);
            
            // Log action
            $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = mysqli_prepare($conn, $audit_query);
            
            if ($audit_stmt === false) {
                throw new Exception("Error preparing audit log statement: " . mysqli_error($conn));
            }
            
            $action = "update";
            $table = "users";
            $details = "Updated staff member: " . $name;
            
            mysqli_stmt_bind_param($audit_stmt, "issss", $_SESSION['user_id'], $action, $table, $staff_id, $details);
            mysqli_stmt_execute($audit_stmt);
            mysqli_stmt_close($audit_stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Staff member updated successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch staff data
$staff_query = "
    SELECT u.*, sd.position
    FROM users u
    LEFT JOIN staff_details sd ON u.user_id = sd.user_id
    WHERE u.user_id = ? AND u.role = 'staff' AND u.deleted_at IS NULL
";
$staff_stmt = mysqli_prepare($conn, $staff_query);

if ($staff_stmt === false) {
    $error_message = "Error preparing staff query: " . mysqli_error($conn);
} else {
    mysqli_stmt_bind_param($staff_stmt, "i", $staff_id);
    mysqli_stmt_execute($staff_stmt);
    $staff_result = mysqli_stmt_get_result($staff_stmt);

    if (mysqli_num_rows($staff_result) == 0) {
        header("Location: manage_staff.php");
        exit();
    }

    $staff = mysqli_fetch_assoc($staff_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff - AgriMarket Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            color: #555;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <h1>Edit Staff Member</h1>
        
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
        
        <div class="form-container">
            <?php if (isset($staff_stmt) && $staff_stmt !== false && isset($staff)): ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone_number">Phone</label>
                    <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($staff['phone_number'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" class="form-control" value="<?php echo htmlspecialchars($staff['position'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password (leave blank to keep current)</label>
                    <div class="password-toggle">
                        <input type="password" id="password" name="password" class="form-control">
                        <button type="button" class="toggle-btn" onclick="togglePassword()">Show</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="update_staff" class="btn btn-primary">Update Staff</button>
                    <a href="manage_staff.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-danger">
                Could not load staff information. Please try again or contact system administrator.
            </div>
            <div class="form-group">
                <a href="manage_staff.php" class="btn btn-primary">Return to Staff Management</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = 'Hide';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = 'Show';
            }
        }
    </script>
</body>
</html> 