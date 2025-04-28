DELIMITER //

DROP TRIGGER IF EXISTS after_order_insert//

CREATE TRIGGER after_order_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    -- Track the order in analytics
    INSERT INTO analytics (type, product_id, count, created_at)
    VALUES ('order', NEW.product_id, NEW.quantity, NOW())
    ON DUPLICATE KEY UPDATE count = count + NEW.quantity;
    
    -- Update product stats
    INSERT INTO product_stats (product_id, total_sales, total_revenue, last_sale_date)
    VALUES (NEW.product_id, NEW.quantity, NEW.quantity * NEW.price, NOW())
    ON DUPLICATE KEY UPDATE 
        total_sales = total_sales + NEW.quantity,
        total_revenue = total_revenue + (NEW.quantity * NEW.price),
        last_sale_date = NOW();
END//

DELIMITER ; 