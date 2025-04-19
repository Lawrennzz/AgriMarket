<?php
include 'config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';
$bulk_success_count = 0;
$bulk_error_count = 0;
$bulk_errors = [];

// Get categories for dropdown
$categories_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
$categories = [];
while ($category = mysqli_fetch_assoc($categories_query)) {
    $categories[$category['category_id']] = $category['name'];
}
mysqli_data_seek($categories_query, 0); // Reset the pointer for later use

// Get vendor_id for the current user
    $user_id = $_SESSION['user_id'];
    $vendor_query = "SELECT vendor_id FROM vendors WHERE user_id = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_query);
    mysqli_stmt_bind_param($vendor_stmt, "i", $user_id);
    mysqli_stmt_execute($vendor_stmt);
    $vendor_result = mysqli_stmt_get_result($vendor_stmt);
$vendor_id = null;

    if ($vendor_row = mysqli_fetch_assoc($vendor_result)) {
        $vendor_id = $vendor_row['vendor_id'];
    } else {
        $error_message = "Vendor profile not found. Please create a vendor profile first.";
    }
mysqli_stmt_close($vendor_stmt);

// Handle single product upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'single') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $packaging = mysqli_real_escape_string($conn, $_POST['packaging'] ?? ''); // Added packaging field

    // Validate inputs
    if (empty($error_message)) {
        if (empty($name)) {
            $error_message = "Product name is required.";
        } elseif (empty($description)) {
            $error_message = "Description is required.";
        } elseif ($price <= 0) {
            $error_message = "Price must be greater than 0.";
        } elseif ($stock < 0) {
            $error_message = "Stock cannot be negative.";
        } elseif ($category_id <= 0) {
            $error_message = "Please select a valid category.";
        }
    }

    // Validate category_id exists
    if (empty($error_message)) {
        $category_check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
        mysqli_stmt_bind_param($category_check, "i", $category_id);
        mysqli_stmt_execute($category_check);
        $category_result = mysqli_stmt_get_result($category_check);
        if (mysqli_num_rows($category_result) === 0) {
            $error_message = "Invalid category selected.";
        }
        mysqli_stmt_close($category_check);
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
                
                // Ensure the uploads/products/ directory exists and is writable
                // Run: mkdir -p uploads/products && chmod 775 uploads/products && chown www-data:www-data uploads/products
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                } else {
                    $error_message = "Failed to upload image. Please try again.";
                }
            } else {
                $error_message = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP";
            }
        } else {
            $error_message = "Image is required.";
        }
    }
        
    if (empty($error_message) && !empty($image_url)) {
        // Insert product into database
        $query = "INSERT INTO products (vendor_id, name, description, price, stock, packaging, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issdisss', $vendor_id, $name, $description, $price, $stock, $packaging, $category_id, $image_url);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Product added successfully!";
            // Clear form
            $_POST = array();
        } else {
            // Log the detailed error for debugging
            $detailed_error = "Failed to add product: " . mysqli_error($conn) . " | Query: " . $query . " | Values: " . json_encode([$vendor_id, $name, $description, $price, $stock, $packaging, $category_id, $image_url]);
            error_log($detailed_error);
            $error_message = "Failed to add product. Please try again. (Error: " . htmlspecialchars(mysqli_error($conn)) . ")";
        }
        
        mysqli_stmt_close($stmt);
    } 
}

// Handle bulk CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'bulk') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext !== 'csv') {
            $error_message = "Please upload a valid CSV file.";
        } else {
            // Process CSV file
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                // Skip header row
                $header = fgetcsv($handle, 1000, ",");
                $required_columns = ["name", "description", "price", "stock", "category_id", "packaging"];
                $optional_columns = ["image_url", "image_file"];
                $found_columns = array_map('strtolower', $header);
                
                // Validate CSV structure
                $missing_columns = array_diff($required_columns, $found_columns);
                
                // Check if at least one of image_url or image_file is present
                if (!in_array('image_url', $found_columns) && !in_array('image_file', $found_columns)) {
                    $missing_columns[] = 'image_url or image_file';
                }
                
                if (!empty($missing_columns)) {
                    $error_message = "CSV file is missing required columns: " . implode(", ", $missing_columns);
                } else {
                    // Image directory
                    if (!file_exists('uploads/products/')) {
                        mkdir('uploads/products/', 0775, true);
                    }
                    
                    // Create temp directory for uploaded images
                    $temp_image_dir = 'uploads/temp/';
                    if (!file_exists($temp_image_dir)) {
                        mkdir($temp_image_dir, 0775, true);
                    }
                    
                    // Prepare the insert statement
                    $insert_query = "INSERT INTO products (vendor_id, name, description, price, stock, packaging, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    
                    if ($insert_stmt) {
                        // Map column indexes
                        $column_indexes = [];
                        foreach (array_merge($required_columns, $optional_columns) as $col) {
                            $idx = array_search(strtolower($col), array_map('strtolower', $header));
                            if ($idx !== false) {
                                $column_indexes[$col] = $idx;
                            }
                        }
                        
                        // Process each row
                        $row_num = 1; // Start with 1 to account for header as row 0
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $row_num++;
                            
                            // Extract data
                            $name = isset($data[$column_indexes['name']]) ? trim($data[$column_indexes['name']]) : '';
                            $description = isset($data[$column_indexes['description']]) ? trim($data[$column_indexes['description']]) : '';
                            $price = isset($data[$column_indexes['price']]) ? floatval(trim($data[$column_indexes['price']])) : 0;
                            $stock = isset($data[$column_indexes['stock']]) ? intval(trim($data[$column_indexes['stock']])) : 0;
                            $category_id = isset($data[$column_indexes['category_id']]) ? intval(trim($data[$column_indexes['category_id']])) : 0;
                            $packaging = isset($data[$column_indexes['packaging']]) ? trim($data[$column_indexes['packaging']]) : '';
                            
                            // Handle image (either URL or file path)
                            $image_url = '';
                            
                            if (isset($column_indexes['image_url']) && isset($data[$column_indexes['image_url']]) && !empty($data[$column_indexes['image_url']])) {
                                // Use direct URL
                                $image_url = trim($data[$column_indexes['image_url']]);
                            } elseif (isset($column_indexes['image_file']) && isset($data[$column_indexes['image_file']]) && !empty($data[$column_indexes['image_file']])) {
                                // Handle local image file path
                                $image_file = trim($data[$column_indexes['image_file']]);
                                
                                // Check if the file exists in the temp directory
                                if (file_exists($temp_image_dir . $image_file)) {
                                    $file_ext = strtolower(pathinfo($image_file, PATHINFO_EXTENSION));
                                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                                    
                                    if (in_array($file_ext, $allowed)) {
                                        $new_filename = uniqid() . '.' . $file_ext;
                                        $upload_path = 'uploads/products/' . $new_filename;
                                        
                                        if (copy($temp_image_dir . $image_file, $upload_path)) {
                                            $image_url = $upload_path;
                                        } else {
                                            $bulk_errors[] = "Row {$row_num}: Failed to move image file {$image_file}";
                                            $bulk_error_count++;
                                            continue;
                                        }
                                    } else {
                                        $bulk_errors[] = "Row {$row_num}: Invalid image format. Allowed: JPG, JPEG, PNG, WEBP";
                                        $bulk_error_count++;
                                        continue;
                                    }
                                } else {
                                    $bulk_errors[] = "Row {$row_num}: Image file {$image_file} not found";
                                    $bulk_error_count++;
                                    continue;
                                }
                            }
                            
                            // Validate row data
                            $row_error = '';
                            if (empty($name)) {
                                $row_error = "Product name is required";
                            } elseif (empty($description)) {
                                $row_error = "Description is required";
                            } elseif ($price <= 0) {
                                $row_error = "Price must be greater than 0";
                            } elseif ($stock < 0) {
                                $row_error = "Stock cannot be negative";
                            } elseif ($category_id <= 0 || !isset($categories[$category_id])) {
                                $row_error = "Invalid category ID";
                            } elseif (empty($image_url)) {
                                $row_error = "Image URL or file is required";
                            }
                            
                            if (!empty($row_error)) {
                                $bulk_errors[] = "Row {$row_num}: {$row_error}";
                                $bulk_error_count++;
                                continue;
                            }
                            
                            // Bind parameters and execute
                            mysqli_stmt_bind_param($insert_stmt, 'issdisss', $vendor_id, $name, $description, $price, $stock, $packaging, $category_id, $image_url);
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $bulk_success_count++;
                            } else {
                                $bulk_errors[] = "Row {$row_num}: Database error - " . mysqli_error($conn);
                                $bulk_error_count++;
                            }
                        }
                        
                        mysqli_stmt_close($insert_stmt);
                        
                        if ($bulk_success_count > 0) {
                            $success_message = "{$bulk_success_count} products uploaded successfully!";
                            if ($bulk_error_count > 0) {
                                $error_message = "{$bulk_error_count} products failed to upload. See details below.";
                            }
                        } elseif ($bulk_error_count > 0) {
                            $error_message = "All products failed to upload. See details below.";
                        } else {
                            $error_message = "No products found in the CSV file.";
                        }
                    } else {
                        $error_message = "Database error: " . mysqli_error($conn);
                    }
                    
                    // Clean up temp directory
                    if (file_exists($temp_image_dir)) {
                        foreach (glob($temp_image_dir . '*') as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                    }
                }
                
                fclose($handle);
            } else {
                $error_message = "Failed to open CSV file.";
            }
        }
    } else {
        $error_message = "Please select a CSV file to upload.";
    }
}

// Handle image upload for bulk import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bulk_images') {
    // Create temp directory for uploaded images
    $temp_image_dir = 'uploads/temp/';
    if (!file_exists($temp_image_dir)) {
        mkdir($temp_image_dir, 0775, true);
    }
    
    $upload_errors = [];
    $upload_success = [];
    
    // Process each uploaded file
    if (!empty($_FILES['bulk_images']['name'][0])) {
        foreach ($_FILES['bulk_images']['name'] as $key => $name) {
            if ($_FILES['bulk_images']['error'][$key] === 0) {
                $tmp_name = $_FILES['bulk_images']['tmp_name'][$key];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($ext, $allowed)) {
                    // Save file to temp directory
                    if (move_uploaded_file($tmp_name, $temp_image_dir . $name)) {
                        $upload_success[] = $name;
                    } else {
                        $upload_errors[] = "Failed to upload {$name}";
                    }
                } else {
                    $upload_errors[] = "{$name}: Invalid format. Allowed: JPG, JPEG, PNG, WEBP";
                }
            } else if ($_FILES['bulk_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                $upload_errors[] = "Error uploading {$name}. Code: " . $_FILES['bulk_images']['error'][$key];
            }
        }
    }
    
    // Prepare response
    $response = [
        'success' => !empty($upload_success),
        'uploaded' => $upload_success,
        'errors' => $upload_errors
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Generate sample CSV
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="product_upload_sample.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['name', 'description', 'price', 'stock', 'category_id', 'packaging', 'image_url', 'image_file']);
    
    // Sample rows
    fputcsv($output, [
        'Organic Tomatoes', 
        'Fresh organic tomatoes grown locally', 
        '2.99', 
        '50', 
        '1', // Replace with actual category ID
        '1 lb package',
        'https://example.com/images/tomatoes.jpg', // Example for remote URL
        'tomatoes.jpg' // Example for uploaded file
    ]);
    fputcsv($output, [
        'Free Range Eggs', 
        'Farm fresh free range eggs', 
        '4.50', 
        '30', 
        '2', // Replace with actual category ID
        'Dozen',
        '', // Empty URL because using file
        'eggs.jpg' // Example for uploaded file
    ]);
    
    fclose($output);
    exit;
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

        /* Additional styles for the bulk upload functionality */
        .upload-tabs {
            display: flex;
            flex-direction: column;
            margin-bottom: 2rem;
        }
        
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .tab-button {
            padding: 1rem 2rem;
            cursor: pointer;
            font-weight: 500;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--medium-gray);
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-pane {
            display: none;
            padding: 1.5rem 0;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .bulk-upload-steps {
            margin-bottom: 2rem;
            padding-left: 1.5rem;
        }
        
        .bulk-upload-steps > li {
            margin-bottom: 1.5rem;
        }
        
        .image-upload-container {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .bulk-upload-requirements {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }
        
        .category-reference {
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .category-list {
            columns: 2;
            list-style-type: none;
            padding: 0;
        }
        
        .category-list li {
            margin-bottom: 0.5rem;
        }
        
        .mt-2 {
            margin-top: 1rem;
        }
        
        .mt-4 {
            margin-top: 2rem;
        }
        
        .uploaded-files li, .error-files li {
            margin-bottom: 0.5rem;
        }
        
        .error-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
            background: #fff8f8;
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

            <div class="upload-tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button active" data-tab="single-upload">Single Upload</button>
                    <button type="button" class="tab-button" data-tab="bulk-upload">Bulk Upload</button>
                </div>
                
                <div class="tab-content">
                    <div id="single-upload" class="tab-pane active">
            <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_type" value="single">
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

                                    <div class="form-group">
                                        <label class="form-label" for="packaging">Packaging</label>
                                        <input type="text" id="packaging" name="packaging" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['packaging'] ?? ''); ?>">
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
                    
                    <div id="bulk-upload" class="tab-pane">
                        <div class="bulk-upload-info">
                            <h3>Bulk Upload Products</h3>
                            <p>Upload multiple products at once using a CSV file. Follow these steps:</p>
                            <ol class="bulk-upload-steps">
                                <li>
                                    <strong>Step 1:</strong> Upload product images (optional if using direct image URLs)
                                    <div class="image-upload-container">
                                        <form id="bulk-images-form" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="upload_bulk_images">
                                            <input type="file" id="bulk_images" name="bulk_images[]" multiple accept=".jpg,.jpeg,.png,.webp">
                                            <button type="button" id="upload-images-btn" class="btn btn-secondary">
                                                <i class="fas fa-upload"></i> Upload Images
                                            </button>
                                        </form>
                                        <div id="image-upload-status" class="mt-2"></div>
                                        <div id="uploaded-images-list" class="mt-2"></div>
                                    </div>
                                </li>
                                <li>
                                    <strong>Step 2:</strong> Prepare your CSV file with the following columns:
                                    <ul class="bulk-upload-requirements">
                                        <li><strong>name</strong> - Product name (required)</li>
                                        <li><strong>description</strong> - Product description (required)</li>
                                        <li><strong>price</strong> - Product price (required, numeric)</li>
                                        <li><strong>stock</strong> - Stock quantity (required, numeric)</li>
                                        <li><strong>category_id</strong> - Category ID (required, must exist in the system)</li>
                                        <li><strong>packaging</strong> - Packaging information (optional)</li>
                                        <li><strong>image_url</strong> - URL to product image (either image_url or image_file is required)</li>
                                        <li><strong>image_file</strong> - Filename of uploaded image (either image_url or image_file is required)</li>
                                    </ul>
                                </li>
                                <li>
                                    <strong>Step 3:</strong> Upload your CSV file
                                </li>
                            </ol>
                            
                            <div class="category-reference">
                                <h4>Available Categories:</h4>
                                <ul class="category-list">
                                    <?php foreach ($categories as $id => $name): ?>
                                        <li><strong><?php echo $id; ?></strong>: <?php echo htmlspecialchars($name); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <a href="?download_sample=1" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Download Sample CSV
                            </a>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="bulk-upload-form mt-4">
                            <input type="hidden" name="upload_type" value="bulk">
                            
                            <div class="form-group">
                                <label class="form-label" for="csv_file">CSV File*</label>
                                <input type="file" id="csv_file" name="csv_file" class="form-control" 
                                       accept=".csv" required>
                                <small id="csv-file-name" class="form-text"></small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-file-upload"></i> Upload Products
                            </button>
                        </form>
                        
                        <?php if (!empty($bulk_errors)): ?>
                            <div class="error-list mt-4">
                                <h4>Upload Errors (<?php echo count($bulk_errors); ?>):</h4>
                                <ul>
                                    <?php foreach ($bulk_errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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

        // Form validation for single upload
        const singleForm = document.querySelector('#single-upload form');
        singleForm.addEventListener('submit', function(e) {
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

        // Tab functionality
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Add active class to current tab and content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Display selected CSV filename
        const csvFileInput = document.getElementById('csv_file');
        const csvFileName = document.getElementById('csv-file-name');

        if (csvFileInput) {
            csvFileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    csvFileName.textContent = 'Selected file: ' + this.files[0].name;
                } else {
                    csvFileName.textContent = '';
                }
            });
        }
        
        // Handle bulk image uploads
        const bulkImagesForm = document.getElementById('bulk-images-form');
        const bulkImagesInput = document.getElementById('bulk_images');
        const uploadImagesBtn = document.getElementById('upload-images-btn');
        const imageUploadStatus = document.getElementById('image-upload-status');
        const uploadedImagesList = document.getElementById('uploaded-images-list');
        
        uploadImagesBtn.addEventListener('click', function() {
            if (!bulkImagesInput.files.length) {
                imageUploadStatus.innerHTML = '<div class="alert alert-error">Please select at least one image</div>';
                return;
            }
            
            imageUploadStatus.innerHTML = '<div class="alert alert-info">Uploading images...</div>';
            
            const formData = new FormData(bulkImagesForm);
            
            fetch('product_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    imageUploadStatus.innerHTML = `<div class="alert alert-success">Successfully uploaded ${data.uploaded.length} images</div>`;
                    
                    // Display uploaded images
                    let listHtml = '<h4>Uploaded Images:</h4><ul class="uploaded-files">';
                    data.uploaded.forEach(file => {
                        listHtml += `<li>${file}</li>`;
                    });
                    listHtml += '</ul>';
                    
                    // Display any errors
                    if (data.errors.length > 0) {
                        listHtml += '<h4>Upload Errors:</h4><ul class="error-files">';
                        data.errors.forEach(error => {
                            listHtml += `<li>${error}</li>`;
                        });
                        listHtml += '</ul>';
                    }
                    
                    uploadedImagesList.innerHTML = listHtml;
                } else {
                    imageUploadStatus.innerHTML = '<div class="alert alert-error">Failed to upload images</div>';
                    
                    if (data.errors.length > 0) {
                        let errorHtml = '<ul class="error-files">';
                        data.errors.forEach(error => {
                            errorHtml += `<li>${error}</li>`;
                        });
                        errorHtml += '</ul>';
                        uploadedImagesList.innerHTML = errorHtml;
                    }
                }
            })
            .catch(error => {
                imageUploadStatus.innerHTML = '<div class="alert alert-error">Error uploading images: ' + error.message + '</div>';
            });
        });
    </script>
</body>
</html>