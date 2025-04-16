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

// Function to get detailed mail configuration info
function getMailConfigInfo() {
    return [
        'PHP Version' => phpversion(),
        'mail() Function Exists' => function_exists('mail') ? 'Yes' : 'No',
        'SMTP Setting' => ini_get('SMTP'),
        'smtp_port Setting' => ini_get('smtp_port'),
        'sendmail_path' => ini_get('sendmail_path'),
        'Server OS' => PHP_OS,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
}

// Process test email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = filter_input(INPUT_POST, 'test_email', FILTER_VALIDATE_EMAIL);
    $dev_mode = isset($_POST['dev_mode']);
    $debug_mode = isset($_POST['debug_mode']);
    
    // Validate email
    if (!$test_email) {
        $error = "Please enter a valid email address.";
        header('Location: email_settings.php?error=' . urlencode($error));
        exit;
    }
    
    // Create mailer instance with specified modes
    $mailer = new Mailer($debug_mode, $dev_mode);
    
    // Create test email content
    $subject = 'AgriMarket Test Email - ' . date('Y-m-d H:i:s');
    
    // Build HTML email content
    $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">';
    $message .= '<h1 style="color: #4CAF50; text-align: center;">Test Email from AgriMarket</h1>';
    $message .= '<p>This is a test email sent from your AgriMarket application to verify that your email settings are working correctly.</p>';
    
    // Show if we're in development mode
    if ($dev_mode) {
        $message .= '<div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
        $message .= '<h3 style="margin-top: 0; color: #856404;">Development Mode Enabled</h3>';
        $message .= '<p>This email was not actually sent but was logged to a file in the logs directory.</p>';
        $message .= '</div>';
    }
    
    // Add server information if in debug mode
    if ($debug_mode) {
        $message .= '<h2 style="margin-top: 20px; color: #2E7D32;">Server Information</h2>';
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $message .= '<tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; background-color: #f2f2f2;">Setting</th>';
        $message .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; background-color: #f2f2f2;">Value</th></tr>';
        
        foreach (getMailConfigInfo() as $key => $value) {
            $message .= '<tr>';
            $message .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>' . htmlspecialchars($key) . ':</strong></td>';
            $message .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($value) . '</td>';
            $message .= '</tr>';
        }
        
        $message .= '</table>';
    }
    
    $message .= '<p style="margin-top: 30px;">This email was sent on: ' . date('Y-m-d H:i:s') . '</p>';
    $message .= '<p>If you received this email, your email configuration is working correctly!</p>';
    
    // Footer
    $message .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; text-align: center;">';
    $message .= '<p>This is an automated message from your AgriMarket system. Please do not reply to this email.</p>';
    $message .= '</div>';
    $message .= '</div>';
    
    // Try to send the email
    $result = $mailer->sendEmail($test_email, $subject, $message);
    
    if ($result) {
        // Success - email sent or logged
        $action = $dev_mode ? 'logged' : 'sent';
        $success = "Test email has been $action successfully to $test_email";
        
        if ($dev_mode) {
            $success .= ". Check your logs directory for the email content.";
        } else {
            $success .= ". Please check your inbox (and spam folder).";
        }
        
        header('Location: email_settings.php?success=' . urlencode($success));
        exit;
    } else {
        // Failure - generate detailed error message
        $error = "Failed to send test email: " . $mailer->getLastError();
        
        // Add configuration info for troubleshooting
        $configInfo = getMailConfigInfo();
        $error .= "\n\nSystem Configuration:\n";
        foreach ($configInfo as $key => $value) {
            $error .= "$key: $value\n";
        }
        
        // Add troubleshooting suggestions based on environment
        if (stripos(PHP_OS, 'win') !== false) {
            $error .= "\n\nTroubleshooting suggestions for Windows:\n";
            $error .= "1. For local development, enable 'Development Mode' to log emails instead of sending\n";
            $error .= "2. Configure an external SMTP server (Gmail, SendGrid, etc.) as the PHP mail() function is often unreliable on Windows\n";
            $error .= "3. Make sure your firewall isn't blocking outgoing SMTP connections\n";
        }
        
        header('Location: email_settings.php?error=' . urlencode($error));
        exit;
    }
} else {
    // No post data - redirect back to settings page
    header('Location: email_settings.php');
    exit;
} 