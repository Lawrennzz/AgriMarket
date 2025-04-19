<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/track_analytics.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$conn = getConnection();
$message = '';
$status = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the form token to prevent CSRF
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        $message = "Security validation failed. Please try again.";
        $status = 'error';
    } else {
        $table = isset($_POST['table']) ? $_POST['table'] : 'all';
        $confirmation = isset($_POST['confirmation']) ? $_POST['confirmation'] : '';
        
        if ($confirmation !== 'DELETE') {
            $message = "Please type DELETE in the confirmation field to proceed.";
            $status = 'error';
        } else {
            // Call the purge function from track_analytics.php
            $success = purge_analytics_data($table);
            
            if ($success) {
                $message = "Successfully purged analytics data for " . ($table === 'all' ? 'all tables' : "table '$table'");
                $status = 'success';
            } else {
                $message = "An error occurred while purging data. Check server logs for details.";
                $status = 'error';
            }
        }
    }
}

// Generate a token for CSRF protection
$_SESSION['token'] = bin2hex(random_bytes(32));

// Get table row counts
$tables = ['analytics', 'analytics_extended', 'product_search_logs', 'product_visits'];
$counts = [];

foreach ($tables as $table) {
    $query = "SELECT COUNT(*) as count FROM $table";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $counts[$table] = $row['count'];
    } else {
        $counts[$table] = 'Table not found';
    }
}
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
        
        .page-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            margin-top: 0;
            font-size: 1.5rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .warning-box {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success-box {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-box {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        button:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1>Clean Analytics Data</h1>
        </div>
        
        <div class="card">
            <h2>Current Analytics Data</h2>
            
            <table>
                <tr>
                    <th>Table</th>
                    <th>Record Count</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td>analytics</td>
                    <td><?php echo $counts['analytics']; ?></td>
                    <td>Primary analytics table tracking page views, searches, and orders</td>
                </tr>
                <tr>
                    <td>analytics_extended</td>
                    <td><?php echo $counts['analytics_extended']; ?></td>
                    <td>Enhanced analytics with more detailed user activity tracking</td>
                </tr>
                <tr>
                    <td>product_search_logs</td>
                    <td><?php echo $counts['product_search_logs']; ?></td>
                    <td>Records of search terms and the products found in those searches</td>
                </tr>
                <tr>
                    <td>product_visits</td>
                    <td><?php echo $counts['product_visits']; ?></td>
                    <td>Individual product page visit records</td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Purge Analytics Data</h2>
            
            <div class="warning-box">
                <h3><i class="fas fa-exclamation-triangle"></i> Warning: Destructive Action</h3>
                <p>Purging analytics data is <strong>permanent</strong> and cannot be undone. This action will delete all collected analytics data, including:</p>
                <ul>
                    <li>Product search history</li>
                    <li>Page visit records</li>
                    <li>Order analytics</li>
                    <li>User activity tracking</li>
                </ul>
                <p>Only proceed if you want to start fresh with analytics collection.</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="<?php echo $status === 'success' ? 'success-box' : 'error-box'; ?>">
                    <p><?php echo $message; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="clean_analytics.php" onsubmit="return confirm('Are you absolutely sure you want to delete this analytics data? This action CANNOT be undone.');">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                
                <div class="form-group">
                    <label for="table">Select Table to Purge:</label>
                    <select name="table" id="table">
                        <option value="all">All Analytics Tables</option>
                        <option value="analytics">analytics</option>
                        <option value="analytics_extended">analytics_extended</option>
                        <option value="product_search_logs">product_search_logs</option>
                        <option value="product_visits">product_visits</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="confirmation">Type DELETE to confirm:</label>
                    <input type="text" name="confirmation" id="confirmation" placeholder="Type DELETE here" required>
                </div>
                
                <button type="submit">Purge Data</button>
            </form>
        </div>
        
        <div class="card">
            <h2>What Happens Next?</h2>
            
            <p>After purging the data:</p>
            <ol>
                <li>All analytics reports will show no data until new user activity is collected.</li>
                <li>New analytics data will start being collected immediately as users search for products, view product pages, and place orders.</li>
                <li>Reports will gradually populate with real user data rather than sample/test data.</li>
            </ol>
            
            <p>For best results, encourage real user activity on your site or wait for organic traffic to generate authentic analytics data.</p>
            
            <p><a href="reports.php" style="color: #007bff;">Return to Analytics Reports</a></p>
        </div>
    </div>
</body>
</html> 