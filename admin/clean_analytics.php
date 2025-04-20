<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/track_analytics.php';

// Check admin permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();
$message = '';
$message_type = '';
$token = md5(uniqid(rand(), true));
$_SESSION['csrf_token'] = $token;

// Get current row counts
function getAnalyticsCounts($conn) {
    $tables = [
        'analytics' => 'Analytics',
        'analytics_extended' => 'Extended Analytics',
        'product_search_logs' => 'Search Logs',
        'product_visits' => 'Product Visits'
    ];
    
    $counts = [];
    
    foreach ($tables as $table => $label) {
        $query = "SELECT COUNT(*) as count FROM $table";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $counts[$table] = [
                'label' => $label,
                'count' => $row['count']
            ];
        } else {
            $counts[$table] = [
                'label' => $label,
                'count' => 'Error querying table'
            ];
        }
    }
    
    return $counts;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Security token mismatch. Please try again.";
        $message_type = "error";
    } else {
        // Confirm deletion with checkbox
        if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
            $message = "You must confirm the deletion by checking the confirmation box.";
            $message_type = "error";
        } else {
            // All checks passed, purge the data
            $purge_result = purge_analytics_data('all');
            
            if ($purge_result) {
                $message = "Successfully purged all analytics data.";
                $message_type = "success";
            } else {
                $message = "Error purging analytics data. Check server logs for details.";
                $message_type = "error";
            }
        }
    }
    
    // Regenerate token after form submission
    $token = md5(uniqid(rand(), true));
    $_SESSION['csrf_token'] = $token;
}

// Get current row counts
$analytics_counts = getAnalyticsCounts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Analytics Data - AgriMarket Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        h1, h2 {
            color: #333;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .warning-box h3 {
            margin-top: 0;
        }
        
        .confirmation-form {
            margin-top: 20px;
        }
        
        .confirmation-checkbox {
            margin-bottom: 15px;
        }
        
        button.danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        button.danger:hover {
            background-color: #c82333;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table th {
            background-color: #f7f7f7;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <h1>Clean Analytics Data</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Current Analytics Data</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data Type</th>
                        <th>Number of Records</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics_counts as $table => $data): ?>
                        <tr>
                            <td><?php echo $data['label']; ?></td>
                            <td><?php echo $data['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>Purge Analytics Data</h2>
            
            <div class="warning-box">
                <h3><i class="fas fa-exclamation-triangle"></i> Warning</h3>
                <p>This action will permanently delete <strong>ALL</strong> analytics data from the system. This includes:</p>
                <ul>
                    <li>Product view records</li>
                    <li>Search logs</li>
                    <li>Order tracking data</li>
                    <li>All analytics data for reporting</li>
                </ul>
                <p><strong>This action cannot be undone!</strong> Make sure you have a backup if you need to preserve this data.</p>
            </div>
            
            <form class="confirmation-form" method="POST" action="clean_analytics.php">
                <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                
                <div class="confirmation-checkbox">
                    <label>
                        <input type="checkbox" name="confirm_delete" value="yes"> 
                        I understand that this action is irreversible and will delete all analytics data.
                    </label>
                </div>
                
                <button type="submit" class="danger">
                    <i class="fas fa-trash-alt"></i> Purge All Analytics Data
                </button>
            </form>
        </div>
    </div>
</body>
</html> 