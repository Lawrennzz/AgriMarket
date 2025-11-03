-- Update product tracking for new product_views structure
USE agrimarket;

-- First, drop any existing triggers on product_views
DROP TRIGGER IF EXISTS after_product_view_insert;

-- Make sure product_stats table exists with the correct structure
CREATE TABLE IF NOT EXISTS `product_stats` (
  `stat_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `total_views` int(11) NOT NULL DEFAULT 0,
  `total_view_clicks` int(11) NOT NULL DEFAULT 0,
  `daily_views` int(11) NOT NULL DEFAULT 0,
  `weekly_views` int(11) NOT NULL DEFAULT 0,
  `monthly_views` int(11) NOT NULL DEFAULT 0,
  `last_view_date` datetime DEFAULT NULL,
  `last_view_click_date` datetime DEFAULT NULL,
  PRIMARY KEY (`stat_id`),
  UNIQUE KEY `product_id` (`product_id`),
  CONSTRAINT `product_stats_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize product_stats based on existing product_views data
INSERT INTO product_stats 
    (product_id, total_views, daily_views, weekly_views, monthly_views, last_view_date)
SELECT 
    product_id, 
    COUNT(*) as total, 
    SUM(view_date >= CURDATE()) as daily,
    SUM(view_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)) as weekly,
    SUM(view_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) as monthly,
    MAX(view_date) as last_view
FROM 
    product_views
GROUP BY 
    product_id
ON DUPLICATE KEY UPDATE 
    total_views = VALUES(total_views),
    daily_views = VALUES(daily_views),
    weekly_views = VALUES(weekly_views),
    monthly_views = VALUES(monthly_views),
    last_view_date = VALUES(last_view_date); 