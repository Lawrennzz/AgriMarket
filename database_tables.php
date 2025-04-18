<?php
// Database tables creation script for AgriMarket Staff Dashboard

// Include configuration
require_once 'includes/config.php';
$conn = getConnection();

// Track if any tables were created
$tables_created = false;

// Check if staff_details table exists
$staff_details_exists = mysqli_query($conn, "SHOW TABLES LIKE 'staff_details'");
if (mysqli_num_rows($staff_details_exists) == 0) {
    $tables_created = true;
    $sql = "CREATE TABLE staff_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        position VARCHAR(100) NOT NULL,
        department VARCHAR(100),
        hire_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Table 'staff_details' created successfully.<br>";
        
        // Insert sample data if users exist
        $check_users = mysqli_query($conn, "SELECT user_id FROM users WHERE role = 'staff' LIMIT 1");
        if (mysqli_num_rows($check_users) > 0) {
            $staff_user = mysqli_fetch_assoc($check_users);
            $staff_id = $staff_user['user_id'];
            
            $sample_data = "INSERT INTO staff_details (user_id, position, department, hire_date) 
                           VALUES ($staff_id, 'Sales Representative', 'Sales', NOW())";
            if (mysqli_query($conn, $sample_data)) {
                echo "- Added sample staff details for user ID: $staff_id<br>";
            }
        }
    } else {
        echo "Error creating 'staff_details' table: " . mysqli_error($conn) . "<br>";
    }
}

// Check if staff_tasks table exists
$staff_tasks_exists = mysqli_query($conn, "SHOW TABLES LIKE 'staff_tasks'");
if (mysqli_num_rows($staff_tasks_exists) == 0) {
    $tables_created = true;
    $sql = "CREATE TABLE staff_tasks (
        task_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        assigned_to INT NOT NULL,
        assigned_by INT NOT NULL,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        due_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Table 'staff_tasks' created successfully.<br>";
        
        // Insert sample data if staff users exist
        $check_users = mysqli_query($conn, "SELECT u.user_id, a.user_id as admin_id 
                                           FROM users u, users a 
                                           WHERE u.role = 'staff' AND a.role = 'admin' 
                                           LIMIT 1");
        if (mysqli_num_rows($check_users) > 0) {
            $users = mysqli_fetch_assoc($check_users);
            $staff_id = $users['user_id'];
            $admin_id = $users['admin_id'] ?? $staff_id; // If no admin, assign the staff member as both
            
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $next_week = date('Y-m-d', strtotime('+7 days'));
            
            $sample_tasks = [
                "INSERT INTO staff_tasks (title, description, assigned_to, assigned_by, status, priority, due_date) 
                VALUES ('Process new orders', 'Check and process all new orders from the weekend.', $staff_id, $admin_id, 'pending', 'high', '$tomorrow')",
                
                "INSERT INTO staff_tasks (title, description, assigned_to, assigned_by, status, priority, due_date) 
                VALUES ('Update product inventory', 'Review and update inventory levels for all products.', $staff_id, $admin_id, 'in_progress', 'medium', '$next_week')",
                
                "INSERT INTO staff_tasks (title, description, assigned_to, assigned_by, status, priority, due_date) 
                VALUES ('Customer follow-up calls', 'Call customers who made large purchases in the last month.', $staff_id, $admin_id, 'pending', 'low', '$next_week')"
            ];
            
            foreach ($sample_tasks as $task_query) {
                if (mysqli_query($conn, $task_query)) {
                    echo "- Added sample task<br>";
                }
            }
        }
    } else {
        echo "Error creating 'staff_tasks' table: " . mysqli_error($conn) . "<br>";
    }
}

// Check if support_tickets table exists
$support_tickets_exists = mysqli_query($conn, "SHOW TABLES LIKE 'support_tickets'");
if (mysqli_num_rows($support_tickets_exists) == 0) {
    $tables_created = true;
    $sql = "CREATE TABLE support_tickets (
        ticket_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open', 'in_progress', 'closed', 'resolved') DEFAULT 'open',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_to INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Table 'support_tickets' created successfully.<br>";
        
        // Insert sample data if users exist
        $check_users = mysqli_query($conn, "SELECT c.user_id as customer_id, s.user_id as staff_id 
                                           FROM users c, users s 
                                           WHERE c.role = 'customer' AND s.role = 'staff' 
                                           LIMIT 1");
        if (mysqli_num_rows($check_users) > 0) {
            $users = mysqli_fetch_assoc($check_users);
            $customer_id = $users['customer_id'] ?? 1; // Fallback
            $staff_id = $users['staff_id'];
            
            $sample_tickets = [
                "INSERT INTO support_tickets (user_id, subject, message, status, priority, assigned_to) 
                VALUES ($customer_id, 'Order delivery delay', 'My order #10045 was supposed to arrive yesterday but I still haven\'t received it.', 'open', 'high', $staff_id)",
                
                "INSERT INTO support_tickets (user_id, subject, message, status, priority, assigned_to) 
                VALUES ($customer_id, 'Product quality issue', 'The vegetables I received in my last order were not fresh.', 'in_progress', 'medium', $staff_id)",
                
                "INSERT INTO support_tickets (user_id, subject, message, status, priority, assigned_to) 
                VALUES ($customer_id, 'Question about payment methods', 'Do you accept PayPal for payments?', 'open', 'low', $staff_id)"
            ];
            
            foreach ($sample_tickets as $ticket_query) {
                if (mysqli_query($conn, $ticket_query)) {
                    echo "- Added sample support ticket<br>";
                }
            }
        }
    } else {
        echo "Error creating 'support_tickets' table: " . mysqli_error($conn) . "<br>";
    }
}

// Check if notifications table exists
$notifications_exists = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($notifications_exists) == 0) {
    $tables_created = true;
    $sql = "CREATE TABLE notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Table 'notifications' created successfully.<br>";
        
        // Insert sample data if staff users exist
        $check_users = mysqli_query($conn, "SELECT user_id FROM users WHERE role = 'staff' LIMIT 1");
        if (mysqli_num_rows($check_users) > 0) {
            $staff_user = mysqli_fetch_assoc($check_users);
            $staff_id = $staff_user['user_id'];
            
            $sample_notifications = [
                "INSERT INTO notifications (user_id, message, is_read) 
                VALUES ($staff_id, 'You have been assigned 3 new tasks by admin.', 0)",
                
                "INSERT INTO notifications (user_id, message, is_read) 
                VALUES ($staff_id, 'New high priority support ticket requires your attention.', 0)",
                
                "INSERT INTO notifications (user_id, message, is_read) 
                VALUES ($staff_id, 'Weekly team meeting tomorrow at 10:00 AM.', 1)",
                
                "INSERT INTO notifications (user_id, message, is_read) 
                VALUES ($staff_id, 'Your task \"Process orders\" is due tomorrow.', 0)"
            ];
            
            foreach ($sample_notifications as $notification_query) {
                if (mysqli_query($conn, $notification_query)) {
                    echo "- Added sample notification<br>";
                }
            }
        }
    } else {
        echo "Error creating 'notifications' table: " . mysqli_error($conn) . "<br>";
    }
}

if ($tables_created) {
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; color: #155724; border-radius: 4px;'>
          <strong>Success!</strong> The database tables have been created successfully with sample data.
          </div>";
} else {
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 4px;'>
          <strong>Note:</strong> All required database tables already exist.
          </div>";
}

echo "<p style='margin-top: 20px;'><a href='staff_dashboard.php' style='padding: 10px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px;'>Go to Staff Dashboard</a></p>";
?> 