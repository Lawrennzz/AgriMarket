<?php
require_once '../includes/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');

    // Get request details
    $request_query = "SELECT * FROM subscription_change_requests WHERE request_id = ?";
    $request_stmt = mysqli_prepare($conn, $request_query);
    mysqli_stmt_bind_param($request_stmt, "i", $request_id);
    mysqli_stmt_execute($request_stmt);
    $request_result = mysqli_stmt_get_result($request_stmt);
    $request = mysqli_fetch_assoc($request_result);
    mysqli_stmt_close($request_stmt);

    if ($request) {
        if ($action === 'approve') {
            // Update vendor's subscription tier
            $update_query = "UPDATE vendors SET subscription_tier = ? WHERE vendor_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "si", $request['requested_tier'], $request['vendor_id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Update request status
                $status_query = "UPDATE subscription_change_requests 
                               SET status = 'approved', admin_notes = ? 
                               WHERE request_id = ?";
                $status_stmt = mysqli_prepare($conn, $status_query);
                mysqli_stmt_bind_param($status_stmt, "si", $admin_notes, $request_id);
                mysqli_stmt_execute($status_stmt);
                mysqli_stmt_close($status_stmt);
                
                $success_message = "Subscription tier change approved successfully.";
            } else {
                $error_message = "Failed to update vendor's subscription tier.";
            }
            mysqli_stmt_close($update_stmt);
        } else if ($action === 'reject') {
            // Update request status to rejected
            $status_query = "UPDATE subscription_change_requests 
                           SET status = 'rejected', admin_notes = ? 
                           WHERE request_id = ?";
            $status_stmt = mysqli_prepare($conn, $status_query);
            mysqli_stmt_bind_param($status_stmt, "si", $admin_notes, $request_id);
            
            if (mysqli_stmt_execute($status_stmt)) {
                $success_message = "Subscription tier change request rejected.";
            } else {
                $error_message = "Failed to update request status.";
            }
            mysqli_stmt_close($status_stmt);
        }
    } else {
        $error_message = "Request not found.";
    }
}

// Get all pending requests
$requests_query = "SELECT r.*, v.business_name, u.email 
                  FROM subscription_change_requests r
                  JOIN vendors v ON r.vendor_id = v.vendor_id
                  JOIN users u ON v.user_id = u.user_id
                  WHERE r.status = 'pending'
                  ORDER BY r.created_at DESC";
$requests_result = mysqli_query($conn, $requests_query);
if (!$requests_result) {
    $error_message = "Error fetching pending requests: " . mysqli_error($conn);
    error_log("SQL Error in subscription_requests.php: " . mysqli_error($conn));
    $requests = [];
} else {
    $requests = [];
    while ($request = mysqli_fetch_assoc($requests_result)) {
        $requests[] = $request;
    }
    mysqli_free_result($requests_result);
}

// Get all completed requests
$completed_query = "SELECT r.*, v.business_name, u.email 
                   FROM subscription_change_requests r
                   JOIN vendors v ON r.vendor_id = v.vendor_id
                   JOIN users u ON v.user_id = u.user_id
                   WHERE r.status != 'pending'
                   ORDER BY r.updated_at DESC
                   LIMIT 50";
$completed_result = mysqli_query($conn, $completed_query);
if (!$completed_result) {
    $error_message = "Error fetching completed requests: " . mysqli_error($conn);
    error_log("SQL Error in subscription_requests.php: " . mysqli_error($conn));
    $completed_requests = [];
} else {
    $completed_requests = [];
    while ($request = mysqli_fetch_assoc($completed_result)) {
        $completed_requests[] = $request;
    }
    mysqli_free_result($completed_result);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subscription Requests - Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Sidebar integration styles */
        .content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background: #f8f9fa;
        }

        /* Ensure sidebar is visible */
        .sidebar {
            z-index: 1000;
        }

        /* Existing styles... */
        .dashboard-header {
            margin-bottom: 2rem;
        }
        
        .dashboard-header h1 {
            margin: 0;
            font-size: 2rem;
            color: #333;
        }
        
        .dashboard-header p {
            margin: 0.5rem 0 0;
            color: #666;
        }

        .requests-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .request-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .request-card.completed {
            opacity: 0.8;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .request-date {
            color: #666;
            font-size: 0.9rem;
        }

        .request-details p {
            margin: 0.5rem 0;
            line-height: 1.4;
        }

        .request-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 0.5rem;
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

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .status-approved {
            color: #4CAF50;
            font-weight: 500;
        }

        .status-rejected {
            color: #f44336;
            font-weight: 500;
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

        @media (max-width: 1200px) {
            .requests-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }

            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include '../sidebar.php'; 
    ?>

    <div class="content">
        <div class="dashboard-header">
            <h1>Subscription Change Requests</h1>
            <p>Manage vendor subscription tier change requests</p>
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

        <div class="requests-container">
            <div class="pending-requests">
                <h2>Pending Requests</h2>
                <?php if (empty($requests)): ?>
                    <p>No pending requests.</p>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <h3><?php echo htmlspecialchars($request['business_name']); ?></h3>
                                <span class="request-date">
                                    <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
                                </span>
                            </div>
                            <div class="request-details">
                                <p>
                                    <strong>Current Tier:</strong> 
                                    <?php echo ucfirst($request['current_tier']); ?>
                                </p>
                                <p>
                                    <strong>Requested Tier:</strong> 
                                    <?php echo ucfirst($request['requested_tier']); ?>
                                </p>
                                <p>
                                    <strong>Contact:</strong> 
                                    <?php echo htmlspecialchars($request['email']); ?>
                                </p>
                            </div>
                            <form method="POST" class="request-actions">
                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                <textarea name="admin_notes" placeholder="Add notes (optional)" class="form-control"></textarea>
                                <div class="action-buttons">
                                    <button type="submit" name="action" value="approve" class="btn btn-success">
                                        Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="completed-requests">
                <h2>Recent Completed Requests</h2>
                <?php if (empty($completed_requests)): ?>
                    <p>No completed requests.</p>
                <?php else: ?>
                    <?php foreach ($completed_requests as $request): ?>
                        <div class="request-card completed">
                            <div class="request-header">
                                <h3><?php echo htmlspecialchars($request['business_name']); ?></h3>
                                <span class="request-date">
                                    <?php echo date('M d, Y H:i', strtotime($request['updated_at'])); ?>
                                </span>
                            </div>
                            <div class="request-details">
                                <p>
                                    <strong>From:</strong> 
                                    <?php echo ucfirst($request['current_tier']); ?>
                                </p>
                                <p>
                                    <strong>To:</strong> 
                                    <?php echo ucfirst($request['requested_tier']); ?>
                                </p>
                                <p>
                                    <strong>Status:</strong> 
                                    <span class="status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </p>
                                <?php if ($request['admin_notes']): ?>
                                    <p>
                                        <strong>Notes:</strong> 
                                        <?php echo htmlspecialchars($request['admin_notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add sidebar JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dropdowns
        const dropdowns = document.querySelectorAll('.sidebar-dropdown > a');
        
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.preventDefault();
                
                const parent = this.parentElement;
                const submenu = this.nextElementSibling;
                
                // Toggle active class
                parent.classList.toggle('active');
                
                // Toggle submenu display
                if (submenu.style.display === 'none' || submenu.style.display === '') {
                    submenu.style.display = 'block';
                } else {
                    submenu.style.display = 'none';
                }
            });
        });
    });
    </script>
</body>
</html> 