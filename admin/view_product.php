<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is staff/admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID";
    header("Location: products.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    error_log("Delete request received for product ID: " . $product_id);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Soft delete the product
        $delete_query = "UPDATE products 
                        SET deleted_at = CURRENT_TIMESTAMP 
                        WHERE product_id = ?";
        
        $delete_stmt = $conn->prepare($delete_query);
        if (!$delete_stmt) {
            throw new Exception("Error preparing delete statement: " . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $product_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("Error executing delete statement: " . $delete_stmt->error);
        }
        
        // Log the deletion
        $log_query = "INSERT INTO admin_logs 
                     (admin_id, action, details, created_at) 
                     VALUES (?, 'delete_product', ?, CURRENT_TIMESTAMP)";
        
        $log_stmt = $conn->prepare($log_query);
        if (!$log_stmt) {
            throw new Exception("Error preparing log statement: " . $conn->error);
        }
        
        $details = "Deleted product ID: " . $product_id;
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        if (!$log_stmt->execute()) {
            throw new Exception("Error logging deletion: " . $log_stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        error_log("Successfully deleted product ID: " . $product_id);
        $_SESSION['success'] = "Product deleted successfully";
        header("Location: products.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error deleting product ID " . $product_id . ": " . $e->getMessage());
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
    }
}

// Fetch product details
$query = "SELECT p.*, c.name as category_name, v.business_name as vendor_name 
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.category_id
          LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
          WHERE p.product_id = ? AND p.deleted_at IS NULL";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = "Error preparing statement: " . $conn->error;
    header("Location: products.php");
    exit();
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Product not found";
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

$page_title = "View Product - " . $product['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>View Product</h1>
            <div class="action-buttons">
                <a href="edit_product.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Product
                </a>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="delete_product" value="1">
                    <button type="submit" class="btn btn-danger" 
                            onclick="return confirm('Are you sure you want to delete this product?')">
                        <i class="fas fa-trash"></i> Delete Product
                    </button>
                </form>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="product-details">
            <div class="row">
                <div class="col-md-6">
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="img-fluid">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="product-info">
                        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                        <p class="vendor">Vendor: <?php echo htmlspecialchars($product['vendor_name']); ?></p>
                        <p class="category">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
                        <p class="price">Price: $<?php echo number_format($product['price'], 2); ?></p>
                        <p class="stock">Stock: <?php echo $product['stock']; ?></p>
                        <p class="packaging">Packaging: <?php echo htmlspecialchars($product['packaging']); ?></p>
                        <div class="description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/admin_footer.php'; ?>
</body>
</html> 