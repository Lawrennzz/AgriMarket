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
    'email_reply_to' => '',
    'email_reply_to_name' => '',
    'email_use_smtp' => '0',
    'email_smtp_host' => 'smtp.gmail.com',
    'email_smtp_port' => '587',
    'email_smtp_username' => '',
    'email_smtp_password' => '',
    'email_smtp_secure' => 'tls'
];

$query = "SELECT name, value FROM settings WHERE name LIKE 'email_%'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['name']] = $row['value'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Get form values
    $email_from = filter_input(INPUT_POST, 'email_from', FILTER_VALIDATE_EMAIL);
    $email_from_name = mysqli_real_escape_string($conn, $_POST['email_from_name']);
    $email_reply_to = filter_input(INPUT_POST, 'email_reply_to', FILTER_VALIDATE_EMAIL) ?: '';
    $email_reply_to_name = isset($_POST['email_reply_to_name']) ? mysqli_real_escape_string($conn, $_POST['email_reply_to_name']) : '';
    $use_smtp = isset($_POST['use_smtp']) ? '1' : '0';
    $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $smtp_port = filter_input(INPUT_POST, 'smtp_port', FILTER_VALIDATE_INT);
    $smtp_username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
    $smtp_password = !empty($_POST['smtp_password']) 
        ? mysqli_real_escape_string($conn, $_POST['smtp_password']) 
        : $settings['email_smtp_password']; // Keep existing password if not provided
    $smtp_secure = mysqli_real_escape_string($conn, $_POST['smtp_secure']);
    
    // Validate required fields
    if (!$email_from) {
        $error_message = "Please enter a valid from email address.";
    } else if (empty($email_from_name)) {
        $error_message = "From name is required.";
    } else if ($use_smtp == '1' && empty($smtp_host)) {
        $error_message = "SMTP host is required when using SMTP.";
    } else if ($use_smtp == '1' && !$smtp_port) {
        $error_message = "SMTP port must be a valid number.";
    } else {
        // Update settings
        $updated = true;
        
        $settings_to_update = [
            'email_from' => $email_from,
            'email_from_name' => $email_from_name,
            'email_reply_to' => $email_reply_to,
            'email_reply_to_name' => $email_reply_to_name,
            'email_use_smtp' => $use_smtp,
            'email_smtp_host' => $smtp_host,
            'email_smtp_port' => $smtp_port,
            'email_smtp_username' => $smtp_username,
            'email_smtp_secure' => $smtp_secure
        ];
        
        // Only update password if provided
        if (!empty($_POST['smtp_password'])) {
            $settings_to_update['email_smtp_password'] = $smtp_password;
        }
        
        foreach ($settings_to_update as $name => $value) {
            // Check if setting exists
            $query = "SELECT COUNT(*) as count FROM settings WHERE name = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $name);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                // Update existing setting
                $query = "UPDATE settings SET value = ? WHERE name = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ss", $value, $name);
            } else {
                // Insert new setting
                $query = "INSERT INTO settings (name, value) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $query);
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

// Get messages from URL parameters (for when redirected from test email page)
if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}

if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - AgriMarket Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-section {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-row input[type="text"],
        .form-row input[type="email"],
        .form-row input[type="password"],
        .form-row input[type="number"],
        .form-row select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-row small {
            display: block;
            margin-top: 4px;
            color: #666;
        }
        
        .checkbox-row {
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        
        .alert-danger {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background-color: #45a049;
        }
        
        .btn-secondary {
            background-color: #2196F3;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #0b7dda;
        }
        
        .service-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fff;
        }
        
        .service-box h3 {
            margin-top: 0;
            color: #333;
        }
        
        .collapsible {
            background-color: #f1f1f1;
            cursor: pointer;
            padding: 10px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-weight: bold;
        }
        
        .collapsible:after {
            content: '\002B'; /* + sign */
            float: right;
            font-weight: bold;
        }
        
        .active:after {
            content: "\2212"; /* - sign */
        }
        
        .collapsible-content {
            padding: 0 18px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease-out;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <h1>Email Settings</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-section">
                <h2>General Email Settings</h2>
                
                <div class="form-row">
                    <label for="email_from">From Email Address*</label>
                    <input type="email" id="email_from" name="email_from" value="<?php echo htmlspecialchars($settings['email_from']); ?>" required>
                    <small>The email address that will appear in the "From" field of outgoing emails.</small>
                </div>
                
                <div class="form-row">
                    <label for="email_from_name">From Name*</label>
                    <input type="text" id="email_from_name" name="email_from_name" value="<?php echo htmlspecialchars($settings['email_from_name']); ?>" required>
                    <small>The name that will appear in the "From" field of outgoing emails.</small>
                </div>
                
                <div class="form-row">
                    <label for="email_reply_to">Reply-To Email Address</label>
                    <input type="email" id="email_reply_to" name="email_reply_to" value="<?php echo htmlspecialchars($settings['email_reply_to']); ?>">
                    <small>Optional. If left empty, the From Email Address will be used.</small>
                </div>
                
                <div class="form-row">
                    <label for="email_reply_to_name">Reply-To Name</label>
                    <input type="text" id="email_reply_to_name" name="email_reply_to_name" value="<?php echo htmlspecialchars($settings['email_reply_to_name']); ?>">
                    <small>Optional. If left empty, the From Name will be used.</small>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Mail Delivery</h2>
                
                <div class="checkbox-row">
                    <label>
                        <input type="checkbox" id="use_smtp" name="use_smtp" value="1" <?php echo $settings['email_use_smtp'] === '1' ? 'checked' : ''; ?>>
                        Use SMTP to send emails (recommended)
                    </label>
                    <small>This allows more reliable email delivery by using a dedicated SMTP server instead of PHP's mail() function.</small>
                </div>
                
                <div id="smtp-settings" <?php echo $settings['email_use_smtp'] === '0' ? 'style="display:none;"' : ''; ?>>
                    <div class="form-row">
                        <label for="smtp_host">SMTP Host*</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['email_smtp_host']); ?>">
                        <small>The hostname of your SMTP server (e.g., smtp.gmail.com, smtp.office365.com)</small>
                    </div>
                    
                    <div class="form-row">
                        <label for="smtp_port">SMTP Port*</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['email_smtp_port']); ?>">
                        <small>Common ports: 587 (TLS), 465 (SSL), 25 (insecure)</small>
                    </div>
                    
                    <div class="form-row">
                        <label for="smtp_secure">Security Protocol</label>
                        <select id="smtp_secure" name="smtp_secure">
                            <option value="" <?php echo $settings['email_smtp_secure'] === '' ? 'selected' : ''; ?>>None</option>
                            <option value="tls" <?php echo $settings['email_smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo $settings['email_smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                        <small>TLS is the modern, secure option and works with port 587</small>
                    </div>
                    
                    <div class="form-row">
                        <label for="smtp_username">SMTP Username</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['email_smtp_username']); ?>">
                        <small>Usually your email address</small>
                    </div>
                    
                    <div class="form-row">
                        <label for="smtp_password">SMTP Password</label>
                        <input type="password" id="smtp_password" name="smtp_password" placeholder="<?php echo empty($settings['email_smtp_password']) ? 'No password set' : '••••••••••'; ?>">
                        <small>Leave empty to keep current password. For Gmail, use an App Password.</small>
                    </div>
                    
                    <div class="service-box">
                        <h3>Using Gmail?</h3>
                        <p>To use Gmail as your SMTP provider:</p>
                        <ol>
                            <li>Make sure 2-Step Verification is enabled on your Google account</li>
                            <li>Generate an App Password at <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
                            <li>Use the following settings:
                                <ul>
                                    <li>SMTP Host: smtp.gmail.com</li>
                                    <li>SMTP Port: 587</li>
                                    <li>Security: TLS</li>
                                    <li>Username: your.email@gmail.com</li>
                                    <li>Password: Your App Password (not your Gmail password)</li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_settings" class="btn-primary">Save Settings</button>
            </div>
        </form>
        
        <div class="form-section" style="margin-top: 30px;">
            <h2>Send Test Email</h2>
            <p>After configuring your email settings, send a test email to verify everything is working correctly.</p>
            
            <form method="POST" action="send_test_email.php">
                <div class="form-row">
                    <label for="test_email">Send to Email Address*</label>
                    <input type="email" id="test_email" name="test_email" required>
                </div>
                
                <div class="checkbox-row">
                    <label>
                        <input type="checkbox" name="dev_mode" value="1">
                        Development Mode (log email to file instead of sending)
                    </label>
                    <small>Perfect for local development environments where sending real emails might not be possible.</small>
                </div>
                
                <div class="checkbox-row">
                    <label>
                        <input type="checkbox" name="debug_mode" value="1">
                        Debug Mode (capture detailed error information)
                    </label>
                </div>
                
                <button type="submit" name="send_test" class="btn-primary">Send Test Email</button>
            </form>
        </div>
        
        <div class="form-section">
            <button class="collapsible">Troubleshooting Email Issues</button>
            <div class="collapsible-content">
                <h3>Common Issues</h3>
                <ul>
                    <li><strong>Local Development:</strong> Most localhost setups can't send emails directly. Use Development Mode or configure an external SMTP service.</li>
                    <li><strong>Authentication Failures:</strong> Double-check your username and password. For Gmail, make sure you're using an App Password.</li>
                    <li><strong>Connection Timeouts:</strong> Some networks block outgoing mail ports. Try using port 587 with TLS.</li>
                    <li><strong>SSL/TLS Issues:</strong> Make sure you're using the correct security protocol for your chosen port.</li>
                </ul>
                
                <h3>Alternative Services</h3>
                <p>If you can't get your email server working, consider these alternatives:</p>
                
                <div class="service-box">
                    <h3>Mailtrap</h3>
                    <p>A fake SMTP server for testing emails in development environments without sending them to real users.</p>
                    <p><a href="https://mailtrap.io/" target="_blank">https://mailtrap.io/</a></p>
                </div>
                
                <div class="service-box">
                    <h3>SendGrid</h3>
                    <p>Email delivery service with a free tier (100 emails/day). Provides reliable delivery and detailed analytics.</p>
                    <p><a href="https://sendgrid.com/" target="_blank">https://sendgrid.com/</a></p>
                </div>
                
                <div class="service-box">
                    <h3>Mailgun</h3>
                    <p>Powerful email API service with features for sending, receiving, and tracking emails.</p>
                    <p><a href="https://www.mailgun.com/" target="_blank">https://www.mailgun.com/</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle SMTP settings visibility
        const useSmtpCheckbox = document.getElementById('use_smtp');
        const smtpSettings = document.getElementById('smtp-settings');
        
        useSmtpCheckbox.addEventListener('change', function() {
            smtpSettings.style.display = this.checked ? 'block' : 'none';
        });
        
        // Make collapsible sections work
        const collapsibles = document.getElementsByClassName('collapsible');
        for (let i = 0; i < collapsibles.length; i++) {
            collapsibles[i].addEventListener('click', function() {
                this.classList.toggle('active');
                const content = this.nextElementSibling;
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                } else {
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        }
    });
    </script>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 