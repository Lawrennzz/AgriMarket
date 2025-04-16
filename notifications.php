<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $mark_query = "UPDATE notifications SET read_status = 1 WHERE notification_id = ? AND user_id = ?";
    $mark_stmt = mysqli_prepare($conn, $mark_query);
    if ($mark_stmt) {
        mysqli_stmt_bind_param($mark_stmt, "ii", $notification_id, $user_id);
        mysqli_stmt_execute($mark_stmt);
        mysqli_stmt_close($mark_stmt);
    } else {
        error_log("Mark notification error: " . mysqli_error($conn));
    }
    
    // Redirect to remove the query parameter
    header("Location: notifications.php");
    exit();
}

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $mark_all_query = "UPDATE notifications SET read_status = 1 WHERE user_id = ?";
    $mark_all_stmt = mysqli_prepare($conn, $mark_all_query);
    if ($mark_all_stmt) {
        mysqli_stmt_bind_param($mark_all_stmt, "i", $user_id);
        mysqli_stmt_execute($mark_all_stmt);
        mysqli_stmt_close($mark_all_stmt);
    } else {
        error_log("Mark all notifications error: " . mysqli_error($conn));
    }
    
    // Redirect to remove the query parameter
    header("Location: notifications.php");
    exit();
}

// Initialize variables
$notifications_result = false;
$unread_count = 0;

// Check if read_status column exists in notifications table
$check_column = "SHOW COLUMNS FROM notifications LIKE 'read_status'";
$column_result = mysqli_query($conn, $check_column);

// Add read_status column if it doesn't exist
if (!$column_result || mysqli_num_rows($column_result) === 0) {
    $add_column = "ALTER TABLE notifications ADD COLUMN read_status TINYINT(1) DEFAULT 0";
    mysqli_query($conn, $add_column);
}

// Fetch notifications
$notifications_query = "SELECT * FROM notifications 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC";
$notifications_stmt = mysqli_prepare($conn, $notifications_query);
if ($notifications_stmt) {
    mysqli_stmt_bind_param($notifications_stmt, "i", $user_id);
    mysqli_stmt_execute($notifications_stmt);
    $notifications_result = mysqli_stmt_get_result($notifications_stmt);
    mysqli_stmt_close($notifications_stmt);
} else {
    error_log("Fetch notifications error: " . mysqli_error($conn));
}

// Count unread notifications
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0";
$unread_stmt = mysqli_prepare($conn, $unread_query);
if ($unread_stmt) {
    mysqli_stmt_bind_param($unread_stmt, "i", $user_id);
    mysqli_stmt_execute($unread_stmt);
    $unread_result = mysqli_stmt_get_result($unread_stmt);
    $unread_row = mysqli_fetch_assoc($unread_result);
    $unread_count = $unread_row ? $unread_row['count'] : 0;
    mysqli_stmt_close($unread_stmt);
} else {
    error_log("Count unread notifications error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .notifications-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            color: var(--dark-gray);
        }
        
        .notifications-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--warning-color);
            color: white;
            border-radius: 50%;
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .notification-card {
            padding: 1.25rem;
            border-radius: var(--border-radius);
            border-left: 4px solid transparent;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .notification-card.unread {
            border-left-color: var(--warning-color);
            background-color: rgba(255, 152, 0, 0.05);
        }
        
        .notification-card.order {
            border-left-color: var(--primary-color);
        }
        
        .notification-card.stock {
            border-left-color: var(--danger-color);
        }
        
        .notification-card.promotion {
            border-left-color: var(--secondary-color);
        }
        
        .notification-icon {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .notification-icon.order {
            color: var(--primary-color);
        }
        
        .notification-icon.stock {
            color: var(--danger-color);
        }
        
        .notification-icon.promotion {
            color: var(--secondary-color);
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-message {
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--medium-gray);
        }
        
        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .notification-actions a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .notification-actions a:hover {
            color: var(--primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .notifications-container {
                padding: 1rem;
                margin: 1rem;
            }
            
            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="notifications-container">
        <div class="notifications-header">
            <div class="notifications-title">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="notifications-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <?php if (mysqli_num_rows($notifications_result) > 0): ?>
                <div class="notifications-actions">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-secondary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (mysqli_num_rows($notifications_result) > 0): ?>
            <div class="notifications-list">
                <?php 
                $current_date = null;
                while ($notification = mysqli_fetch_assoc($notifications_result)): 
                    // Get notification date
                    $notification_date = date('Y-m-d', strtotime($notification['created_at']));
                    $is_today = $notification_date === date('Y-m-d');
                    $is_yesterday = $notification_date === date('Y-m-d', strtotime('-1 day'));
                    
                    // Display date header if date changes
                    if ($notification_date !== $current_date):
                        $current_date = $notification_date;
                        if ($is_today):
                            echo '<h3 class="date-header">Today</h3>';
                        elseif ($is_yesterday):
                            echo '<h3 class="date-header">Yesterday</h3>';
                        else:
                            echo '<h3 class="date-header">' . date('F j, Y', strtotime($notification['created_at'])) . '</h3>';
                        endif;
                    endif;
                    
                    // Set notification icon based on type
                    $icon_class = 'fa-bell';
                    if ($notification['type'] === 'order') {
                        $icon_class = 'fa-shopping-bag';
                    } elseif ($notification['type'] === 'stock') {
                        $icon_class = 'fa-exclamation-triangle';
                    } elseif ($notification['type'] === 'promotion') {
                        $icon_class = 'fa-tag';
                    }
                    
                    // Set card class based on read status and type
                    $read_status = isset($notification['read_status']) ? $notification['read_status'] : 0;
                    $card_class = $read_status == 0 ? 'unread' : '';
                    $card_class .= ' ' . $notification['type'];
                ?>
                    <div class="notification-card <?php echo $card_class; ?>">
                        <div class="notification-icon <?php echo $notification['type']; ?>">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-meta">
                                <div class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php 
                                    $created_at = strtotime($notification['created_at']);
                                    $now = time();
                                    $diff = $now - $created_at;
                                    
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . ' min ago';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' hr ago';
                                    } elseif ($is_yesterday) {
                                        echo 'Yesterday at ' . date('g:i A', $created_at);
                                    } else {
                                        echo date('M j', $created_at) . ' at ' . date('g:i A', $created_at);
                                    }
                                    ?>
                                </div>
                                <?php if ($read_status == 0): ?>
                                    <div class="notification-actions">
                                        <a href="?mark_read=<?php echo $notification['notification_id']; ?>">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>You don't have any notifications at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>