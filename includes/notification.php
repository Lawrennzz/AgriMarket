<?php
require_once 'config.php';

/**
 * Notification handling class
 * Manages database notifications only
 */
class Notification {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a new notification in the database
     */
    public function create($user_id, $message, $type = 'info', $reference_id = null, $reference_type = null) {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, message, type, reference_id, reference_type, created_at, is_read) 
                                    VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        
        $stmt->bind_param("issss", $user_id, $message, $type, $reference_id, $reference_type);
        $result = $stmt->execute();
        
        if ($result) {
            return $stmt->insert_id;
        } else {
            return false;
        }
    }
    
    /**
     * Mark a notification as read
     */
    public function markAsRead($notification_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);
        return $stmt->execute();
    }
    
    /**
     * Get all unread notifications for a user
     */
    public function getUnreadNotifications($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $notifications = [];
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Get all notifications for a user (with pagination)
     */
    public function getAllNotifications($user_id, $offset = 0, $limit = 20) {
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?, ?");
        $stmt->bind_param("iii", $user_id, $offset, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $notifications = [];
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Create an order notification
     */
    public function notifyOrderCreated($order_id) {
        // Get order details
        $stmt = $this->conn->prepare("SELECT o.*, u.email, u.name, u.user_id 
                                     FROM orders o
                                     JOIN users u ON o.user_id = u.user_id
                                     WHERE o.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if (!$order) {
            return false;
        }
        
        // Create database notification
        $message = "Your order #{$order_id} has been placed successfully.";
        $db_result = $this->create($order['user_id'], $message, 'order', $order_id, 'order');
        
        // Also send email notification if Mailer class exists
        try {
            if (class_exists('Mailer')) {
                // Get order items
                $items_query = "SELECT oi.*, p.name FROM order_items oi 
                               JOIN products p ON oi.product_id = p.product_id
                               WHERE oi.order_id = ?";
                $items_stmt = $this->conn->prepare($items_query);
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                
                $order_items = [];
                while ($item = $items_result->fetch_assoc()) {
                    $order_items[] = $item;
                }
                
                // Send the confirmation email
                $mailer = new Mailer();
                $mailer->sendOrderConfirmation(
                    $order_id,
                    $order['email'],
                    $order['name'],
                    $order,
                    $order_items
                );
            }
        } catch (Exception $e) {
            // Log the error but continue with the notification
            error_log("Failed to send order confirmation email: " . $e->getMessage());
        }
        
        return $db_result;
    }
    
    /**
     * Create an order status update notification
     */
    public function notifyOrderStatusUpdate($order_id, $new_status) {
        // Get order details
        $stmt = $this->conn->prepare("SELECT o.*, u.email, u.name, u.user_id 
                                     FROM orders o
                                     JOIN users u ON o.user_id = u.user_id
                                     WHERE o.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if (!$order) {
            return false;
        }
        
        // Create status-specific message
        $message = "Your order #{$order_id} status has been updated to " . ucfirst($new_status) . ".";
        
        // Create database notification
        $db_result = $this->create($order['user_id'], $message, 'order_update', $order_id, 'order');
        
        // Also send email notification if Mailer class exists
        try {
            if (class_exists('Mailer')) {
                // Send the status update email
                $mailer = new Mailer();
                $mailer->sendOrderStatusUpdate(
                    $order_id,
                    $order['email'],
                    $order['name'],
                    $new_status,
                    $order
                );
            }
        } catch (Exception $e) {
            // Log the error but continue with the notification
            error_log("Failed to send order status update email: " . $e->getMessage());
        }
        
        return $db_result;
    }
    
    /**
     * Send promotional notification to a specific user
     */
    public function sendPromotion($user_id, $subject, $message) {
        // Get user details
        $stmt = $this->conn->prepare("SELECT email, name FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return false;
        }
        
        // Create database notification
        return $this->create($user_id, $subject, 'promotion');
    }
    
    /**
     * Send promotional notification to multiple users
     */
    public function sendBulkPromotion($user_ids, $subject, $message) {
        if (empty($user_ids)) {
            return false;
        }
        
        $success_count = 0;
        
        foreach ($user_ids as $user_id) {
            if ($this->sendPromotion($user_id, $subject, $message)) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    
    /**
     * Send promotional notification to all users
     */
    public function sendPromotionToAllUsers($subject, $message) {
        // Get all active users
        $stmt = $this->conn->prepare("SELECT user_id, email, name FROM users WHERE status = 'active'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $success_count = 0;
        
        while ($user = $result->fetch_assoc()) {
            // Create database notification
            if ($this->create($user['user_id'], $subject, 'promotion')) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
}
?> 