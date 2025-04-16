<?php
session_start(); // Start the session
include 'config.php'; // Include your database connection

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
            // Security answer is correct, send SMS verification
            $verification_code = rand(100000, 999999); // Generate a 6-digit code

            // Send SMS using Twilio or another SMS service
            // Example: $twilio->messages->create($user['phone_number'], ['from' => 'your_twilio_number', 'body' => "Your verification code is: $verification_code"]);

            // Store the verification code in the session
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['step'] = 3; // Move to SMS verification
            $step = 3; // Update local variable for immediate use
        } else {
            $error = "Incorrect security answer.";
        }
    } elseif ($step === 3) {
        $input_code = mysqli_real_escape_string($conn, $_POST['verification_code']);
        $email = $_SESSION['email']; // Retrieve email from session

        // Validate verification code
        if ($input_code == $_SESSION['verification_code']) {
            // Allow user to set a new password
            $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password in the database
            $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
            mysqli_stmt_execute($update_stmt);

            $success = "Your password has been reset successfully!";
            // Clear session data
            unset($_SESSION['verification_code']);
            unset($_SESSION['step']);
            unset($_SESSION['email']);
            unset($_SESSION['user']);
            $step = 1; // Reset to initial step
        } else {
            $error = "Invalid verification code.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .error-message, .success-message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="email"], input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if ($step === 1): ?>
                <div>
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <button type="submit">Next</button>
            <?php elseif ($step === 2): ?>
                <div>
                    <label><?php echo htmlspecialchars($_SESSION['user']['security_question']); ?></label>
                    <input type="text" name="security_answer" required>
                </div>
                <button type="submit">Verify Answer</button>
            <?php elseif ($step === 3): ?>
                <div>
                    <label for="verification_code">Verification Code (sent via SMS):</label>
                    <input type="text" name="verification_code" required>
                </div>
                <div>
                    <label for="new_password">New Password:</label>
                    <input type="password" name="new_password" required>
                </div>
                <button type="submit">Reset Password</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>