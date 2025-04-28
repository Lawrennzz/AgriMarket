-- Create product_stats_daily table
CREATE TABLE IF NOT EXISTS product_stats_daily (
    daily_stat_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    stat_date DATE NOT NULL,
    daily_sales INT DEFAULT 0,
    daily_orders INT DEFAULT 0,
    daily_views INT DEFAULT 0,
    daily_revenue DECIMAL(10,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_date (product_id, stat_date),
    INDEX (product_id),
    INDEX (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial daily stats for today for existing products
INSERT INTO product_stats_daily (product_id, stat_date, daily_sales, daily_orders, daily_views, daily_revenue)
SELECT 
    product_id,
    CURDATE() as stat_date,
    0 as daily_sales,
    0 as daily_orders,
    0 as daily_views,
    0.00 as daily_revenue
FROM products; 