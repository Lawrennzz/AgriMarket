<?php
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
    $subscription_tier = mysqli_real_escape_string($conn, $_POST['subscription_tier']);
    
    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else if (empty($business_name)) {
        $error = "Business name is required";
    } else {
        // Check if email or username already exists
        $check_sql = "SELECT * FROM users WHERE email = ? OR username = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ss", $email, $username);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email or username already exists";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Insert into users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'vendor';
                
                $user_sql = "INSERT INTO users (username, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $user_sql);

                if ($stmt === false) {
                    die('MySQL prepare error: ' . mysqli_error($conn));
                }

                mysqli_stmt_bind_param($stmt, 'sssss', $username, $name, $email, $hashed_password, $role);
                
                if (mysqli_stmt_execute($stmt)) {
                    $user_id = mysqli_insert_id($conn);
                    
                    // Insert into vendors table
                    $vendor_sql = "INSERT INTO vendors (user_id, business_name, subscription_tier) VALUES (?, ?, ?)";
                    $vendor_stmt = mysqli_prepare($conn, $vendor_sql);
                    mysqli_stmt_bind_param($vendor_stmt, "iss", $user_id, $business_name, $subscription_tier);
                    
                    if (mysqli_stmt_execute($vendor_stmt)) {
                        mysqli_commit($conn);
                        $success = "Vendor registration successful! Please login.";
                    } else {
                        throw new Exception("Error inserting vendor details");
                    }
                } else {
                    throw new Exception("Error inserting user details");
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Error occurred during registration: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vendor Register - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-container {
            min-height: calc(100vh - 180px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .register-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
        }

        .form-control {
            padding-left: 2.5rem;
        }

        .register-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--medium-gray);
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .success-message {
            background-color: var(--success-color);
            color: white;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .error-message {
            background-color: var(--danger-color);
            color: white;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1>Vendor Registration</h1>
                <p>Join AgriMarket as a Vendor</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Email address" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-store"></i>
                    <input type="text" name="business_name" class="form-control" placeholder="Business Name" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-layer-group"></i>
                    <select name="subscription_tier" class="form-control" required>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary register-btn">Register as Vendor</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                    | <a href="register.php">Register as Customer</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>