<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$page_title = "Customer Support";
$staff_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Handle message actions
if (isset($_POST['mark_read']) && !empty($_POST['message_id'])) {
    $message_id = (int)$_POST['message_id'];
    
    $query = "UPDATE customer_messages SET status = 'read', staff_id = ?, updated_at = NOW() WHERE message_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $staff_id, $message_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Message marked as read.";
        } else {
            $error_message = "Error updating message status: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_POST['reply']) && !empty($_POST['message_id']) && !empty($_POST['reply_text'])) {
    $message_id = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update original message
        $update_query = "UPDATE customer_messages SET status = 'answered', staff_id = ?, updated_at = NOW() WHERE message_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ii", $staff_id, $message_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Get customer info from original message
        $customer_query = "SELECT user_id, subject FROM customer_messages WHERE message_id = ?";
        $customer_stmt = mysqli_prepare($conn, $customer_query);
        mysqli_stmt_bind_param($customer_stmt, "i", $message_id);
        mysqli_stmt_execute($customer_stmt);
        $customer_result = mysqli_stmt_get_result($customer_stmt);
        $customer_info = mysqli_fetch_assoc($customer_result);
        mysqli_stmt_close($customer_stmt);
        
        // Create reply
        $reply_query = "INSERT INTO customer_messages (user_id, staff_id, subject, message, is_reply, parent_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 1, ?, 'sent', NOW())";
        $reply_stmt = mysqli_prepare($conn, $reply_query);
        $subject = "RE: " . $customer_info['subject'];
        mysqli_stmt_bind_param($reply_stmt, "iissi", $customer_info['user_id'], $staff_id, $subject, $reply_text, $message_id);
        mysqli_stmt_execute($reply_stmt);
        mysqli_stmt_close($reply_stmt);
        
        // Create notification for the customer
        $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                              VALUES (?, 'You have received a response to your support inquiry.', 'support', NOW())";
        $notification_stmt = mysqli_prepare($conn, $notification_query);
        mysqli_stmt_bind_param($notification_stmt, "i", $customer_info['user_id']);
        mysqli_stmt_execute($notification_stmt);
        mysqli_stmt_close($notification_stmt);
        
        // Log the action
        $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at) 
                     VALUES (?, 'replied to message', 'customer_messages', ?, 'Staff replied to customer inquiry', NOW())";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "ii", $staff_id, $message_id);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        mysqli_commit($conn);
        $success_message = "Reply sent successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "Error sending reply: " . $e->getMessage();
    }
}

// Get messages with pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter conditions
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query
$query = "
    SELECT cm.*, u.name as customer_name, u.email as customer_email 
    FROM customer_messages cm
    LEFT JOIN users u ON cm.user_id = u.user_id
    WHERE cm.is_reply = 0
";

// Add filters
switch ($filter) {
    case 'unread':
        $query .= " AND cm.status = 'unread'";
        break;
    case 'answered':
        $query .= " AND cm.status = 'answered'";
        break;
    case 'assigned':
        $query .= " AND cm.staff_id = $staff_id AND cm.status != 'answered'";
        break;
}

// Add search
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (cm.subject LIKE '%$search%' OR cm.message LIKE '%$search%' OR u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

// Count total messages (for pagination) - Fixed approach
try {
    // First check if customer_messages table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'customer_messages'");
    if (mysqli_num_rows($table_check) == 0) {
        throw new Exception("The customer_messages table does not exist. Please run database setup first.");
    }
    
    // Build a simpler count query without using subquery
    $count_base_query = "
        SELECT COUNT(*) as total 
        FROM customer_messages cm
        LEFT JOIN users u ON cm.user_id = u.user_id
        WHERE cm.is_reply = 0
    ";
    
    // Add filters
    switch ($filter) {
        case 'unread':
            $count_base_query .= " AND cm.status = 'unread'";
            break;
        case 'answered':
            $count_base_query .= " AND cm.status = 'answered'";
            break;
        case 'assigned':
            $count_base_query .= " AND cm.staff_id = $staff_id AND cm.status != 'answered'";
            break;
    }
    
    // Add search
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $count_base_query .= " AND (cm.subject LIKE '%$search%' OR cm.message LIKE '%$search%' OR u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
    }
    
    $count_result = mysqli_query($conn, $count_base_query);
    
    if ($count_result === false) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_messages = $count_row['total'];
    $total_pages = ceil($total_messages / $limit);
    
    // Add sorting and pagination to the main query
    $query .= " ORDER BY cm.created_at DESC LIMIT $offset, $limit";
    
    // Execute query
    $result = mysqli_query($conn, $query);
    
    if ($result === false) {
        throw new Exception("Error executing query: " . mysqli_error($conn));
    }
    
    // Get message counts for filter badges
    $unread_count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM customer_messages WHERE is_reply = 0 AND status = 'unread'");
    if ($unread_count_result) {
        $unread_count = mysqli_fetch_assoc($unread_count_result)['count'];
    } else {
        $unread_count = 0;
    }
    
    $assigned_count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM customer_messages WHERE is_reply = 0 AND staff_id = $staff_id AND status != 'answered'");
    if ($assigned_count_result) {
        $assigned_count = mysqli_fetch_assoc($assigned_count_result)['count'];
    } else {
        $assigned_count = 0;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $total_messages = 0;
    $total_pages = 1;
    $result = null;
    $unread_count = 0;
    $assigned_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .filter-bar {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-tab {
            padding: 8px 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            position: relative;
        }
        
        .filter-tab:hover {
            background-color: #e9ecef;
        }
        
        .filter-tab.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .filter-tab .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            font-size: 12px;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
            margin-left: auto;
        }
        
        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .messages-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .message-item {
            padding: 15px;
            border-bottom: 1px solid #f2f2f2;
        }
        
        .message-item:last-child {
            border-bottom: none;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .message-subject {
            font-weight: 500;
            font-size: 16px;
            color: #333;
            margin: 0;
        }
        
        .message-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .status-unread {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-read {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-answered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .message-meta {
            display: flex;
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .meta-item {
            margin-right: 20px;
            display: flex;
            align-items: center;
        }
        
        .meta-item i {
            margin-right: 5px;
            width: 16px;
        }
        
        .message-content {
            color: #212529;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #388E3C;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .reply-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f2f2f2;
            display: none;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-top: 1px solid #f2f2f2;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .pagination-links {
            display: flex;
            gap: 5px;
        }
        
        .pagination-link {
            display: inline-block;
            padding: 5px 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .pagination-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination-link.disabled {
            color: #6c757d;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .search-form {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Customer Support</h1>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    All Messages
                </a>
                <a href="?filter=unread<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                    Unread
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=assigned<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filter == 'assigned' ? 'active' : ''; ?>">
                    Assigned to Me
                    <?php if ($assigned_count > 0): ?>
                        <span class="badge"><?php echo $assigned_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=answered<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filter == 'answered' ? 'active' : ''; ?>">
                    Answered
                </a>
            </div>
            
            <form action="" method="GET" class="search-form">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>
        
        <div class="messages-container">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($message = mysqli_fetch_assoc($result)): ?>
                    <div class="message-item">
                        <div class="message-header">
                            <h3 class="message-subject">
                                <?php echo htmlspecialchars($message['subject']); ?>
                                <span class="message-status status-<?php echo $message['status']; ?>">
                                    <?php echo ucfirst($message['status']); ?>
                                </span>
                            </h3>
                            <div class="message-meta">
                                <span class="message-date">
                                    <?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="message-info">
                            <div class="customer-details">
                                <span class="customer-name">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($message['customer_name']); ?>
                                </span>
                                <span class="customer-email">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($message['customer_email']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        
                        <div class="message-actions">
                            <a href="view_message.php?id=<?php echo $message['message_id']; ?>" class="action-button">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if ($message['status'] === 'unread'): ?>
                                <form method="post" class="action-form" style="display: inline;">
                                    <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                    <button type="submit" name="mark_read" class="action-button">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($message['status'] !== 'answered'): ?>
                                <button type="button" class="action-button reply-button" data-id="<?php echo $message['message_id']; ?>">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reply-form" id="reply-form-<?php echo $message['message_id']; ?>" style="display: none;">
                            <form method="post">
                                <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                <div class="form-group">
                                    <label for="reply-<?php echo $message['message_id']; ?>">Your Reply</label>
                                    <textarea name="reply_text" id="reply-<?php echo $message['message_id']; ?>" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="reply" class="btn btn-primary">Send Reply</button>
                                    <button type="button" class="btn btn-secondary cancel-reply" data-id="<?php echo $message['message_id']; ?>">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (isset($error_message) && !empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                        <p>Please make sure that the database is properly set up with the customer_messages table.</p>
                        <div class="setup-instructions">
                            <h4>Setup Instructions:</h4>
                            <p>To fix this issue, please execute the database schema by running your database setup script. This will create the necessary tables.</p>
                            <pre>
-- Create customer_messages table
CREATE TABLE `customer_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','answered') NOT NULL DEFAULT 'unread',
  `is_reply` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_message_user` (`user_id`),
  KEY `idx_message_staff` (`staff_id`),
  KEY `idx_message_status` (`status`),
  CONSTRAINT `fk_message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_message_parent` FOREIGN KEY (`parent_id`) REFERENCES `customer_messages` (`message_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                            </pre>
                        </div>
                    <?php else: ?>
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No Messages Found</h3>
                        <p>There are no messages that match your current filters.</p>
                        <?php if (!empty($search) || $filter !== 'all'): ?>
                            <div class="empty-actions">
                                <a href="customer_support.php" class="btn btn-outline">Clear Filters</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($result && mysqli_num_rows($result) > 0 && $total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="pagination-link <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle reply buttons
            const replyButtons = document.querySelectorAll('.reply-button');
            replyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.getAttribute('data-id');
                    const replyForm = document.getElementById('reply-form-' + messageId);
                    replyForm.style.display = 'block';
                    this.style.display = 'none';
                });
            });
            
            // Handle cancel buttons
            const cancelButtons = document.querySelectorAll('.cancel-reply');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.getAttribute('data-id');
                    const replyForm = document.getElementById('reply-form-' + messageId);
                    replyForm.style.display = 'none';
                    
                    // Show the reply button again
                    const replyButton = document.querySelector(`.reply-button[data-id="${messageId}"]`);
                    if (replyButton) {
                        replyButton.style.display = 'inline-flex';
                    }
                });
            });
            
            // Alert timeout
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.5s';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html> 