<?php
include 'config.php';
require_once 'classes/AuditLog.php';

// Check if user is logged in and has appropriate permissions (vendor or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'vendor' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Initialize the audit logger
$auditLogger = new AuditLog();

// Check if product ID is provided
if (!isset($_GET['id'])) {
    header("Location: view_product.php?error=No product ID provided");
    exit();
}

$product_id = intval($_GET['id']);

// Fetch product details
$product_query = "SELECT p.*, v.user_id as vendor_user_id FROM products p JOIN vendors v ON p.vendor_id = v.vendor_id WHERE p.product_id = ? AND p.deleted_at IS NULL";
$stmt = mysqli_prepare($conn, $product_query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$product_result = mysqli_stmt_get_result($stmt);

if ($product_row = mysqli_fetch_assoc($product_result)) {
    // Check if user is allowed to delete this product (vendor owns it or user is admin/staff)
    if ($_SESSION['role'] === 'vendor' && $_SESSION['user_id'] != $product_row['vendor_user_id']) {
        header("Location: view_product.php?error=You do not have permission to delete this product");
        exit();
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Soft delete the product by setting deleted_at timestamp
        $delete_query = "UPDATE products SET deleted_at = NOW() WHERE product_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            // Log the deletion in audit logs
            $auditLogger->log(
                $_SESSION['user_id'],
                'delete',
                'products',
                $product_id,
                [
                    'product_name' => $product_row['name'],
                    'price' => $product_row['price'],
                    'deleted_by' => $_SESSION['username'] ?? $_SESSION['user_id'],
                    'role' => $_SESSION['role']
                ]
            );
            
            // Commit the transaction
            mysqli_commit($conn);
            
            // Set success message in session
            $_SESSION['success_message'] = "Product deleted successfully";
            header("Location: view_product.php");
            exit();
        } else {
            throw new Exception("Failed to delete product");
        }
    } catch (Exception $e) {
        // Rollback the transaction
        mysqli_rollback($conn);
        header("Location: view_product.php?error=" . urlencode($e->getMessage()));
        exit();
    }
    
    mysqli_stmt_close($delete_stmt);
} else {
    header("Location: view_product.php?error=Product not found");
}

mysqli_stmt_close($stmt);
exit(); 