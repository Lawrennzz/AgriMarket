<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$page_title = "Agricultural Workflows";

// Fetch workflow categories
$categories_query = "
    SELECT * FROM article_categories 
    WHERE parent_id = (SELECT category_id FROM article_categories WHERE slug = 'agricultural-workflows')
    ORDER BY name ASC
";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch workflow articles
$workflows_query = "
    SELECT a.*, ac.name as category_name, u.name as author_name 
    FROM articles a
    JOIN article_categories ac ON a.category_id = ac.category_id
    JOIN users u ON a.author_id = u.user_id
    WHERE (ac.slug = 'agricultural-workflows' OR ac.parent_id = (SELECT category_id FROM article_categories WHERE slug = 'agricultural-workflows'))
    AND a.status = 'published'
    ORDER BY a.published_date DESC
    LIMIT 9
";
$workflows_result = mysqli_query($conn, $workflows_query);
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
        .workflows-container {
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
        
        .workflow-categories {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 40px;
            gap: 15px;
        }
        
        .category-tag {
            background-color: #f1f1f1;
            color: #333;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .category-tag:hover, .category-tag.active {
            background-color: #2e7d32;
            color: white;
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
        
        .workflow-process {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .workflow-steps {
            display: flex;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .workflow-step {
            flex: 1;
            min-width: 200px;
            text-align: center;
            padding: 20px;
            position: relative;
        }
        
        .workflow-step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 50px;
            height: 2px;
            background-color: #ddd;
            transform: translateX(25px);
        }
        
        .step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background-color: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 auto 15px auto;
        }
        
        .step-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
        }
        
        .step-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .workflow-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .workflow-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .workflow-card:hover {
            transform: translateY(-5px);
        }
        
        .workflow-img {
            height: 200px;
            overflow: hidden;
        }
        
        .workflow-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .workflow-content {
            padding: 20px;
        }
        
        .workflow-category {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .workflow-title {
            font-size: 1.3rem;
            margin: 10px 0;
            color: #333;
        }
        
        .workflow-meta {
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
        
        .workflow-tools {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .tool-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        
        .tool-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .tool-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .tool-info {
            flex: 1;
        }
        
        .tool-name {
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }
        
        .tool-description {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
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
        
        .download-template {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
            margin-top: 40px;
        }
        
        .download-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 15px;
        }
        
        .download-description {
            color: #666;
            max-width: 600px;
            margin: 0 auto 20px auto;
        }
        
        .download-btn {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 12px 25px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .download-btn:hover {
            background-color: #1b5e20;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="workflows-container">
        <div class="page-header">
            <h1>Agricultural Workflows</h1>
            <p>Discover efficient farm management processes, planning strategies, and operational workflows to streamline your agricultural activities and maximize productivity.</p>
        </div>
        
        <div class="workflow-categories">
            <a href="agricultural_workflows.php" class="category-tag active">All Workflows</a>
            <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
                <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                    <a href="agricultural_workflows.php?category=<?php echo $category['slug']; ?>" class="category-tag">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <!-- Featured Workflow Process -->
        <div class="workflow-process">
            <h2 class="section-title">Seasonal Crop Planning Workflow</h2>
            <p>An effective crop planning workflow helps farmers make informed decisions about what to plant, when to plant, and how to manage their resources throughout the growing season.</p>
            
            <div class="workflow-steps">
                <div class="workflow-step">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Soil Assessment</h3>
                    <p class="step-description">Conduct soil tests to analyze nutrient levels, pH, and texture. Identify soil amendments needed.</p>
                </div>
                
                <div class="workflow-step">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Market Research</h3>
                    <p class="step-description">Research current market trends, pricing, and demand forecasts for potential crops.</p>
                </div>
                
                <div class="workflow-step">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Crop Selection</h3>
                    <p class="step-description">Choose crops based on soil conditions, climate, market demand, and rotation requirements.</p>
                </div>
                
                <div class="workflow-step">
                    <div class="step-number">4</div>
                    <h3 class="step-title">Resource Planning</h3>
                    <p class="step-description">Estimate required inputs, equipment, labor, and funding for the selected crops.</p>
                </div>
                
                <div class="workflow-step">
                    <div class="step-number">5</div>
                    <h3 class="step-title">Schedule Creation</h3>
                    <p class="step-description">Develop detailed planting, maintenance, and harvest schedules for each crop.</p>
                </div>
            </div>
        </div>
        
        <!-- Workflow Articles -->
        <h2 class="section-title">Explore Workflow Guides</h2>
        
        <div class="workflow-cards">
            <?php if ($workflows_result && mysqli_num_rows($workflows_result) > 0): ?>
                <?php while ($workflow = mysqli_fetch_assoc($workflows_result)): ?>
                    <div class="workflow-card">
                        <div class="workflow-img">
                            <img src="<?php echo htmlspecialchars($workflow['image_url']); ?>" alt="<?php echo htmlspecialchars($workflow['title']); ?>">
                        </div>
                        <div class="workflow-content">
                            <span class="workflow-category"><?php echo htmlspecialchars($workflow['category_name']); ?></span>
                            <h3 class="workflow-title"><?php echo htmlspecialchars($workflow['title']); ?></h3>
                            <div class="workflow-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($workflow['author_name']); ?></span>
                                <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($workflow['published_date'])); ?></span>
                            </div>
                            <p><?php echo substr(htmlspecialchars($workflow['summary']), 0, 120) . '...'; ?></p>
                            <a href="article.php?id=<?php echo $workflow['article_id']; ?>" class="read-more">View Workflow</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                    <p>No workflow guides found in this category. Check back soon for new content!</p>
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
        
        <!-- Digital Tools Section -->
        <div class="workflow-tools">
            <h2 class="section-title">Digital Tools for Agricultural Management</h2>
            <p>Incorporate these digital tools into your workflows to improve efficiency, data management, and decision-making processes.</p>
            
            <div class="tools-grid">
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Crop Planning Software</h3>
                        <p class="tool-description">Schedule planting, harvesting, and crop rotations</p>
                    </div>
                </div>
                
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-cloud-sun-rain"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Weather Monitoring Apps</h3>
                        <p class="tool-description">Track weather patterns and receive alerts</p>
                    </div>
                </div>
                
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Market Analytics Tools</h3>
                        <p class="tool-description">Track pricing trends and market forecasts</p>
                    </div>
                </div>
                
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-tractor"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Equipment Management</h3>
                        <p class="tool-description">Schedule maintenance and track usage</p>
                    </div>
                </div>
                
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Inventory Tracking</h3>
                        <p class="tool-description">Monitor seed, fertilizer, and supplies</p>
                    </div>
                </div>
                
                <div class="tool-item">
                    <div class="tool-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="tool-info">
                        <h3 class="tool-name">Financial Planning</h3>
                        <p class="tool-description">Budget management and expense tracking</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Download Templates Section -->
        <div class="download-template">
            <h2 class="download-title">Free Workflow Templates</h2>
            <p class="download-description">Download our professionally designed templates to help you implement efficient agricultural workflows on your farm. These templates are customizable to fit your specific needs and operations.</p>
            <a href="downloads/workflow_templates.zip" class="download-btn">
                <i class="fas fa-download"></i> Download Templates
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>