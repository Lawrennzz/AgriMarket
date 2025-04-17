<?php
session_start(); // Start the session at the very beginning

include 'config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// User count
$users_count_query = "SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL";
$users_count_stmt = mysqli_prepare($conn, $users_count_query);
mysqli_stmt_execute($users_count_stmt);
$users_count = mysqli_fetch_assoc(mysqli_stmt_get_result($users_count_stmt))['count'];
mysqli_stmt_close($users_count_stmt);

// Order count (last 30 days)
$orders_count_query = "SELECT COUNT(*) as count FROM orders WHERE created_at >= NOW() - INTERVAL 30 DAY";
$orders_count_stmt = mysqli_prepare($conn, $orders_count_query);
mysqli_stmt_execute($orders_count_stmt);
$orders_count = mysqli_fetch_assoc(mysqli_stmt_get_result($orders_count_stmt))['count'];
mysqli_stmt_close($orders_count_stmt);

// Recent activity (audit logs)
$activity_query = "
    SELECT a.*, u.name 
    FROM audit_logs a 
    JOIN users u ON a.user_id = u.user_id 
    ORDER BY a.created_at DESC LIMIT 5";
$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);

// Analytics (last 30 days)
$analytics_query = "
    SELECT type, SUM(count) as total 
    FROM analytics 
    WHERE recorded_at >= NOW() - INTERVAL 30 DAY 
    GROUP BY type";
$analytics_stmt = mysqli_prepare($conn, $analytics_query);
mysqli_stmt_execute($analytics_stmt);
$analytics_result = mysqli_stmt_get_result($analytics_stmt);
$data = ['search' => 0, 'visit' => 0, 'order' => 0];
while ($row = mysqli_fetch_assoc($analytics_result)) {
    $data[$row['type']] = (int)$row['total'];
}
mysqli_stmt_close($analytics_stmt);

// Include Mailer class
require_once 'includes/Mailer.php';

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $message = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');
    $subject = htmlspecialchars($_POST['subject'] ?? 'New Notification from AgriMarket', ENT_QUOTES, 'UTF-8');
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == '1';
    
    if (!empty($message)) {
        $success_count = 0;
        $error_count = 0;
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert notifications in the database
            $notification_query = "
                INSERT INTO notifications (user_id, message, type) 
                SELECT user_id, ?, 'promotion' 
                FROM users 
                WHERE role = 'customer'";
            $notification_stmt = mysqli_prepare($conn, $notification_query);
            mysqli_stmt_bind_param($notification_stmt, "s", $message);
            
            if (mysqli_stmt_execute($notification_stmt)) {
                $affected_rows = mysqli_stmt_affected_rows($notification_stmt);
                $success_count += $affected_rows;
                $success = "Notification sent successfully to $affected_rows user(s)!";
            } else {
                throw new Exception("Failed to send notification to database: " . mysqli_error($conn));
            }
            mysqli_stmt_close($notification_stmt);
            
            // Send emails if requested
            if ($send_email) {
                // Get customer emails
                $customers_query = "SELECT user_id, name, email FROM users WHERE role = 'customer'";
                $customers_stmt = mysqli_prepare($conn, $customers_query);
                mysqli_stmt_execute($customers_stmt);
                $customers_result = mysqli_stmt_get_result($customers_stmt);
                
                $mailer = new Mailer();
                $email_recipients = [];
                
                while ($customer = mysqli_fetch_assoc($customers_result)) {
                    $email_recipients[] = [
                        'email' => $customer['email'],
                        'name' => $customer['name']
                    ];
                }
                
                mysqli_stmt_close($customers_stmt);
                
                // Send emails in bulk
                if (!empty($email_recipients)) {
                    $email_results = $mailer->sendBulkNotification($email_recipients, $subject, $message);
                    
                    $email_success_count = count(array_filter($email_results));
                    $email_error_count = count($email_results) - $email_success_count;
                    
                    if ($email_error_count > 0) {
                        $success .= " However, $email_error_count email(s) could not be sent.";
                        
                        // Log errors
                        foreach ($mailer->getErrors() as $error_msg) {
                            error_log("Email error: $error_msg");
                        }
                    } else {
                        $success .= " Email notifications sent successfully to $email_success_count recipient(s).";
                    }
                }
            }
            
            // Log action
            $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, details) VALUES (?, ?, ?, ?)";
            $audit_stmt = mysqli_prepare($conn, $audit_query);
            $action = "send_notification";
            $table_name = "notifications";
            $details = "Sent promotion: " . $message . ($send_email ? " (with email)" : "");
            mysqli_stmt_bind_param($audit_stmt, "isss", $_SESSION['user_id'], $action, $table_name, $details);
            mysqli_stmt_execute($audit_stmt);
            mysqli_stmt_close($audit_stmt);
            
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    } else {
        $error = "Message cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - AgriMarket</title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .welcome-message {
            font-size: 1.5rem;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .activity-card {
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 10px;
            color: #555;
        }

        .notification-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 10px;
            resize: vertical;
        }

        .notification-form .btn {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .notification-form .btn:hover {
            background-color: #45a049;
        }

        .notification-form .form-group {
            margin-bottom: 15px;
        }

        .notification-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .notification-form input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 1rem;
        }

        .notification-form .checkbox-container {
            display: block;
            position: relative;
            padding-left: 35px;
            margin-bottom: 15px;
            cursor: pointer;
            font-size: 1rem;
            user-select: none;
        }

        .notification-form .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .notification-form .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 20px;
            width: 20px;
            background-color: #eee;
            border-radius: 4px;
        }

        .notification-form .checkbox-container:hover input ~ .checkmark {
            background-color: #ccc;
        }

        .notification-form .checkbox-container input:checked ~ .checkmark {
            background-color: #4CAF50;
        }

        .notification-form .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        .notification-form .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }

        .notification-form .checkbox-container .checkmark:after {
            left: 7px;
            top: 3px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .quick-actions {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #333;
            transition: background 0.3s;
        }

        .quick-action-btn:hover {
            background: #f0f0f0;
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #4CAF50;
        }

        .admin-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .admin-actions .btn {
            flex: 1;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s, transform 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .admin-actions .btn:hover {
            transform: translateY(-3px);
        }

        .admin-actions .btn i {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        /* Content area styles to work with sidebar */
        .content {
            margin-left: 250px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions, .admin-actions {
                flex-direction: column;
            }

            .quick-action-btn, .admin-actions .btn {
                width: 100%;
            }
            
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <div class="dashboard-header">
            <h1 class="welcome-message">Admin Dashboard</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $users_count; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-value"><?php echo $orders_count; ?></div>
                <div class="stat-label">Orders (Last 30 Days)</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Activity</h2>
                    <a href="audit_logs.php" class="btn btn-primary">View All</a>
                </div>
                <?php if (mysqli_num_rows($activity_result) > 0): ?>
                    <?php while ($activity = mysqli_fetch_assoc($activity_result)): ?>
                        <div class="activity-card">
                            <p>
                                <?php echo htmlspecialchars($activity['name'], ENT_QUOTES, 'UTF-8'); ?> 
                                performed <?php echo htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8'); ?> 
                                on <?php echo htmlspecialchars($activity['table_name'], ENT_QUOTES, 'UTF-8'); ?> 
                                (ID: <?php echo (int)$activity['record_id']; ?>) 
                                at <?php echo htmlspecialchars($activity['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <?php if ($activity['details']): ?>
                                <p>Details: <?php echo htmlspecialchars($activity['details'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No recent activity found.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Send Promotion Notification</h2>
                </div>
                <form method="POST" action="" class="notification-form">
                    <div class="form-group">
                        <label for="subject">Email Subject:</label>
                        <input type="text" name="subject" id="subject" class="form-control" 
                               value="New Notification from AgriMarket" 
                               placeholder="Email subject line">
                    </div>
                    <div class="form-group">
                        <label for="message">Notification Message:</label>
                        <textarea name="message" id="message" placeholder="Enter promotion message" 
                                  class="form-control" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="send_email" value="1" checked>
                            Also send as email to customers
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <button type="submit" name="send_notification" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>