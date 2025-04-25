<?php
include '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission for updating tier limits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tiers'])) {
    foreach ($_POST['tiers'] as $tier => $limit) {
        $tier = mysqli_real_escape_string($conn, $tier);
        $limit = intval($limit);
        
        $update_query = "UPDATE subscription_tier_limits SET product_limit = ? WHERE tier = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "is", $limit, $tier);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            $error_message = "Failed to update tier limits: " . mysqli_error($conn);
            break;
        }
        mysqli_stmt_close($update_stmt);
    }
    
    if (empty($error_message)) {
        $success_message = "Subscription tier limits updated successfully.";
    }
}

// Get current tier limits
$tiers_query = "SELECT * FROM subscription_tier_limits ORDER BY product_limit";
$tiers_result = mysqli_query($conn, $tiers_query);
$tiers = [];
while ($tier = mysqli_fetch_assoc($tiers_result)) {
    $tiers[] = $tier;
}

// Get subscription change requests statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM subscription_change_requests";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subscription Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'admin_navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Subscription Settings</h1>
            <p>Manage subscription tiers and their limits</p>
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

        <div class="settings-container">
            <div class="settings-section">
                <h2>Subscription Tiers</h2>
                <form method="POST" class="tier-settings-form">
                    <input type="hidden" name="update_tiers" value="1">
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>Tier Name</th>
                                <th>Product Limit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier): ?>
                                <tr>
                                    <td><?php echo ucfirst($tier['tier']); ?></td>
                                    <td>
                                        <input type="number" 
                                               name="tiers[<?php echo $tier['tier']; ?>]" 
                                               value="<?php echo $tier['product_limit']; ?>"
                                               min="1"
                                               class="form-control">
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-primary">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <div class="settings-section">
                <h2>Subscription Change Requests Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Requests</h3>
                        <p><?php echo $stats['total_requests']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Requests</h3>
                        <p><?php echo $stats['pending_requests']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Approved Requests</h3>
                        <p><?php echo $stats['approved_requests']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Rejected Requests</h3>
                        <p><?php echo $stats['rejected_requests']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .settings-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .settings-table th,
        .settings-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .settings-table th {
            background: #e9ecef;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-gray);
            font-size: 1rem;
        }

        .stat-card p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 