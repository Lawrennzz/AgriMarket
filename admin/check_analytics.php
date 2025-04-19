<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();

// Function to check table structure
function checkTableStructure($conn, $table) {
    $query = "DESCRIBE $table";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return false;
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get record count
function getRecordCount($conn, $table) {
    $query = "SELECT COUNT(*) as count FROM $table";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return 0;
    }
    return mysqli_fetch_assoc($result)['count'];
}

// Check analytics tables
$tables = [
    'analytics' => [
        'name' => 'Basic Analytics',
        'structure' => checkTableStructure($conn, 'analytics'),
        'count' => getRecordCount($conn, 'analytics')
    ],
    'analytics_extended' => [
        'name' => 'Extended Analytics',
        'structure' => checkTableStructure($conn, 'analytics_extended'),
        'count' => getRecordCount($conn, 'analytics_extended')
    ]
];

// Get sample data
$basic_analytics_query = "SELECT * FROM analytics ORDER BY recorded_at DESC LIMIT 5";
$basic_result = mysqli_query($conn, $basic_analytics_query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Tables Diagnostics - AgriMarket</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .diagnostic-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .table-info {
            margin-bottom: 30px;
        }
        
        .structure-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .structure-table th, .structure-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .structure-table th {
            background-color: #f5f5f5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-good {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .data-preview {
            margin-top: 20px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <h1>Analytics Tables Diagnostics</h1>
        
        <div class="diagnostic-section">
            <h2>Analytics Tables Structure</h2>
            
            <?php foreach ($tables as $table => $info): ?>
                <div class="table-info">
                    <h3><?php echo $info['name']; ?> Table Structure</h3>
                    <?php if ($info['structure']): ?>
                        <table class="structure-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($info['structure'] as $field): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($field['Field']); ?></td>
                                        <td><?php echo htmlspecialchars($field['Type']); ?></td>
                                        <td><?php echo htmlspecialchars($field['Null']); ?></td>
                                        <td><?php echo htmlspecialchars($field['Key']); ?></td>
                                        <td><?php echo htmlspecialchars($field['Default'] ?? 'NULL'); ?></td>
                                        <td><?php echo htmlspecialchars($field['Extra']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            Records: <span class="status-badge <?php echo $info['count'] > 0 ? 'status-good' : 'status-warning'; ?>">
                                <?php echo number_format($info['count']); ?> records
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="status-badge status-error">Table not found or not accessible</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="diagnostic-section">
            <h2>Analytics Data</h2>
            <?php if ($basic_result && mysqli_num_rows($basic_result) > 0): ?>
                <div class="data-preview">
                    <table class="structure-table">
                        <thead>
                            <tr>
                                <th>analytic_id</th>
                                <th>type</th>
                                <th>product_id</th>
                                <th>count</th>
                                <th>recorded_at</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($basic_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['analytic_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                    <td><?php echo htmlspecialchars($row['recorded_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No analytics data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html> 