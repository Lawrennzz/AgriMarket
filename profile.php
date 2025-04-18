<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$page_title = "My Profile";
$success_message = "";
$error_message = "";

// Add avatar column to staff_details table if it doesn't exist
$check_avatar_column = mysqli_query($conn, "SHOW COLUMNS FROM staff_details LIKE 'avatar'");
if (mysqli_num_rows($check_avatar_column) == 0) {
    mysqli_query($conn, "ALTER TABLE staff_details ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
}

// Add emergency_contact column to staff_details table if it doesn't exist
$check_emergency_column = mysqli_query($conn, "SHOW COLUMNS FROM staff_details LIKE 'emergency_contact'");
if (mysqli_num_rows($check_emergency_column) == 0) {
    mysqli_query($conn, "ALTER TABLE staff_details ADD COLUMN emergency_contact VARCHAR(100) DEFAULT NULL");
}

// Fetch staff data
$staff = null;
$error_message = null;
$success_message = null;

// Get data from users table
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
if (!$stmt) {
    $error_message = "Database error: " . mysqli_error($conn);
} else {
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff = mysqli_fetch_assoc($result);
    
    // Get staff details if available
    $profile_stmt = mysqli_prepare($conn, "SELECT * FROM staff_details WHERE user_id = ?");
    if (!$profile_stmt) {
        $error_message = "Database error: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($profile_stmt, "i", $staff_id);
        mysqli_stmt_execute($profile_stmt);
        $profile_result = mysqli_stmt_get_result($profile_stmt);
        
        if (mysqli_num_rows($profile_result) > 0) {
            $staff_profile = mysqli_fetch_assoc($profile_result);
            $staff = array_merge($staff, $staff_profile);
        }
    }
}

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $position = isset($_POST['position']) ? trim($_POST['position']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } else {
        // Check if email already exists for another user
        $email_check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        if (!$email_check) {
            $error_message = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($email_check, "si", $email, $staff_id);
            mysqli_stmt_execute($email_check);
            mysqli_stmt_store_result($email_check);
            
            if (mysqli_stmt_num_rows($email_check) > 0) {
                $error_message = "This email is already used by another account.";
            } else {
                // Update user table
                $user_update = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?");
                if (!$user_update) {
                    $error_message = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($user_update, "sssi", $name, $email, $phone, $staff_id);
                    $user_updated = mysqli_stmt_execute($user_update);
                    
                    // Update or insert into staff_details table
                    if ($user_updated) {
                        // Check if profile exists to determine update or insert
                        $check_profile = mysqli_prepare($conn, "SELECT user_id FROM staff_details WHERE user_id = ?");
                        if (!$check_profile) {
                            $error_message = "Database error: " . mysqli_error($conn);
                        } else {
                            mysqli_stmt_bind_param($check_profile, "i", $staff_id);
                            mysqli_stmt_execute($check_profile);
                            mysqli_stmt_store_result($check_profile);
                            
                            if (mysqli_stmt_num_rows($check_profile) > 0) {
                                // Update existing profile
                                $profile_update = mysqli_prepare($conn, "UPDATE staff_details SET department = ?, position = ?, emergency_contact = ? WHERE user_id = ?");
                                if (!$profile_update) {
                                    $error_message = "Database error: " . mysqli_error($conn);
                                } else {
                                    mysqli_stmt_bind_param($profile_update, "sssi", $department, $position, $emergency_contact, $staff_id);
                                    $profile_updated = mysqli_stmt_execute($profile_update);
                                }
                            } else {
                                // Insert new profile
                                $profile_insert = mysqli_prepare($conn, "INSERT INTO staff_details (user_id, department, position, emergency_contact) VALUES (?, ?, ?, ?)");
                                if (!$profile_insert) {
                                    $error_message = "Database error: " . mysqli_error($conn);
                                } else {
                                    mysqli_stmt_bind_param($profile_insert, "isss", $staff_id, $department, $position, $emergency_contact);
                                    $profile_updated = mysqli_stmt_execute($profile_insert);
                                }
                            }
                            
                            if ($user_updated && ($profile_updated || !isset($profile_updated))) {
                                $success_message = "Profile updated successfully.";
                                
                                // Update session variables
                                $_SESSION['name'] = $name;
                                $_SESSION['email'] = $email;
                                
                                // Reload staff data
                                $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
                                mysqli_stmt_bind_param($stmt, "i", $staff_id);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_stmt_get_result($stmt);
                                $staff = mysqli_fetch_assoc($result);
                                
                                if (isset($profile_updated) && $profile_updated) {
                                    $profile_stmt = mysqli_prepare($conn, "SELECT * FROM staff_details WHERE user_id = ?");
                                    mysqli_stmt_bind_param($profile_stmt, "i", $staff_id);
                                    mysqli_stmt_execute($profile_stmt);
                                    $profile_result = mysqli_stmt_get_result($profile_stmt);
                                    
                                    if (mysqli_num_rows($profile_result) > 0) {
                                        $staff_profile = mysqli_fetch_assoc($profile_result);
                                        $staff = array_merge($staff, $staff_profile);
                                    }
                                }
                            } else {
                                $error_message = "Error updating profile: " . mysqli_error($conn);
                            }
                        }
                    } else {
                        $error_message = "Error updating basic information: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        $password_check = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
        if (!$password_check) {
            $error_message = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($password_check, "i", $staff_id);
            mysqli_stmt_execute($password_check);
            $password_result = mysqli_stmt_get_result($password_check);
            $user_data = mysqli_fetch_assoc($password_result);
            
            if (!password_verify($current_password, $user_data['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Hash new password and update
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
                if (!$password_update) {
                    $error_message = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($password_update, "si", $hashed_password, $staff_id);
                    
                    if (mysqli_stmt_execute($password_update)) {
                        $success_message = "Password changed successfully.";
                    } else {
                        $error_message = "Error changing password: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_type = $_FILES['avatar']['type'];
        $file_size = $_FILES['avatar']['size'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file_type, $allowed_types)) {
            $error_message = "Only JPG, PNG and GIF images are allowed.";
        } elseif ($file_size > $max_size) {
            $error_message = "Image size must be less than 2MB.";
        } else {
            // Create directory if it doesn't exist
            $upload_dir = 'uploads/avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'staff_' . $staff_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Update avatar in database
                $has_profile = false;
                if (isset($profile_stmt)) {
                    mysqli_stmt_execute($profile_stmt);
                    $profile_result = mysqli_stmt_get_result($profile_stmt);
                    $has_profile = mysqli_num_rows($profile_result) > 0;
                }
                
                if ($has_profile) {
                    $avatar_update = mysqli_prepare($conn, "UPDATE staff_details SET avatar = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($avatar_update, "si", $file_path, $staff_id);
                    $avatar_updated = mysqli_stmt_execute($avatar_update);
                } else {
                    $avatar_insert = mysqli_prepare($conn, "INSERT INTO staff_details (user_id, avatar, position) VALUES (?, ?, 'Staff')");
                    mysqli_stmt_bind_param($avatar_insert, "is", $staff_id, $file_path);
                    $avatar_updated = mysqli_stmt_execute($avatar_insert);
                }
                
                if (isset($avatar_updated) && $avatar_updated) {
                    $success_message = "Profile picture uploaded successfully.";
                    
                    // Reload staff profile data
                    $profile_stmt = mysqli_prepare($conn, "SELECT * FROM staff_details WHERE user_id = ?");
                    if (!$profile_stmt) {
                        $error_message = "Database error: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($profile_stmt, "i", $staff_id);
                        mysqli_stmt_execute($profile_stmt);
                        $profile_result = mysqli_stmt_get_result($profile_stmt);
                        
                        if (mysqli_num_rows($profile_result) > 0) {
                            $staff_profile = mysqli_fetch_assoc($profile_result);
                            $staff = array_merge($staff, $staff_profile);
                        }
                    }
                } else {
                    $error_message = "Error updating profile picture in database: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Error uploading profile picture.";
            }
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
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
        
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
        }
        
        .profile-sidebar {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }
        
        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #f0f0f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-avatar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            text-align: center;
            padding: 8px 0;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .upload-avatar:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }
        
        .staff-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .staff-role {
            font-size: 0.9rem;
            color: #666;
            margin: 0 0 20px 0;
        }
        
        .staff-info {
            text-align: left;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
        }
        
        .info-item i {
            color: #4CAF50;
            min-width: 30px;
            margin-top: 4px;
        }
        
        .info-item span {
            color: #333;
        }
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tab-button.active, .tab-button:hover {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
        }
        
        .submit-btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        
        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><?php echo $page_title; ?></h1>
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
        
        <?php if (isset($staff)): ?>
            <div class="profile-container">
                <div class="profile-sidebar">
                    <div class="avatar-container">
                        <?php if (isset($staff['avatar']) && !empty($staff['avatar'])): ?>
                            <img src="<?php echo $staff['avatar']; ?>" alt="Profile Picture" class="avatar">
                        <?php else: ?>
                            <img src="images/default-avatar.png" alt="Default Profile" class="avatar">
                        <?php endif; ?>
                        
                        <label for="avatar-upload" class="upload-avatar">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                    </div>
                    
                    <h2 class="staff-name"><?php echo htmlspecialchars($staff['name']); ?></h2>
                    <p class="staff-role">Staff Member</p>
                    
                    <div class="staff-info">
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($staff['email']); ?></span>
                        </div>
                        
                        <?php if (isset($staff['phone']) && !empty($staff['phone'])): ?>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($staff['phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($staff['position']) && !empty($staff['position'])): ?>
                            <div class="info-item">
                                <i class="fas fa-briefcase"></i>
                                <span><?php echo htmlspecialchars($staff['position']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($staff['department']) && !empty($staff['department'])): ?>
                            <div class="info-item">
                                <i class="fas fa-building"></i>
                                <span><?php echo htmlspecialchars($staff['department']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined: <?php echo date('M d, Y', strtotime($staff['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <form action="" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="file" name="avatar" id="avatar-upload" accept="image/*" onchange="this.form.submit()">
                        <input type="hidden" name="upload_avatar" value="1">
                    </form>
                </div>
                
                <div class="form-container">
                    <div class="profile-tabs">
                        <button type="button" class="tab-button active" data-tab="personal-info">Personal Information</button>
                        <button type="button" class="tab-button" data-tab="change-password">Change Password</button>
                    </div>
                    
                    <div id="personal-info" class="tab-content active">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo isset($staff['phone']) ? htmlspecialchars($staff['phone']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" name="department" id="department" class="form-control" value="<?php echo isset($staff['department']) ? htmlspecialchars($staff['department']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" name="position" id="position" class="form-control" value="<?php echo isset($staff['position']) ? htmlspecialchars($staff['position']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" id="emergency_contact" class="form-control" value="<?php echo isset($staff['emergency_contact']) ? htmlspecialchars($staff['emergency_contact']) : ''; ?>">
                            </div>
                            
                            <button type="submit" name="update_profile" class="submit-btn">Update Profile</button>
                        </form>
                    </div>
                    
                    <div id="change-password" class="tab-content">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="submit-btn">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Staff profile could not be found. Please contact the administrator.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to current tab
                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Avatar upload trigger
            document.querySelector('.upload-avatar').addEventListener('click', function() {
                document.getElementById('avatar-upload').click();
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.5s';
                        setTimeout(() => alert.remove(), 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html> 