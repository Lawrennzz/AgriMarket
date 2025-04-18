<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if article ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: knowledge_hub.php');
    exit();
}

$article_id = intval($_GET['id']);

// Fetch article details
$article_query = "
    SELECT a.*, ac.name as category_name, ac.slug as category_slug, u.name as author_name, u.profile_image 
    FROM articles a
    JOIN article_categories ac ON a.category_id = ac.category_id
    JOIN users u ON a.author_id = u.user_id
    WHERE a.article_id = ? AND a.status = 'published'
";
$article_stmt = mysqli_prepare($conn, $article_query);
mysqli_stmt_bind_param($article_stmt, "i", $article_id);
mysqli_stmt_execute($article_stmt);
$article_result = mysqli_stmt_get_result($article_stmt);

if (mysqli_num_rows($article_result) === 0) {
    header('Location: knowledge_hub.php');
    exit();
}

$article = mysqli_fetch_assoc($article_result);
$page_title = $article['title'];

// Update view count
$update_views_query = "UPDATE articles SET view_count = view_count + 1 WHERE article_id = ?";
$update_stmt = mysqli_prepare($conn, $update_views_query);
mysqli_stmt_bind_param($update_stmt, "i", $article_id);
mysqli_stmt_execute($update_stmt);

// Fetch related articles
$related_articles_query = "
    SELECT a.article_id, a.title, a.summary, a.image_url, a.published_date 
    FROM articles a
    WHERE a.category_id = ? 
    AND a.article_id != ? 
    AND a.status = 'published'
    ORDER BY a.published_date DESC
    LIMIT 3
";
$related_stmt = mysqli_prepare($conn, $related_articles_query);
mysqli_stmt_bind_param($related_stmt, "ii", $article['category_id'], $article_id);
mysqli_stmt_execute($related_stmt);
$related_results = mysqli_stmt_get_result($related_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .article-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .article-header {
            position: relative;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .article-header-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            object-fit: cover;
            filter: brightness(0.6);
        }
        
        .article-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), rgba(0,0,0,0.5));
            z-index: -1;
        }
        
        .article-header-content {
            max-width: 800px;
            margin: 0 auto;
            color: white;
            text-align: center;
            position: relative;
        }
        
        .article-category {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-decoration: none;
        }
        
        .article-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        
        .article-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            font-size: 0.9rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .article-content-wrapper {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 40px;
        }
        
        .article-main {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .article-content {
            line-height: 1.8;
            color: #333;
            font-size: 1.1rem;
        }
        
        .article-content p {
            margin-bottom: 1.5rem;
        }
        
        .article-content h2 {
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #2e7d32;
            font-size: 1.8rem;
        }
        
        .article-content h3 {
            margin-top: 1.8rem;
            margin-bottom: 0.8rem;
            color: #2e7d32;
            font-size: 1.5rem;
        }
        
        .article-content ul, .article-content ol {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .article-content li {
            margin-bottom: 0.5rem;
        }
        
        .article-content img {
            max-width: 100%;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        
        .article-content blockquote {
            border-left: 4px solid #2e7d32;
            padding-left: 1.5rem;
            font-style: italic;
            color: #555;
            margin: 1.5rem 0;
        }
        
        .article-sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sidebar-block {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .sidebar-title {
            font-size: 1.3rem;
            color: #2e7d32;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f5e9;
        }
        
        .author-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .author-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .author-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .author-title {
            color: #777;
            font-size: 0.9rem;
        }
        
        .author-bio {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .related-articles {
            margin-top: 50px;
        }
        
        .related-title {
            font-size: 1.5rem;
            color: #2e7d32;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .related-title:after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            bottom: 0;
            left: 0;
            background-color: #2e7d32;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .related-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
        }
        
        .related-img {
            height: 180px;
            overflow: hidden;
        }
        
        .related-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-content {
            padding: 20px;
        }
        
        .related-article-title {
            font-size: 1.2rem;
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .related-article-title a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s;
        }
        
        .related-article-title a:hover {
            color: #2e7d32;
        }
        
        .related-date {
            color: #777;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .sidebar-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-list-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .sidebar-list-item:last-child {
            border-bottom: none;
        }
        
        .sidebar-list-item a {
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s;
        }
        
        .sidebar-list-item a:hover {
            color: #2e7d32;
        }
        
        .sidebar-list-icon {
            color: #2e7d32;
        }
        
        .share-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .share-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: transform 0.3s, background-color 0.3s;
        }
        
        .share-button:hover {
            transform: translateY(-3px);
        }
        
        .share-facebook {
            background-color: #3b5998;
        }
        
        .share-twitter {
            background-color: #1da1f2;
        }
        
        .share-linkedin {
            background-color: #0077b5;
        }
        
        .share-email {
            background-color: #757575;
        }
        
        @media (max-width: 992px) {
            .article-content-wrapper {
                grid-template-columns: 1fr;
            }
            
            .article-header {
                padding: 40px 0;
            }
            
            .article-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .article-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .related-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="article-container">
        <header class="article-header">
            <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="article-header-image">
            <div class="article-overlay"></div>
            <div class="article-header-content">
                <a href="farming_techniques.php?category=<?php echo htmlspecialchars($article['category_slug']); ?>" class="article-category">
                    <?php echo htmlspecialchars($article['category_name']); ?>
                </a>
                <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>
                <div class="article-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($article['author_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y', strtotime($article['published_date'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $article['read_time'] ?? '5'; ?> min read</span>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="article-content-wrapper">
            <main class="article-main">
                <div class="article-content">
                    <?php echo $article['content']; ?>
                </div>
                
                <div class="related-articles">
                    <h2 class="related-title">Related Articles</h2>
                    
                    <div class="related-grid">
                        <?php if (mysqli_num_rows($related_results) > 0): ?>
                            <?php while ($related = mysqli_fetch_assoc($related_results)): ?>
                                <div class="related-card">
                                    <div class="related-img">
                                        <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    </div>
                                    <div class="related-content">
                                        <h3 class="related-article-title">
                                            <a href="article.php?id=<?php echo $related['article_id']; ?>">
                                                <?php echo htmlspecialchars($related['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="related-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <span><?php echo date('M j, Y', strtotime($related['published_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No related articles found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            
            <aside class="article-sidebar">
                <div class="sidebar-block">
                    <h3 class="sidebar-title">About the Author</h3>
                    <div class="author-info">
                        <div class="author-avatar">
                            <img src="<?php echo htmlspecialchars($article['profile_image'] ?? 'images/default-profile.jpg'); ?>" alt="<?php echo htmlspecialchars($article['author_name']); ?>">
                        </div>
                        <div>
                            <div class="author-name"><?php echo htmlspecialchars($article['author_name']); ?></div>
                            <div class="author-title">Agricultural Expert</div>
                        </div>
                    </div>
                    <p class="author-bio">
                        An experienced agricultural specialist with expertise in modern farming techniques, sustainable practices, and agricultural innovation.
                    </p>
                </div>
                
                <div class="sidebar-block">
                    <h3 class="sidebar-title">Share This Article</h3>
                    <div class="share-buttons">
                        <a href="#" class="share-button share-facebook" title="Share on Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="share-button share-twitter" title="Share on Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="share-button share-linkedin" title="Share on LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="share-button share-email" title="Share via Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-block">
                    <h3 class="sidebar-title">Explore More</h3>
                    <ul class="sidebar-list">
                        <li class="sidebar-list-item">
                            <a href="farming_techniques.php">
                                <i class="fas fa-seedling sidebar-list-icon"></i>
                                <span>Modern Farming Techniques</span>
                            </a>
                        </li>
                        <li class="sidebar-list-item">
                            <a href="market_pricing.php">
                                <i class="fas fa-chart-line sidebar-list-icon"></i>
                                <span>Market Pricing Analysis</span>
                            </a>
                        </li>
                        <li class="sidebar-list-item">
                            <a href="agricultural_workflows.php">
                                <i class="fas fa-tasks sidebar-list-icon"></i>
                                <span>Agricultural Workflows</span>
                            </a>
                        </li>
                        <li class="sidebar-list-item">
                            <a href="knowledge_hub.php">
                                <i class="fas fa-book sidebar-list-icon"></i>
                                <span>Knowledge Hub Home</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 