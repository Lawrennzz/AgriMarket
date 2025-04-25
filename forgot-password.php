<?php
session_start(); // Start the session
include 'config.php'; // Include your database connection
require_once 'includes/Mailer.php';

$error = '';
$success = '';
$step = isset($_SESSION['step']) ? $_SESSION['step'] : 1; // Default to step 1 if not set

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step === 1) {
        // Check if the email key exists in the POST request
        if (isset($_POST['email'])) {
            $email = mysqli_real_escape_string($conn, $_POST['email']);

            // Validate email
            if (empty($email)) {
                $error = "Email is required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                // Check if the email exists in the database
                $sql = "SELECT * FROM users WHERE email = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) > 0) {
                    // Fetch user data
                    $user = mysqli_fetch_assoc($result);
                    $_SESSION['email'] = $email; // Store email in session
                    $_SESSION['user'] = $user; // Store user data in session
                    $_SESSION['step'] = 2; // Move to security question verification
                    $step = 2; // Update local variable for immediate use
                } else {
                    $error = "Email not found.";
                }
            }
        }
    } elseif ($step === 2) {
        $security_answer = mysqli_real_escape_string($conn, $_POST['security_answer']);
        $user = $_SESSION['user']; // Retrieve user data from session

        // Validate security answer
        if (empty($security_answer)) {
            $error = "Security answer is required.";
        } elseif (strcasecmp($security_answer, $user['security_answer']) === 0) {
            // Generate verification code
            $verification_code = rand(100000, 999999);
            $_SESSION['verification_code'] = $verification_code;

            // Send verification code via email
            $mailer = new Mailer();
            $email_subject = "Password Reset Verification Code - AgriMarket";
            $email_message = "Hello " . htmlspecialchars($user['name']) . ",<br><br>";
            $email_message .= "You have requested to reset your password. Here is your verification code:<br><br>";
            $email_message .= "<h2 style='text-align: center; color: #4CAF50;'>" . $verification_code . "</h2><br>";
            $email_message .= "If you did not request this password reset, please ignore this email.<br><br>";
            $email_message .= "Best regards,<br>AgriMarket Team";

            if ($mailer->sendNotification($user['email'], $email_subject, $email_message, $user['name'])) {
                $_SESSION['step'] = 3;
                $step = 3;
                $success = "A verification code has been sent to your email.";
            } else {
                $error = "Failed to send verification code. Please try again.";
            }
        } else {
            $error = "Incorrect security answer.";
        }
    } elseif ($step === 3) {
        $input_code = mysqli_real_escape_string($conn, $_POST['verification_code']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_SESSION['email']; // Retrieve email from session

        // Validate verification code
        if ($input_code != $_SESSION['verification_code']) {
            $error = "Invalid verification code.";
        } elseif (empty($new_password)) {
            $error = "New password is required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Allow user to set a new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password in the database
            $update_sql = "UPDATE users SET password = ? WHERE email = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Your password has been reset successfully! You can now login with your new password.";
                // Clear session data
                unset($_SESSION['verification_code']);
                unset($_SESSION['step']);
                unset($_SESSION['email']);
                unset($_SESSION['user']);
                $step = 1; // Reset to initial step
            } else {
                $error = "Error updating password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 450px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #4CAF50;
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 15px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .step.active {
            background: #4CAF50;
            color: white;
            transform: scale(1.1);
        }

        .step.completed {
            background: #81c784;
            color: white;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #4CAF50;
            font-size: 1.2rem;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: #aaa;
        }

        .btn-primary {
            width: 100%;
            padding: 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #43a047;
            transform: translateY(-1px);
        }

        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .text-center {
            text-align: center;
            margin-top: 20px;
        }

        .text-center a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
        }

        .text-center a:hover {
            text-decoration: underline;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
        }
    </style>
</head>
<body>
   
    <div class="container">
        <div class="header">
            <h1>Reset Password</h1>
            <p>Follow the steps to reset your password</p>
        </div>

        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">2</div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if ($step === 1): ?>
                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email address" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Continue</button>
            <?php elseif ($step === 2): ?>
                <div class="form-group">
                    <i class="fas fa-question-circle"></i>
                    <label><?php echo htmlspecialchars($_SESSION['user']['security_question']); ?></label>
                    <input type="text" name="security_answer" class="form-control" placeholder="Enter your answer" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Verify Answer</button>
            <?php elseif ($step === 3): ?>
                <div class="form-group">
                    <i class="fas fa-key"></i>
                    <input type="text" name="verification_code" class="form-control" placeholder="Enter verification code" required autofocus>
                </div>
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            <?php endif; ?>
        </form>

        <?php if ($step === 3 && !$success): ?>
            <p class="text-center">
                Didn't receive the code? <a href="forgot-password.php" onclick="return confirm('Are you sure you want to restart the password reset process?')">Try again</a>
            </p>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>