CREATE OR REPLACE VIEW most_viewed_products AS
SELECT 
    p.id AS product_id,
    p.name AS product_name,
    p.price,
    COALESCE(ps.total_view_clicks, 0) as total_views,
    COALESCE(ps.daily_views, 0) as daily_views,
    COALESCE(ps.weekly_views, 0) as weekly_views,
    COALESCE(ps.monthly_views, 0) as monthly_views,
    ps.last_view_click_date
FROM 
    products p
LEFT JOIN 
    product_stats ps ON p.id = ps.product_id
ORDER BY 
    ps.total_view_clicks DESC; 