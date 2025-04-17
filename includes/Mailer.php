<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Mailer class for sending emails
 */
class Mailer {
    /**
     * @var PHPMailer
     */
    private $mail;
    
    /**
     * @var array Error messages
     */
    private $errors = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        try {
            $this->mail->SMTPDebug = 0; // 0 = off, 1 = client, 2 = client & server
            $this->mail->isSMTP();
            $this->mail->Host = Env::get('SMTP_HOST', 'smtp.example.com');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = Env::get('EMAIL_USER', '');
            $this->mail->Password = Env::get('EMAIL_PASS', '');
            $this->mail->SMTPSecure = Env::get('SMTP_SECURE', 'tls');
            $this->mail->Port = Env::get('SMTP_PORT', 587);
            
            // Set default sender
            $this->mail->setFrom(
                $this->extractEmail(Env::get('EMAIL_FROM', '')),
                $this->extractName(Env::get('EMAIL_FROM', 'AgriMarket'))
            );
            
            // Set default reply-to
            if (Env::get('EMAIL_REPLY_TO')) {
                $this->mail->addReplyTo(
                    $this->extractEmail(Env::get('EMAIL_REPLY_TO', '')),
                    $this->extractName(Env::get('EMAIL_REPLY_TO', 'AgriMarket Support'))
                );
            }
            
            // Character set
            $this->mail->CharSet = 'UTF-8';
            
            // Use HTML
            $this->mail->isHTML(true);
        } catch (Exception $e) {
            $this->errors[] = "Mailer initialization failed: {$e->getMessage()}";
        }
    }
    
    /**
     * Send a notification email to a user
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Notification message
     * @param string $toName Recipient name (optional)
     * @return bool Success status
     */
    public function sendNotification($to, $subject, $message, $toName = '') {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($to, $toName);
            
            // Set subject
            $this->mail->Subject = $subject;
            
            // Build the email content
            $body = $this->getNotificationTemplate($message);
            
            // Set content
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            
            // Send the email
            return $this->mail->send();
        } catch (Exception $e) {
            $this->errors[] = "Email could not be sent. Error: {$e->getMessage()}";
            return false;
        }
    }
    
    /**
     * Send a notification to multiple users
     * 
     * @param array $recipients Array of arrays with 'email' and 'name' keys
     * @param string $subject Email subject
     * @param string $message Notification message
     * @return array Array of success/failure status for each recipient
     */
    public function sendBulkNotification($recipients, $subject, $message) {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $name = is_array($recipient) && isset($recipient['name']) ? $recipient['name'] : '';
            
            $success = $this->sendNotification($email, $subject, $message, $name);
            $results[$email] = $success;
        }
        
        return $results;
    }
    
    /**
     * Get the notification email template with the message
     * 
     * @param string $message Notification message
     * @return string HTML email template
     */
    private function getNotificationTemplate($message) {
        $siteUrl = Env::get('SITE_URL', 'http://localhost/AgriMarket');
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>AgriMarket Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #4CAF50;
                    padding: 20px;
                    text-align: center;
                }
                .header h1 {
                    color: white;
                    margin: 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                .message {
                    background-color: white;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #777;
                }
                .button {
                    display: inline-block;
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>AgriMarket</h1>
                </div>
                <div class="content">
                    <div class="message">
                        ' . nl2br(htmlspecialchars($message)) . '
                    </div>
                    <div style="text-align: center;">
                        <a href="' . $siteUrl . '" class="button">Visit AgriMarket</a>
                    </div>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' AgriMarket. All rights reserved.</p>
                    <p>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get error messages
     * 
     * @return array Error messages
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Extract email from a formatted email string (e.g., "Name <email@example.com>")
     * 
     * @param string $emailString Formatted email string
     * @return string Email address
     */
    private function extractEmail($emailString) {
        if (preg_match('/<([^>]+)>/', $emailString, $matches)) {
            return $matches[1];
        }
        
        return $emailString;
    }
    
    /**
     * Extract name from a formatted email string (e.g., "Name <email@example.com>")
     * 
     * @param string $emailString Formatted email string
     * @return string Name
     */
    private function extractName($emailString) {
        if (preg_match('/^([^<]+)</', $emailString, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Send an order confirmation email
     * 
     * @param int $order_id Order ID
     * @param string $email Customer email
     * @param string $name Customer name
     * @param array $orderData Order details
     * @param array $orderItems Order items
     * @return bool Success status
     */
    public function sendOrderConfirmation($order_id, $email, $name, $orderData, $orderItems) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($email, $name);
            
            // Set subject
            $this->mail->Subject = "AgriMarket Order Confirmation - Order #" . str_pad($order_id, 8, '0', STR_PAD_LEFT);
            
            // Build the email content
            $body = $this->getOrderConfirmationTemplate($order_id, $name, $orderData, $orderItems);
            
            // Set content
            $this->mail->Body = $body;
            $this->mail->AltBody = "Thank you for your order #" . str_pad($order_id, 8, '0', STR_PAD_LEFT) . 
                                 ". Your order has been received and is being processed.";
            
            // Send the email
            return $this->mail->send();
        } catch (Exception $e) {
            $this->errors[] = "Order confirmation email could not be sent. Error: {$e->getMessage()}";
            return false;
        }
    }
    
    /**
     * Get the order confirmation email template
     * 
     * @param int $order_id Order ID
     * @param string $name Customer name
     * @param array $orderData Order details including shipping address and payment info
     * @param array $orderItems Order items with product details
     * @return string HTML email template
     */
    private function getOrderConfirmationTemplate($order_id, $name, $orderData, $orderItems) {
        $siteUrl = Env::get('SITE_URL', 'http://localhost/AgriMarket');
        $formattedOrderId = str_pad($order_id, 8, '0', STR_PAD_LEFT);
        
        // Payment method may be stored in shipping_address JSON
        $shippingAddress = is_string($orderData['shipping_address']) ? 
                          json_decode($orderData['shipping_address'], true) : 
                          $orderData['shipping_address'];
        
        // Get payment method from either direct field or shipping address
        $payment_method = isset($orderData['payment_method']) && !empty($orderData['payment_method']) ? 
                        $orderData['payment_method'] : 
                        ($shippingAddress['payment_method'] ?? 'Not specified');
        
        // Format payment method for display
        $payment_method = ucwords(str_replace('_', ' ', $payment_method));
        
        // Build order items HTML
        $itemsHtml = '';
        $subtotal = 0;
        
        foreach ($orderItems as $item) {
            $price = $item['price'] * $item['quantity'];
            $subtotal += $price;
            
            $itemsHtml .= '
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['name']) . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">' . htmlspecialchars($item['quantity']) . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">$' . number_format($item['price'], 2) . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">$' . number_format($price, 2) . '</td>
            </tr>';
        }
        
        // Calculate totals
        $shipping = 5.00; // Fixed shipping
        $tax = $subtotal * 0.05; // 5% tax
        $total = $orderData['total'];
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>AgriMarket Order Confirmation</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #4CAF50;
                    padding: 20px;
                    text-align: center;
                }
                .header h1 {
                    color: white;
                    margin: 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                .order-info {
                    background-color: white;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .order-id {
                    font-size: 18px;
                    font-weight: bold;
                    color: #4CAF50;
                    margin-bottom: 10px;
                }
                .section-title {
                    border-bottom: 1px solid #eee;
                    padding-bottom: 10px;
                    margin-top: 20px;
                    margin-bottom: 15px;
                    font-size: 16px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th {
                    background-color: #f1f1f1;
                    text-align: left;
                    padding: 10px;
                }
                .summary {
                    margin-top: 15px;
                    border-top: 1px solid #eee;
                    padding-top: 15px;
                }
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                }
                .total-row {
                    font-weight: bold;
                    font-size: 16px;
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #eee;
                }
                .address-info {
                    margin-bottom: 5px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #777;
                }
                .button {
                    display: inline-block;
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Order Confirmation</h1>
                </div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($name) . ',</p>
                    <p>Thank you for your order! We\'ve received your order and are working on processing it right away.</p>
                    
                    <div class="order-info">
                        <div class="order-id">Order #' . $formattedOrderId . '</div>
                        <p>Placed on ' . date('F j, Y', strtotime($orderData['created_at'])) . '</p>
                        <p>Status: <strong>' . ucfirst($orderData['status']) . '</strong></p>
                    </div>
                    
                    <h3 class="section-title">Order Summary</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsHtml . '
                        </tbody>
                    </table>
                    
                    <div class="summary">
                        <div class="summary-row">
                            <div>Subtotal:</div>
                            <div>$' . number_format($subtotal, 2) . '</div>
                        </div>
                        <div class="summary-row">
                            <div>Shipping:</div>
                            <div>$' . number_format($shipping, 2) . '</div>
                        </div>
                        <div class="summary-row">
                            <div>Tax (5%):</div>
                            <div>$' . number_format($tax, 2) . '</div>
                        </div>
                        <div class="summary-row total-row">
                            <div>Total:</div>
                            <div>$' . number_format($total, 2) . '</div>
                        </div>
                    </div>
                    
                    <h3 class="section-title">Shipping Information</h3>
                    <div class="address-info">
                        <strong>' . htmlspecialchars($shippingAddress['full_name'] ?? 'N/A') . '</strong>
                    </div>
                    <div class="address-info">
                        ' . htmlspecialchars($shippingAddress['address'] ?? 'N/A') . '
                    </div>
                    <div class="address-info">
                        ' . htmlspecialchars(
                            ($shippingAddress['city'] ?? 'N/A') . ', ' . 
                            ($shippingAddress['state'] ?? '') . ' ' . 
                            ($shippingAddress['zip'] ?? '')
                        ) . '
                    </div>
                    <div class="address-info">
                        Phone: ' . htmlspecialchars($shippingAddress['phone'] ?? 'N/A') . '
                    </div>
                    
                    <h3 class="section-title">Payment Information</h3>
                    <div class="address-info">
                        Payment Method: ' . htmlspecialchars($payment_method) . '
                    </div>
                    <div class="address-info">
                        Payment Status: ' . htmlspecialchars(ucfirst($orderData['payment_status'] ?? 'Pending')) . '
                    </div>
                    
                    <div style="text-align: center; margin-top: 25px;">
                        <a href="' . $siteUrl . '/order_details.php?order_id=' . $order_id . '" class="button">View Order Details</a>
                    </div>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' AgriMarket. All rights reserved.</p>
                    <p>If you have any questions, please contact our customer support at support@agrimarket.com</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Send an order status update email
     * 
     * @param int $order_id Order ID
     * @param string $email Customer email
     * @param string $name Customer name
     * @param string $newStatus New order status
     * @param array $orderData Order details
     * @return bool Success status
     */
    public function sendOrderStatusUpdate($order_id, $email, $name, $newStatus, $orderData) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($email, $name);
            
            // Set subject
            $this->mail->Subject = "AgriMarket Order #" . str_pad($order_id, 8, '0', STR_PAD_LEFT) . " Status Update";
            
            // Build the email content
            $body = $this->getOrderStatusUpdateTemplate($order_id, $name, $newStatus, $orderData);
            
            // Set content
            $this->mail->Body = $body;
            $this->mail->AltBody = "Your order #" . str_pad($order_id, 8, '0', STR_PAD_LEFT) . 
                                 " status has been updated to " . ucfirst($newStatus) . ".";
            
            // Send the email
            return $this->mail->send();
        } catch (Exception $e) {
            $this->errors[] = "Order status update email could not be sent. Error: {$e->getMessage()}";
            return false;
        }
    }
    
    /**
     * Get the order status update email template
     * 
     * @param int $order_id Order ID
     * @param string $name Customer name
     * @param string $newStatus New order status
     * @param array $orderData Order details
     * @return string HTML email template
     */
    private function getOrderStatusUpdateTemplate($order_id, $name, $newStatus, $orderData) {
        $siteUrl = Env::get('SITE_URL', 'http://localhost/AgriMarket');
        $formattedOrderId = str_pad($order_id, 8, '0', STR_PAD_LEFT);
        
        // Get status message based on new status
        $statusMessage = '';
        $statusColor = '#4CAF50'; // Default green
        
        switch (strtolower($newStatus)) {
            case 'processing':
                $statusMessage = "We're now processing your order and will prepare it for shipping soon.";
                $statusColor = '#3498db'; // Blue
                break;
            case 'shipped':
                $statusMessage = "Your order has been shipped and is on its way to you!";
                $statusColor = '#2980b9'; // Darker blue
                break;
            case 'delivered':
                $statusMessage = "Your order has been delivered. We hope you enjoy your purchase!";
                $statusColor = '#4CAF50'; // Green
                break;
            case 'cancelled':
                $statusMessage = "Your order has been cancelled. If you did not request this cancellation, please contact our customer support.";
                $statusColor = '#e74c3c'; // Red
                break;
            default:
                $statusMessage = "Your order status has been updated.";
                break;
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>AgriMarket Order Status Update</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: ' . $statusColor . ';
                    padding: 20px;
                    text-align: center;
                }
                .header h1 {
                    color: white;
                    margin: 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                .order-info {
                    background-color: white;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .order-id {
                    font-size: 18px;
                    font-weight: bold;
                    color: ' . $statusColor . ';
                    margin-bottom: 10px;
                }
                .status-badge {
                    display: inline-block;
                    background-color: ' . $statusColor . ';
                    color: white;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-weight: bold;
                    margin-top: 5px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #777;
                }
                .button {
                    display: inline-block;
                    background-color: ' . $statusColor . ';
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Order Status Update</h1>
                </div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($name) . ',</p>
                    <p>The status of your order has been updated.</p>
                    
                    <div class="order-info">
                        <div class="order-id">Order #' . $formattedOrderId . '</div>
                        <p>New Status: <span class="status-badge">' . ucfirst($newStatus) . '</span></p>
                        <p>' . $statusMessage . '</p>
                    </div>
                    
                    <p>Order placed on: ' . date('F j, Y', strtotime($orderData['created_at'])) . '</p>
                    
                    <div style="text-align: center; margin-top: 25px;">
                        <a href="' . $siteUrl . '/order_details.php?order_id=' . $order_id . '" class="button">View Order Details</a>
                    </div>
                    
                    <p style="margin-top: 20px;">Thank you for shopping with AgriMarket!</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' AgriMarket. All rights reserved.</p>
                    <p>If you have any questions, please contact our customer support at support@agrimarket.com</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
?> 