-- Create the products table if it doesn't exist
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create the product view summary table
CREATE TABLE IF NOT EXISTS product_view_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    total_views INT DEFAULT 0,
    daily_views INT DEFAULT 0,
    weekly_views INT DEFAULT 0,
    monthly_views INT DEFAULT 0,
    last_viewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_total_views (total_views),
    INDEX idx_last_viewed (last_viewed_at),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Create view tracking procedure
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS update_product_view_stats(IN p_product_id INT)
BEGIN
    -- Update existing record if exists
    UPDATE product_view_summary 
    SET 
        total_views = total_views + 1,
        daily_views = daily_views + 1,
        weekly_views = weekly_views + 1,
        monthly_views = monthly_views + 1,
        last_viewed_at = NOW()
    WHERE product_id = p_product_id;
    
    -- Insert new record if doesn't exist
    IF ROW_COUNT() = 0 THEN
        INSERT INTO product_view_summary 
            (product_id, total_views, daily_views, weekly_views, monthly_views, last_viewed_at)
        VALUES 
            (p_product_id, 1, 1, 1, 1, NOW());
    END IF;
END //

-- Create procedure to reset periodic counters
CREATE PROCEDURE IF NOT EXISTS reset_periodic_views()
BEGIN
    -- Reset daily views
    UPDATE product_view_summary 
    SET daily_views = 0 
    WHERE DATE(last_viewed_at) < CURDATE();
    
    -- Reset weekly views
    UPDATE product_view_summary 
    SET weekly_views = 0 
    WHERE YEARWEEK(last_viewed_at) < YEARWEEK(NOW());
    
    -- Reset monthly views
    UPDATE product_view_summary 
    SET monthly_views = 0 
    WHERE MONTH(last_viewed_at) < MONTH(NOW()) 
       OR YEAR(last_viewed_at) < YEAR(NOW());
END //

-- Create view for most viewed products report
CREATE OR REPLACE VIEW most_viewed_products AS
SELECT 
    p.id AS product_id,
    p.name AS product_name,
    p.price,
    COALESCE(pvs.total_views, 0) as total_views,
    COALESCE(pvs.daily_views, 0) as daily_views,
    COALESCE(pvs.weekly_views, 0) as weekly_views,
    COALESCE(pvs.monthly_views, 0) as monthly_views,
    pvs.last_viewed_at
FROM 
    products p
LEFT JOIN 
    product_view_summary pvs ON p.id = pvs.product_id
ORDER BY 
    pvs.total_views DESC //

DELIMITER ;

-- Create event to automatically reset periodic views
CREATE EVENT IF NOT EXISTS reset_periodic_views_event
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO CALL reset_periodic_views(); 