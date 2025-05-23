<?php
include 'config.php';
require_once 'classes/AuditLog.php';

// Check if user is logged in and has appropriate permissions (vendor or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'vendor' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// Initialize the audit logger
$auditLogger = new AuditLog();

$success_message = '';
$error_message = '';

// Check if product ID is provided
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Fetch product details
    $product_query = "SELECT p.*, v.user_id as vendor_user_id FROM products p JOIN vendors v ON p.vendor_id = v.vendor_id WHERE p.product_id = ? AND p.deleted_at IS NULL";
    $stmt = mysqli_prepare($conn, $product_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $product_result = mysqli_stmt_get_result($stmt);

    if ($product_row = mysqli_fetch_assoc($product_result)) {
        // Check if user is allowed to edit this product (vendor owns it or user is admin)
        if ($_SESSION['role'] === 'vendor' && $_SESSION['user_id'] != $product_row['vendor_user_id']) {
            header("Location: products.php");
            exit();
        }
        
        // Store original product data for audit logging
        $original_product = $product_row;
        
        // Product details
        $name = $product_row['name'];
        $description = $product_row['description'];
        $price = $product_row['price'];
        $stock = $product_row['stock'];
        $packaging = $product_row['packaging'];
        $category_id = $product_row['category_id'];
        $image_url = $product_row['image_url'];
    } else {
        $error_message = "Product not found.";
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "No product ID provided.";
}

// Handle form submission for editing the product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $packaging = mysqli_real_escape_string($conn, $_POST['packaging'] ?? '');
    $category_id = intval($_POST['category_id']);

    // Handle image upload
    $image_url = $product_row['image_url']; // Keep the existing image URL by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/products/' . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = $upload_path; // Update the image URL if upload is successful
            } else {
                $error_message = "Failed to upload image. Please try again.";
            }
        } else {
            $error_message = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP";
        }
    }

    // Update product in the database
    $update_query = "UPDATE products SET name = ?, description = ?, price = ?, stock = ?, packaging = ?, category_id = ?, image_url = ? WHERE product_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ssdiissi', $name, $description, $price, $stock, $packaging, $category_id, $image_url, $product_id);

    if (mysqli_stmt_execute($update_stmt)) {
        // Prepare changes for audit log
        $changes = [];
        if ($original_product['name'] !== $name) $changes['name'] = ['from' => $original_product['name'], 'to' => $name];
        if ($original_product['description'] !== $description) $changes['description'] = ['from' => $original_product['description'], 'to' => $description];
        if (floatval($original_product['price']) !== $price) $changes['price'] = ['from' => $original_product['price'], 'to' => $price];
        if (intval($original_product['stock']) !== $stock) $changes['stock'] = ['from' => $original_product['stock'], 'to' => $stock];
        if ($original_product['packaging'] !== $packaging) $changes['packaging'] = ['from' => $original_product['packaging'], 'to' => $packaging];
        if (intval($original_product['category_id']) !== $category_id) $changes['category_id'] = ['from' => $original_product['category_id'], 'to' => $category_id];
        if ($original_product['image_url'] !== $image_url) $changes['image_url'] = ['from' => $original_product['image_url'], 'to' => $image_url];
        
        // Log the update in audit logs
        if (!empty($changes)) {
            $auditLogger->log(
                $_SESSION['user_id'],
                'update',
                'products',
                $product_id,
                [
                    'changes' => $changes,
                    'updated_by' => $_SESSION['username'] ?? $_SESSION['user_id'],
                    'role' => $_SESSION['role']
                ]
            );
        }
        
        $success_message = "Product updated successfully!";
    } else {
        $error_message = "Failed to update product. Please try again.";
    }
    mysqli_stmt_close($update_stmt);
}

// Handle product deletion
if (isset($_POST['delete_product'])) {
    $delete_query = "DELETE FROM products WHERE product_id = ?";
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
                'product_name' => $original_product['name'],
                'price' => $original_product['price'],
                'deleted_by' => $_SESSION['username'] ?? $_SESSION['user_id'],
                'role' => $_SESSION['role']
            ]
        );
        
        $success_message = "Product deleted successfully!";
        header("Location: products.php"); // Redirect to product list after deletion
        exit();
    } else {
        $error_message = "Failed to delete product. Please try again.";
    }
    mysqli_stmt_close($delete_stmt);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: var(--medium-gray);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .form-section {
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .image-preview {
            width: 100%;
            height: 300px;
            border: 2px dashed var(--light-gray);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .image-preview-placeholder {
            color: var(--medium-gray);
            text-align: center;
        }

        .image-preview-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .btn-upload {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--light-gray);
            color: var(--dark-gray);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-upload:hover {
            background: var(--medium-gray);
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .upload-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="upload-container">
        <div class="form-header">
            <h1 class="form-title">Edit Product</h1>
            <p class="form-subtitle">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    Manage product details
                <?php else: ?>
                    Update the details of your product below
                <?php endif; ?>
            </p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label" for="name">Product Name*</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description*</label>
                        <textarea id="description" name="description" class="form-control" 
                                  required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="category">Category*</label>
                        <select id="category" name="category_id" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php
                            // Fetch categories for dropdown
                            $categories_query = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
                            while ($category = mysqli_fetch_assoc($categories_query)): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo ($category['category_id'] == $category_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="price">Price*</label>
                        <input type="number" id="price" name="price" class="form-control" 
                               value="<?php echo htmlspecialchars($price); ?>" min="0.01" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="stock">Stock Quantity*</label>
                        <input type="number" id="stock" name="stock" class="form-control" 
                               value="<?php echo htmlspecialchars($stock); ?>" min="0" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="packaging">Packaging</label>
                        <input type="text" id="packaging" name="packaging" class="form-control" 
                               value="<?php echo htmlspecialchars($packaging); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Product Image</label>
                        <div class="image-preview" id="imagePreview">
                            <?php if ($image_url): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Product Image">
                            <?php else: ?>
                                <div class="image-preview-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload image</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="file-input-wrapper">
                            <input type="file" name="image" id="image" class="file-input" 
                                   accept=".jpg,.jpeg,.png,.webp">
                            <div class="btn-upload">
                                <i class="fas fa-upload"></i>
                                Choose Image
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" name="edit_product" class="btn btn-primary" style="width: 100%;">
                Update Product
            </button>
        </form>

        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
            <button type="submit" name="delete_product" class="btn btn-danger" style="width: 100%;">Delete Product</button>
        </form>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Image preview functionality
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" alt="Product Image">`;
                }
                reader.readAsDataURL(file);
            } else {
                imagePreview.innerHTML = `<div class="image-preview-placeholder">
                                              <i class="fas fa-cloud-upload-alt"></i>
                                              <div>Click to upload image</div>
                                          </div>`;
            }
        });
    </script>
</body>
</html>