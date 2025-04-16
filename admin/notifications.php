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

$conn = getConnection();
// Initialize Mailer with error handling
try {
    $mailer = new Mailer();
} catch (Exception $e) {
    error_log("Failed to initialize Mailer: " . $e->getMessage());
    $error_message = "Email functionality is currently unavailable. Please try again later.";
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error_message = "Subject and message are required fields.";
    } else {
        try {
            // Determine recipients based on type
            $recipients = [];
            
            if ($recipient_type === 'all') {
                $query = "SELECT user_id, email, name FROM users WHERE is_active = 1";
            } elseif ($recipient_type === 'customers') {
                $query = "SELECT user_id, email, name FROM users WHERE role = 'customer' AND is_active = 1";
            } elseif ($recipient_type === 'vendors') {
                $query = "SELECT u.user_id, u.email, u.name FROM users u 
                          JOIN vendors v ON u.user_id = v.user_id 
                          WHERE u.is_active = 1";
            }
            
            $result = mysqli_query($conn, $query);
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $recipients[] = [
                        'user_id' => $row['user_id'],
                        'email' => $row['email'],
                        'name' => $row['name']
                    ];
                }
            }
            
            // If no recipients found in database, add test recipients for development
            if (empty($recipients)) {
                // Add test recipients for development purposes
                if ($recipient_type === 'all' || $recipient_type === 'customers') {
                    $recipients[] = [
                        'user_id' => 1,
                        'email' => 'test.customer@example.com',
                        'name' => 'Test Customer'
                    ];
                }
                
                if ($recipient_type === 'all' || $recipient_type === 'vendors') {
                    $recipients[] = [
                        'user_id' => 2,
                        'email' => 'test.vendor@example.com',
                        'name' => 'Test Vendor'
                    ];
                }
                
                // Log that we're using test recipients
                error_log('No recipients found in database, using test recipients for development.');
            }
            
            // Send emails
            if (!empty($recipients)) {
                if (isset($mailer) && method_exists($mailer, 'sendPromotionEmail')) {
                    $email_sent = false;
                    
                    // Send to each recipient individually
                    foreach ($recipients as $recipient) {
                        if ($mailer->sendPromotionEmail($recipient['email'], $recipient['name'], $subject, $message)) {
                            $email_sent = true;
                            
                            // Insert notification into database
                            try {
                                $notification_message = $subject . ": " . $message; // Combine subject and message
                                $insert_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                                               VALUES (?, ?, 'promotion', NOW())";
                                $stmt = mysqli_prepare($conn, $insert_query);
                                
                                if ($stmt === false) {
                                    // Log SQL error for debugging
                                    error_log("SQL Prepare Error: " . mysqli_error($conn));
                                } else {
                                    mysqli_stmt_bind_param($stmt, "is", $recipient['user_id'], $notification_message);
                                    mysqli_stmt_execute($stmt);
                                    mysqli_stmt_close($stmt);
                                }
                            } catch (Exception $e) {
                                // Log exception but continue with other recipients
                                error_log("Failed to insert notification: " . $e->getMessage());
                            }
                        }
                    }
                    
                    if ($email_sent) {
                        $success_message = "Notifications sent successfully to " . count($recipients) . " recipients.";
                    } else {
                        $error_message = "Failed to send emails. Please try again.";
                    }
                } else {
                    $error_message = "Email functionality is not available. Please check the Mailer class.";
                }
            } else {
                $error_message = "No recipients found for the selected category.";
            }
        } catch (Exception $e) {
            $error_message = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get recent notifications
$notifications_query = "SELECT n.*, u.name as user_name, u.email 
                        FROM notifications n 
                        JOIN users u ON n.user_id = u.user_id 
                        WHERE n.type = 'promotion' 
                        ORDER BY n.created_at DESC 
                        LIMIT 50";

// Add error handling for the notifications query
$notifications_result = mysqli_query($conn, $notifications_query);
if (!$notifications_result) {
    error_log("Failed to retrieve notifications: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - AgriMarket</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .notification-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], 
        select, 
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        textarea {
            min-height: 150px;
        }
        
        .btn-submit {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .btn-submit:hover {
            background-color: #45a049;
        }
        
        .notification-history {
            margin-top: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>
    <?php include_once 'admin_header.php'; ?>
    
    <div class="container">
        <h1>Admin Notifications</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="notification-form">
            <h2>Send Promotional Email</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="recipient_type">Recipient</label>
                    <select name="recipient_type" id="recipient_type" required>
                        <option value="all">All Users</option>
                        <option value="customers">Customers Only</option>
                        <option value="vendors">Vendors Only</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" name="subject" id="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea name="message" id="message" required></textarea>
                </div>
                
                <button type="submit" name="send_notification" class="btn-submit">Send Notification</button>
            </form>
        </div>
        
        <div class="notification-history">
            <h2>Recent Notifications</h2>
            
            <?php if ($notifications_result && mysqli_num_rows($notifications_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Recipient</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                <td><?php echo htmlspecialchars($notification['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($notification['email']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No promotional notifications have been sent yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once 'footer.php'; ?>
</body>
</html> 