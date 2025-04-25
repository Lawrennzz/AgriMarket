<?php
require_once 'includes/config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Get current vendor's subscription info
$vendor_id = $_SESSION['vendor_id'];
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subscription Upgrade</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'vendor/vendor_navbar.php'; ?>

    <div class="content">
        <div class="dashboard-header">
            <h1>Upgrade Subscription</h1>
            <p>Choose a new subscription tier to expand your business</p>
        </div>

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

        <!-- Current Tier Info -->
        <div class="current-tier-card">
            <h2>Current Subscription</h2>
            <div class="tier-info">
                <p><strong>Tier:</strong> <?php echo ucfirst($vendor_data['subscription_tier']); ?></p>
                <p><strong>Product Limit:</strong> <?php echo $vendor_data['product_limit']; ?></p>
                <p><strong>Products Used:</strong> <?php echo $vendor_data['current_products']; ?></p>
            </div>
        </div>

        <!-- Available Tiers -->
        <div class="tiers-grid">
            <?php foreach ($available_tiers as $tier): ?>
                <div class="tier-card">
                    <div class="tier-header">
                        <h3><?php echo ucfirst($tier['tier']); ?></h3>
                        <div class="price">
                            $<?php echo number_format($tier['price'], 2); ?>/month
                        </div>
                    </div>
                    
                    <div class="tier-features">
                        <p><strong>Product Limit:</strong> <?php echo $tier['product_limit']; ?></p>
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
    </div>

    <style>
        .content {
            margin-left: 250px;
            padding: 20px;
        }

        .current-tier-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .tier-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tier-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .tier-header h3 {
            margin: 0;
            color: #333;
        }

        .price {
            font-size: 1.5rem;
            color: #2196F3;
            font-weight: bold;
            margin-top: 0.5rem;
        }

        .tier-features ul {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .tier-features li {
            margin: 0.5rem 0;
            color: #666;
        }

        .tier-features i {
            color: #4CAF50;
            margin-right: 0.5rem;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: #2196F3;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }

            .tiers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 