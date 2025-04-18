<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$page_title = "Market Pricing Analysis";

// Get current date for market data query
$current_date = date('Y-m-d');

// Fetch latest market prices
$market_prices_query = "
    SELECT mp.*, p.name as product_name, p.image_url, c.name as category_name
    FROM market_prices mp
    JOIN products p ON mp.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE mp.date <= '$current_date'
    GROUP BY mp.product_id
    ORDER BY mp.date DESC
    LIMIT 8
";
$market_prices_result = mysqli_query($conn, $market_prices_query);

// Fetch price trend articles
$price_articles_query = "
    SELECT a.*, ac.name as category_name, u.name as author_name 
    FROM articles a
    JOIN article_categories ac ON a.category_id = ac.category_id
    JOIN users u ON a.author_id = u.user_id
    WHERE (ac.slug = 'market-pricing' OR ac.parent_id = (SELECT category_id FROM article_categories WHERE slug = 'market-pricing'))
    AND a.status = 'published'
    ORDER BY a.published_date DESC
    LIMIT 6
";
$price_articles_result = mysqli_query($conn, $price_articles_query);

// Fetch price forecast data for chart
$forecast_query = "
    SELECT p.name, 
           mp.price as current_price,
           mpf.price_1_month,
           mpf.price_3_month,
           mpf.price_6_month
    FROM market_price_forecasts mpf
    JOIN products p ON mpf.product_id = p.product_id
    JOIN market_prices mp ON mp.product_id = p.product_id
    WHERE mp.date = (SELECT MAX(date) FROM market_prices WHERE product_id = p.product_id)
    GROUP BY p.product_id
    ORDER BY p.name
    LIMIT 5
";
$forecast_result = mysqli_query($conn, $forecast_query);

// Prepare data for chart
$chart_labels = [];
$current_prices = [];
$price_1_month = [];
$price_3_month = [];
$price_6_month = [];

if ($forecast_result && mysqli_num_rows($forecast_result) > 0) {
    while ($row = mysqli_fetch_assoc($forecast_result)) {
        $chart_labels[] = $row['name'];
        $current_prices[] = $row['current_price'];
        $price_1_month[] = $row['price_1_month'];
        $price_3_month[] = $row['price_3_month'];
        $price_6_month[] = $row['price_6_month'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AgriMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .pricing-container {
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
        
        .market-update {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .update-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .update-title {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }
        
        .update-date {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .prices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .price-card {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .price-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .price-info {
            flex: 1;
        }
        
        .product-name {
            font-size: 1.1rem;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .product-category {
            font-size: 0.8rem;
            color: #666;
            margin: 0 0 8px 0;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .price-trend {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .trend-up {
            color: #2e7d32;
        }
        
        .trend-down {
            color: #c62828;
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
        
        .price-forecast {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .chart-container {
            height: 400px;
            margin-bottom: 20px;
        }
        
        .forecast-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 8px;
            border-radius: 3px;
        }
        
        .legend-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .articles-section {
            margin-top: 50px;
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
        
        .price-alert {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 40px;
        }
        
        .alert-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2e7d32;
        }
        
        .alert-button {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            height: 47px;
        }
        
        .alert-button:hover {
            background-color: #1b5e20;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="pricing-container">
        <div class="page-header">
            <h1>Market Pricing Analysis</h1>
            <p>Stay informed with real-time market prices, trends, and forecasts to help you make data-driven decisions for buying, selling, and planning your agricultural business.</p>
        </div>
        
        <div class="market-update">
            <div class="update-header">
                <h2 class="update-title">Current Market Prices</h2>
                <span class="update-date">Updated: <?php echo date('F j, Y'); ?></span>
            </div>
            
            <div class="prices-grid">
                <?php if ($market_prices_result && mysqli_num_rows($market_prices_result) > 0): ?>
                    <?php while ($price = mysqli_fetch_assoc($market_prices_result)): ?>
                        <?php 
                        // Calculate price trend
                        $trend_direction = rand(-1, 1); // In a real app, this would be calculated from historical data
                        $trend_percentage = rand(1, 15) / 10; // Random percentage between 0.1% and 1.5%
                        ?>
                        <div class="price-card">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($price['image_url']); ?>" alt="<?php echo htmlspecialchars($price['product_name']); ?>">
                            </div>
                            <div class="price-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($price['product_name']); ?></h3>
                                <p class="product-category"><?php echo htmlspecialchars($price['category_name']); ?></p>
                                <div class="product-price">₱<?php echo number_format($price['price'], 2); ?></div>
                                <div class="price-trend <?php echo $trend_direction > 0 ? 'trend-up' : ($trend_direction < 0 ? 'trend-down' : ''); ?>">
                                    <?php if ($trend_direction > 0): ?>
                                        <i class="fas fa-arrow-up"></i> <?php echo $trend_percentage; ?>% from yesterday
                                    <?php elseif ($trend_direction < 0): ?>
                                        <i class="fas fa-arrow-down"></i> <?php echo $trend_percentage; ?>% from yesterday
                                    <?php else: ?>
                                        <i class="fas fa-equals"></i> No change from yesterday
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 30px 0;">
                        <p>No current market prices available. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="price-forecast">
            <h2 class="section-title">Price Forecast (Next 6 Months)</h2>
            
            <div class="chart-container">
                <canvas id="forecastChart"></canvas>
            </div>
            
            <div class="forecast-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #2e7d32;"></div>
                    <span class="legend-label">Current Price</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #4caf50;"></div>
                    <span class="legend-label">1 Month Forecast</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #8bc34a;"></div>
                    <span class="legend-label">3 Month Forecast</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #cddc39;"></div>
                    <span class="legend-label">6 Month Forecast</span>
                </div>
            </div>
        </div>
        
        <div class="price-alert">
            <h2 class="section-title">Set Price Alerts</h2>
            <p>Receive notifications when prices for your selected products reach your target threshold.</p>
            
            <form class="alert-form" action="set_price_alert.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="product">Product</label>
                    <select class="form-control" id="product" name="product" required>
                        <option value="">Select a product</option>
                        <option value="1">Rice</option>
                        <option value="2">Corn</option>
                        <option value="3">Tomatoes</option>
                        <option value="4">Potatoes</option>
                        <option value="5">Onions</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="price_threshold">Price Threshold ($)</label>
                    <input type="number" class="form-control" id="price_threshold" name="price_threshold" min="1" step="0.01" required>
                </div>
                
                <button type="submit" class="alert-button">Set Alert</button>
            </form>
        </div>
        
        <div class="articles-section">
            <h2 class="section-title">Market Insights & Trends</h2>
            
            <div class="articles-grid">
                <?php if ($price_articles_result && mysqli_num_rows($price_articles_result) > 0): ?>
                    <?php while ($article = mysqli_fetch_assoc($price_articles_result)): ?>
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
                    <div style="grid-column: 1 / -1; text-align: center; padding: 30px 0;">
                        <p>No market insight articles available. Check back soon for updates!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Price forecast chart
        const ctx = document.getElementById('forecastChart').getContext('2d');
        
        const forecastChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Current Price',
                        data: <?php echo json_encode($current_prices); ?>,
                        backgroundColor: '#2e7d32',
                        borderWidth: 0
                    },
                    {
                        label: '1 Month Forecast',
                        data: <?php echo json_encode($price_1_month); ?>,
                        backgroundColor: '#4caf50',
                        borderWidth: 0
                    },
                    {
                        label: '3 Month Forecast',
                        data: <?php echo json_encode($price_3_month); ?>,
                        backgroundColor: '#8bc34a',
                        borderWidth: 0
                    },
                    {
                        label: '6 Month Forecast',
                        data: <?php echo json_encode($price_6_month); ?>,
                        backgroundColor: '#cddc39',
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Price (₱)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₱' + context.raw.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 