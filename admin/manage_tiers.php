<?php
require_once '../includes/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_tier':
                $tier = mysqli_real_escape_string($conn, $_POST['tier']);
                $product_limit = intval($_POST['product_limit']);
                $price = floatval($_POST['price']);
                $features = mysqli_real_escape_string($conn, $_POST['features']);
                
                $update_query = "UPDATE subscription_tier_limits 
                               SET product_limit = ?, price = ?, features = ?
                               WHERE tier = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "idss", $product_limit, $price, $features, $tier);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Tier '$tier' updated successfully.";
                } else {
                    $error_message = "Error updating tier: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'add_tier':
                $tier = mysqli_real_escape_string($conn, $_POST['new_tier']);
                $product_limit = intval($_POST['new_product_limit']);
                $price = floatval($_POST['new_price']);
                $features = mysqli_real_escape_string($conn, $_POST['new_features']);
                
                $insert_query = "INSERT INTO subscription_tier_limits (tier, product_limit, price, features)
                               VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "sids", $tier, $product_limit, $price, $features);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "New tier '$tier' added successfully.";
                } else {
                    $error_message = "Error adding tier: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete_tier':
                $tier = mysqli_real_escape_string($conn, $_POST['tier']);
                
                // Check if any vendors are using this tier
                $check_query = "SELECT COUNT(*) as count FROM vendors WHERE subscription_tier = ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "s", $tier);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                
                if ($row['count'] > 0) {
                    $error_message = "Cannot delete tier '$tier' as it is currently being used by vendors.";
                } else {
                    $delete_query = "DELETE FROM subscription_tier_limits WHERE tier = ?";
                    $stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($stmt, "s", $tier);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Tier '$tier' deleted successfully.";
                    } else {
                        $error_message = "Error deleting tier: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get all subscription tiers
$tiers_query = "SELECT * FROM subscription_tier_limits WHERE tier != 'standard' ORDER BY price ASC";
$tiers_result = mysqli_query($conn, $tiers_query);
$tiers = [];
if ($tiers_result) {
    while ($tier = mysqli_fetch_assoc($tiers_result)) {
        $tiers[] = $tier;
    }
    mysqli_free_result($tiers_result);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Subscription Tiers - Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>

    <div class="content">
        <div class="dashboard-header">
            <h1>Manage Subscription Tiers</h1>
            <p>Configure subscription tier limits, pricing, and features</p>
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

        <!-- Add New Tier Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Tier</h2>
            <form method="POST" class="tier-form">
                <input type="hidden" name="action" value="add_tier">
                <div class="form-group">
                    <label>Tier Name:</label>
                    <input type="text" name="new_tier" class="form-control" required pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, hyphens, and underscores allowed. 'standard' is not allowed">
                    <small class="form-text text-muted">Note: 'standard' tier is not allowed</small>
                </div>
                <div class="form-group">
                    <label>Product Limit:</label>
                    <input type="number" name="new_product_limit" class="form-control" required min="1">
                </div>
                <div class="form-group">
                    <label>Monthly Price ($):</label>
                    <input type="number" name="new_price" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Features (one per line):</label>
                    <textarea name="new_features" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-success">Add Tier</button>
            </form>
        </div>

        <!-- Existing Tiers -->
        <div class="tiers-grid">
            <?php foreach ($tiers as $tier): ?>
                <div class="tier-card">
                    <form method="POST" class="tier-form">
                        <input type="hidden" name="action" value="update_tier">
                        <input type="hidden" name="tier" value="<?php echo htmlspecialchars($tier['tier']); ?>">
                        
                        <div class="tier-header">
                            <h3><?php echo ucfirst(htmlspecialchars($tier['tier'])); ?></h3>
                            <?php if ($tier['tier'] !== 'basic'): ?>
                                <button type="submit" name="action" value="delete_tier" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this tier?');">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Product Limit:</label>
                            <input type="number" name="product_limit" class="form-control" value="<?php echo htmlspecialchars($tier['product_limit']); ?>" required min="1">
                        </div>
                        
                        <div class="form-group">
                            <label>Monthly Price ($):</label>
                            <input type="number" name="price" class="form-control" value="<?php echo htmlspecialchars($tier['price']); ?>" required min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label>Features:</label>
                            <textarea name="features" class="form-control" rows="4" required><?php echo htmlspecialchars($tier['features']); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Tier</button>
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

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .tier-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .tier-header h3 {
            margin: 0;
            color: #333;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        textarea.form-control {
            resize: vertical;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-primary {
            background: #2196F3;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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