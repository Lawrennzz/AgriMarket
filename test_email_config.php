<?php
// Include required files
require_once 'includes/env.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load environment variables
Env::load();

// Check if environment variables are set
echo "<h1>Email Configuration Test</h1>";
echo "<p>This script checks your email configuration in the .env file.</p>";

// Output current settings
echo "<h2>Current Settings</h2>";
echo "<ul>";
echo "<li>SMTP Host: " . (Env::get('SMTP_HOST') ? Env::get('SMTP_HOST') : 'Not set') . "</li>";
echo "<li>SMTP Port: " . (Env::get('SMTP_PORT') ? Env::get('SMTP_PORT') : 'Not set') . "</li>";
echo "<li>SMTP Secure: " . (Env::get('SMTP_SECURE') ? Env::get('SMTP_SECURE') : 'Not set') . "</li>";
echo "<li>Email User: " . (Env::get('EMAIL_USER') ? Env::get('EMAIL_USER') : 'Not set') . "</li>";
echo "<li>Email From: " . (Env::get('EMAIL_FROM') ? Env::get('EMAIL_FROM') : 'Not set') . "</li>";
echo "<li>Email Reply-To: " . (Env::get('EMAIL_REPLY_TO') ? Env::get('EMAIL_REPLY_TO') : 'Not set') . "</li>";
echo "<li>Site URL: " . (Env::get('SITE_URL') ? Env::get('SITE_URL') : 'Not set') . "</li>";
echo "</ul>";

// Check if password is set (without showing it)
if (Env::get('EMAIL_PASS')) {
    echo "<p style='color: green;'>✓ Email password is set.</p>";
} else {
    echo "<p style='color: red;'>✗ Email password is not set.</p>";
}

// Show instructions for Gmail App Password
echo "<h2>Setting Up Gmail App Password</h2>";
echo "<p>If you're using Gmail, you need to set up an App Password:</p>";
echo "<ol>";
echo "<li>Go to your <a href='https://myaccount.google.com/security' target='_blank'>Google Account Security settings</a></li>";
echo "<li>Enable 2-Step Verification if not already enabled</li>";
echo "<li>Go to <a href='https://myaccount.google.com/apppasswords' target='_blank'>App passwords</a></li>";
echo "<li>Select 'Mail' and your device</li>";
echo "<li>Copy the generated 16-character password</li>";
echo "<li>Paste it as the EMAIL_PASS value in your .env file</li>";
echo "</ol>";

// Provide .env file format
echo "<h2>Example .env File Format</h2>";
echo "<pre>
# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
EMAIL_USER=your_email@gmail.com
EMAIL_PASS=your_app_password_here
EMAIL_FROM=AgriMarket &lt;your_email@gmail.com&gt;
EMAIL_REPLY_TO=support@agrimarket.com

# Other Configuration
SITE_URL=http://localhost/AgriMarket
</pre>";

// Test connection if requested
if (isset($_GET['test']) && $_GET['test'] == '1') {
    echo "<h2>Connection Test Results</h2>";
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host = Env::get('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = Env::get('EMAIL_USER', '');
        $mail->Password = Env::get('EMAIL_PASS', '');
        $mail->SMTPSecure = Env::get('SMTP_SECURE', 'tls');
        $mail->Port = Env::get('SMTP_PORT', 587);
        
        // Recipients
        $mail->setFrom(
            Env::get('EMAIL_USER', ''),
            'AgriMarket'
        );
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Test Connection';
        $mail->Body = 'This is just a connection test.';
        
        // Don't actually send, just test connection
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Start output buffering
        ob_start();
        // Connect only
        $mail->smtpConnect();
        $output = ob_get_clean();
        
        echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
        echo nl2br(htmlspecialchars($output));
        echo "</div>";
        
        echo "<p style='color: green;'>✓ Connection to SMTP server successful!</p>";
        
        $mail->smtpClose();
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Connection failed!</p>";
        echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
        echo "Error: " . $e->getMessage();
        echo "</div>";
    }
}

// Test Send Email
if (isset($_GET['send']) && $_GET['send'] == '1' && isset($_GET['email']) && !empty($_GET['email'])) {
    $test_email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$test_email) {
        echo "<p style='color: red;'>Invalid email address provided.</p>";
    } else {
        echo "<h2>Email Sending Test Results</h2>";
        
        try {
            require_once 'includes/Mailer.php';
            $mailer = new Mailer();
            $result = $mailer->sendNotification(
                $test_email, 
                'AgriMarket Email Test', 
                'This is a test email from your AgriMarket system. If you received this, your email configuration is working correctly!'
            );
            
            if ($result) {
                echo "<p style='color: green;'>✓ Email sent successfully to {$test_email}!</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to send email.</p>";
                echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
                foreach ($mailer->getErrors() as $error) {
                    echo htmlspecialchars($error) . "<br>";
                }
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Show test forms
echo "<h2>Test Your Configuration</h2>";
echo "<div style='display: flex; gap: 20px;'>";

// Connection test form
echo "<div style='flex: 1;'>";
echo "<h3>Test SMTP Connection</h3>";
echo "<p>Click the button below to test the connection to your SMTP server:</p>";
echo "<form method='get'>";
echo "<input type='hidden' name='test' value='1'>";
echo "<button type='submit' style='padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Test Connection</button>";
echo "</form>";
echo "</div>";

// Email test form
echo "<div style='flex: 1;'>";
echo "<h3>Send Test Email</h3>";
echo "<p>Enter an email address to send a test email:</p>";
echo "<form method='get'>";
echo "<input type='hidden' name='send' value='1'>";
echo "<input type='email' name='email' placeholder='recipient@example.com' required style='padding: 8px; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; width: 250px;'>";
echo "<button type='submit' style='padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Send Test Email</button>";
echo "</form>";
echo "</div>";

echo "</div>";

echo "<h2>Next Steps</h2>";
echo "<p>Once your email configuration is working:</p>";
echo "<ol>";
echo "<li>Test sending an order confirmation: <a href='test_order_email.php'>Test Order Confirmation Email</a></li>";
echo "<li>Return to <a href='admin_dashboard.php'>Admin Dashboard</a></li>";
echo "</ol>";
?> 