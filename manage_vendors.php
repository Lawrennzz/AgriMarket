<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Fetch vendors
$vendors_query = "SELECT v.vendor_id, u.name AS vendor_name, v.user_id, v.created_at 
                  FROM vendors v 
                  JOIN users u ON v.user_id = u.user_id 
                  WHERE v.deleted_at IS NULL 
                  ORDER BY v.created_at DESC";
$vendors_stmt = mysqli_prepare($conn, $vendors_query);

if ($vendors_stmt === false) {
    die('MySQL prepare error: ' . mysqli_error($conn));
}

mysqli_stmt_execute($vendors_stmt);
$vendors_result = mysqli_stmt_get_result($vendors_stmt);

// Handle vendor deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_vendor'])) {
    $vendor_id_to_delete = (int)$_POST['vendor_id'];
    $delete_query = "UPDATE vendors SET deleted_at = NOW() WHERE vendor_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $vendor_id_to_delete);
    if (mysqli_stmt_execute($delete_stmt)) {
        $success = "Vendor deleted successfully!";
    } else {
        $error = "Failed to delete vendor.";
    }
    mysqli_stmt_close($delete_stmt);
    header("Location: manage_vendors.php"); // Refresh page
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Vendors - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
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

        .vendor-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .vendor-table th, .vendor-table td {
            padding: 1rem;
            border: 1px solid var(--light-gray);
            text-align: left;
        }

        .vendor-table th {
            background: var(--primary-color);
            color: white;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Manage Vendors</h1>
            <p class="form-subtitle">Manage vendor accounts</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <table class="vendor-table">
            <thead>
                <tr>
                    <th>Vendor ID</th>
                    <th>Vendor Name</th>
                    <th>User ID</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($vendor = mysqli_fetch_assoc($vendors_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vendor['vendor_id']); ?></td>
                        <td><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                        <td><?php echo htmlspecialchars($vendor['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($vendor['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="edit_vendor.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn btn-primary">Edit</a>
                            <form method="POST" action="manage_vendors.php" style="display:inline;">
                                <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                <button type="submit" name="delete_vendor" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this vendor?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>