<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$success_message = '';
$error_message = '';
$product = null;

// Check if product ID is provided
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    // Fetch product details
    $product_query = "SELECT p.*, c.name as category_name, v.business_name as vendor_name 
                     FROM products p 
                     JOIN categories c ON p.category_id = c.category_id
                     JOIN vendors v ON p.vendor_id = v.vendor_id
                     WHERE p.product_id = ?";
    
    $stmt = mysqli_prepare($conn, $product_query);
    
    if (!$stmt) {
        $error_message = "Database error: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $product = mysqli_fetch_assoc($result);
        } else {
            $error_message = "Product not found.";
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $error_message = "No product ID provided.";
}

// Handle form submission for updating product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $packaging = isset($_POST['packaging']) ? trim(mysqli_real_escape_string($conn, $_POST['packaging'])) : '';
    
    // Validate input
    if (empty($name) || empty($description) || $price <= 0) {
        $error_message = "Please fill all required fields with valid values.";
    } else {
        // Handle image upload if provided
        $image_url = $product['image_url']; // Default to existing image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = 'uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                } else {
                    $error_message = "Failed to upload image. Please try again.";
                }
            } else {
                $error_message = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP";
            }
        }
        
        if (empty($error_message)) {
            // Update product in database
            $update_query = "UPDATE products 
                            SET name = ?, description = ?, price = ?, 
                                stock = ?, category_id = ?, packaging = ?, 
                                image_url = ?, updated_at = NOW() 
                            WHERE product_id = ?";
                            
            $update_stmt = mysqli_prepare($conn, $update_query);
            
            if (!$update_stmt) {
                $error_message = "Database error: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param(
                    $update_stmt, 
                    "ssdiissi", 
                    $name, $description, $price, $stock, $category_id, $packaging, $image_url, $product_id
                );
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Log this action to audit log
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) 
                                 VALUES (?, 'update', 'products', ?, ?)";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    
                    if ($log_stmt) {
                        $details = "Staff member updated product details";
                        mysqli_stmt_bind_param($log_stmt, "iis", $_SESSION['user_id'], $product_id, $details);
                        mysqli_stmt_execute($log_stmt);
                    }
                    
                    $success_message = "Product updated successfully!";
                    
                    // Refresh product data
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if ($result && mysqli_num_rows($result) > 0) {
                        $product = mysqli_fetch_assoc($result);
                    }
                } else {
                    $error_message = "Failed to update product: " . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

// Fetch all categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

$page_title = "Edit Product";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .form-section {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .image-preview {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #555;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Edit Product</h1>
        </div>
        
        <div class="actions-bar">
            <a href="products.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($product): ?>
            <div class="form-container">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-main">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="price" class="form-label">Price ($)</label>
                                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock" class="form-label">Stock Quantity</label>
                                    <input type="number" id="stock" name="stock" class="form-control" min="0" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-sidebar">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select id="category_id" name="category_id" class="form-control" required>
                                        <?php if ($categories_result): ?>
                                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                                <option value="<?php echo $category['category_id']; ?>" <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="packaging" class="form-label">Packaging (Optional)</label>
                                    <input type="text" id="packaging" name="packaging" class="form-control" value="<?php echo htmlspecialchars($product['packaging'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="image" class="form-label">Product Image</label>
                                    <div class="image-preview">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product Image">
                                        <?php else: ?>
                                            <div class="no-image">No image available</div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" id="image" name="image" class="form-control">
                                    <small>Leave empty to keep current image. Upload a new image to replace.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Vendor</label>
                                    <div class="form-static">
                                        <?php echo htmlspecialchars($product['vendor_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_product" class="submit-btn">Update Product</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Product not found or you don't have permission to edit it.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                const preview = document.querySelector('.image-preview');
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Product Preview">`;
                }
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html> 