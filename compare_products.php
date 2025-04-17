<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get comparison products from GET parameters or session
$compare_products = [];
if (isset($_GET['products'])) {
    $product_ids = explode(',', $_GET['products']);
    // Sanitize IDs
    foreach ($product_ids as $id) {
        if (is_numeric($id)) {
            $compare_products[] = (int)$id;
        }
    }
} elseif (isset($_SESSION['compare_products'])) {
    $compare_products = $_SESSION['compare_products'];
}

// Store in session
$_SESSION['compare_products'] = $compare_products;

// Add product to comparison
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    
    // Check if product exists
    $check_query = "SELECT product_id FROM products WHERE product_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $product_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0 && !in_array($product_id, $compare_products) && count($compare_products) < 4) {
        $compare_products[] = $product_id;
        $_SESSION['compare_products'] = $compare_products;
        $success_message = "Product added to comparison.";
    } elseif (in_array($product_id, $compare_products)) {
        $error_message = "Product is already in comparison.";
    } elseif (count($compare_products) >= 4) {
        $error_message = "You can compare up to 4 products at a time. Please remove a product before adding another.";
    }
    mysqli_stmt_close($check_stmt);
    
    // Redirect to remove GET parameters
    header('Location: compare_products.php');
    exit();
}

// Remove product from comparison
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    $key = array_search($remove_id, $compare_products);
    
    if ($key !== false) {
        unset($compare_products[$key]);
        $compare_products = array_values($compare_products); // Reindex array
        $_SESSION['compare_products'] = $compare_products;
        $success_message = "Product removed from comparison.";
    }
    
    // Redirect to remove GET parameters
    header('Location: compare_products.php');
    exit();
}

// Clear all products from comparison
if (isset($_GET['clear'])) {
    $compare_products = [];
    $_SESSION['compare_products'] = $compare_products;
    $success_message = "Comparison cleared.";
    
    // Redirect to remove GET parameters
    header('Location: compare_products.php');
    exit();
}

// Fetch product details if there are products to compare
$products = [];
if (!empty($compare_products)) {
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($compare_products) - 1) . '?';
    
    $products_query = "
        SELECT p.*, c.name AS category_name, v.vendor_id, u.name AS vendor_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
        LEFT JOIN users u ON v.user_id = u.user_id
        WHERE p.product_id IN ($placeholders)
    ";
    
    $products_stmt = mysqli_prepare($conn, $products_query);
    
    if ($products_stmt) {
        // Dynamically bind parameters
        $types = str_repeat('i', count($compare_products));
        $products_stmt->bind_param($types, ...$compare_products);
        
        mysqli_stmt_execute($products_stmt);
        $result = mysqli_stmt_get_result($products_stmt);
        
        while ($product = mysqli_fetch_assoc($result)) {
            $products[$product['product_id']] = $product;
        }
        
        mysqli_stmt_close($products_stmt);
    } else {
        $error_message = "Error preparing statement: " . mysqli_error($conn);
    }
}

// Track this view in analytics
if (!empty($compare_products)) {
    $analytics_query = "INSERT INTO analytics (user_id, type, details, count) VALUES (?, 'compare', ?, 1)";
    $analytics_stmt = mysqli_prepare($conn, $analytics_query);
    
    if ($analytics_stmt) {
        $details = 'Products: ' . implode(',', $compare_products);
        mysqli_stmt_bind_param($analytics_stmt, "is", $user_id, $details);
        mysqli_stmt_execute($analytics_stmt);
        mysqli_stmt_close($analytics_stmt);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compare Products - AgriMarket</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .compare-container {
            overflow-x: auto;
        }
        
        .compare-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .compare-table th, .compare-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .compare-table th {
            background-color: #f9f9f9;
            font-weight: 500;
            vertical-align: top;
            width: 150px;
        }
        
        .compare-table tr:last-child th,
        .compare-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-header {
            position: relative;
            text-align: center;
            padding-bottom: 1.5rem;
        }
        
        .product-header .remove-btn {
            position: absolute;
            top: 0;
            right: 0;
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .product-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .add-to-cart {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .add-to-cart:hover {
            background-color: var(--primary-dark);
        }
        
        .rating {
            color: #ffc107;
            margin-bottom: 0.5rem;
        }
        
        .stock {
            font-weight: 500;
        }
        
        .in-stock {
            color: #28a745;
        }
        
        .low-stock {
            color: #ffc107;
        }
        
        .out-of-stock {
            color: #dc3545;
        }
        
        .highlight {
            background-color: #e8f5e9;
        }
        
        .empty-compare {
            text-align: center;
            padding: 3rem 1rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .empty-compare i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-compare h2 {
            margin-bottom: 1rem;
        }
        
        .empty-compare p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        .empty-compare a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .empty-compare a:hover {
            background-color: var(--primary-dark);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .compare-actions {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: flex-end;
        }
        
        .compare-actions a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .clear-btn {
            color: #dc3545;
            background-color: #fff;
            border: 1px solid #dc3545;
            margin-right: 1rem;
        }
        
        .clear-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        
        .highlight-diff {
            background-color: rgba(255, 193, 7, 0.2);
        }
        
        .add-more-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-top: 2rem;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .add-more-section h2 {
            margin-bottom: 1rem;
            color: var(--dark-gray);
        }
        
        .add-more-section p {
            margin-bottom: 1.5rem;
            color: var(--medium-gray);
        }
        
        @media (max-width: 768px) {
            .compare-table th {
                width: 100px;
            }
            
            .product-image {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-balance-scale"></i> Compare Products</h1>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (count($compare_products) > 0): ?>
            <div class="compare-actions">
                <a href="compare_products.php?clear=1" class="clear-btn">
                    <i class="fas fa-trash"></i> Clear All
                </a>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add More Products
                </a>
            </div>
            
            <div class="compare-container">
                <table class="compare-table">
                    <tr>
                        <th></th>
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td class="product-header">
                                    <a href="compare_products.php?remove=<?php echo $product_id; ?>" class="remove-btn" title="Remove from comparison">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <img src="<?php echo htmlspecialchars($products[$product_id]['image_url']); ?>" alt="<?php echo htmlspecialchars($products[$product_id]['name']); ?>" class="product-image">
                                    <div class="product-name"><?php echo htmlspecialchars($products[$product_id]['name']); ?></div>
                                    <div class="product-price">$<?php echo number_format($products[$product_id]['price'], 2); ?></div>
                                    <a href="product_details.php?id=<?php echo $product_id; ?>" class="btn btn-secondary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    
                                    <?php if ($products[$product_id]['stock'] > 0): ?>
                                        <form method="post" action="cart.php" style="margin-top: 0.5rem;">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                            <button type="submit" class="add-to-cart">
                                                <i class="fas fa-shopping-cart"></i> Add to Cart
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Category</th>
                        <?php 
                        $categories = [];
                        foreach ($compare_products as $product_id): 
                            if (isset($products[$product_id])):
                                $categories[] = $products[$product_id]['category_name'];
                            endif;
                        endforeach;
                        $unique_categories = array_unique($categories);
                        $highlight_category = count($unique_categories) > 1;
                        ?>
                        
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td <?php echo $highlight_category ? 'class="highlight-diff"' : ''; ?>>
                                    <?php echo htmlspecialchars($products[$product_id]['category_name']); ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Vendor</th>
                        <?php 
                        $vendors = [];
                        foreach ($compare_products as $product_id): 
                            if (isset($products[$product_id])):
                                $vendors[] = $products[$product_id]['vendor_name'];
                            endif;
                        endforeach;
                        $unique_vendors = array_unique($vendors);
                        $highlight_vendor = count($unique_vendors) > 1;
                        ?>
                        
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td <?php echo $highlight_vendor ? 'class="highlight-diff"' : ''; ?>>
                                    <?php echo htmlspecialchars($products[$product_id]['vendor_name']); ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Price</th>
                        <?php 
                        $prices = [];
                        foreach ($compare_products as $product_id): 
                            if (isset($products[$product_id])):
                                $prices[] = $products[$product_id]['price'];
                            endif;
                        endforeach;
                        $min_price = min($prices);
                        ?>
                        
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td <?php echo $products[$product_id]['price'] == $min_price ? 'class="highlight"' : ''; ?>>
                                    $<?php echo number_format($products[$product_id]['price'], 2); ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Stock</th>
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td>
                                    <?php if ($products[$product_id]['stock'] > 10): ?>
                                        <span class="stock in-stock">In Stock (<?php echo $products[$product_id]['stock']; ?>)</span>
                                    <?php elseif ($products[$product_id]['stock'] > 0): ?>
                                        <span class="stock low-stock">Low Stock (<?php echo $products[$product_id]['stock']; ?> left)</span>
                                    <?php else: ?>
                                        <span class="stock out-of-stock">Out of Stock</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Description</th>
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($products[$product_id]['description'])); ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Specifications</th>
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td>
                                    <?php 
                                    if (isset($products[$product_id]['specifications']) && !empty($products[$product_id]['specifications'])) {
                                        $specs = json_decode($products[$product_id]['specifications'], true);
                                        if (is_array($specs) && !empty($specs)):
                                    ?>
                                        <ul>
                                            <?php foreach ($specs as $key => $value): ?>
                                                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No specifications available</p>
                                    <?php endif;
                                    } else { ?>
                                        <p>No specifications available</p>
                                    <?php } ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <th>Created At</th>
                        <?php foreach ($compare_products as $product_id): ?>
                            <?php if (isset($products[$product_id])): ?>
                                <td>
                                    <?php echo date('M d, Y', strtotime($products[$product_id]['created_at'])); ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-compare">
                <i class="fas fa-balance-scale"></i>
                <h2>No products to compare</h2>
                <p>Add products to compare their features side by side.</p>
                <a href="products.php">
                    <i class="fas fa-shopping-basket"></i> Browse Products
                </a>
            </div>
        <?php endif; ?>
        
        <?php if (count($compare_products) > 0 && count($compare_products) < 4): ?>
            <div class="add-more-section">
                <h2>Add More Products to Compare</h2>
                <p>You can compare up to 4 products. Add more to make a better comparison.</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Browse More Products
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html> 