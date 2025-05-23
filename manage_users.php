<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Fetch users
$users_query = "SELECT user_id, name, email, role, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC";
$users_stmt = mysqli_prepare($conn, $users_query);
mysqli_stmt_execute($users_stmt);
$users_result = mysqli_stmt_get_result($users_stmt);

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id_to_delete = (int)$_POST['user_id'];
    $delete_query = "UPDATE users SET deleted_at = NOW() WHERE user_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $user_id_to_delete);
    if (mysqli_stmt_execute($delete_stmt)) {
        $success = "User deleted successfully!";
        // Log action
        $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
        $audit_stmt = mysqli_prepare($conn, $audit_query);
        $action = "delete_user";
        $table_name = "users";
        $details = "Deleted user ID: $user_id_to_delete";
        mysqli_stmt_bind_param($audit_stmt, "issis", $_SESSION['user_id'], $action, $table_name, $user_id_to_delete, $details);
        mysqli_stmt_execute($audit_stmt);
        mysqli_stmt_close($audit_stmt);
    } else {
        $error = "Failed to delete user.";
    }
    mysqli_stmt_close($delete_stmt);
    header("Location: manage_users.php"); // Refresh page
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - AgriMarket</title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .user-table th, .user-table td {
            padding: 1rem;
            border: 1px solid var(--light-gray);
            text-align: left;
        }

        .user-table th {
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
            <h1 class="form-title">Manage Users</h1>
            <p class="form-subtitle">Manage user accounts and roles</p>
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

        <table class="user-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary">Edit</a>
                            <form method="POST" action="manage_users.php" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
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