-- SQL script to create tables for product view tracking
-- Create this file as sql/product_view_tracking.sql

-- Create product_views table
CREATE TABLE IF NOT EXISTS `product_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `source` varchar(50) DEFAULT 'direct',
  `view_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  KEY `view_date` (`view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create analytics table for more general tracking
CREATE TABLE IF NOT EXISTS `analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `data` text NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `action` (`action`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraints if products table exists
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`product_id`) 
  REFERENCES `products` (`id`) ON DELETE CASCADE;

-- Add foreign key constraints if users table exists
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_2` FOREIGN KEY (`user_id`) 
  REFERENCES `users` (`id`) ON DELETE SET NULL; 