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

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    if (!empty($message)) {
        $notification_query = "
            INSERT INTO notifications (user_id, message, type) 
            SELECT user_id, ?, 'promotion' 
            FROM users 
            WHERE role = 'customer'";
        $notification_stmt = mysqli_prepare($conn, $notification_query);
        mysqli_stmt_bind_param($notification_stmt, "s", $message);
        if (mysqli_stmt_execute($notification_stmt)) {
            $success = "Notification sent successfully!";
        } else {
            $error = "Failed to send notification.";
        }
        mysqli_stmt_close($notification_stmt);

        // Log action
        $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, details) VALUES (?, ?, ?, ?)";
        $audit_stmt = mysqli_prepare($conn, $audit_query);
        $action = "send_notification";
        $table_name = "notifications";
        $details = "Sent promotion: " . $message;
        mysqli_stmt_bind_param($audit_stmt, "isss", $_SESSION['user_id'], $action, $table_name, $details);
        mysqli_stmt_execute($audit_stmt);
        mysqli_stmt_close($audit_stmt);
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
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
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
                <form method="POST" action="">
                    <textarea name="message" placeholder="Enter promotion message" class="form-control" required></textarea>
                    <button type="submit" name="send_notification" class="btn btn-primary">Send Notification</button>
                </form>
            </div>
        </div>

        <div class="admin-actions">
            <a href="audit_logs.php" class="btn btn-primary">
                <i class="fas fa-history"></i> View Audit Logs
            </a>
            <a href="manage_users.php" class="btn btn-primary">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="manage_vendors.php" class="btn btn-primary">
                <i class="fas fa-store"></i> Manage Vendors
            </a>
            <a href="manage_products.php" class="btn btn-primary">
                <i class="fas fa-box"></i> Manage Products
            </a>
            <a href="manage_orders.php" class="btn btn-primary">
                <i class="fas fa-shopping-cart"></i> Manage Orders
            </a>
            <a href="settings.php" class="btn btn-secondary">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>