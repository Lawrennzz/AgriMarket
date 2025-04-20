<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!empty($search_query)) {
    // Search products
    $query = "SELECT p.*, v.business_name as vendor_name 
              FROM products p 
              JOIN vendors v ON p.vendor_id = v.vendor_id 
              WHERE p.name LIKE ? OR p.description LIKE ?";
    $stmt = $conn->prepare($query);
    $search_param = "%$search_query%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $product_ids = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
        $product_ids[] = $row['product_id'];
    }
    
    // Log the search with all found product IDs
    if (!empty($product_ids)) {
        logProductSearch($conn, $search_query, $product_ids);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - AgriMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
        
        <?php if (empty($results)): ?>
            <div class="alert alert-info">
                No products found matching your search.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($results as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                                <p class="card-text">
                                    <small class="text-muted">Vendor: <?php echo htmlspecialchars($product['vendor_name']); ?></small>
                                </p>
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 