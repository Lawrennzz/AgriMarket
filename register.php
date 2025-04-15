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
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    }
    // Check if passwords match
    else if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    }
    // Check password strength
    else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    }
    else {
        // Check if email already exists
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $user_type);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Registration successful! Please login.";
            } else {
                $error = "Error occurred during registration";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - AgriMarket</title>
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

        .register-header p {
            color: var(--medium-gray);
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

        .user-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-type-option {
            flex: 1;
            text-align: center;
        }

        .user-type-option input[type="radio"] {
            display: none;
        }

        .user-type-option label {
            display: block;
            padding: 1rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .user-type-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background-color: rgba(76, 175, 80, 0.1);
        }

        .user-type-option i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
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
                <h1>Create Account</h1>
                <p>Join AgriMarket today</p>
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

                <div class="user-type-selector">
                    <div class="user-type-option">
                        <input type="radio" id="customer" name="user_type" value="customer" required checked>
                        <label for="customer">
                            <i class="fas fa-user"></i>
                            <div>Customer</div>
                        </label>
                    </div>
                    <div class="user-type-option">
                        <input type="radio" id="vendor" name="user_type" value="vendor" required>
                        <label for="vendor">
                            <i class="fas fa-store"></i>
                            <div>Vendor</div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary register-btn">Create Account</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>