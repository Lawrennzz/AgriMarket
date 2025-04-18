<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];

// Handle search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Validate sort parameter - only allow valid column names
$valid_sort_columns = ['product_id', 'name', 'price', 'stock', 'created_at', 'vendor_id', 'category_id'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_sort_columns) ? $_GET['sort'] : 'created_at';

// Validate order parameter
$order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build the base query
$query = "
    SELECT p.*, c.name as category_name, v.name as vendor_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN users v ON p.vendor_id = v.user_id
    WHERE 1=1
";

// Add search condition
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%' OR p.product_id LIKE '%$search%')";
}

// Add category filter
if (!empty($category)) {
    $category = mysqli_real_escape_string($conn, $category);
    $query .= " AND p.category_id = '$category'";
}

// Add status filter
if (!empty($status)) {
    // Status filter removed as product status doesn't exist in the database
    // Comment left to maintain code tracking
}

// Add sorting
$query .= " ORDER BY p.$sort $order";

// Count total products (for pagination) - improved version
$count_query = "
    SELECT COUNT(*) as total 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN users v ON p.vendor_id = v.user_id
    WHERE 1=1
";

// Add search condition to count query
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $count_query .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%' OR p.product_id LIKE '%$search%')";
}

// Add category filter to count query
if (!empty($category)) {
    $category = mysqli_real_escape_string($conn, $category);
    $count_query .= " AND p.category_id = '$category'";
}

// Add status filter to count query
if (!empty($status)) {
    // Status filter removed as product status doesn't exist in the database
    // Comment left to maintain code tracking
}

$count_result = mysqli_query($conn, $count_query);

// Add error handling for count query
if (!$count_result) {
    // Debug: Show the error and the query
    echo "Count Query Error: " . mysqli_error($conn);
    echo "<br>Query: " . $count_query;
    exit;
}

$count_row = mysqli_fetch_assoc($count_result);
$total_products = $count_row['total'];
$total_pages = ceil($total_products / $limit);

// Add pagination
$query .= " LIMIT $offset, $limit";

// Execute the query
$result = mysqli_query($conn, $query);

// Add error handling for main query
if (!$result) {
    // Debug: Show the error and the query
    echo "Main Query Error: " . mysqli_error($conn);
    echo "<br>Query: " . $query;
    exit;
}

// Fetch categories for the filter dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

$page_title = "View Products";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }
        
        .action-button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
        }
        
        .action-button i {
            margin-right: 5px;
        }
        
        .action-button:hover {
            background-color: #388E3C;
        }
        
        .filter-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .filter-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .filter-button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .filter-button.secondary {
            background-color: #6c757d;
        }
        
        .products-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            color: #333;
        }
        
        .products-table th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .products-table th a:hover {
            color: #4CAF50;
        }
        
        .products-table td {
            padding: 12px 15px;
            border-top: 1px solid #f2f2f2;
            vertical-align: middle;
        }
        
        .products-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            object-fit: cover;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
        }
        
        .product-category {
            font-size: 13px;
            color: #6c757d;
        }
        
        .product-vendor {
            font-size: 13px;
            color: #6c757d;
        }
        
        .product-price {
            font-weight: 500;
            color: #333;
        }
        
        .product-stock {
            font-weight: 500;
        }
        
        .stock-low {
            color: #dc3545;
        }
        
        .stock-medium {
            color: #fd7e14;
        }
        
        .stock-high {
            color: #28a745;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            font-size: 14px;
        }
        
        .btn-view {
            background-color: #17a2b8;
        }
        
        .btn-edit {
            background-color: #007bff;
        }
        
        .btn-delete {
            background-color: #dc3545;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-top: 1px solid #f2f2f2;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .pagination-links {
            display: flex;
            gap: 5px;
        }
        
        .pagination-link {
            display: inline-block;
            padding: 5px 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .pagination-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination-link.disabled {
            color: #6c757d;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .products-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Products</h1>
            <a href="add_product.php" class="action-button">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
        
        <div class="filter-container">
            <form action="" method="GET" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label" for="search">Search</label>
                    <input type="text" class="filter-control" id="search" name="search" placeholder="Search by name, description, ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="category">Category</label>
                    <select class="filter-control" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="sort">Sort By</label>
                    <select class="filter-control" id="sort" name="sort">
                        <option value="created_at" <?php echo $sort == 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                        <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="price" <?php echo $sort == 'price' ? 'selected' : ''; ?>>Price</option>
                        <option value="stock" <?php echo $sort == 'stock' ? 'selected' : ''; ?>>Stock</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="order">Order</label>
                    <select class="filter-control" id="order" name="order">
                        <option value="DESC" <?php echo $order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        <option value="ASC" <?php echo $order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-button">Apply Filters</button>
                    <a href="view_product.php" class="filter-button secondary">Reset</a>
                </div>
            </form>
        </div>
        
        <div class="products-container">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th width="60">Image</th>
                            <th>
                                <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=name&order=<?php echo $sort == 'name' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>">
                                    Product
                                    <?php if ($sort == 'name'): ?>
                                        <i class="fas fa-sort-<?php echo $order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>
                                <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=price&order=<?php echo $sort == 'price' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>">
                                    Price
                                    <?php if ($sort == 'price'): ?>
                                        <i class="fas fa-sort-<?php echo $order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=stock&order=<?php echo $sort == 'stock' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>">
                                    Stock
                                    <?php if ($sort == 'stock'): ?>
                                        <i class="fas fa-sort-<?php echo $order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = mysqli_fetch_assoc($result)): ?>
                            <?php
                            // Determine stock status
                            $stock_class = '';
                            if ($product['stock'] <= 5) {
                                $stock_class = 'stock-low';
                            } elseif ($product['stock'] <= 20) {
                                $stock_class = 'stock-medium';
                            } else {
                                $stock_class = 'stock-high';
                            }
                            
                            // Image path - corrected to use image_url instead of image
                            $image_path = !empty($product['image_url']) ? $product['image_url'] : 'images/placeholder-product.jpg';
                            ?>
                            <tr>
                                <td>
                                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                                </td>
                                <td>
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-id">ID: <?php echo $product['product_id']; ?></div>
                                </td>
                                <td class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td class="product-vendor"><?php echo htmlspecialchars($product['vendor_name'] ?? 'Unknown'); ?></td>
                                <td class="product-price">$<?php echo number_format($product['price'], 2); ?></td>
                                <td class="product-stock <?php echo $stock_class; ?>"><?php echo $product['stock']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="btn-icon btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-icon btn-delete" title="Delete" onclick="confirmDelete(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo min($offset + 1, $total_products); ?> to <?php echo min($offset + mysqli_num_rows($result), $total_products); ?> of <?php echo $total_products; ?> products
                    </div>
                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No products found</h3>
                    <p>No products match your search criteria. Try adjusting your filters or add a new product.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function confirmDelete(productId, productName) {
            if (confirm("Are you sure you want to delete the product: " + productName + "?")) {
                window.location.href = "delete_product.php?id=" + productId;
            }
        }
    </script>
</body>
</html> 