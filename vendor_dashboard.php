<?php
// Get vendor's subscription tier and product limit
$limit_query = "SELECT v.subscription_tier, stl.product_limit,
               (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.vendor_id AND p.deleted_at IS NULL) as current_products
               FROM vendors v 
               JOIN subscription_tier_limits stl ON v.subscription_tier = stl.tier
               WHERE v.vendor_id = ?";
$limit_stmt = mysqli_prepare($conn, $limit_query);

if ($limit_stmt === false) {
    // If table doesn't exist, use default values
    $limit_data = [
        'subscription_tier' => 'basic',
        'product_limit' => 10,
        'current_products' => 0
    ];
    $error_message = "Subscription tier limits not configured. Using default values. Please contact administrator.";
} else {
    mysqli_stmt_bind_param($limit_stmt, "i", $vendor_id);
    mysqli_stmt_execute($limit_stmt);
    $limit_result = mysqli_stmt_get_result($limit_stmt);
    $limit_data = mysqli_fetch_assoc($limit_result);
    mysqli_stmt_close($limit_stmt);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add styles for product limit card */
        .dashboard-card.product-limit {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .product-limit-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            margin: 1rem 0;
            overflow: hidden;
            position: relative;
        }

        .product-limit-progress {
            background: var(--primary-color);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .product-limit-text {
            margin-top: 0.5rem;
            color: var(--dark-gray);
            font-size: 1.1rem;
        }

        .product-limit-warning {
            color: var(--danger-color);
            margin-top: 0.5rem;
            display: none;
        }

        .product-limit-bar.near-limit .product-limit-progress {
            background: var(--warning-color);
        }

        .product-limit-bar.at-limit .product-limit-progress {
            background: var(--danger-color);
        }

        .product-limit-bar.near-limit ~ .product-limit-warning,
        .product-limit-bar.at-limit ~ .product-limit-warning {
            display: block;
        }

        .upgrade-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 1rem;
            transition: background-color 0.3s ease;
        }

        .upgrade-button:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($vendor_name); ?>!</h1>
            <p>Manage your products and view your sales statistics</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($limit_data): ?>
        <div class="dashboard-card product-limit">
            <div class="card-header">
                <h2><i class="fas fa-box"></i> Product Upload Capacity</h2>
            </div>
            <?php
            $percentage = ($limit_data['current_products'] / $limit_data['product_limit']) * 100;
            $limit_class = $percentage >= 100 ? 'at-limit' : ($percentage >= 80 ? 'near-limit' : '');
            ?>
            <div class="product-limit-bar <?php echo $limit_class; ?>">
                <div class="product-limit-progress" style="width: <?php echo min(100, $percentage); ?>%;"></div>
            </div>
            <div class="product-limit-text">
                <strong><?php echo $limit_data['current_products']; ?></strong> of <strong><?php echo $limit_data['product_limit']; ?></strong> products used
                <br>
                Current Plan: <strong><?php echo ucfirst($limit_data['subscription_tier']); ?></strong>
            </div>
            <?php if ($percentage >= 80): ?>
            <div class="product-limit-warning">
                <?php if ($percentage >= 100): ?>
                    <p><i class="fas fa-exclamation-triangle"></i> You have reached your product limit.</p>
                <?php else: ?>
                    <p><i class="fas fa-exclamation-circle"></i> You are approaching your product limit.</p>
                <?php endif; ?>
                <?php if ($limit_data['subscription_tier'] != 'enterprise'): ?>
                    <a href="subscription_upgrade.php" class="upgrade-button">
                        <i class="fas fa-arrow-up"></i> Upgrade Your Plan
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Rest of the dashboard content -->
    </div>
</body>
</html> 