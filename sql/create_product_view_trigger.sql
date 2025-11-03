USE agrimarket;

DROP TRIGGER IF EXISTS after_product_view_insert;

CREATE TRIGGER after_product_view_insert
AFTER INSERT ON product_views
FOR EACH ROW
BEGIN
    DECLARE current_date DATE;
    DECLARE week_start DATE;
    DECLARE month_start DATE;
    
    SET current_date = CURDATE();
    SET week_start = DATE_SUB(current_date, INTERVAL WEEKDAY(current_date) DAY);
    SET month_start = DATE_FORMAT(current_date, '%Y-%m-01');
    
    -- Update or insert a record in product_stats table
    INSERT INTO product_stats 
        (product_id, total_views, daily_views, weekly_views, monthly_views, last_view_date) 
    VALUES 
        (NEW.product_id, 1, 
         (NEW.view_date >= current_date), 
         (NEW.view_date >= week_start), 
         (NEW.view_date >= month_start), 
         NOW()) 
    ON DUPLICATE KEY UPDATE 
        total_views = total_views + 1,
        daily_views = daily_views + (NEW.view_date >= current_date),
        weekly_views = weekly_views + (NEW.view_date >= week_start),
        monthly_views = monthly_views + (NEW.view_date >= month_start),
        last_view_date = NOW();
END; 