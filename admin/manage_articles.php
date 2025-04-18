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

// Handle article operations
$message = '';
$error = '';

// Delete article
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $article_id = $_GET['delete'];
    $delete_query = "DELETE FROM articles WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article deleted successfully";
    } else {
        $error = "Error deleting article: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Toggle article featured status
if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) {
    $article_id = $_GET['toggle_featured'];
    
    // Get current status
    $status_query = "SELECT is_featured FROM articles WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $status_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $current_status);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    // Toggle status
    $new_status = $current_status ? 0 : 1;
    $update_query = "UPDATE articles SET is_featured = ? WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $new_status, $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article featured status updated";
    } else {
        $error = "Error updating article status: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Update article status
if (isset($_GET['change_status']) && is_numeric($_GET['article_id']) && !empty($_GET['new_status'])) {
    $article_id = $_GET['article_id'];
    $new_status = $_GET['new_status'];
    
    // Validate status
    $valid_statuses = ['draft', 'published', 'archived'];
    if (in_array($new_status, $valid_statuses)) {
        $update_query = "UPDATE articles SET status = ? WHERE article_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $article_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Article status updated to " . ucfirst($new_status);
        } else {
            $error = "Error updating article status: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Invalid status";
    }
}

// Fetch articles with category and author information
$query = "SELECT a.*, c.name as category_name, u.username as author_name 
          FROM articles a
          JOIN article_categories c ON a.category_id = c.category_id
          JOIN users u ON a.author_id = u.user_id
          ORDER BY a.created_at DESC";
$result = mysqli_query($conn, $query);

// Fetch categories for dropdown
$categories_query = "SELECT * FROM article_categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Get article count by status
$count_query = "SELECT status, COUNT(*) as count FROM articles GROUP BY status";
$count_result = mysqli_query($conn, $count_query);
$count_by_status = [
    'draft' => 0,
    'published' => 0,
    'archived' => 0
];

if ($count_result) {
    while ($row = mysqli_fetch_assoc($count_result)) {
        $count_by_status[$row['status']] = $row['count'];
    }
}
$total_articles = array_sum($count_by_status);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Articles - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .article-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .article-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .article-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .article-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            border-bottom: 1px solid #ddd;
        }
        
        .article-content {
            padding: 15px;
        }
        
        .article-title {
            font-size: 18px;
            margin-bottom: 8px;
            color: #333;
        }
        
        .article-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .article-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: bold;
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
        
        .article-summary {
            color: #555;
            margin-bottom: 15px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .article-actions {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #ffd700;
            color: #333;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card:last-child {
            margin-right: 0;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .action-button {
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            margin-right: 5px;
        }
        
        .action-button:last-child {
            margin-right: 0;
        }
        
        .edit-btn {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .delete-btn {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .feature-btn {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .publish-btn {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
        }
        
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Manage Knowledge Hub Articles</h1>
        
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
        
        <!-- Article Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">Total Articles</div>
                <div class="stat-value"><?php echo $total_articles; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Published</div>
                <div class="stat-value" style="color: #2e7d32;"><?php echo $count_by_status['published']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Drafts</div>
                <div class="stat-value" style="color: #555;"><?php echo $count_by_status['draft']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Archived</div>
                <div class="stat-value" style="color: #c62828;"><?php echo $count_by_status['archived']; ?></div>
            </div>
        </div>
        
        <!-- Article Management Actions -->
        <div class="actions-bar">
            <a href="edit_article.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Article
            </a>
            
            <a href="../knowledge_hub.php" class="btn btn-secondary" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Knowledge Hub
            </a>
        </div>
        
        <!-- Articles Grid -->
        <div class="article-grid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($article = mysqli_fetch_assoc($result)): ?>
                    <div class="article-card" style="position: relative;">
                        <?php if ($article['is_featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star"></i> Featured
                            </div>
                        <?php endif; ?>
                        
                        <div class="article-image" style="background-image: url('<?php echo !empty($article['image_url']) ? '../' . $article['image_url'] : '../assets/images/default-article.jpg'; ?>')"></div>
                        
                        <div class="article-content">
                            <h3 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h3>
                            
                            <div class="article-meta">
                                <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($article['category_name']); ?></span>
                                <span class="article-status status-<?php echo $article['status']; ?>"><?php echo ucfirst($article['status']); ?></span>
                            </div>
                            
                            <p class="article-summary">
                                <?php echo htmlspecialchars(substr($article['summary'], 0, 100) . (strlen($article['summary']) > 100 ? '...' : '')); ?>
                            </p>
                            
                            <div class="article-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name']); ?></span>
                                <span><i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> views</span>
                            </div>
                            
                            <div class="article-actions">
                                <div>
                                    <a href="edit_article.php?id=<?php echo $article['article_id']; ?>" class="action-button edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    
                                    <a href="?toggle_featured=<?php echo $article['article_id']; ?>" class="action-button feature-btn" title="<?php echo $article['is_featured'] ? 'Remove from featured' : 'Add to featured'; ?>">
                                        <i class="fas <?php echo $article['is_featured'] ? 'fa-star' : 'fa-star'; ?>"></i> 
                                        <?php echo $article['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                    </a>
                                </div>
                                
                                <div class="dropdown">
                                    <button class="action-button">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-content">
                                        <?php if ($article['status'] != 'published'): ?>
                                            <a href="?article_id=<?php echo $article['article_id']; ?>&change_status=published"><i class="fas fa-check-circle"></i> Publish</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($article['status'] != 'draft'): ?>
                                            <a href="?article_id=<?php echo $article['article_id']; ?>&change_status=draft"><i class="fas fa-file"></i> Mark as Draft</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($article['status'] != 'archived'): ?>
                                            <a href="?article_id=<?php echo $article['article_id']; ?>&change_status=archived"><i class="fas fa-archive"></i> Archive</a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete=<?php echo $article['article_id']; ?>" onclick="return confirm('Are you sure you want to delete this article?');"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data-message">
                    <p>No articles found. <a href="edit_article.php">Create your first article</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
    
    <script>
        // JavaScript for handling article actions and notifications
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 4 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 4000);
        });
    </script>
</body>
</html> 