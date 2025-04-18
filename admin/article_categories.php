<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "You don't have permission to access this page";
    header("Location: ../login.php");
    exit();
}

// Define log_admin_action function if it doesn't exist
if (!function_exists('log_admin_action')) {
    function log_admin_action($user_id, $action) {
        global $conn;
        $timestamp = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($conn, "INSERT INTO admin_logs (user_id, action, timestamp) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $timestamp);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$message = '';
$error = '';

// Handle Delete Category
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has articles
    $check_query = "SELECT COUNT(*) as article_count FROM articles WHERE category_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['article_count'] > 0) {
        $error = "Cannot delete category that has articles. Reassign articles first.";
    } else {
        // Delete category
        $delete_query = "DELETE FROM article_categories WHERE category_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Category deleted successfully";
            
            // Log the action
            log_admin_action($_SESSION['user_id'], "Deleted article category #$category_id");
        } else {
            $error = "Error deleting category: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $name = trim($_POST['name']);
    $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Generate slug if not provided
    if (empty($slug)) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    // Form validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    // Check for duplicate slug
    $slug_check_query = "SELECT category_id FROM article_categories WHERE slug = ? AND category_id != ?";
    $stmt = mysqli_prepare($conn, $slug_check_query);
    mysqli_stmt_bind_param($stmt, "si", $slug, $category_id);
    mysqli_stmt_execute($stmt);
    $slug_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($slug_result) > 0) {
        $errors[] = "A category with this slug already exists";
    }
    mysqli_stmt_close($stmt);
    
    // If no errors, proceed with insert/update
    if (empty($errors)) {
        if ($category_id > 0) {
            // Update existing category
            $update_query = "UPDATE article_categories SET name = ?, slug = ?, description = ? WHERE category_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $slug, $description, $category_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Category updated successfully";
                
                // Log the action
                log_admin_action($_SESSION['user_id'], "Updated article category: $name");
            } else {
                $error = "Error updating category: " . mysqli_error($conn);
            }
        } else {
            // Insert new category
            $insert_query = "INSERT INTO article_categories (name, slug, description) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "sss", $name, $slug, $description);
            
            if (mysqli_stmt_execute($stmt)) {
                $category_id = mysqli_insert_id($conn);
                $message = "Category created successfully";
                
                // Log the action
                log_admin_action($_SESSION['user_id'], "Created new article category: $name");
            } else {
                $error = "Error creating category: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get all categories
$categories_query = "SELECT * FROM article_categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Initialize edit variables
$edit_category = [
    'category_id' => 0,
    'name' => '',
    'slug' => '',
    'description' => ''
];

// Load category for editing
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM article_categories WHERE category_id = ?";
    $stmt = mysqli_prepare($conn, $edit_query);
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $edit_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($edit_result) > 0) {
        $edit_category = mysqli_fetch_assoc($edit_result);
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Article Categories - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .container {
            display: flex;
            gap: 20px;
        }
        
        .left-col {
            flex: 2;
        }
        
        .right-col {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
        }
        
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .categories-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .categories-table th,
        .categories-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .categories-table th {
            font-weight: 600;
            color: #555;
            background-color: #f8f9fa;
        }
        
        .categories-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .categories-table .empty-row td {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .action-links a {
            margin-right: 10px;
            color: #4CAF50;
            text-decoration: none;
        }
        
        .action-links a.delete {
            color: #F44336;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        .form-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .count-badge {
            display: inline-block;
            background-color: #f0f0f0;
            color: #666;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="main-container">
        <h1>Article Categories</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="container">
            <div class="left-col">
                <div class="card">
                    <div class="card-header">
                        <h2>Categories</h2>
                    </div>
                    
                    <table class="categories-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Articles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($categories_result) > 0): ?>
                                <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                    <?php
                                    // Get article count for this category
                                    $count_query = "SELECT COUNT(*) as count FROM articles WHERE category_id = " . $category['category_id'];
                                    $count_result = mysqli_query($conn, $count_query);
                                    $count_row = mysqli_fetch_assoc($count_result);
                                    $article_count = $count_row['count'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                        <td>
                                            <?php echo $article_count; ?>
                                            <?php if ($article_count > 0): ?>
                                                <a href="knowledge_hub.php?category=<?php echo $category['category_id']; ?>">(View)</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-links">
                                            <a href="?edit=<?php echo $category['category_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($article_count == 0): ?>
                                                <a href="?delete=<?php echo $category['category_id']; ?>" class="delete" onclick="return confirm('Are you sure you want to delete this category?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="empty-row">
                                    <td colspan="4">No categories found. Create your first category using the form on the right.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="right-col">
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo ($edit_category['category_id'] > 0) ? 'Edit Category' : 'Add New Category'; ?></h2>
                    </div>
                    
                    <form method="post" action="">
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                        
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_category['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">Slug</label>
                            <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($edit_category['slug']); ?>">
                            <div class="form-note">Will be auto-generated if left blank</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($edit_category['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo ($edit_category['category_id'] > 0) ? 'Update Category' : 'Add Category'; ?>
                            </button>
                            
                            <?php if ($edit_category['category_id'] > 0): ?>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-plus"></i> Add New
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
        
        // Auto-generate slug from name
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');
        
        nameInput.addEventListener('input', function() {
            if (!slugInput.value || slugInput.dataset.auto !== 'false') {
                const slug = this.value.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                slugInput.value = slug;
                slugInput.dataset.auto = 'true';
            }
        });
        
        slugInput.addEventListener('input', function() {
            this.dataset.auto = 'false';
        });
    </script>
</body>
</html> 