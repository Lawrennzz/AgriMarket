<?php
// Test email sending functionality
require_once 'includes/Mailer.php';

// Check if running in CLI or web
$is_cli = (php_sapi_name() === 'cli');

// Simple function to output messages in CLI or web mode
function output($message) {
    global $is_cli;
    if ($is_cli) {
        echo $message . PHP_EOL;
    } else {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '<br>';
    }
}

output("Email Test Script");
output("----------------");

// Create mailer instance
$mailer = new Mailer();

// Test recipient
$to = $is_cli && isset($argv[1]) ? $argv[1] : (isset($_GET['email']) ? $_GET['email'] : '');

if (empty($to)) {
    output("Usage: ");
    if ($is_cli) {
        output("  php test_email.php recipient@example.com");
    } else {
        output("  test_email.php?email=recipient@example.com");
    }
    output("Please provide a test email address.");
    exit;
}

// Send a test email
$subject = "Test Email from AgriMarket";
$message = "This is a test email sent from the AgriMarket notification system.\n\n" .
           "If you received this email, the email sending functionality is working correctly.";

output("Sending email to: $to");
$result = $mailer->sendNotification($to, $subject, $message);

if ($result) {
    output("Email sent successfully!");
} else {
    output("Failed to send email.");
    output("Errors:");
    foreach ($mailer->getErrors() as $error) {
        output(" - $error");
    }
}

// If in web mode, provide a backlink
if (!$is_cli) {
    echo '<p><a href="admin_dashboard.php">Return to Dashboard</a></p>';
}
?> 