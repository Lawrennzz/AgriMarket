<?php
include 'config.php';
require_once 'classes/AuditLog.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Initialize audit logger
$auditLogger = new AuditLog();

// Initialize variables
$vendor_id = 0;
$user_id = 0;
$business_name = '';
$subscription_tier = '';
$username = '';
$name = '';
$email = '';
$success_message = '';
$error_message = '';

// Check if vendor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error_message = "No vendor ID specified.";
} else {
    $vendor_id = intval($_GET['id']);
    
    // Fetch vendor data
    $vendor_query = "SELECT v.*, u.username, u.name, u.email 
                     FROM vendors v 
                     JOIN users u ON v.user_id = u.user_id 
                     WHERE v.vendor_id = ? AND v.deleted_at IS NULL";
    $vendor_stmt = mysqli_prepare($conn, $vendor_query);
    
    if ($vendor_stmt) {
        mysqli_stmt_bind_param($vendor_stmt, "i", $vendor_id);
        mysqli_stmt_execute($vendor_stmt);
        $vendor_result = mysqli_stmt_get_result($vendor_stmt);
        
        if ($vendor_data = mysqli_fetch_assoc($vendor_result)) {
            // Store original data for audit logging
            $original_data = $vendor_data;
            
            // Set variables
            $user_id = $vendor_data['user_id'];
            $business_name = $vendor_data['business_name'];
            $subscription_tier = $vendor_data['subscription_tier'];
            $username = $vendor_data['username'];
            $name = $vendor_data['name'];
            $email = $vendor_data['email'];
        } else {
            $error_message = "Vendor not found.";
        }
        mysqli_stmt_close($vendor_stmt);
    } else {
        $error_message = "Database error: " . mysqli_error($conn);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vendor'])) {
    // Validate inputs
    $business_name = trim($_POST['business_name']);
    $subscription_tier = $_POST['subscription_tier'];
    
    if (empty($business_name)) {
        $error_message = "Business name is required.";
    } elseif (!in_array($subscription_tier, ['basic', 'premium', 'enterprise'])) {
        $error_message = "Invalid subscription tier.";
    } else {
        // Update vendor data
        $update_query = "UPDATE vendors SET business_name = ?, subscription_tier = ? WHERE vendor_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "ssi", $business_name, $subscription_tier, $vendor_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Log the update in audit logs
                $changes = [
                    'business_name' => [
                        'from' => $original_data['business_name'],
                        'to' => $business_name
                    ],
                    'subscription_tier' => [
                        'from' => $original_data['subscription_tier'],
                        'to' => $subscription_tier
                    ]
                ];
                
                $auditLogger->log(
                    $_SESSION['user_id'],
                    'update',
                    'vendors',
                    $vendor_id,
                    $changes
                );
                
                $success_message = "Vendor updated successfully!";
                
                // Refresh data
                $business_name = $business_name;
                $subscription_tier = $subscription_tier;
            } else {
                $error_message = "Failed to update vendor: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Vendor - AgriMarket Admin</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: var(--medium-gray);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-section {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        
        .info-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .info-item {
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 500;
            display: inline-block;
            width: 150px;
            color: var(--medium-gray);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Edit Vendor</h1>
            <p class="form-subtitle">Update vendor information</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($vendor_id) && empty($error_message)): ?>
            <div class="info-section">
                <h2 class="info-title">User Information</h2>
                <div class="info-item">
                    <span class="info-label">Vendor ID:</span>
                    <span><?php echo htmlspecialchars($vendor_id); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username:</span>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span><?php echo htmlspecialchars($name); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span><?php echo htmlspecialchars($email); ?></span>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="business_name" class="form-label">Business Name*</label>
                    <input type="text" id="business_name" name="business_name" class="form-control" value="<?php echo htmlspecialchars($business_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="subscription_tier" class="form-label">Subscription Tier*</label>
                    <select id="subscription_tier" name="subscription_tier" class="form-control" required>
                        <option value="basic" <?php echo ($subscription_tier === 'basic') ? 'selected' : ''; ?>>Basic</option>
                        <option value="premium" <?php echo ($subscription_tier === 'premium') ? 'selected' : ''; ?>>Premium</option>
                        <option value="enterprise" <?php echo ($subscription_tier === 'enterprise') ? 'selected' : ''; ?>>Enterprise</option>
                    </select>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="update_vendor" class="btn btn-primary">Update Vendor</button>
                    <a href="manage_vendors.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php elseif (empty($vendor_id)): ?>
            <div class="alert alert-error">
                No vendor ID provided. <a href="manage_vendors.php">Return to vendor management</a>.
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 