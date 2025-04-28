<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Get vendor information first
$user_id = $_SESSION['user_id'];
$vendor_query = "SELECT * FROM vendors WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $vendor_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$vendor_result = mysqli_stmt_get_result($stmt);
$vendor = mysqli_fetch_assoc($vendor_result);
mysqli_stmt_close($stmt);

if (!$vendor) {
    $error_message = "Vendor information not found. Please contact support.";
} else {
    // Store vendor_id in session
    $_SESSION['vendor_id'] = $vendor['vendor_id'];
    
    // Get current vendor's subscription info
    $vendor_id = $vendor['vendor_id'];
    $vendor_query = "SELECT v.subscription_tier, v.vendor_id, stl.product_limit,
                     (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.vendor_id AND p.deleted_at IS NULL) as current_products
                     FROM vendors v 
                     JOIN subscription_tier_limits stl ON v.subscription_tier = stl.tier
                     WHERE v.vendor_id = ?";

    $stmt = mysqli_prepare($conn, $vendor_query);
    mysqli_stmt_bind_param($stmt, "i", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vendor_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$vendor_data) {
        $error_message = "Could not retrieve subscription information. Please try again later.";
    } else {
        // Get available subscription tiers (excluding current tier)
        $tiers_query = "SELECT * FROM subscription_tier_limits WHERE tier != ? ORDER BY price ASC";
        $stmt = mysqli_prepare($conn, $tiers_query);
        mysqli_stmt_bind_param($stmt, "s", $vendor_data['subscription_tier']);
        mysqli_stmt_execute($stmt);
        $tiers_result = mysqli_stmt_get_result($stmt);
        $available_tiers = [];
        while ($tier = mysqli_fetch_assoc($tiers_result)) {
            $available_tiers[] = $tier;
        }
        mysqli_stmt_close($stmt);

        // Handle upgrade request
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requested_tier'])) {
            $requested_tier = mysqli_real_escape_string($conn, $_POST['requested_tier']);
            
            // Check if there's already a pending request
            $check_query = "SELECT * FROM subscription_change_requests 
                           WHERE vendor_id = ? AND status = 'pending'";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "i", $vendor_id);
            mysqli_stmt_execute($stmt);
            $existing_request = mysqli_stmt_get_result($stmt)->fetch_assoc();
            mysqli_stmt_close($stmt);
            
            if ($existing_request) {
                $error_message = "You already have a pending upgrade request. Please wait for admin approval.";
            } else {
                // Create new upgrade request
                $insert_query = "INSERT INTO subscription_change_requests 
                                (vendor_id, current_tier, requested_tier) 
                                VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "iss", $vendor_id, $vendor_data['subscription_tier'], $requested_tier);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Subscription upgrade request submitted successfully. Please wait for admin approval.";
                } else {
                    $error_message = "Error submitting upgrade request. Please try again.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subscription Upgrade</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="content">
        <h1 class="page-title">Upgrade Subscription</h1>
        <p class="page-subtitle">Choose a new subscription tier to expand your business</p>

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

        <?php if (isset($vendor_data)): ?>
            <!-- Current Tier Info -->
            <div class="current-tier-card">
                <h2>Current Subscription</h2>
                <div class="tier-info">
                    <div class="info-row">
                        <span class="info-label">Tier:</span>
                        <span class="info-value"><?php echo ucfirst($vendor_data['subscription_tier']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Product Limit:</span>
                        <span class="info-value"><?php echo $vendor_data['product_limit']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Products Used:</span>
                        <span class="info-value"><?php echo $vendor_data['current_products']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Available Tiers -->
            <div class="tiers-grid">
                <?php foreach ($available_tiers as $tier): ?>
                    <div class="tier-card">
                        <div class="tier-header">
                            <h3><?php echo ucfirst($tier['tier']); ?></h3>
                            <div class="price">
                                $<?php echo number_format($tier['price'], 2); ?><span class="price-period">/month</span>
                            </div>
                        </div>
                        
                        <div class="tier-features">
                            <div class="product-limit">
                                <span class="limit-label">Product Limit:</span>
                                <span class="limit-value"><?php echo $tier['product_limit']; ?></span>
                            </div>
                            <ul>
                                <?php foreach (explode("\n", $tier['features']) as $feature): ?>
                                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <form method="POST" class="upgrade-form">
                            <input type="hidden" name="requested_tier" value="<?php echo htmlspecialchars($tier['tier']); ?>">
                            <button type="submit" class="btn btn-primary">Request Upgrade</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }

        .current-tier-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 3rem;
        }

        .current-tier-card h2 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }

        .tier-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
        }

        .info-label {
            font-weight: 600;
            color: #4a5568;
            width: 150px;
        }

        .info-value {
            color: #2d3748;
        }

        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .tier-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .tier-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }

        .tier-header {
            text-align: left;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #edf2f7;
        }

        .tier-header h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin: 0;
            margin-bottom: 1rem;
        }

        .price {
            font-size: 2.5rem;
            color: #2196F3;
            font-weight: 700;
        }

        .price-period {
            font-size: 1rem;
            color: #718096;
            font-weight: normal;
        }

        .product-limit {
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .limit-label {
            font-weight: 600;
            color: #4a5568;
            margin-right: 0.5rem;
        }

        .limit-value {
            color: #2d3748;
            font-weight: 600;
        }

        .tier-features ul {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .tier-features li {
            display: flex;
            align-items: center;
            margin: 1rem 0;
            color: #4a5568;
            font-size: 0.95rem;
        }

        .tier-features i {
            color: #48bb78;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2196F3;
            color: white;
        }

        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background: #f0fff4;
            color: #2f855a;
            border: 1px solid #c6f6d5;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .tiers-grid {
                grid-template-columns: 1fr;
            }

            .tier-card {
                padding: 1.5rem;
            }
        }
    </style>
</body>
</html> 