<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../classes/Mailer.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Helper function to better identify mail configuration issues
function get_mail_configuration_info() {
    $info = array(
        'PHP Version' => phpversion(),
        'mail() Function Exists' => function_exists('mail') ? 'Yes' : 'No',
        'SMTP Setting' => ini_get('SMTP'),
        'smtp_port Setting' => ini_get('smtp_port'),
        'sendmail_path' => ini_get('sendmail_path'),
        'Server OS' => PHP_OS
    );
    
    // Check if running in XAMPP
    $info['XAMPP Detected'] = (stripos($_SERVER['SERVER_SOFTWARE'], 'xampp') !== false) ? 'Yes' : 'No';
    
    return $info;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
    $dev_mode = isset($_POST['dev_mode']) ? true : false;
    $debug_mode = isset($_POST['debug_mode']) ? true : false;
    
    // Basic validation
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: email_settings.php?error=' . urlencode('Please enter a valid email address.'));
        exit;
    }
    
    // Initialize mailer
    $mailer = new Mailer($debug_mode, $dev_mode);
    
    // Create test email content
    $subject = 'AgriMarket Email Test - ' . date('Y-m-d H:i:s');
    $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">';
    $message .= '<h2 style="color: #4CAF50;">AgriMarket Test Email</h2>';
    $message .= '<p>This is a test email from AgriMarket. If you\'re seeing this, your email configuration is working correctly!</p>';
    $message .= '<p><strong>Sent at:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    $message .= '<p><strong>Environment Info:</strong><br>';
    $message .= 'PHP Version: ' . phpversion() . '<br>';
    $message .= 'Server: ' . $_SERVER['SERVER_SOFTWARE'] . '<br>';
    $message .= 'Mode: ' . ($dev_mode ? 'Development' : 'Production') . '</p>';
    
    if ($dev_mode) {
        $message .= '<p style="background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;"><strong>Note:</strong> This test is running in development mode. The email is not actually sent but logged to a file.</p>';
    }
    
    $message .= '</div>';
    
    // Try to send the email
    $result = $mailer->sendEmail($test_email, $subject, $message);
    
    if ($result) {
        // Success
        $success_message = 'Test email ' . ($dev_mode ? 'logged' : 'sent') . ' successfully to ' . $test_email;
        
        if ($dev_mode) {
            $success_message .= '. Check the logs directory for the email content.';
        }
        
        header('Location: email_settings.php?success=' . urlencode($success_message));
        exit;
    } else {
        // Failure
        $error_info = get_mail_configuration_info();
        $error_message = 'Failed to send test email: ' . $mailer->getLastError();
        $error_message .= '<br><br>PHP Configuration: ' . print_r($error_info, true);
        
        // Suggest fixes based on environment
        if (stripos(PHP_OS, 'WIN') !== false) {
            $error_message .= '<br><br><strong>Recommendations for Windows:</strong><br>';
            $error_message .= '1. Configure php.ini with a valid SMTP server.<br>';
            $error_message .= '2. Enable SMTP in email settings above and use an external service like Gmail, Outlook, or SendGrid.<br>';
            $error_message .= '3. For local testing, consider using Mailtrap.io or enabling dev mode to log emails instead of sending them.';
        }
        
        header('Location: email_settings.php?error=' . urlencode($error_message));
        exit;
    }
} else {
    // Not a POST request or missing parameters
    header('Location: email_settings.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Test Email - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        
        .btn-back {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .btn-back:hover {
            background-color: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <h1>Test Email Result</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <h3>Success!</h3>
                <p><?php echo $success_message; ?></p>
            </div>
            
            <p>If you don't see the email in your inbox:</p>
            <ul>
                <li>Check your spam or junk folder</li>
                <li>Verify that the email address you provided is correct</li>
                <li>Some email providers might block emails from new or unverified sources</li>
            </ul>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <h3>Error!</h3>
                <p><?php echo $error_message; ?></p>
            </div>
            
            <p>Possible reasons for failure:</p>
            <ul>
                <li>Your server might not be configured to send emails</li>
                <li>If you're using a local development environment, your ISP might be blocking outgoing mail traffic</li>
                <li>The "From" email address might be rejected by email servers</li>
                <li>Check the PHP error logs for more details</li>
            </ul>
        <?php endif; ?>
        
        <a href="email_settings.php" class="btn-back">Back to Email Settings</a>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 