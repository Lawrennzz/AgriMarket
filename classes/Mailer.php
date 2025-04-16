<?php
/**
 * Mailer Class for AgriMarket
 * Handles email sending functionality using PHPMailer
 */

class Mailer {
    // Database connection
    private $conn;
    
    // Email settings
    private $fromEmail;
    private $fromName;
    private $replyToEmail;
    private $replyToName;
    
    // SMTP settings
    private $useSmtp = false;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpSecure; // tls, ssl, or ''
    
    // Debug and development mode
    private $debugMode = false;
    private $devMode = false;
    
    // Error message
    private $lastError = '';
    
    /**
     * Constructor
     * 
     * @param bool $debugMode Enable debug mode for verbose output
     * @param bool $devMode Enable development mode (log emails instead of sending)
     */
    public function __construct($debugMode = false, $devMode = false) {
        // Load database connection
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/functions.php';
        $this->conn = getConnection();
        
        // Set modes
        $this->debugMode = $debugMode;
        $this->devMode = $devMode;
        
        // Load settings
        $this->loadSettings();
    }
    
    /**
     * Load email settings from database
     */
    private function loadSettings() {
        // Default settings
        $defaults = [
            'email_from' => 'noreply@agrimarket.com',
            'email_from_name' => 'AgriMarket',
            'email_reply_to' => '',
            'email_reply_to_name' => '',
            'email_use_smtp' => '0',
            'email_smtp_host' => '',
            'email_smtp_port' => '587',
            'email_smtp_username' => '',
            'email_smtp_password' => '',
            'email_smtp_secure' => 'tls'
        ];
        
        // Query to get settings
        $query = "SELECT name, value FROM settings WHERE name LIKE 'email_%'";
        $result = mysqli_query($this->conn, $query);
        
        // Initialize settings with defaults
        $settings = $defaults;
        
        // Update with values from database
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $settings[$row['name']] = $row['value'];
            }
        }
        
        // Set class properties
        $this->fromEmail = $settings['email_from'];
        $this->fromName = $settings['email_from_name'];
        $this->replyToEmail = !empty($settings['email_reply_to']) ? $settings['email_reply_to'] : $settings['email_from'];
        $this->replyToName = !empty($settings['email_reply_to_name']) ? $settings['email_reply_to_name'] : $settings['email_from_name'];
        
        // SMTP settings
        $this->useSmtp = ($settings['email_use_smtp'] === '1');
        $this->smtpHost = $settings['email_smtp_host'];
        $this->smtpPort = (int)$settings['email_smtp_port'];
        $this->smtpUsername = $settings['email_smtp_username'];
        $this->smtpPassword = $settings['email_smtp_password'];
        $this->smtpSecure = $settings['email_smtp_secure'];
        
        // Log settings if in debug mode
        if ($this->debugMode) {
            $logSettings = $settings;
            $logSettings['email_smtp_password'] = !empty($logSettings['email_smtp_password']) ? '******' : 'not set';
            error_log('Email settings loaded: ' . print_r($logSettings, true));
        }
    }
    
    /**
     * Get the last error message
     * 
     * @return string The last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Send an email using PHPMailer
     * 
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $htmlBody HTML body of the email
     * @param string $textBody Plain text body (optional)
     * @param array $attachments Array of file paths to attach (optional)
     * @param array $cc Array of CC addresses (optional)
     * @param array $bcc Array of BCC addresses (optional)
     * @return bool Success or failure
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = '', $attachments = [], $cc = [], $bcc = []) {
        // Reset last error
        $this->lastError = '';
        
        // Validate required parameters
        if (empty($to)) {
            $this->lastError = 'Recipient email is required';
            return false;
        }
        
        if (empty($subject)) {
            $this->lastError = 'Email subject is required';
            return false;
        }
        
        if (empty($htmlBody)) {
            $this->lastError = 'Email body is required';
            return false;
        }
        
        // For development mode, just log the email
        if ($this->devMode) {
            return $this->logEmail($to, $subject, $htmlBody, $textBody, $attachments, $cc, $bcc);
        }
        
        // Check if PHPMailer is available
        if (!$this->isPHPMailerAvailable()) {
            // Fall back to PHP mail() function
            return $this->sendWithPHPMail($to, $subject, $htmlBody);
        }
        
        try {
            // Create PHPMailer instance
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new Exception("PHPMailer class not found");
            }
            
            // Use string for class name to avoid undefined class errors in static analysis
            $phpmailerClass = 'PHPMailer\PHPMailer\PHPMailer';
            $mail = new $phpmailerClass(true);
            
            // Debug settings
            if ($this->debugMode) {
                $mail->SMTPDebug = 2; // 2 = client and server messages
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer [$level]: $str");
                };
            }
            
            // Use SMTP if configured
            if ($this->useSmtp && !empty($this->smtpHost)) {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->Port = $this->smtpPort;
                
                // Use authentication if username is provided
                if (!empty($this->smtpUsername)) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $this->smtpUsername;
                    $mail->Password = $this->smtpPassword;
                }
                
                // Set encryption type - use string constants instead of class constants
                if ($this->smtpSecure === 'tls') {
                    $mail->SMTPSecure = 'tls'; // Instead of PHPMailer::ENCRYPTION_STARTTLS
                } else if ($this->smtpSecure === 'ssl') {
                    $mail->SMTPSecure = 'ssl'; // Instead of PHPMailer::ENCRYPTION_SMTPS
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                
                // Set timeout
                $mail->Timeout = 30;
            }
            
            // Set sender
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addReplyTo($this->replyToEmail, $this->replyToName);
            
            // Add recipients
            if (is_array($to)) {
                foreach ($to as $email) {
                    $mail->addAddress($email);
                }
            } else {
                $mail->addAddress($to);
            }
            
            // Add CC recipients
            if (!empty($cc) && is_array($cc)) {
                foreach ($cc as $email) {
                    $mail->addCC($email);
                }
            }
            
            // Add BCC recipients
            if (!empty($bcc) && is_array($bcc)) {
                foreach ($bcc as $email) {
                    $mail->addBCC($email);
                }
            }
            
            // Set content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            
            // Set plain text body if provided
            if (!empty($textBody)) {
                $mail->AltBody = $textBody;
            } else {
                // Generate plain text from HTML if not provided
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
            }
            
            // Add attachments
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    } else if ($this->debugMode) {
                        error_log("Attachment not found: $attachment");
                    }
                }
            }
            
            // Send the email
            if (!$mail->send()) {
                $this->lastError = "Mailer Error: " . $mail->ErrorInfo;
                if ($this->debugMode) {
                    error_log($this->lastError);
                }
                return false;
            }
            
            if ($this->debugMode) {
                error_log("Email sent successfully to: " . (is_array($to) ? implode(', ', $to) : $to));
            }
            
            return true;
        } catch (Exception $e) {
            $this->lastError = "Exception: " . $e->getMessage();
            if ($this->debugMode) {
                error_log($this->lastError);
                error_log($e->getTraceAsString());
            }
            return false;
        }
    }
    
    /**
     * Log email to file instead of sending (for development mode)
     */
    private function logEmail($to, $subject, $htmlBody, $textBody, $attachments, $cc, $bcc) {
        // Create logs directory if it doesn't exist
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        // Generate a unique log file name
        $fileName = $logsDir . '/email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
        
        // Format the log content
        $logContent = "--- EMAIL LOG ---\n";
        $logContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "To: " . (is_array($to) ? implode(', ', $to) : $to) . "\n";
        
        if (!empty($cc)) {
            $logContent .= "CC: " . (is_array($cc) ? implode(', ', $cc) : $cc) . "\n";
        }
        
        if (!empty($bcc)) {
            $logContent .= "BCC: " . (is_array($bcc) ? implode(', ', $bcc) : $bcc) . "\n";
        }
        
        $logContent .= "From: {$this->fromName} <{$this->fromEmail}>\n";
        $logContent .= "Reply-To: {$this->replyToName} <{$this->replyToEmail}>\n";
        $logContent .= "Subject: $subject\n";
        
        if (!empty($attachments)) {
            $logContent .= "Attachments: " . implode(', ', $attachments) . "\n";
        }
        
        $logContent .= "\n--- HTML CONTENT ---\n";
        $logContent .= $htmlBody;
        
        if (!empty($textBody)) {
            $logContent .= "\n\n--- TEXT CONTENT ---\n";
            $logContent .= $textBody;
        }
        
        // Write to file
        if (file_put_contents($fileName, $logContent)) {
            if ($this->debugMode) {
                error_log("Email logged to file: $fileName");
            }
            return true;
        } else {
            $this->lastError = "Failed to write email log to file";
            if ($this->debugMode) {
                error_log($this->lastError);
            }
            return false;
        }
    }
    
    /**
     * Fallback method to send email using PHP's mail() function
     */
    private function sendWithPHPMail($to, $subject, $htmlBody) {
        // Convert to string if array
        if (is_array($to)) {
            $to = implode(', ', $to);
        }
        
        // Set headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->replyToName} <{$this->replyToEmail}>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Attempt to send
        if ($this->debugMode) {
            error_log("Attempting to send mail with PHP mail() function");
            error_log("To: $to | Subject: $subject");
        }
        
        $result = @mail($to, $subject, $htmlBody, $headers);
        
        if (!$result) {
            $this->lastError = "Failed to send email using PHP mail() function";
            if ($this->debugMode) {
                error_log($this->lastError);
            }
            return false;
        }
        
        if ($this->debugMode) {
            error_log("Email sent successfully via PHP mail() function");
        }
        
        return true;
    }
    
    /**
     * Check if PHPMailer is available
     */
    private function isPHPMailerAvailable() {
        // Try autoloader first
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return true;
            }
        }
        
        // Try manual inclusion
        if (file_exists(__DIR__ . '/../PHPMailer/src/PHPMailer.php')) {
            require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
            require_once __DIR__ . '/../PHPMailer/src/Exception.php';
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return true;
            }
        }
        
        if ($this->debugMode) {
            error_log("PHPMailer not found. Will use PHP mail() function instead.");
        }
        
        return false;
    }
    
    /**
     * Send an order confirmation email
     * 
     * @param string $to Recipient email
     * @param array $orderData Order data
     * @return bool Success or failure
     */
    public function sendOrderConfirmation($to, $orderData) {
        // Validate order data
        if (empty($orderData['order_id']) || empty($orderData['items'])) {
            $this->lastError = "Missing required order data";
            return false;
        }
        
        // Set defaults for optional fields
        $orderData['shipping_address'] = isset($orderData['shipping_address']) ? $orderData['shipping_address'] : '';
        $orderData['payment_method'] = isset($orderData['payment_method']) ? $orderData['payment_method'] : 'Not specified';
        $orderData['total'] = isset($orderData['total']) ? $orderData['total'] : 0;
        $orderData['subtotal'] = isset($orderData['subtotal']) ? $orderData['subtotal'] : 0;
        $orderData['shipping'] = isset($orderData['shipping']) ? $orderData['shipping'] : 0;
        $orderData['tax'] = isset($orderData['tax']) ? $orderData['tax'] : 0;
        $orderData['customer_name'] = isset($orderData['customer_name']) ? $orderData['customer_name'] : 'Customer';
        $orderData['order_date'] = isset($orderData['order_date']) ? $orderData['order_date'] : date('Y-m-d H:i:s');
        
        // Create subject
        $subject = "Order Confirmation - Order #{$orderData['order_id']}";
        
        // Create email body
        $message = $this->getOrderConfirmationTemplate($orderData);
        
        // Send the email
        return $this->sendEmail($to, $subject, $message);
    }
    
    /**
     * Generate order confirmation email template
     */
    private function getOrderConfirmationTemplate($orderData) {
        // Format currency
        $formatter = function($amount) {
            return '$' . number_format($amount, 2);
        };
        
        $html = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="color: #4CAF50;">Order Confirmation</h1>
                <p>Thank you for your order!</p>
            </div>
            
            <div style="background-color: #fff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h2 style="margin-top: 0; color: #2E7D32;">Order Summary</h2>
                <p><strong>Order Number:</strong> #' . $orderData['order_id'] . '</p>
                <p><strong>Order Date:</strong> ' . $orderData['order_date'] . '</p>
                <p><strong>Payment Method:</strong> ' . $orderData['payment_method'] . '</p>
            </div>
            
            <div style="background-color: #fff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h2 style="margin-top: 0; color: #2E7D32;">Order Details</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Product</th>
                            <th style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">Quantity</th>
                            <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Price</th>
                            <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Total</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        // Add order items
        foreach ($orderData['items'] as $item) {
            $name = htmlspecialchars($item['name']);
            $quantity = $item['quantity'];
            $price = $formatter($item['price']);
            $total = $formatter($item['price'] * $item['quantity']);
            
            $html .= "
                <tr>
                    <td style=\"padding: 10px; text-align: left; border-bottom: 1px solid #ddd;\">$name</td>
                    <td style=\"padding: 10px; text-align: center; border-bottom: 1px solid #ddd;\">$quantity</td>
                    <td style=\"padding: 10px; text-align: right; border-bottom: 1px solid #ddd;\">$price</td>
                    <td style=\"padding: 10px; text-align: right; border-bottom: 1px solid #ddd;\">$total</td>
                </tr>";
        }
        
        // Add totals
        $html .= '
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right; border-top: 1px solid #ddd;"><strong>Subtotal:</strong></td>
                            <td style="padding: 10px; text-align: right; border-top: 1px solid #ddd;">' . $formatter($orderData['subtotal']) . '</td>
                        </tr>';
        
        if ($orderData['shipping'] > 0) {
            $html .= '
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right;"><strong>Shipping:</strong></td>
                            <td style="padding: 10px; text-align: right;">' . $formatter($orderData['shipping']) . '</td>
                        </tr>';
        }
        
        if ($orderData['tax'] > 0) {
            $html .= '
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right;"><strong>Tax:</strong></td>
                            <td style="padding: 10px; text-align: right;">' . $formatter($orderData['tax']) . '</td>
                        </tr>';
        }
        
        $html .= '
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right;"><strong>Total:</strong></td>
                            <td style="padding: 10px; text-align: right; font-weight: bold; color: #4CAF50;">' . $formatter($orderData['total']) . '</td>
                        </tr>
                    </tfoot>
                </table>
            </div>';
        
        // Add shipping address if provided
        if (!empty($orderData['shipping_address'])) {
            $html .= '
            <div style="background-color: #fff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h2 style="margin-top: 0; color: #2E7D32;">Shipping Address</h2>
                <p>' . nl2br(htmlspecialchars($orderData['shipping_address'])) . '</p>
            </div>';
        }
        
        // Footer
        $html .= '
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px;">
                <p>Thank you for shopping with AgriMarket! If you have any questions about your order, please contact our customer service.</p>
                <p>&copy; ' . date('Y') . ' AgriMarket. All rights reserved.</p>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Send a promotional email to a user
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param array $promotions Array of promotion details
     * @return bool Success or failure
     */
    public function sendPromotionEmail($to, $subject, $promotions) {
        // Validate inputs
        if (empty($to)) {
            $this->lastError = "Recipient email is required";
            return false;
        }
        
        if (empty($subject)) {
            $subject = "Special Offers from AgriMarket";
        }
        
        // Make sure promotions is an array
        if (!is_array($promotions)) {
            $promotions = [];
        }
        
        // Build HTML email content
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">';
        $message .= '<h1 style="color: #4CAF50; text-align: center;">Special Offers Just For You!</h1>';
        $message .= '<p>Check out these amazing deals on agricultural products:</p>';
        
        // Add promotions
        if (!empty($promotions)) {
            foreach ($promotions as $promotion) {
                if (!is_array($promotion)) {
                    continue;
                }
                
                // Set defaults for required fields
                $title = isset($promotion['title']) ? htmlspecialchars($promotion['title']) : 'Special Offer';
                $description = isset($promotion['description']) ? htmlspecialchars($promotion['description']) : 'Check out this special offer!';
                $discount = isset($promotion['discount']) ? htmlspecialchars($promotion['discount']) : '';
                $expiry = isset($promotion['expiry']) ? htmlspecialchars($promotion['expiry']) : '';
                $link = isset($promotion['link']) ? htmlspecialchars($promotion['link']) : '';
                
                $message .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
                $message .= "<h2 style=\"color: #2E7D32; margin-top: 0;\">$title</h2>";
                $message .= "<p>$description</p>";
                
                if (!empty($discount)) {
                    $message .= "<p style=\"font-weight: bold; color: #E53935;\">Discount: $discount</p>";
                }
                
                if (!empty($expiry)) {
                    $message .= "<p><strong>Valid until:</strong> $expiry</p>";
                }
                
                if (!empty($link)) {
                    $message .= "<p><a href=\"$link\" style=\"background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;\">Shop Now</a></p>";
                }
                
                $message .= '</div>';
            }
        } else {
            // No promotions provided, show default message
            $message .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
            $message .= '<h2 style="color: #2E7D32; margin-top: 0;">Stay Tuned for Special Offers</h2>';
            $message .= '<p>We\'re preparing some amazing deals for you. Check back soon for special offers on our products!</p>';
            $message .= '</div>';
        }
        
        // Add footer with unsubscribe option
        $message .= '<p style="margin-top: 20px; font-size: 0.9em; color: #666; text-align: center;">';
        $message .= 'If you no longer wish to receive promotional emails, you can <a href="#" style="color: #4CAF50;">unsubscribe here</a>.';
        $message .= '</p>';
        $message .= '</div>';
        
        // Send the email
        return $this->sendEmail($to, $subject, $message);
    }
} 