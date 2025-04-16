<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'customer';
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$name = trim($_POST['name'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$security_question = trim($_POST['security_question'] ?? '');
$security_answer = trim($_POST['security_answer'] ?? '');
$password = trim($_POST['password']);
$business_name = trim($_POST['business_name'] ?? '');
$subscription_tier = trim($_POST['subscription_tier'] ?? '');
$site_name = trim($_POST['site_name'] ?? '');
$contact_email = trim($_POST['contact_email'] ?? '');
$default_shipping_fee = trim($_POST['default_shipping_fee'] ?? '');

$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// Validate inputs
if (empty($username) || strlen($username) < 3) {
    $_SESSION['error_message'] = "Username must be at least 3 characters long.";
    header("Location: settings.php");
    exit();
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Please enter a valid email address.";
    header("Location: settings.php");
    exit();
}

if ($name && strlen($name) < 2) {
    $_SESSION['error_message'] = "Full name must be at least 2 characters long.";
    header("Location: settings.php");
    exit();
}

if ($phone_number && strlen($phone_number) < 7) {
    $_SESSION['error_message'] = "Phone number must be at least 7 characters long.";
    header("Location: settings.php");
    exit();
}

if ($security_question && strlen($security_question) < 5) {
    $_SESSION['error_message'] = "Security question must be at least 5 characters long.";
    header("Location: settings.php");
    exit();
}

if ($security_answer && !$security_question) {
    $_SESSION['error_message'] = "Please provide a security question if setting an answer.";
    header("Location: settings.php");
    exit();
}

if (!empty($password) && strlen($password) < 6) {
    $_SESSION['error_message'] = "Password must be at least 6 characters long.";
    header("Location: settings.php");
    exit();
}

if ($role === 'vendor' && $business_name) {
    if (strlen($business_name) < 3) {
        $_SESSION['error_message'] = "Business name must be at least 3 characters long.";
        header("Location: settings.php");
        exit();
    }
    if (!in_array($subscription_tier, ['basic', 'premium', 'enterprise'])) {
        $_SESSION['error_message'] = "Please select a valid subscription tier.";
        header("Location: settings.php");
        exit();
    }
}

if ($role === 'admin') {
    if ($site_name && strlen($site_name) < 3) {
        $_SESSION['error_message'] = "Site name must be at least 3 characters long.";
        header("Location: settings.php");
        exit();
    }
    if ($contact_email && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Please enter a valid contact email.";
        header("Location: settings.php");
        exit();
    }
    if ($default_shipping_fee !== '' && (!is_numeric($default_shipping_fee) || $default_shipping_fee < 0)) {
        $_SESSION['error_message'] = "Default shipping fee must be a non-negative number.";
        header("Location: settings.php");
        exit();
    }
}

// Check for unique username and email
$check_query = "SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ssi", $username, $email, $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    $_SESSION['error_message'] = "Username or email is already taken.";
    header("Location: settings.php");
    exit();
}
mysqli_stmt_close($check_stmt);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update users table
    $query = "UPDATE users SET username = ?, email = ?, name = ?, phone_number = ?, security_question = ?, security_answer = ?";
    $params = [$username, $email, $name ?: null, $phone_number ?: null, $security_question ?: null, $security_answer ?: null];
    $types = "ssssss";

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query .= ", password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }
    $query .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update user: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);

    // Update or insert vendor profile if vendor
    if ($role === 'vendor' && $business_name && $subscription_tier) {
        $vendor_check_query = "SELECT vendor_id FROM vendors WHERE user_id = ?";
        $vendor_check_stmt = mysqli_prepare($conn, $vendor_check_query);
        mysqli_stmt_bind_param($vendor_check_stmt, "i", $user_id);
        mysqli_stmt_execute($vendor_check_stmt);
        $vendor_check_result = mysqli_stmt_get_result($vendor_check_stmt);
        $vendor_exists = mysqli_num_rows($vendor_check_result) > 0;
        mysqli_stmt_close($vendor_check_stmt);

        if ($vendor_exists) {
            $vendor_query = "UPDATE vendors SET business_name = ?, subscription_tier = ? WHERE user_id = ?";
            $vendor_stmt = mysqli_prepare($conn, $vendor_query);
            mysqli_stmt_bind_param($vendor_stmt, "ssi", $business_name, $subscription_tier, $user_id);
            if (!mysqli_stmt_execute($vendor_stmt)) {
                throw new Exception("Failed to update vendor profile: " . mysqli_error($conn));
            }
            mysqli_stmt_close($vendor_stmt);
        } else {
            $vendor_query = "INSERT INTO vendors (user_id, business_name, subscription_tier) VALUES (?, ?, ?)";
            $vendor_stmt = mysqli_prepare($conn, $vendor_query);
            mysqli_stmt_bind_param($vendor_stmt, "iss", $user_id, $business_name, $subscription_tier);
            if (!mysqli_stmt_execute($vendor_stmt)) {
                throw new Exception("Failed to create vendor profile: " . mysqli_error($conn));
            }
            mysqli_stmt_close($vendor_stmt);
        }
    }

    // Update system settings if admin
    if ($role === 'admin') {
        $settings_to_update = [
            'site_name' => $site_name ?: 'AgriMarket',
            'contact_email' => $contact_email ?: null,
            'default_shipping_fee' => $default_shipping_fee !== '' ? $default_shipping_fee : '0.00'
        ];

        foreach ($settings_to_update as $name => $value) {
            $settings_check_query = "SELECT setting_id FROM settings WHERE name = ?";
            $settings_check_stmt = mysqli_prepare($conn, $settings_check_query);
            mysqli_stmt_bind_param($settings_check_stmt, "s", $name);
            mysqli_stmt_execute($settings_check_stmt);
            $settings_check_result = mysqli_stmt_get_result($settings_check_stmt);
            $setting_exists = mysqli_num_rows($settings_check_result) > 0;
            mysqli_stmt_close($settings_check_stmt);

            if ($setting_exists) {
                $settings_query = "UPDATE settings SET value = ? WHERE name = ?";
                $settings_stmt = mysqli_prepare($conn, $settings_query);
                mysqli_stmt_bind_param($settings_stmt, "ss", $value, $name);
            } else {
                $settings_query = "INSERT INTO settings (name, value) VALUES (?, ?)";
                $settings_stmt = mysqli_prepare($conn, $settings_query);
                mysqli_stmt_bind_param($settings_stmt, "ss", $name, $value);
            }
            if (!mysqli_stmt_execute($settings_stmt)) {
                throw new Exception("Failed to update system setting '$name': " . mysqli_error($conn));
            }
            mysqli_stmt_close($settings_stmt);
        }
    }

    // Commit transaction
    mysqli_commit($conn);
    $_SESSION['success_message'] = "Settings updated successfully!";
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    $_SESSION['error_message'] = "Error: " . htmlspecialchars($e->getMessage());
    error_log("Update settings error: " . $e->getMessage());
}

header("Location: settings.php");
exit();
?>