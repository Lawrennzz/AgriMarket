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

// Handle article deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $article_id = intval($_GET['delete']);
    
    // Delete article
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

// Handle publishing/unpublishing
if (isset($_GET['publish']) && is_numeric($_GET['publish'])) {
    $article_id = intval($_GET['publish']);
    
    $update_query = "UPDATE articles SET status = 'published' WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article published successfully";
    } else {
        $error = "Error publishing article: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

if (isset($_GET['unpublish']) && is_numeric($_GET['unpublish'])) {
    $article_id = intval($_GET['unpublish']);
    
    $update_query = "UPDATE articles SET status = 'draft' WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article unpublished successfully";
    } else {
        $error = "Error unpublishing article: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Handle featuring/unfeaturing
if (isset($_GET['feature']) && is_numeric($_GET['feature'])) {
    $article_id = intval($_GET['feature']);
    
    $update_query = "UPDATE articles SET is_featured = 1 WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article featured successfully";
    } else {
        $error = "Error featuring article: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

if (isset($_GET['unfeature']) && is_numeric($_GET['unfeature'])) {
    $article_id = intval($_GET['unfeature']);
    
    $update_query = "UPDATE articles SET is_featured = 0 WHERE article_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Article unfeatured successfully";
    } else {
        $error = "Error unfeaturing article: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filtering
$where_clauses = [];
$params = [];
$types = "";

// Category filter
$category_filter = isset($_GET['category']) && !empty($_GET['category']) ? intval($_GET['category']) : 0;
if ($category_filter > 0) {
    $where_clauses[] = "a.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

// Status filter
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['published', 'draft']) ? $_GET['status'] : '';
if (!empty($status_filter)) {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Search
$search = isset($_GET['search']) && !empty($_GET['search']) ? $_GET['search'] : '';
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $where_clauses[] = "(a.title LIKE ? OR a.summary LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Featured filter
$featured_filter = isset($_GET['featured']) ? intval($_GET['featured']) : -1;
if ($featured_filter >= 0) {
    $where_clauses[] = "a.is_featured = ?";
    $params[] = $featured_filter;
    $types .= "i";
}

// Build WHERE clause
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Count total articles for pagination
$count_query = "SELECT COUNT(*) as total FROM articles a $where_sql";
$stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$total_articles = $row['total'];
$total_pages = ceil($total_articles / $items_per_page);

// Fetch articles with filtering, sorting, and pagination
$query = "SELECT a.*, c.name as category_name, u.username as author_name, 
          (SELECT COUNT(*) FROM article_views WHERE article_id = a.article_id) as view_count
          FROM articles a
          LEFT JOIN article_categories c ON a.category_id = c.category_id
          LEFT JOIN users u ON a.author_id = u.user_id
          $where_sql
          ORDER BY a.created_at DESC
          LIMIT ?, ?";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    $params[] = $offset;
    $params[] = $items_per_page;
    $types .= "ii";
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $offset, $items_per_page);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all categories for filter dropdown
$categories_query = "SELECT * FROM article_categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Hub Articles - AgriMarket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 500;
            white-space: nowrap;
        }
        
        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .article-title {
            font-weight: 500;
            color: #333;
        }
        
        .article-summary {
            color: #666;
            margin-top: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-warning {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .badge-featured {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            background-color: #f5f5f5;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background-color: #e0e0e0;
        }
        
        .pagination .active {
            background-color: #1976d2;
            color: white;
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
        
        .text-muted {
            color: #757575;
        }
        
        .featured-icon {
            color: gold;
            margin-left: 5px;
        }
        
        .view-count {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #757575;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <div class="header-actions">
            <h1>Knowledge Hub Articles</h1>
            <a href="edit_article.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Article
            </a>
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
        
        <form method="get" action="">
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="category">Category:</label>
                    <select name="category" id="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="featured">Featured:</label>
                    <select name="featured" id="featured" class="filter-select">
                        <option value="-1" <?php echo $featured_filter === -1 ? 'selected' : ''; ?>>All</option>
                        <option value="1" <?php echo $featured_filter === 1 ? 'selected' : ''; ?>>Featured</option>
                        <option value="0" <?php echo $featured_filter === 0 ? 'selected' : ''; ?>>Not Featured</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" name="search" id="search" class="filter-input" placeholder="Title or Summary" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="knowledge_hub.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </div>
        </form>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Views</th>
                        <th>Date Created</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($article = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <div class="article-title">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                        <?php if ($article['is_featured']): ?>
                                            <i class="fas fa-star featured-icon" title="Featured article"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="article-summary">
                                        <?php echo htmlspecialchars($article['summary']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></td>
                                <td>
                                    <div class="view-count">
                                        <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($article['created_at'])); ?></td>
                                <td>
                                    <?php if ($article['status'] === 'published'): ?>
                                        <span class="badge badge-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../knowledge_hub_article.php?id=<?php echo $article['article_id']; ?>" class="btn btn-info btn-sm" target="_blank" title="View article">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_article.php?id=<?php echo $article['article_id']; ?>" class="btn btn-primary btn-sm" title="Edit article">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($article['status'] === 'draft'): ?>
                                            <a href="?publish=<?php echo $article['article_id']; ?>" class="btn btn-success btn-sm" title="Publish article">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?unpublish=<?php echo $article['article_id']; ?>" class="btn btn-warning btn-sm" title="Unpublish article">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$article['is_featured']): ?>
                                            <a href="?feature=<?php echo $article['article_id']; ?>" class="btn btn-secondary btn-sm" title="Feature article">
                                                <i class="far fa-star"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?unfeature=<?php echo $article['article_id']; ?>" class="btn btn-secondary btn-sm" title="Unfeature article">
                                                <i class="fas fa-star"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete=<?php echo $article['article_id']; ?>" class="btn btn-danger btn-sm" title="Delete article" 
                                           onclick="return confirm('Are you sure you want to delete this article? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <p>No articles found. <?php echo !empty($search) || $category_filter > 0 || !empty($status_filter) || $featured_filter >= 0 ? 'Try changing your filters.' : ''; ?></p>
                                <a href="edit_article.php" class="btn btn-primary btn-sm">Create your first article</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo $featured_filter >= 0 ? '&featured=' . $featured_filter : ''; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo $featured_filter >= 0 ? '&featured=' . $featured_filter : ''; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1): ?>
                    <span>...</span>
                <?php endif; ?>
                
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo $featured_filter >= 0 ? '&featured=' . $featured_filter : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                    <span>...</span>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo $featured_filter >= 0 ? '&featured=' . $featured_filter : ''; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo $featured_filter >= 0 ? '&featured=' . $featured_filter : ''; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html> 