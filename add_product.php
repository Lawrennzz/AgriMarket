<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$page_title = "Add Product";
$success_message = "";
$error_message = "";

// Fetch categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Fetch vendors for dropdown
$vendors_query = "SELECT v.vendor_id, u.name FROM vendors v JOIN users u ON v.user_id = u.user_id ORDER BY u.name";
$vendors_result = mysqli_query($conn, $vendors_query);
$vendors = [];
while ($row = mysqli_fetch_assoc($vendors_result)) {
    $vendors[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    $packaging = isset($_POST['packaging']) ? trim($_POST['packaging']) : '';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if ($vendor_id <= 0) {
        $errors[] = "Please select a vendor";
    }
    
    // Process image upload if there are no validation errors
    $image_url = '';
    
    if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/products/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($image_file_type, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed";
        } else if ($_FILES['image']['size'] > 2000000) { // 2MB
            $errors[] = "Image file is too large. Maximum size is 2MB";
        } else if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = $target_file;
        } else {
            $errors[] = "Failed to upload image";
        }
    }
    
    // Insert new product if there are no errors
    if (empty($errors)) {
        $query = "
            INSERT INTO products (
                name, description, price, stock, category_id, vendor_id, 
                packaging, image_url, featured, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt, 
                "ssdiiissl", 
                $name, $description, $price, $stock, $category_id, $vendor_id, 
                $packaging, $image_url, $featured
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $product_id = mysqli_insert_id($conn);
                
                // Log the action
                $log_query = "
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
                    VALUES (?, 'added product', 'products', ?, ?)
                ";
                
                $log_stmt = mysqli_prepare($conn, $log_query);
                $log_details = "Product: $name";
                
                if ($log_stmt) {
                    mysqli_stmt_bind_param($log_stmt, "iis", $_SESSION['user_id'], $product_id, $log_details);
                    mysqli_stmt_execute($log_stmt);
                }
                
                $success_message = "Product added successfully!";
                
                // Reset form fields
                $name = $description = $packaging = '';
                $price = $stock = 0;
                $category_id = $vendor_id = 0;
                $featured = 0;
            } else {
                $error_message = "Error adding product: " . mysqli_stmt_error($stmt);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
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
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 15px;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 15px;
            background-color: white;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-right: 10px;
        }
        
        .form-check-label {
            margin-bottom: 0;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #388E3C;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -10px;
            margin-left: -10px;
        }
        
        .form-col {
            flex: 0 0 100%;
            max-width: 100%;
            padding-right: 10px;
            padding-left: 10px;
            box-sizing: border-box;
        }
        
        @media (min-width: 768px) {
            .form-col-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .form-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'staff_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Add New Product</h1>
        </div>
        
        <div class="form-container">
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
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="name">Product Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($name ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="price">Price (USD) *</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($price ?? '0'); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($category_id) && $category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="stock">Stock Quantity *</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" required value="<?php echo htmlspecialchars($stock ?? '0'); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Vendor *</label>
                            <select class="form-select" id="vendor_id" name="vendor_id" required>
                                <option value="">Select Vendor</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo $vendor['vendor_id']; ?>" <?php echo (isset($vendor_id) && $vendor_id == $vendor['vendor_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendor['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-col form-col-6">
                        <div class="form-group">
                            <label class="form-label" for="packaging">Packaging</label>
                            <input type="text" class="form-control" id="packaging" name="packaging" value="<?php echo htmlspecialchars($packaging ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Product Description</label>
                    <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="image">Product Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <small class="form-text text-muted">Recommended size: 800x800 pixels, max file size: 2MB</small>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1" <?php echo (isset($featured) && $featured) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="featured">Feature this product on the homepage</label>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <a href="view_product.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 