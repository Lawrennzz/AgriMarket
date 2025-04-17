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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    // Get and sanitize input
    $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $position = htmlspecialchars($_POST['position'] ?? '', ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8');
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    } else {
        // Check if email already exists
        $check_query = "SELECT user_id FROM users WHERE email = ? AND deleted_at IS NULL";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $errors[] = "Email already exists";
        }
        
        mysqli_stmt_close($check_stmt);
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (empty($errors)) {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert user record
            $user_query = "INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'staff', ?)";
            $user_stmt = mysqli_prepare($conn, $user_query);
            mysqli_stmt_bind_param($user_stmt, "ssss", $name, $email, $password_hash, $phone);
            
            if (!mysqli_stmt_execute($user_stmt)) {
                throw new Exception("Error adding user: " . mysqli_error($conn));
            }
            
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($user_stmt);
            
            // Insert staff details
            $staff_query = "INSERT INTO staff_details (user_id, position) VALUES (?, ?)";
            $staff_stmt = mysqli_prepare($conn, $staff_query);
            mysqli_stmt_bind_param($staff_stmt, "is", $user_id, $position);
            
            if (!mysqli_stmt_execute($staff_stmt)) {
                throw new Exception("Error adding staff details: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($staff_stmt);
            
            // Log action
            $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = mysqli_prepare($conn, $audit_query);
            $action = "create";
            $table = "users";
            $details = "Added new staff member: " . $name;
            
            mysqli_stmt_bind_param($audit_stmt, "issss", $_SESSION['user_id'], $action, $table, $user_id, $details);
            mysqli_stmt_execute($audit_stmt);
            mysqli_stmt_close($audit_stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Staff member added successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Staff - AgriMarket</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
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
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 12px;
            cursor: pointer;
            color: #777;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Add New Staff Member</h1>
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
        
        <form method="post" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="position">Position</label>
                <select id="position" name="position" class="form-control" required>
                    <option value="">Select Position</option>
                    <option value="customer_support">Customer Support</option>
                    <option value="product_manager">Product Manager</option>
                    <option value="order_processor">Order Processor</option>
                    <option value="content_manager">Content Manager</option>
                    <option value="inventory_manager">Inventory Manager</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
                </div>
                <small>Password must be at least 8 characters long</small>
            </div>
            
            <div class="btn-container">
                <a href="manage_staff.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="submit" name="add_staff" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Staff Member
                </button>
            </div>
        </form>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        function togglePasswordVisibility() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.querySelector(".toggle-password");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html> 