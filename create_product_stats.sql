-- Drop the table if it exists
DROP TABLE IF EXISTS product_stats;

-- Create the product_stats table
CREATE TABLE product_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    total_sales INT DEFAULT 0,
    total_views INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    last_sale_date DATETIME DEFAULT NULL,
    last_view_date DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_stats_product_id (product_id)
);

-- Initialize stats for all existing products
INSERT INTO product_stats (product_id, total_sales, total_views, total_revenue)
SELECT 
    p.product_id,
    COALESCE((
        SELECT SUM(oi.quantity)
        FROM order_items oi
        WHERE oi.product_id = p.product_id
    ), 0) as total_sales,
    COALESCE((
        SELECT COUNT(*)
        FROM analytics a
        WHERE a.product_id = p.product_id
        AND a.type = 'product_view'
    ), 0) as total_views,
    COALESCE((
        SELECT SUM(oi.quantity * oi.price)
        FROM order_items oi
        WHERE oi.product_id = p.product_id
    ), 0) as total_revenue
FROM products p
WHERE NOT EXISTS (
    SELECT 1 
    FROM product_stats ps 
    WHERE ps.product_id = p.product_id
);

-- Update last sale dates
UPDATE product_stats ps
JOIN (
    SELECT 
        oi.product_id,
        MAX(o.created_at) as last_sale
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    GROUP BY oi.product_id
) sales ON ps.product_id = sales.product_id
SET ps.last_sale_date = sales.last_sale;

-- Update last view dates
UPDATE product_stats ps
JOIN (
    SELECT 
        product_id,
        MAX(created_at) as last_view
    FROM analytics
    WHERE type = 'product_view'
    GROUP BY product_id
) views ON ps.product_id = views.product_id
SET ps.last_view_date = views.last_view; 