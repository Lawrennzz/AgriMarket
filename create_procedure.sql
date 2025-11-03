DELIMITER //

DROP PROCEDURE IF EXISTS update_product_view_stats//

CREATE PROCEDURE update_product_view_stats(IN p_product_id INT)
BEGIN
    UPDATE product_stats 
    SET 
        total_view_clicks = COALESCE(total_view_clicks, 0) + 1,
        daily_views = COALESCE(daily_views, 0) + 1,
        weekly_views = COALESCE(weekly_views, 0) + 1,
        monthly_views = COALESCE(monthly_views, 0) + 1,
        last_view_click_date = NOW()
    WHERE product_id = p_product_id;
    
    IF ROW_COUNT() = 0 THEN
        INSERT INTO product_stats 
            (product_id, total_view_clicks, daily_views, weekly_views, monthly_views, last_view_click_date)
        VALUES 
            (p_product_id, 1, 1, 1, 1, NOW());
    END IF;
END//

DROP PROCEDURE IF EXISTS reset_periodic_views//

CREATE PROCEDURE reset_periodic_views()
BEGIN
    UPDATE product_stats 
    SET daily_views = 0 
    WHERE DATE(last_view_click_date) < CURDATE();
    
    UPDATE product_stats 
    SET weekly_views = 0 
    WHERE YEARWEEK(last_view_click_date) < YEARWEEK(NOW());
    
    UPDATE product_stats 
    SET monthly_views = 0 
    WHERE MONTH(last_view_click_date) < MONTH(NOW()) 
       OR YEAR(last_view_click_date) < YEAR(NOW());
END//

DELIMITER ; 