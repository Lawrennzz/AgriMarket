-- Create product_views table for tracking product views
CREATE TABLE IF NOT EXISTS `product_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `view_datetime` DATETIME NOT NULL,
  `source` VARCHAR(100) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_view_datetime` (`view_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 