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

// Initialize variables
$article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$title = '';
$summary = '';
$content = '';
$category_id = '';
$image_url = '';
$is_featured = 0;
$status = 'draft';
$message = '';
$error = '';

// Fetch categories for dropdown
$categories_query = "SELECT * FROM article_categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// If editing existing article
if ($article_id > 0) {
    $article_query = "SELECT * FROM articles WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $article_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $article = mysqli_fetch_assoc($result);
        $title = $article['title'];
        $summary = $article['summary'];
        $content = $article['content'];
        $category_id = $article['category_id'];
        $image_url = $article['image_url'];
        $is_featured = $article['is_featured'];
        $status = $article['status'];
    } else {
        $error = "Article not found";
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $summary = mysqli_real_escape_string($conn, $_POST['summary']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $category_id = intval($_POST['category_id']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Handle image upload
    $image_url = $image_url; // Default to existing image
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/articles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $file_path = $upload_dir . $file_name;
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = 'uploads/articles/' . $file_name;
            } else {
                $error = "Failed to upload image";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG and GIF are allowed";
        }
    }
    
    // Validate form data
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($summary)) {
        $error = "Summary is required";
    } elseif (empty($content)) {
        $error = "Content is required";
    } elseif (empty($category_id)) {
        $error = "Category is required";
    }
    
    // If no errors, save article
    if (empty($error)) {
        if ($article_id > 0) {
            // Update existing article
            $query = "UPDATE articles SET 
                title = ?, 
                summary = ?, 
                content = ?, 
                image_url = ?, 
                category_id = ?, 
                is_featured = ?, 
                status = ?, 
                updated_at = NOW()
                WHERE article_id = ?";
                
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssiiis", $title, $summary, $content, $image_url, $category_id, $is_featured, $status, $article_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Article updated successfully";
            } else {
                $error = "Error updating article: " . mysqli_error($conn);
            }
        } else {
            // Insert new article
            $query = "INSERT INTO articles (
                title, 
                summary, 
                content, 
                image_url, 
                category_id, 
                author_id, 
                is_featured, 
                status, 
                view_count, 
                published_date, 
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, " . 
            ($status === 'published' ? 'NOW()' : 'NULL') . 
            ", NOW(), NOW())";
            
            $author_id = $_SESSION['user_id'];
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssiiis", $title, $summary, $content, $image_url, $category_id, $author_id, $is_featured, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $article_id = mysqli_insert_id($conn);
                $message = "Article created successfully";
            } else {
                $error = "Error creating article: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article_id > 0 ? 'Edit' : 'Create'; ?> Article - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-check input {
            margin-right: 10px;
        }
        
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .ck-editor__editable {
            min-height: 350px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
            text-transform: uppercase;
        }
        
        .status-draft {
            background-color: #f0f0f0;
            color: #555;
        }
        
        .status-published {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-archived {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .form-section {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
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
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <div class="header-row">
            <h1><?php echo $article_id > 0 ? 'Edit' : 'Create'; ?> Article</h1>
            <?php if ($article_id > 0): ?>
                <span class="status-badge status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
            <?php endif; ?>
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
        
        <form method="post" enctype="multipart/form-data" class="form-container">
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="summary">Summary</label>
                    <textarea id="summary" name="summary" class="form-control" rows="3" required><?php echo htmlspecialchars($summary); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select a category</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Article Content</h3>
                
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="10" required><?php echo htmlspecialchars($content); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Media & Display</h3>
                
                <div class="form-group">
                    <label for="image">Featured Image</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                    
                    <?php if (!empty($image_url)): ?>
                        <div class="mt-2">
                            <p>Current image:</p>
                            <img src="<?php echo '../' . $image_url; ?>" alt="Article image" class="preview-image">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" <?php echo $is_featured ? 'checked' : ''; ?>>
                    <label for="is_featured">Feature this article on homepage</label>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Publishing</h3>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
            </div>
            
            <div class="btn-container">
                <div>
                    <a href="manage_articles.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Articles
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Article
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
    
    <script src="https://cdn.ckeditor.com/ckeditor5/34.0.0/classic/ckeditor.js"></script>
    <script>
        // Initialize rich text editor
        ClassicEditor
            .create(document.querySelector('#content'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
            })
            .catch(error => {
                console.error(error);
            });
            
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