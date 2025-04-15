<?php
include 'config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Get categories for dropdown
$categories_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $user_id = $_SESSION['user_id'];
    
    // Fetch the actual vendor_id from the vendors table
    $vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_query);
    mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
    mysqli_stmt_execute($vendor_stmt);
    $vendor_result = mysqli_stmt_get_result($vendor_stmt);

    if ($vendor_row = mysqli_fetch_assoc($vendor_result)) {
        $vendor_id = $vendor_row['vendor_id'];
    } else {
        $error_message = "Vendor profile not found. Please create a vendor profile first.";
    }

    // Validate inputs
    if (empty($error_message) && (empty($name) || empty($description) || $price <= 0 || $stock < 0 || $category_id <= 0)) {
        $error_message = "Please fill all required fields with valid values.";
    }

    if (empty($error_message)) {
        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = 'uploads/products/' . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                } else {
                    $error_message = "Failed to upload image. Please try again.";
                }
            } else {
                $error_message = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP";
            }
        }
    }
        
    if (empty($error_message)) {
        // Insert product into database
        $query = "INSERT INTO products (vendor_id, name, description, price, stock, packaging, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        $packaging = ''; // Since packaging is not provided in the form
        mysqli_stmt_bind_param($stmt, 'issiiiss', $vendor_id, $name, $description, $price, $stock, $packaging, $category_id, $image_url);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Product added successfully!";
            // Clear form
            $_POST = array();
        } else {
            $error_message = "Failed to add product. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Product - AgriMarket</title>
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

        .price-input {
            position: relative;
        }

        .price-input::before {
            content: '$';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
        }

        .price-input input {
            padding-left: 2rem;
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

    <div class="container">
        <div class="upload-container">
            <div class="form-header">
                <h1 class="form-title">Add New Product</h1>
                <p class="form-subtitle">Fill in the details below to list your product</p>
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
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description*</label>
                            <textarea id="description" name="description" class="form-control" 
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="category">Category*</label>
                            <select id="category" name="category_id" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php while ($category = mysqli_fetch_assoc($categories_query)): ?>
                                    <option value="<?php echo $category['category_id']; ?>"
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="price">Price*</label>
                            <div class="price-input">
                                <input type="number" id="price" name="price" class="form-control" 
                                       min="0.01" step="0.01" 
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="stock">Stock Quantity*</label>
                            <input type="number" id="stock" name="stock" class="form-control" 
                                   min="0" value="<?php echo htmlspecialchars($_POST['stock'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <div class="image-preview" id="imagePreview">
                                <div class="image-preview-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload image</div>
                                </div>
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

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Add Product
                </button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Image preview functionality
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const previewPlaceholder = imagePreview.querySelector('.image-preview-placeholder');

        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewPlaceholder.style.display = 'none';
                    
                    let img = imagePreview.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        imagePreview.appendChild(img);
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                previewPlaceholder.style.display = 'flex';
                const img = imagePreview.querySelector('img');
                if (img) {
                    img.remove();
                }
            }
        });

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const price = document.getElementById('price').value;
            const stock = document.getElementById('stock').value;

            if (parseFloat(price) <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
            }

            if (parseInt(stock) < 0) {
                e.preventDefault();
                alert('Stock cannot be negative');
            }
        });
    </script>
</body>
</html>