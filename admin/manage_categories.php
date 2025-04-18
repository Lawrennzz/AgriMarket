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

$message = '';
$error = '';

// Handle category deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has articles
    $check_query = "SELECT COUNT(*) as article_count FROM articles WHERE category_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['article_count'] > 0) {
        $error = "Cannot delete category that has articles assigned to it";
    } else {
        // Delete category
        $delete_query = "DELETE FROM article_categories WHERE category_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Category deleted successfully";
        } else {
            $error = "Error deleting category: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission for adding/editing category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    
    // Validate form data
    if (empty($name)) {
        $error = "Category name is required";
    } else {
        // Check if category name already exists (excluding current category if editing)
        $check_query = "SELECT * FROM article_categories WHERE name = ? AND category_id != ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "si", $name, $category_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $error = "A category with this name already exists";
        } else {
            if ($category_id > 0) {
                // Update existing category
                $query = "UPDATE article_categories SET name = ?, description = ? WHERE category_id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ssi", $name, $description, $category_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Category updated successfully";
                } else {
                    $error = "Error updating category: " . mysqli_error($conn);
                }
            } else {
                // Insert new category
                $query = "INSERT INTO article_categories (name, description) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ss", $name, $description);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Category added successfully";
                } else {
                    $error = "Error adding category: " . mysqli_error($conn);
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Get category for editing if ID is provided
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $category_id = intval($_GET['edit']);
    $query = "SELECT * FROM article_categories WHERE category_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_category = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

// Fetch all categories with article counts
$query = "SELECT c.*, COUNT(a.article_id) as article_count 
          FROM article_categories c
          LEFT JOIN articles a ON c.category_id = a.category_id
          GROUP BY c.category_id
          ORDER BY c.name";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Article Categories - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
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
            font-size: 1rem;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .category-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .category-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        .category-card p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .article-count {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 15px;
        }
        
        .category-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f5f5f5;
            color: #333;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-icon:hover {
            background-color: #e0e0e0;
        }
        
        .btn-icon.edit {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-icon.edit:hover {
            background-color: #bbdefb;
        }
        
        .btn-icon.delete {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .btn-icon.delete:hover {
            background-color: #ffcdd2;
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
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }
        
        .close-modal:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <div class="header-actions">
            <h1>Manage Article Categories</h1>
            <button class="btn btn-primary" id="addCategoryBtn">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </div>
        
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
        
        <div class="categories-grid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($category = mysqli_fetch_assoc($result)): ?>
                    <div class="category-card">
                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        <div class="article-count">
                            <i class="fas fa-newspaper"></i> 
                            <?php echo $category['article_count']; ?> Articles
                        </div>
                        <p><?php echo htmlspecialchars($category['description'] ?? 'No description provided'); ?></p>
                        <div class="category-actions">
                            <a href="?edit=<?php echo $category['category_id']; ?>" class="btn-icon edit" title="Edit category">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                            <?php if ($category['article_count'] == 0): ?>
                                <a href="?delete=<?php echo $category['category_id']; ?>" class="btn-icon delete" title="Delete category" 
                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn-icon delete" title="Cannot delete category with articles" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card">
                    <p>No categories found. Add your first category to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Category Form Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h2>
            
            <form method="post" action="">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Category Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
    
    <script>
        // Modal functionality
        const modal = document.getElementById('categoryModal');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const closeBtn = document.querySelector('.close-modal');
        
        addCategoryBtn.addEventListener('click', function() {
            modal.style.display = 'block';
        });
        
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Auto-show modal if editing
        <?php if ($edit_category): ?>
            modal.style.display = 'block';
        <?php endif; ?>
        
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
    </script>
</body>
</html> 