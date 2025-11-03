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

// Get date range from GET parameters or use default (last 30 days)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Query to get most viewed products combining both tables
$query = "
    SELECT 
        p.product_id,
        p.name,
        p.price,
        p.image_url,
        c.name as category_name,
        v.name as vendor_name,
        (
            SELECT COUNT(*) 
            FROM analytics 
            WHERE product_id = p.product_id 
            AND type = 'visit'
            AND created_at BETWEEN ? AND ?
        ) as analytics_views,
        (
            SELECT COUNT(*) 
            FROM product_visits 
            WHERE product_id = p.product_id 
            AND created_at BETWEEN ? AND ?
        ) as product_visits,
        (
            SELECT COUNT(*) 
            FROM analytics 
            WHERE product_id = p.product_id 
            AND type = 'visit'
            AND created_at BETWEEN ? AND ?
        ) + (
            SELECT COUNT(*) 
            FROM product_visits 
            WHERE product_id = p.product_id 
            AND created_at BETWEEN ? AND ?
        ) as total_views
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
    GROUP BY p.product_id, p.name, p.price, p.image_url, c.name, v.name
    HAVING total_views > 0
    ORDER BY total_views DESC
    LIMIT 10
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ssssssss", 
    $start_date, $end_date,  // For analytics_views
    $start_date, $end_date,  // For product_visits
    $start_date, $end_date,  // For total_views analytics part
    $start_date, $end_date   // For total_views product_visits part
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$most_viewed_products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get total views for the period
$total_views_query = "
    SELECT 
    (
        (SELECT COUNT(*) FROM analytics 
         WHERE type = 'visit' AND created_at BETWEEN ? AND ?)
        +
        (SELECT COUNT(*) FROM product_visits 
         WHERE created_at BETWEEN ? AND ?)
    ) as total_views
";

$total_stmt = mysqli_prepare($conn, $total_views_query);
mysqli_stmt_bind_param($total_stmt, "ssss", $start_date, $end_date, $start_date, $end_date);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_views = mysqli_fetch_assoc($total_result)['total_views'];

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Viewed Products Report</h3>
                    <div class="card-tools">
                        <form method="GET" class="form-inline">
                            <div class="form-group mr-2">
                                <label for="start_date" class="mr-2">Start Date:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                            <div class="form-group mr-2">
                                <label for="end_date" class="mr-2">End Date:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $end_date; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-eye"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Views</span>
                                    <span class="info-box-number"><?php echo number_format($total_views); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-calendar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Date Range</span>
                                    <span class="info-box-number">
                                        <?php echo date('M d, Y', strtotime($start_date)); ?> - 
                                        <?php echo date('M d, Y', strtotime($end_date)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Vendor</th>
                                    <th>Price</th>
                                    <th>Analytics Views</th>
                                    <th>Product Visits</th>
                                    <th>Total Views</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($most_viewed_products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No view data found for the selected date range.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($most_viewed_products as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($product['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                             class="img-thumbnail mr-2" style="width: 50px; height: 50px;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">ID: <?php echo $product['product_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['vendor_name']); ?></td>
                                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                                            <td><?php echo number_format($product['analytics_views']); ?></td>
                                            <td><?php echo number_format($product['product_visits']); ?></td>
                                            <td><?php echo number_format($product['total_views']); ?></td>
                                            <td>
                                                <?php 
                                                $percentage = $total_views > 0 ? 
                                                    ($product['total_views'] / $total_views) * 100 : 0;
                                                echo number_format($percentage, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?> 