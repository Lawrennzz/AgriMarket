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

// Get current email settings
$settings = [
    'email_from' => 'noreply@agrimarket.com',
    'email_from_name' => 'AgriMarket',
    'email_smtp_host' => 'smtp.gmail.com',
    'email_smtp_port' => '587',
    'email_smtp_username' => '',
    'email_smtp_password' => '',
    'email_smtp_secure' => 'tls',
    'email_use_smtp' => '0'  // Add default value for email_use_smtp
];

$query = "SELECT name, value FROM settings WHERE name LIKE 'email_%'";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['name']] = $row['value'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $email_from = mysqli_real_escape_string($conn, $_POST['email_from']);
    $email_from_name = mysqli_real_escape_string($conn, $_POST['email_from_name']);
    $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $smtp_port = mysqli_real_escape_string($conn, $_POST['smtp_port']);
    $smtp_username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
    $smtp_password = isset($_POST['smtp_password']) && !empty($_POST['smtp_password']) 
                     ? mysqli_real_escape_string($conn, $_POST['smtp_password']) 
                     : $settings['email_smtp_password']; // Keep existing password if not changed
    $smtp_secure = mysqli_real_escape_string($conn, $_POST['smtp_secure']);
    $use_smtp = isset($_POST['use_smtp']) ? '1' : '0';
    
    // Basic validation
    if (!filter_var($email_from, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (!is_numeric($smtp_port)) {
        $error_message = "SMTP port must be a number.";
    } else {
        // Update settings
        $updated = true;
        
        $settings_to_update = [
            'email_from' => $email_from,
            'email_from_name' => $email_from_name,
            'email_smtp_host' => $smtp_host,
            'email_smtp_port' => $smtp_port,
            'email_smtp_username' => $smtp_username,
            'email_smtp_secure' => $smtp_secure,
            'email_use_smtp' => $use_smtp
        ];
        
        // Only update password if a new one was provided
        if (isset($_POST['smtp_password']) && !empty($_POST['smtp_password'])) {
            $settings_to_update['email_smtp_password'] = $smtp_password;
        }
        
        foreach ($settings_to_update as $name => $value) {
            // Check if setting exists
            $check_query = "SELECT setting_id FROM settings WHERE name = ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "s", $name);
            mysqli_stmt_execute($stmt);
            $check_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing setting
                $update_query = "UPDATE settings SET value = ? WHERE name = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ss", $value, $name);
            } else {
                // Insert new setting
                $insert_query = "INSERT INTO settings (name, value) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "ss", $name, $value);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                $updated = false;
                $error_message = "Failed to update settings: " . mysqli_error($conn);
                break;
            }
            
            // Update local settings array
            $settings[$name] = $value;
        }
        
        if ($updated) {
            $success_message = "Email settings updated successfully.";
        }
    }
}

// Check for error or success messages from send_test_email.php
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}

// Add a development mode option
$dev_mode = false;
if (isset($_POST['dev_mode'])) {
    $dev_mode = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-submit {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .btn-submit:hover {
            background-color: #45a049;
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
        
        .settings-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section h3 {
            margin-top: 0;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <h1>Email Settings</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="settings-form">
            <form method="POST" action="">
                <div class="settings-section">
                    <h3>General Email Settings</h3>
                    <div class="form-group">
                        <label for="email_from">From Email Address</label>
                        <input type="email" id="email_from" name="email_from" value="<?php echo htmlspecialchars($settings['email_from']); ?>" required>
                        <small>This is the email address that will appear in the "From" field of all outgoing emails.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_from_name">From Name</label>
                        <input type="text" id="email_from_name" name="email_from_name" value="<?php echo htmlspecialchars($settings['email_from_name']); ?>" required>
                        <small>This is the name that will appear in the "From" field of all outgoing emails.</small>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>SMTP Server Settings</h3>
                    <p>Configure these settings to send emails through an SMTP server for better deliverability.</p>
                    
                    <div class="form-group">
                        <label><input type="checkbox" name="use_smtp" value="1" <?php echo $settings['email_use_smtp'] === '1' ? 'checked' : ''; ?>> Use SMTP Server</label>
                        <small>Enable this to use SMTP for sending emails instead of PHP's built-in mail function.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['email_smtp_host']); ?>">
                        <small>The hostname of your SMTP server. Common examples:
                            <ul>
                                <li>Gmail: smtp.gmail.com</li>
                                <li>Outlook/Office 365: smtp.office365.com</li>
                                <li>Yahoo: smtp.mail.yahoo.com</li>
                                <li>Sendgrid: smtp.sendgrid.net</li>
                            </ul>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_port">SMTP Port</label>
                        <input type="text" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['email_smtp_port']); ?>">
                        <small>The port for your SMTP server:
                            <ul>
                                <li>Port 587: TLS (recommended, most secure)</li>
                                <li>Port 465: SSL (older)</li>
                                <li>Port 25: No encryption (not recommended)</li>
                            </ul>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_secure">Security Protocol</label>
                        <select id="smtp_secure" name="smtp_secure">
                            <option value="none" <?php echo $settings['email_smtp_secure'] === 'none' ? 'selected' : ''; ?>>None</option>
                            <option value="ssl" <?php echo $settings['email_smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo $settings['email_smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        </select>
                        <small>Security protocol for the SMTP connection (TLS is recommended for most providers)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_username">SMTP Username</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['email_smtp_username']); ?>">
                        <small>Your SMTP server username (often your complete email address)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_password">SMTP Password</label>
                        <input type="password" id="smtp_password" name="smtp_password" placeholder="<?php echo empty($settings['email_smtp_password']) ? 'No password set' : '••••••••••••'; ?>">
                        <small>Your SMTP server password. For Gmail, you might need to generate an "App Password" in your Google Account settings.</small>
                    </div>
                    
                    <div class="gmail-note" style="background-color: #f8f9fa; padding: 15px; border-left: 5px solid #4285F4; margin: 15px 0;">
                        <h4 style="margin-top: 0;">Using Gmail?</h4>
                        <p>If you're using Gmail, you need to:</p>
                        <ol>
                            <li>Enable 2-Step Verification in your Google Account</li>
                            <li>Create an "App Password" at <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
                            <li>Use that App Password here instead of your regular Gmail password</li>
                        </ol>
                        <p>This is required because Google blocks "less secure apps" by default.</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <p><strong>Note:</strong> If SMTP settings are not configured or invalid, the system will fall back to using PHP's built-in mail() function.</p>
                </div>
                
                <button type="submit" name="save_settings" class="btn-submit">Save Settings</button>
            </form>
        </div>
        
        <div class="test-email">
            <h2>Send Test Email</h2>
            <p>Use this form to send a test email and verify your email settings.</p>
            
            <form method="POST" action="send_test_email.php">
                <div class="form-group">
                    <label for="test_email">Test Email Address</label>
                    <input type="email" id="test_email" name="test_email" placeholder="Enter your email address" required>
                </div>
                
                <div class="form-group">
                    <label><input type="checkbox" name="dev_mode" value="1"> Development Mode</label>
                    <small>In development mode, emails will be logged to files instead of being sent. Useful for local testing.</small>
                </div>
                
                <div class="form-group">
                    <label><input type="checkbox" name="debug_mode" value="1" checked> Debug Mode</label>
                    <small>Enable debug mode to get more detailed error information.</small>
                </div>
                
                <button type="submit" name="send_test" class="btn-submit">Send Test Email</button>
            </form>
        </div>
        
        <div class="email-diagnostics">
            <h2>Email Diagnostics</h2>
            <p>If you're having trouble sending emails, use these tools to diagnose the problem.</p>
            
            <div class="diagnostics-section">
                <h3>Tools</h3>
                <ul>
                    <li><a href="send_test_email.php" class="btn btn-primary">Send Test Email</a></li>
                    <li><a href="smtp_test.php" class="btn btn-info">SMTP Connection Test</a> - Test your SMTP connection without sending an actual email</li>
                </ul>
            </div>
            
            <div class="diagnostics-section">
                <h3>Common Email Issues</h3>
                <ul>
                    <li><strong>Gmail Users:</strong> You need to create an "App Password" since Gmail blocks direct password login for security reasons.</li>
                    <li><strong>Port Blocking:</strong> Many ISPs block port 25. Try using port 587 with TLS instead.</li>
                    <li><strong>SMTP Security Settings:</strong> Make sure your SMTP encryption setting (SSL/TLS) matches the port you're using.</li>
                    <li><strong>Incorrect Credentials:</strong> Double check your username and password.</li>
                    <li><strong>Local Development Environment:</strong> Some hosting environments disable email sending. Try a solution like Mailtrap.</li>
                </ul>
            </div>
            
            <div class="diagnostics-section">
                <h3>Alternative Solutions</h3>
                <p>If you can't get SMTP working, consider these alternatives:</p>
                <ul>
                    <li><strong>Mailtrap:</strong> <a href="https://mailtrap.io/" target="_blank">Mailtrap.io</a> provides a fake SMTP server that captures all emails for testing.</li>
                    <li><strong>SendGrid:</strong> <a href="https://sendgrid.com/" target="_blank">SendGrid</a> offers a free tier that allows sending 100 emails per day.</li>
                    <li><strong>Mailgun:</strong> <a href="https://www.mailgun.com/" target="_blank">Mailgun</a> also has a free tier with limited emails per month.</li>
                </ul>
            </div>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="email-troubleshooting">
                <h2>Troubleshooting</h2>
                <div class="alert alert-danger">
                    <h3>Error Details</h3>
                    <pre><?php echo htmlspecialchars($error_message); ?></pre>
                </div>
                
                <h3>Common Email Issues</h3>
                <ul>
                    <li><strong>SMTP Connection Failure:</strong> Verify your SMTP host, port, and security settings.</li>
                    <li><strong>Authentication Failed:</strong> Check your SMTP username and password.</li>
                    <li><strong>Local Mail Server Issues:</strong> On local development environments, the mail server may not be configured.</li>
                    <li><strong>Port Blocking:</strong> Your ISP or firewall might be blocking outgoing mail ports (25, 465, 587).</li>
                    <li><strong>Missing PHP Extensions:</strong> Make sure necessary PHP extensions are installed (openssl, etc.).</li>
                </ul>
                
                <h3>Check PHP Mail Configuration</h3>
                <p>You can verify your PHP mail configuration by setting up a simple PHP info page.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 