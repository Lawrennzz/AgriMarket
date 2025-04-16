<?php
/**
 * Mailer Class for AgriMarket
 * 
 * Handles email sending functionality
 */

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Define a function to check if PHPMailer is available
function is_phpmailer_available() {
    static $available = null;
    
    if ($available === null) {
        // Try vendor autoload first (composer installation)
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        // Try manual inclusion if vendor autoload not found
        elseif (file_exists(__DIR__ . '/../PHPMailer/src/PHPMailer.php')) {
            require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
            require_once __DIR__ . '/../PHPMailer/src/Exception.php';
        }
        
        // Check if the PHPMailer class exists
        $available = class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    return $available;
}

/**
 * Mailer class for sending emails
 */
class Mailer {
    // SMTP settings
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption; // ssl or tls
    
    // Sender info
    private $fromEmail;
    private $fromName;
    
    // Database connection
    private $conn;
    
    // Use SMTP flag
    private $useSmtp = false;
    
    // Debug mode
    private $debugMode = false;
    
    // Development mode (logs emails instead of sending)
    private $devMode = false;
    
    // Last error message
    private $lastError = '';
    
    /**
     * Constructor
     * 
     * @param bool $debugMode Enable debug mode
     * @param bool $devMode Enable development mode (logs emails instead of sending)
     */
    public function __construct($debugMode = false, $devMode = false) {
        $this->conn = getConnection();
        $this->debugMode = $debugMode;
        $this->devMode = $devMode;
        $this->loadEmailSettings();
    }
    
    /**
     * Get the last error message
     * 
     * @return string Last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Set debug mode
     * 
     * @param bool $debug Enable debug mode
     */
    public function setDebugMode($debug) {
        $this->debugMode = (bool)$debug;
    }
    
    /**
     * Set development mode
     * 
     * @param bool $devMode Enable development mode
     */
    public function setDevMode($devMode) {
        $this->devMode = (bool)$devMode;
    }
    
    /**
     * Load email settings from database
     */
    private function loadEmailSettings() {
        // Check which column name is used in the settings table
        $query = "SHOW COLUMNS FROM settings LIKE 'setting_key'";
        $result = mysqli_query($this->conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            // Table has setting_key column
            $query = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%'";
            $key_column = 'setting_key';
        } else {
            // Table probably has name column instead
            $query = "SELECT name, value FROM settings WHERE name LIKE 'email_%'";
            $key_column = 'name';
        }
        
        $result = mysqli_query($this->conn, $query);
        
        // Default values
        $this->fromEmail = 'info@agrimarket.com';
        $this->fromName = 'AgriMarket';
        $this->useSmtp = false;
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $key = str_replace('email_', '', $row[$key_column]);
                $value = $row[$key_column === 'setting_key' ? 'setting_value' : 'value'];
                
                switch ($key) {
                    case 'smtp_host':
                        $this->host = $value;
                        break;
                    case 'smtp_port':
                        $this->port = $value;
                        break;
                    case 'smtp_username':
                        $this->username = $value;
                        break;
                    case 'smtp_password':
                        $this->password = $value;
                        break;
                    case 'smtp_encryption':
                    case 'smtp_secure':
                        $this->encryption = $value;
                        break;
                    case 'from_email':
                    case 'from':
                        $this->fromEmail = $value;
                        break;
                    case 'from_name':
                        $this->fromName = $value;
                        break;
                    case 'use_smtp':
                        $this->useSmtp = ($value === '1' || $value === 'true');
                        break;
                }
            }
        }
        
        if ($this->debugMode) {
            error_log("Email settings loaded: " . json_encode([
                'fromEmail' => $this->fromEmail,
                'fromName' => $this->fromName,
                'useSmtp' => $this->useSmtp,
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption
            ]));
        }
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     * @param string $plainTextMessage Plain text version (optional)
     * @param array $attachments Array of attachments (optional)
     * @return bool Success or failure
     */
    public function sendEmail($to, $subject, $message, $plainTextMessage = '', $attachments = []) {
        // Reset last error
        $this->lastError = '';
        
        // Validate email format
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Invalid email format: $to";
            error_log($this->lastError);
            return false;
        }
        
        // If in development mode, just log the email
        if ($this->devMode) {
            $logFile = __DIR__ . '/../logs/email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            
            // Create logs directory if it doesn't exist
            if (!file_exists(__DIR__ . '/../logs')) {
                mkdir(__DIR__ . '/../logs', 0755, true);
            }
            
            // Log email details
            $emailLog = "To: $to\n";
            $emailLog .= "Subject: $subject\n";
            $emailLog .= "From: {$this->fromName} <{$this->fromEmail}>\n";
            $emailLog .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
            $emailLog .= "HTML Content:\n$message\n\n";
            
            if (!empty($plainTextMessage)) {
                $emailLog .= "Plain Text Content:\n$plainTextMessage\n\n";
            }
            
            if (!empty($attachments) && is_array($attachments)) {
                $emailLog .= "Attachments:\n";
                foreach ($attachments as $attachment) {
                    $emailLog .= "- $attachment\n";
                }
            }
            
            // Write to log file
            file_put_contents($logFile, $emailLog);
            error_log("Email logged to file: $logFile (DEV MODE)");
            
            return true; // Return success in dev mode
        }
        
        // If SMTP is enabled and PHPMailer is available
        if ($this->useSmtp && is_phpmailer_available()) {
            try {
                // Use the fully qualified class name to avoid namespace issues
                $phpmailerClass = 'PHPMailer\PHPMailer\PHPMailer';
                $mail = new $phpmailerClass(true);
                
                // Enable verbose debug output if in debug mode
                if ($this->debugMode) {
                    $mail->SMTPDebug = 2; // 2 = client and server
                    $mail->Debugoutput = function($str, $level) {
                        error_log("PHPMailer Debug [$level]: $str");
                    };
                }
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = $this->host;
                
                // Most SMTP servers require authentication
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
                $mail->Port = intval($this->port); // Make sure port is an integer
                
                // Security settings
                if ($this->encryption === 'ssl') {
                    $mail->SMTPSecure = 'ssl';
                } elseif ($this->encryption === 'tls') {
                    $mail->SMTPSecure = 'tls';
                } else {
                    $mail->SMTPSecure = ''; // No encryption
                    $mail->SMTPAutoTLS = false; // Disable auto TLS
                }
                
                // Additional SMTP options for troubleshooting
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // For Gmail, you might need to enable less secure apps or use app passwords
                if (strpos($this->host, 'gmail.com') !== false) {
                    error_log("Gmail detected. Make sure you've enabled 'Less secure app access' or created an App Password.");
                }
                
                // Set a longer timeout for SMTP connections
                $mail->Timeout = 60; // 60 seconds
                
                // Recipients
                $mail->setFrom($this->fromEmail, $this->fromName);
                $mail->addAddress($to);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message;
                
                if (!empty($plainTextMessage)) {
                    $mail->AltBody = $plainTextMessage;
                } else {
                    $mail->AltBody = strip_tags($message);
                }
                
                // Attachments - make sure it's an array
                if (!empty($attachments) && is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (is_string($attachment) && file_exists($attachment)) {
                            $mail->addAttachment($attachment);
                        }
                    }
                }
                
                // Try to send the email
                if (!$mail->send()) {
                    $this->lastError = "SMTP Error: " . $mail->ErrorInfo;
                    error_log($this->lastError);
                    return false;
                }
                
                if ($this->debugMode) {
                    error_log("Email sent successfully to $to using SMTP");
                }
                
                return true;
            } catch (\Exception $e) {
                $this->lastError = "SMTP Error: " . $e->getMessage();
                error_log($this->lastError);
                
                if ($this->debugMode) {
                    error_log("Falling back to PHP mail function");
                }
                // Fall back to PHP mail if PHPMailer fails
            }
        } else if ($this->useSmtp) {
            $this->lastError = "SMTP is enabled but PHPMailer is not available";
            error_log($this->lastError);
            
            if ($this->debugMode) {
                error_log("PHPMailer available: " . (is_phpmailer_available() ? 'Yes' : 'No'));
                error_log("Falling back to PHP mail function");
            }
        }
        
        // Use PHP's mail function as fallback
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>" . "\r\n";
        
        if ($this->debugMode) {
            error_log("Sending mail using PHP mail() function");
            error_log("To: $to");
            error_log("Subject: $subject");
            error_log("Headers: " . str_replace("\r\n", " | ", $headers));
        }
        
        $success = @mail($to, $subject, $message, $headers);
        
        if (!$success) {
            $this->lastError = "PHP mail() function failed to send email to $to. Check your mail server configuration.";
            error_log($this->lastError);
            
            // Additional troubleshooting information
            if ($this->debugMode) {
                if (!function_exists('mail')) {
                    error_log("PHP mail function is not available");
                }
                error_log("PHP mail configuration: " . ini_get('sendmail_path'));
                error_log("PHP SMTP: " . ini_get('SMTP'));
                error_log("PHP smtp_port: " . ini_get('smtp_port'));
            }
        } else if ($this->debugMode) {
            error_log("Email sent successfully to $to using PHP mail()");
        }
        
        return $success;
    }
    
    /**
     * Send a promotional email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param array $promotions Array of promotion details
     * @return bool Success or failure
     */
    public function sendPromotionEmail($to, $subject, $promotions) {
        // Ensure promotions is an array
        if (!is_array($promotions)) {
            error_log("Promotions must be an array, " . gettype($promotions) . " given");
            $promotions = []; // Set to empty array to avoid issues
        }
        
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">';
        $message .= '<h2 style="color: #4CAF50; margin-bottom: 20px;">Special Offers Just For You!</h2>';
        $message .= '<p>Check out these amazing deals on agricultural products:</p>';
        
        if (!empty($promotions)) {
            foreach ($promotions as $promo) {
                if (!is_array($promo)) {
                    continue; // Skip non-array items
                }
                
                $message .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
                
                // Ensure title and description exist
                $title = isset($promo['title']) ? htmlspecialchars($promo['title']) : 'Special Offer';
                $description = isset($promo['description']) ? htmlspecialchars($promo['description']) : 'Check out this special offer!';
                
                $message .= '<h3 style="color: #2E7D32; margin-top: 0;">' . $title . '</h3>';
                $message .= '<p>' . $description . '</p>';
                
                if (isset($promo['discount'])) {
                    $message .= '<p style="font-weight: bold; color: #E53935;">Discount: ' . htmlspecialchars($promo['discount']) . '</p>';
                }
                
                if (isset($promo['expiry'])) {
                    $message .= '<p><strong>Valid until:</strong> ' . htmlspecialchars($promo['expiry']) . '</p>';
                }
                
                if (isset($promo['link'])) {
                    $message .= '<p><a href="' . htmlspecialchars($promo['link']) . '" style="background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">Shop Now</a></p>';
                }
                
                $message .= '</div>';
            }
        } else {
            $message .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
            $message .= '<h3 style="color: #2E7D32; margin-top: 0;">No Promotions Available</h3>';
            $message .= '<p>Check back later for special offers!</p>';
            $message .= '</div>';
        }
        
        $message .= '<p style="margin-top: 20px; font-size: 0.9em; color: #666;">If you no longer wish to receive promotional emails, you can <a href="#" style="color: #4CAF50;">unsubscribe here</a>.</p>';
        $message .= '</div>';
        
        return $this->sendEmail($to, $subject, $message);
    }
    
    /**
     * Send an order confirmation email
     * 
     * @param string $to Recipient email
     * @param array $orderDetails Order details
     * @return bool Success or failure
     */
    public function sendOrderConfirmation($to, $orderDetails) {
        // Make sure we have order details as an array
        if (!is_array($orderDetails)) {
            error_log("Order details must be an array");
            return false;
        }
        
        // Ensure all required keys exist
        $required_keys = ['order_id', 'order_date', 'customer_name', 'payment_method', 'items', 'subtotal', 'shipping', 'total'];
        foreach ($required_keys as $key) {
            if (!isset($orderDetails[$key])) {
                error_log("Missing required key in order details: $key");
                return false;
            }
        }
        
        // Ensure items is an array
        if (!is_array($orderDetails['items'])) {
            error_log("Order items must be an array, " . gettype($orderDetails['items']) . " given");
            $orderDetails['items'] = []; // Set to empty array to avoid issues
        }
        
        $subject = 'Order Confirmation - AgriMarket Order #' . $orderDetails['order_id'];
        
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">';
        $message .= '<h2 style="color: #4CAF50; margin-bottom: 20px;">Thank You for Your Order!</h2>';
        $message .= '<p>Dear ' . htmlspecialchars($orderDetails['customer_name']) . ',</p>';
        $message .= '<p>Your order has been received and is now being processed. Here are your order details:</p>';
        
        $message .= '<div style="background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
        $message .= '<h3 style="margin-top: 0; color: #2E7D32;">Order Summary</h3>';
        $message .= '<p><strong>Order Number:</strong> #' . $orderDetails['order_id'] . '</p>';
        $message .= '<p><strong>Order Date:</strong> ' . $orderDetails['order_date'] . '</p>';
        $message .= '<p><strong>Payment Method:</strong> ' . htmlspecialchars($orderDetails['payment_method']) . '</p>';
        
        // Order items table
        $message .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $message .= '<thead>';
        $message .= '<tr style="background-color: #f2f2f2;">';
        $message .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Product</th>';
        $message .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Qty</th>';
        $message .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Price</th>';
        $message .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Total</th>';
        $message .= '</tr>';
        $message .= '</thead>';
        $message .= '<tbody>';
        
        // Only try to loop through items if it's an array
        if (is_array($orderDetails['items']) && !empty($orderDetails['items'])) {
            foreach ($orderDetails['items'] as $item) {
                if (!is_array($item)) {
                    continue; // Skip non-array items
                }
                
                // Make sure all required item keys exist
                if (!isset($item['name']) || !isset($item['quantity']) || !isset($item['price'])) {
                    continue; // Skip items with missing data
                }
                
                $message .= '<tr>';
                $message .= '<td style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">' . htmlspecialchars($item['name']) . '</td>';
                $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . $item['quantity'] . '</td>';
                $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . formatCurrency($item['price']) . '</td>';
                $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . formatCurrency($item['price'] * $item['quantity']) . '</td>';
                $message .= '</tr>';
            }
        } else {
            $message .= '<tr><td colspan="4" style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">No items in order</td></tr>';
        }
        
        $message .= '</tbody>';
        $message .= '<tfoot>';
        $message .= '<tr>';
        $message .= '<td colspan="3" style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;"><strong>Subtotal:</strong></td>';
        $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . formatCurrency($orderDetails['subtotal']) . '</td>';
        $message .= '</tr>';
        
        $message .= '<tr>';
        $message .= '<td colspan="3" style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;"><strong>Shipping:</strong></td>';
        $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . formatCurrency($orderDetails['shipping']) . '</td>';
        $message .= '</tr>';
        
        if (isset($orderDetails['tax']) && $orderDetails['tax'] > 0) {
            $message .= '<tr>';
            $message .= '<td colspan="3" style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;"><strong>Tax:</strong></td>';
            $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">' . formatCurrency($orderDetails['tax']) . '</td>';
            $message .= '</tr>';
        }
        
        $message .= '<tr>';
        $message .= '<td colspan="3" style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;"><strong>Total:</strong></td>';
        $message .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd; font-weight: bold; color: #4CAF50;">' . formatCurrency($orderDetails['total']) . '</td>';
        $message .= '</tr>';
        $message .= '</tfoot>';
        $message .= '</table>';
        
        // Shipping address
        if (isset($orderDetails['shipping_address'])) {
            $message .= '<div style="margin-top: 20px;">';
            $message .= '<h3 style="color: #2E7D32;">Shipping Address</h3>';
            $message .= '<p>' . nl2br(htmlspecialchars($orderDetails['shipping_address'])) . '</p>';
            $message .= '</div>';
        }
        
        $message .= '</div>';
        
        $message .= '<p>You can view your order status and history by logging into your account.</p>';
        $message .= '<p><a href="https://agrimarket.com/my-account/orders" style="background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">View Your Orders</a></p>';
        
        $message .= '<p style="margin-top: 20px;">If you have any questions about your order, please contact our customer service team.</p>';
        $message .= '<p>Thank you for shopping with AgriMarket!</p>';
        
        $message .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.9em; color: #666;">';
        $message .= '<p>© ' . date('Y') . ' AgriMarket. All rights reserved.</p>';
        $message .= '</div>';
        
        $message .= '</div>';
        
        return $this->sendEmail($to, $subject, $message);
    }
} 