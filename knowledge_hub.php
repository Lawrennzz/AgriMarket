<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$page_title = "Agricultural Knowledge Hub";

// Fetch article categories
$categories_query = "SELECT * FROM article_categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch featured/latest articles
$featured_articles_query = "
    SELECT a.*, ac.name as category_name, u.name as author_name 
    FROM articles a
    JOIN article_categories ac ON a.category_id = ac.category_id
    JOIN users u ON a.author_id = u.user_id
    WHERE a.status = 'published'
    ORDER BY a.is_featured DESC, a.published_date DESC
    LIMIT 6
";
$featured_articles_result = mysqli_query($conn, $featured_articles_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .knowledge-hub-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .hub-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .hub-header h1 {
            color: #2e7d32;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .hub-header p {
            color: #555;
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .category-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .category-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        
        .category-img {
            height: 180px;
            overflow: hidden;
        }
        
        .category-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .category-card:hover .category-img img {
            transform: scale(1.05);
        }
        
        .category-content {
            padding: 20px;
        }
        
        .category-content h3 {
            color: #2e7d32;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .category-content p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .category-link {
            display: inline-block;
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 0;
            position: relative;
        }
        
        .category-link:after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: #2e7d32;
            transform: scaleX(0);
            transform-origin: bottom right;
            transition: transform 0.3s;
        }
        
        .category-link:hover:after {
            transform: scaleX(1);
            transform-origin: bottom left;
        }
        
        .featured-articles {
            margin-top: 50px;
        }
        
        .section-title {
            color: #2e7d32;
            font-size: 1.8rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            bottom: 0;
            left: 0;
            background-color: #2e7d32;
        }
        
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .article-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .article-card:hover {
            transform: translateY(-5px);
        }
        
        .article-img {
            height: 200px;
            overflow: hidden;
        }
        
        .article-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .article-content {
            padding: 20px;
        }
        
        .article-category {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .article-title {
            font-size: 1.3rem;
            margin: 10px 0;
            color: #333;
        }
        
        .article-meta {
            display: flex;
            justify-content: space-between;
            color: #777;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .read-more {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .read-more:hover {
            background-color: #1b5e20;
        }
        
        .search-bar {
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-right: none;
            border-radius: 4px 0 0 4px;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2e7d32;
        }
        
        .search-button {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .search-button:hover {
            background-color: #1b5e20;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="knowledge-hub-container">
        <div class="hub-header">
            <h1>Agricultural Knowledge Hub</h1>
            <p>Explore our comprehensive collection of resources on modern farming techniques, market pricing trends, and efficient agricultural workflows to empower your farming operations and boost productivity.</p>
        </div>
        
        <div class="search-bar">
            <form class="search-form" action="article_search.php" method="GET">
                <input type="text" name="query" class="search-input" placeholder="Search for articles, topics, or keywords...">
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <div class="category-cards">
            <div class="category-card">
                <div class="category-img">
                    <img src="images/modern-farming.jpg" alt="Modern Farming Techniques">
                </div>
                <div class="category-content">
                    <h3>Modern Farming Techniques</h3>
                    <p>Discover cutting-edge farming methods, sustainable practices, and innovative technologies to improve crop yields and soil health.</p>
                    <a href="farming_techniques.php" class="category-link">Explore Techniques</a>
                </div>
            </div>
            
            <div class="category-card">
                <div class="category-img">
                    <img src="images/market-pricing.jpg" alt="Market Pricing Analysis">
                </div>
                <div class="category-content">
                    <h3>Market Pricing Analysis</h3>
                    <p>Stay updated with current market trends, pricing forecasts, and demand patterns to make informed decisions for your agricultural business.</p>
                    <a href="market_pricing.php" class="category-link">View Market Insights</a>
                </div>
            </div>
            
            <div class="category-card">
                <div class="category-img">
                    <img src="images/agricultural-workflows.jpg" alt="Agricultural Workflows">
                </div>
                <div class="category-content">
                    <h3>Agricultural Workflows</h3>
                    <p>Learn efficient farm management processes, planning strategies, and operational workflows to streamline your agricultural activities.</p>
                    <a href="agricultural_workflows.php" class="category-link">Improve Your Workflow</a>
                </div>
            </div>
        </div>
        
        <div class="featured-articles">
            <h2 class="section-title">Featured Articles</h2>
            
            <div class="articles-grid">
                <?php if ($featured_articles_result && mysqli_num_rows($featured_articles_result) > 0): ?>
                    <?php while ($article = mysqli_fetch_assoc($featured_articles_result)): ?>
                        <div class="article-card">
                            <div class="article-img">
                                <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                            </div>
                            <div class="article-content">
                                <span class="article-category"><?php echo htmlspecialchars($article['category_name']); ?></span>
                                <h3 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h3>
                                <div class="article-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name']); ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($article['published_date'])); ?></span>
                                </div>
                                <p><?php echo substr(htmlspecialchars($article['summary']), 0, 120) . '...'; ?></p>
                                <a href="article.php?id=<?php echo $article['article_id']; ?>" class="read-more">Read More</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center;">
                        <p>No articles found. Check back soon for new content!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 