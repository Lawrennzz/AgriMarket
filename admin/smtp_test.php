<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$conn = getConnection();
$success_message = '';
$error_message = '';
$debug_output = '';

// Get current email settings
$settings = [
    'email_from' => 'noreply@agrimarket.com',
    'email_from_name' => 'AgriMarket',
    'email_smtp_host' => 'smtp.gmail.com',
    'email_smtp_port' => '587',
    'email_smtp_username' => '',
    'email_smtp_password' => '',
    'email_smtp_secure' => 'tls',
    'email_use_smtp' => '1'
];

// Fetch settings from the database
$query = "SHOW COLUMNS FROM settings LIKE 'setting_key'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    // Table has setting_key column
    $query = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%'";
    $key_column = 'setting_key';
    $value_column = 'setting_value';
} else {
    // Table probably has name column instead
    $query = "SELECT name, value FROM settings WHERE name LIKE 'email_%'";
    $key_column = 'name';
    $value_column = 'value';
}

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row[$key_column];
        $settings[$key] = $row[$value_column];
    }
}

// Run SMTP test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    $debug_output .= "Starting SMTP connection test...\n";
    $debug_output .= "Host: " . $settings['email_smtp_host'] . "\n";
    $debug_output .= "Port: " . $settings['email_smtp_port'] . "\n";
    $debug_output .= "Encryption: " . $settings['email_smtp_secure'] . "\n";
    $debug_output .= "Username: " . $settings['email_smtp_username'] . "\n";
    $debug_output .= "Password: " . (empty($settings['email_smtp_password']) ? "Not set" : "Set (hidden)") . "\n\n";
    
    // Load PHPMailer
    $phpmailerClass = null;
    $phpmailerExceptionClass = null;
    $phpmailerSMTPClass = null;
    
    // Try to load PHPMailer classes
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Check for PHPMailer class existence
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailerClass = 'PHPMailer\PHPMailer\PHPMailer';
            $phpmailerExceptionClass = 'PHPMailer\PHPMailer\Exception';
            $phpmailerSMTPClass = 'PHPMailer\PHPMailer\SMTP';
        }
    } else if (file_exists(__DIR__ . '/../PHPMailer/src/PHPMailer.php')) {
        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/../PHPMailer/src/Exception.php';
        
        // Check for PHPMailer class existence
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailerClass = 'PHPMailer\PHPMailer\PHPMailer';
            $phpmailerExceptionClass = 'PHPMailer\PHPMailer\Exception';
            $phpmailerSMTPClass = 'PHPMailer\PHPMailer\SMTP';
        }
    }
    
    // Check if PHPMailer is available
    if (!$phpmailerClass) {
        $error_message = "PHPMailer is not available. Please install PHPMailer using Composer or manually.";
        $debug_output .= "PHPMailer not found. Cannot test SMTP connection.\n";
    } else {
        $debug_output .= "PHPMailer is available. Proceeding with connection test.\n";
        
        try {
            // Create a new PHPMailer instance for testing
            $mail = new $phpmailerClass(true);
            
            // Enable verbose debugging
            $mail->SMTPDebug = 3; // 3 = debug output for connection + messages
            $mail->Debugoutput = function($str, $level) use (&$debug_output) {
                $debug_output .= "$str\n";
            };
            
            // Set up SMTP
            $mail->isSMTP();
            $mail->Host = $settings['email_smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['email_smtp_username'];
            $mail->Password = $settings['email_smtp_password'];
            $mail->Port = intval($settings['email_smtp_port']);
            
            // Set security
            if ($settings['email_smtp_secure'] === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } elseif ($settings['email_smtp_secure'] === 'tls') {
                $mail->SMTPSecure = 'tls';
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Disable certificate verification for testing
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set timeout
            $mail->Timeout = 30;
            
            // Try to connect
            $debug_output .= "Attempting to connect to SMTP server...\n";
            $mail->smtpConnect();
            
            $success_message = "SMTP connection successful! Your SMTP settings are correct.";
            $debug_output .= "Connection established successfully.\n";
            
            // Close the connection
            $mail->smtpClose();
        } catch (Exception $e) {
            $error_message = "SMTP connection failed: " . $e->getMessage();
            $debug_output .= "Connection failed: " . $e->getMessage() . "\n";
            
            // Add troubleshooting advice
            if (strpos($e->getMessage(), 'Could not connect to SMTP host') !== false) {
                $debug_output .= "\nPossible reasons:\n";
                $debug_output .= "- Wrong SMTP host or port\n";
                $debug_output .= "- Your ISP might be blocking outgoing connections to this port\n";
                $debug_output .= "- Firewall/antivirus is blocking the connection\n";
            } else if (strpos($e->getMessage(), 'authentication failed') !== false) {
                $debug_output .= "\nAuthentication failed. Possible reasons:\n";
                $debug_output .= "- Wrong username or password\n";
                $debug_output .= "- For Gmail, you need to use an App Password\n";
                $debug_output .= "- Two-factor authentication might be required\n";
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
    <title>SMTP Test - AgriMarket</title>
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
        
        .debug-output {
            background-color: #272822;
            color: #f8f8f2;
            font-family: monospace;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
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
        <h1>SMTP Connection Test</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <h3>Success!</h3>
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <h3>Error!</h3>
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])): ?>
            <h3>Test Results</h3>
            <div class="debug-output"><?php echo htmlspecialchars($debug_output); ?></div>
            
            <?php if (empty($success_message)): ?>
                <div class="troubleshooting">
                    <h3>Troubleshooting Suggestions</h3>
                    <ul>
                        <li><strong>Check Credentials:</strong> Make sure your username and password are correct.</li>
                        <li><strong>Port Selection:</strong> Try a different port (587 with TLS is recommended).</li>
                        <li><strong>Gmail Users:</strong> Create an "App Password" in your Google Account Settings.</li>
                        <li><strong>Firewall/Antivirus:</strong> Temporarily disable to check if they're causing issues.</li>
                        <li><strong>Try Alternative Service:</strong> Consider using a service like Mailtrap or SendGrid.</li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>This tool will test your SMTP connection settings without sending an actual email.</p>
            <p>Click the button below to start the test. The diagnostic information will help identify any issues with your SMTP configuration.</p>
            
            <form method="POST" action="">
                <button type="submit" name="test_smtp" class="btn-submit">Start SMTP Test</button>
            </form>
        <?php endif; ?>
        
        <a href="email_settings.php" class="btn-back">Back to Email Settings</a>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 