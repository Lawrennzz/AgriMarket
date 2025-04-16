<?php
include 'config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Check if product ID is provided
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Fetch product details
    $product_query = "SELECT * FROM products WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $product_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $product_result = mysqli_stmt_get_result($stmt);

    if ($product_row = mysqli_fetch_assoc($product_result)) {
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
        $success_message = "Product deleted successfully!";
        header("Location: products_list.php"); // Redirect to product list after deletion
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

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="upload-container">
        <h1 class="form-title">Edit Product</h1>

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
                <label class="form-label" for="image">Product Image</label>
                <input type="file" id="image" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <button type="submit" name="edit_product" class="btn btn-primary">Update Product</button>
        </form>

        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
            <button type="submit" name="delete_product" class="btn btn-danger">Delete Product</button>
        </form>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>