<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get date range for reports
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Initialize arrays for results
$mostSearchedProducts = [];
$mostViewedProducts = [];
$mostOrderedProducts = [];

// Function to safely execute queries
function executeQuery($conn, $query, $types, $params) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error);
        error_log("Query was: " . $query);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}

// Get most searched products
$searchQuery = "SELECT 
    p.name, 
    p.product_id,
    COUNT(*) as search_count,
    GROUP_CONCAT(DISTINCT psl.search_term) as search_terms
FROM 
    products p
    INNER JOIN product_search_logs psl ON psl.product_ids LIKE CONCAT('%', p.product_id, '%')
WHERE 
    DATE(psl.created_at) BETWEEN ? AND ?
GROUP BY 
    p.product_id, p.name
ORDER BY 
    search_count DESC
LIMIT 10";

$mostSearchedProducts = executeQuery($conn, $searchQuery, "ss", [$start_date, $end_date]);

// Get most viewed products
$viewQuery = "SELECT 
    p.name, 
    p.product_id,
    COUNT(*) as view_count
FROM 
    products p
    INNER JOIN analytics a ON a.product_id = p.product_id
WHERE 
    a.type = 'visit' 
    AND DATE(a.created_at) BETWEEN ? AND ?
GROUP BY 
    p.product_id, p.name
ORDER BY 
    view_count DESC
LIMIT 10";

$mostViewedProducts = executeQuery($conn, $viewQuery, "ss", [$start_date, $end_date]);

// Most ordered products
$orders_query = "
    SELECT 
        p.product_id, 
        p.name, 
        p.description, 
        SUM(oi.quantity) as order_count,
        COUNT(DISTINCT o.order_id) as total_orders
    FROM 
        order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
    WHERE 
        DATE(o.created_at) BETWEEN ? AND ?
        AND o.status = 'delivered'
        AND o.deleted_at IS NULL
    GROUP BY 
        p.product_id, p.name, p.description
    ORDER BY 
        order_count DESC
    LIMIT 10";

$most_ordered = executeQuery($conn, $orders_query, "ss", [$start_date, $end_date]);

// Debug information
error_log("Search Products Count: " . count($mostSearchedProducts));
error_log("Viewed Products Count: " . count($mostViewedProducts));
error_log("Ordered Products Count: " . count($most_ordered));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        .card {
            background: white;
            border-radius: 10px;
            border: none;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-header {
            background: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        .card-header h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .card-body {
            padding: 1.5rem;
        }
        .table {
            margin: 0;
        }
        .table th {
            border-top: none;
            font-weight: 600;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 0.5rem 1.5rem;
        }
        .date-input {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include_once '../sidebar.php'; ?>

    <div class="main-content">
        <h2 class="mb-4">Reports & Analytics</h2>
        
        <!-- Date Range Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control date-input" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control date-input" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Most Searched Products -->
        <div class="card">
            <div class="card-header">
                <h4>
                    <i class="fas fa-search me-2"></i>
                    Most Searched Products
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Search Count</th>
                                <th>Search Terms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mostSearchedProducts)): ?>
                                <?php foreach ($mostSearchedProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['search_count']; ?></td>
                                    <td><?php echo htmlspecialchars($product['search_terms']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No search data available for the selected period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Most Viewed Products -->
        <div class="card">
            <div class="card-header">
                <h4>
                    <i class="fas fa-eye me-2"></i>
                    Most Viewed Products
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>View Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mostViewedProducts)): ?>
                                <?php foreach ($mostViewedProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['view_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">No view data available for the selected period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Most Ordered Products -->
        <div class="card">
            <div class="card-header">
                <h4>
                    <i class="fas fa-shopping-cart me-2"></i>
                    Most Ordered Products
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Order Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($most_ordered)): ?>
                                <?php foreach ($most_ordered as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['order_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">No order data available for the selected period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 