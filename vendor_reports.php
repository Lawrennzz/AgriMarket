<?php
session_start();
include 'config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$vendor_id = null;
$user_id = $_SESSION['user_id'];

// Get vendor ID from the user ID
$vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ? AND deleted_at IS NULL";
$vendor_stmt = mysqli_prepare($conn, $vendor_query);
mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
mysqli_stmt_execute($vendor_stmt);
$vendor_result = mysqli_stmt_get_result($vendor_stmt);

if ($vendor_row = mysqli_fetch_assoc($vendor_result)) {
    $vendor_id = $vendor_row['vendor_id'];
} else {
    // Handle case where user is not a vendor
    $_SESSION['error'] = "Vendor profile not found.";
    header("Location: dashboard.php");
    exit();
}

// Set date range for reports (default: last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Get total sales in the date range
$sales_query = "SELECT SUM(oi.quantity * oi.price) as total_sales, 
                COUNT(DISTINCT o.order_id) as order_count 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE p.vendor_id = ?
                AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND o.status != 'cancelled'";
$sales_stmt = mysqli_prepare($conn, $sales_query);
mysqli_stmt_bind_param($sales_stmt, "iss", $vendor_id, $start_date, $end_date);
mysqli_stmt_execute($sales_stmt);
$sales_result = mysqli_stmt_get_result($sales_stmt);
$sales_data = mysqli_fetch_assoc($sales_result);

// Get top selling products
$top_products_query = "SELECT p.product_id, p.name, p.image_url, 
                      SUM(oi.quantity) as total_quantity, 
                      SUM(oi.quantity * oi.price) as total_revenue
                      FROM order_items oi
                      JOIN orders o ON oi.order_id = o.order_id
                      JOIN products p ON oi.product_id = p.product_id
                      WHERE p.vendor_id = ?
                      AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                      AND o.status != 'cancelled'
                      GROUP BY p.product_id
                      ORDER BY total_quantity DESC
                      LIMIT 5";
$top_products_stmt = mysqli_prepare($conn, $top_products_query);
mysqli_stmt_bind_param($top_products_stmt, "iss", $vendor_id, $start_date, $end_date);
mysqli_stmt_execute($top_products_stmt);
$top_products_result = mysqli_stmt_get_result($top_products_stmt);

// Get monthly sales for chart
$monthly_sales_query = "SELECT 
                       DATE_FORMAT(o.created_at, '%Y-%m') as month,
                       SUM(oi.quantity * oi.price) as monthly_sales
                       FROM order_items oi
                       JOIN orders o ON oi.order_id = o.order_id
                       JOIN products p ON oi.product_id = p.product_id
                       WHERE p.vendor_id = ?
                       AND o.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                       AND o.status != 'cancelled'
                       GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
                       ORDER BY month ASC";
$monthly_sales_stmt = mysqli_prepare($conn, $monthly_sales_query);
mysqli_stmt_bind_param($monthly_sales_stmt, "i", $vendor_id);
mysqli_stmt_execute($monthly_sales_stmt);
$monthly_sales_result = mysqli_stmt_get_result($monthly_sales_stmt);

$months = [];
$sales_values = [];

while ($row = mysqli_fetch_assoc($monthly_sales_result)) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $sales_values[] = $row['monthly_sales'];
}

// Get recent orders
$recent_orders_query = "SELECT o.order_id, o.created_at, o.total, o.status,
                       u.name as customer_name
                       FROM orders o
                       JOIN order_items oi ON o.order_id = oi.order_id
                       JOIN products p ON oi.product_id = p.product_id
                       JOIN users u ON o.user_id = u.user_id
                       WHERE p.vendor_id = ?
                       GROUP BY o.order_id
                       ORDER BY o.created_at DESC
                       LIMIT 10";
$recent_orders_stmt = mysqli_prepare($conn, $recent_orders_query);
mysqli_stmt_bind_param($recent_orders_stmt, "i", $vendor_id);
mysqli_stmt_execute($recent_orders_stmt);
$recent_orders_result = mysqli_stmt_get_result($recent_orders_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Reports - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .reports-header {
            margin-bottom: 2rem;
        }
        
        .date-filter {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .date-group {
            flex: 1;
            min-width: 200px;
        }
        
        .date-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .date-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
        }
        
        .date-submit {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            height: 38px;
        }
        
        .date-submit:hover {
            background: var(--primary-dark);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            color: var(--primary-color);
            border-radius: 50%;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }
        
        .stat-label {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .reports-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .reports-section h2 {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 1rem;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .product-stats {
            display: flex;
            color: var(--medium-gray);
            font-size: 0.9rem;
            gap: 1rem;
        }
        
        .chart-container {
            height: 300px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .orders-table th {
            font-weight: 500;
            color: var(--medium-gray);
            border-bottom: 2px solid var(--light-gray);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .status-processing {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .status-shipped {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .status-delivered {
            background: rgba(0, 150, 136, 0.1);
            color: #009688;
        }
        
        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .product-stats {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="reports-container">
        <div class="reports-header">
            <h1><i class="fas fa-chart-bar"></i> Vendor Reports</h1>
            <p>View your sales statistics and product performance.</p>
        </div>
        
        <form action="" method="GET" class="date-filter">
            <div class="date-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="date-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <button type="submit" class="date-submit">Apply Filter</button>
        </form>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($sales_data['total_sales'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo number_format($sales_data['order_count'] ?? 0); ?></div>
                <div class="stat-label">Orders Received</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <?php
                // Calculate average order value
                $avg_order_value = 0;
                if (($sales_data['order_count'] ?? 0) > 0) {
                    $avg_order_value = ($sales_data['total_sales'] ?? 0) / $sales_data['order_count'];
                }
                ?>
                <div class="stat-value">$<?php echo number_format($avg_order_value, 2); ?></div>
                <div class="stat-label">Avg. Order Value</div>
            </div>
        </div>
        
        <div class="reports-section">
            <h2>Monthly Sales</h2>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        
        <div class="reports-section">
            <h2>Top Selling Products</h2>
            <?php if (mysqli_num_rows($top_products_result) > 0): ?>
                <?php while ($product = mysqli_fetch_assoc($top_products_result)): ?>
                    <div class="product-item">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                        
                        <div class="product-details">
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-stats">
                                <span><i class="fas fa-box"></i> <?php echo $product['total_quantity']; ?> units sold</span>
                                <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($product['total_revenue'], 2); ?> revenue</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No product sales data available for the selected period.</p>
            <?php endif; ?>
        </div>
        
        <div class="reports-section">
            <h2>Recent Orders</h2>
            <?php if (mysqli_num_rows($recent_orders_result) > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($recent_orders_result)): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>$<?php echo number_format($order['total'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent orders found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
    // Sales chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Monthly Sales',
                data: <?php echo json_encode($sales_values); ?>,
                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                borderColor: '#4CAF50',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: '#4CAF50',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.parsed.y.toFixed(2);
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });
    </script>
</body>
</html> 