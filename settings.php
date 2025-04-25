<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'customer';
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch user data
$query = "SELECT username, email, name, phone_number, security_question, security_answer, role FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Fetch vendor data if user is a vendor
$vendor = null;
if ($role === 'vendor') {
    $vendor_query = "SELECT business_name, subscription_tier FROM vendors WHERE user_id = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_query);
    mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
    mysqli_stmt_execute($vendor_stmt);
    $vendor_result = mysqli_stmt_get_result($vendor_stmt);
    $vendor = mysqli_fetch_assoc($vendor_result);
    mysqli_stmt_close($vendor_stmt);
}

// Fetch system settings if user is an admin
$system_settings = [];
if ($role === 'admin') {
    $settings_query = "SELECT name, value FROM settings";
    $settings_result = mysqli_query($conn, $settings_query);
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $system_settings[$row['name']] = $row['value'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Settings - AgriMarket</title>
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
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
        .section-header {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: var(--dark-gray);
        }
        .vendor-prompt {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #e9ecef;
            border-radius: var(--border-radius);
        }
        .subscription-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .subscription-info p {
            margin: 0;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-container">
            <h1>Account Settings</h1>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="update_settings.php" method="POST" id="settingsForm">
                <h2 class="section-header">Profile</h2>
                <div class="form-group">
                    <label for="username" class="form-label">Username*</label>
                    <input type="text" name="username" id="username" class="form-control" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email*</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" name="name" id="name" class="form-control" 
                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="tel" name="phone_number" id="phone_number" class="form-control" 
                           value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="security_question" class="form-label">Security Question</label>
                    <input type="text" name="security_question" id="security_question" class="form-control" 
                           value="<?php echo htmlspecialchars($user['security_question'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="security_answer" class="form-label">Security Answer</label>
                    <input type="text" name="security_answer" id="security_answer" class="form-control" 
                           value="<?php echo htmlspecialchars($user['security_answer'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Enter new password">
                </div>

                <?php if ($role === 'vendor'): ?>
                    <h2 class="section-header">Vendor Profile</h2>
                    <?php if (!$vendor): ?>
                        <div class="vendor-prompt">
                            <p>Create your vendor profile to start selling products.</p>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="business_name" class="form-label">Business Name<?php echo $vendor ? '*' : ''; ?></label>
                        <input type="text" name="business_name" id="business_name" class="form-control" 
                               value="<?php echo htmlspecialchars($vendor['business_name'] ?? ''); ?>" 
                               <?php echo $vendor ? 'required' : ''; ?>>
                    </div>
                    <?php if ($vendor): ?>
                        <div class="form-group">
                            <label class="form-label">Current Subscription Tier</label>
                            <div class="subscription-info">
                                <p><?php echo ucfirst($vendor['subscription_tier']); ?></p>
                                <a href="subscription_upgrade.php" class="btn btn-secondary">Request Tier Change</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($role === 'admin'): ?>
                    <h2 class="section-header">System Settings</h2>
                    <div class="form-group">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" name="site_name" id="site_name" class="form-control" 
                               value="<?php echo htmlspecialchars($system_settings['site_name'] ?? 'AgriMarket'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" id="contact_email" class="form-control" 
                               value="<?php echo htmlspecialchars($system_settings['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_shipping_fee" class="form-label">Default Shipping Fee</label>
                        <input type="number" name="default_shipping_fee" id="default_shipping_fee" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo htmlspecialchars($system_settings['default_shipping_fee'] ?? '0.00'); ?>">
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Update Settings</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const name = document.getElementById('name').value.trim();
            const phoneNumber = document.getElementById('phone_number').value.trim();
            const password = document.getElementById('password').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long.');
                return;
            }

            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            if (name && name.length < 2) {
                e.preventDefault();
                alert('Full name must be at least 2 characters long.');
                return;
            }

            if (phoneNumber && phoneNumber.length < 7) {
                e.preventDefault();
                alert('Phone number must be at least 7 characters long.');
                return;
            }

            if (password && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }

            <?php if ($role === 'vendor'): ?>
                const businessName = document.getElementById('business_name').value.trim();
                if (businessName && businessName.length < 3) {
                    e.preventDefault();
                    alert('Business name must be at least 3 characters long.');
                    return;
                }
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                const siteName = document.getElementById('site_name').value.trim();
                const contactEmail = document.getElementById('contact_email').value.trim();
                if (siteName && siteName.length < 3) {
                    e.preventDefault();
                    alert('Site name must be at least 3 characters long.');
                    return;
                }
                if (contactEmail && !emailRegex.test(contactEmail)) {
                    e.preventDefault();
                    alert('Please enter a valid contact email.');
                    return;
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>