<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$page_title = "Modern Farming Techniques";

// Fetch subcategories
$subcategories_query = "
    SELECT * FROM article_categories 
    WHERE parent_id = (SELECT category_id FROM article_categories WHERE slug = 'farming-techniques')
    ORDER BY name ASC
";
$subcategories_result = mysqli_query($conn, $subcategories_query);

// Fetch articles for farming techniques category
$articles_query = "
    SELECT a.*, ac.name as category_name, u.name as author_name 
    FROM articles a
    JOIN article_categories ac ON a.category_id = ac.category_id
    JOIN users u ON a.author_id = u.user_id
    WHERE (ac.slug = 'farming-techniques' OR ac.parent_id = (SELECT category_id FROM article_categories WHERE slug = 'farming-techniques'))
    AND a.status = 'published'
    ORDER BY a.published_date DESC
    LIMIT 12
";
$articles_result = mysqli_query($conn, $articles_query);
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
        .techniques-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background-color: #e8f5e9;
            padding: 50px 0;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 8px;
        }
        
        .page-header h1 {
            color: #2e7d32;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .page-header p {
            color: #555;
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .subcategories {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 40px;
            gap: 15px;
        }
        
        .subcategory-tag {
            background-color: #f1f1f1;
            color: #333;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .subcategory-tag:hover, .subcategory-tag.active {
            background-color: #2e7d32;
            color: white;
        }
        
        .featured-technique {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 50px;
        }
        
        .featured-img {
            height: 100%;
            min-height: 400px;
        }
        
        .featured-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .featured-content {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .featured-tag {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 15px;
        }
        
        .featured-title {
            font-size: 1.8rem;
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .featured-meta {
            display: flex;
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .featured-meta span {
            margin-right: 20px;
        }
        
        .featured-description {
            color: #555;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .read-more-btn {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            align-self: flex-start;
        }
        
        .read-more-btn:hover {
            background-color: #1b5e20;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 15px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            background-color: #f1f1f1;
            color: #333;
            transition: background-color 0.3s;
        }
        
        .pagination a:hover, .pagination span.current {
            background-color: #2e7d32;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="techniques-container">
        <div class="page-header">
            <h1>Modern Farming Techniques</h1>
            <p>Discover the latest agricultural methodologies, sustainable farming practices, and innovative technologies that are revolutionizing the farming industry.</p>
        </div>
        
        <div class="subcategories">
            <a href="farming_techniques.php" class="subcategory-tag active">All Techniques</a>
            <?php if ($subcategories_result && mysqli_num_rows($subcategories_result) > 0): ?>
                <?php while ($subcategory = mysqli_fetch_assoc($subcategories_result)): ?>
                    <a href="farming_techniques.php?category=<?php echo $subcategory['slug']; ?>" class="subcategory-tag">
                        <?php echo htmlspecialchars($subcategory['name']); ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <!-- Featured Technique -->
        <div class="featured-technique">
            <div class="featured-img">
                <img src="images/hydroponics.jpg" alt="Hydroponic Farming">
            </div>
            <div class="featured-content">
                <span class="featured-tag">Innovative Technique</span>
                <h2 class="featured-title">Hydroponic Farming: Growing Crops Without Soil</h2>
                <div class="featured-meta">
                    <span><i class="fas fa-user"></i> Expert Staff</span>
                    <span><i class="fas fa-calendar-alt"></i> Updated June 15, 2023</span>
                </div>
                <p class="featured-description">
                    Discover how hydroponic farming allows growing plants using nutrient-rich water solutions instead of soil. 
                    This technique can increase yields by up to 30%, reduce water usage by 90%, and allows year-round 
                    cultivation regardless of external weather conditions.
                </p>
                <a href="article.php?id=15" class="read-more-btn">Read Complete Guide</a>
            </div>
        </div>
        
        <h2 class="section-title">Explore All Techniques</h2>
        
        <div class="articles-grid">
            <?php if ($articles_result && mysqli_num_rows($articles_result) > 0): ?>
                <?php while ($article = mysqli_fetch_assoc($articles_result)): ?>
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
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                    <p>No articles found in this category. Check back soon for new content!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="pagination">
            <a href="#" class="current">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <span>...</span>
            <a href="#">Next</a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 