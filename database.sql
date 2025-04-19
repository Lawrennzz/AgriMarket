CREATE DATABASE agrimarket;
USE agrimarket;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL, -- For hashed passwords
    email VARCHAR(100) NOT NULL CHECK (email REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'),
    role ENUM('admin', 'vendor', 'customer', 'staff') NOT NULL,
    name VARCHAR(100),
    security_question VARCHAR(255),
    security_answer VARCHAR(255),
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE (username),
    UNIQUE (email)
);

-- Vendor profiles
CREATE TABLE vendors (
    vendor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_name VARCHAR(100) NOT NULL,
    subscription_tier ENUM('basic', 'premium', 'enterprise') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Categories
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Livestock', 'Cattle, poultry, hogs, etc.'),
('Crops', 'Corn, soybeans, hay, etc.'),
('Edible Forestry Products', 'Almonds, walnuts, etc.'),
('Dairy', 'Milk products'),
('Fish Farming', 'Aquaculture products'),
('Miscellaneous', 'Honey, etc.');

-- Products
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL,
    packaging VARCHAR(50),
    image_url VARCHAR(255) CHECK (image_url REGEXP '^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[/#?]?.*$'),
    featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address VARCHAR(255) NOT NULL,
    processed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Order items
CREATE TABLE order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

-- Order status history
CREATE TABLE order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- Reviews
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_id INT NOT NULL,
    rating DECIMAL(2,1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    moderated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    INDEX (user_id, product_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type ENUM('order', 'stock', 'promotion') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Analytics
CREATE TABLE analytics (
    analytic_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('search', 'visit', 'order') NOT NULL,
    product_id INT,
    count INT DEFAULT 1,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

-- Wishlist
CREATE TABLE wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Cart
CREATE TABLE cart (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Audit logs
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Settings
CREATE TABLE settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create staff_details table
CREATE TABLE staff_details (
    staff_detail_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    position VARCHAR(50) NOT NULL,
    department VARCHAR(50),
    hire_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Create staff_tasks table
CREATE TABLE staff_tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATE,
    completion_date DATETIME,
    assigned_by INT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
);

-- Payment logs table
CREATE TABLE payment_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    order_id INT,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL
);

-- Extended Analytics table
CREATE TABLE analytics_extended (
    analytic_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    vendor_id INT,
    type VARCHAR(50) NOT NULL, -- More flexibility with types: 'search', 'visit', 'order', 'wishlist', 'cart', 'compare', etc.
    product_id INT,
    category_id INT,
    quantity INT DEFAULT 1,
    session_id VARCHAR(255),
    device_type VARCHAR(50),
    referrer VARCHAR(255),
    details TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

CREATE INDEX idx_analytics_extended_type ON analytics_extended(type);
CREATE INDEX idx_analytics_extended_user ON analytics_extended(user_id);
CREATE INDEX idx_analytics_extended_vendor ON analytics_extended(vendor_id);
CREATE INDEX idx_analytics_extended_product ON analytics_extended(product_id);
CREATE INDEX idx_analytics_extended_date ON analytics_extended(recorded_at);

-- Indexes for performance
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_product_vendor_id ON products(vendor_id);
CREATE INDEX idx_order_user_status ON orders(user_id, status);
CREATE INDEX idx_analytics_type ON analytics(type);
CREATE INDEX idx_notifications_user ON notifications(user_id);

-- Create customer_messages table
CREATE TABLE `customer_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','answered') NOT NULL DEFAULT 'unread',
  `is_reply` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_message_user` (`user_id`),
  KEY `idx_message_staff` (`staff_id`),
  KEY `idx_message_status` (`status`),
  CONSTRAINT `fk_message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_message_parent` FOREIGN KEY (`parent_id`) REFERENCES `customer_messages` (`message_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================
-- AgriMarket Database Update Script
-- ================================

-- Ensure we're using the correct database
USE agrimarket;

-- ================================
-- 1. Add missing columns to orders table if needed
-- ================================

-- Check and add payment_method column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'payment_method';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL',
    'SELECT "payment_method column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add subtotal column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'subtotal';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0',
    'SELECT "subtotal column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add shipping column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'shipping';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN shipping DECIMAL(10,2) DEFAULT 0',
    'SELECT "shipping column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add tax column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'tax';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0',
    'SELECT "tax column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add payment_status column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'payment_status';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN payment_status ENUM("pending", "processing", "completed", "failed", "refunded") DEFAULT "pending"',
    'SELECT "payment_status column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add transaction_id column
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'transaction_id';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL',
    'SELECT "transaction_id column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================
-- 2. Update payment_logs table with additional fields
-- ================================

-- Check if payment_logs table exists, create if not
SET @table_exists = 0;
SELECT COUNT(*) INTO @table_exists 
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name = 'payment_logs';

SET @sql = IF(@table_exists = 0, 
    'CREATE TABLE payment_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        order_id INT,
        status VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL
    )',
    'SELECT "payment_logs table already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add subtotal column to payment_logs if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'payment_logs' 
AND column_name = 'subtotal';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE payment_logs ADD COLUMN subtotal DECIMAL(10,2) DEFAULT NULL AFTER amount',
    'SELECT "subtotal column already exists in payment_logs" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add tax column to payment_logs if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'payment_logs' 
AND column_name = 'tax';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE payment_logs ADD COLUMN tax DECIMAL(10,2) DEFAULT NULL AFTER subtotal',
    'SELECT "tax column already exists in payment_logs" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping column to payment_logs if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'payment_logs' 
AND column_name = 'shipping';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE payment_logs ADD COLUMN shipping DECIMAL(10,2) DEFAULT NULL AFTER tax',
    'SELECT "shipping column already exists in payment_logs" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add payment_details column to payment_logs if it doesn't exist
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'payment_logs' 
AND column_name = 'payment_details';

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE payment_logs ADD COLUMN payment_details JSON DEFAULT NULL',
    'SELECT "payment_details column already exists in payment_logs" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update status field to ENUM type if possible (safely)
SET @column_type = '';
SELECT DATA_TYPE INTO @column_type
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'payment_logs' 
AND column_name = 'status';

SET @sql = IF(@column_type != 'enum', 
    'ALTER TABLE payment_logs MODIFY COLUMN status ENUM("pending", "processing", "completed", "failed", "refunded") DEFAULT "pending"',
    'SELECT "status column is already ENUM type" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if index exists and add if needed
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics
WHERE table_schema = DATABASE()
AND table_name = 'payment_logs'
AND index_name = 'idx_payment_logs_order_id';

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_payment_logs_order_id ON payment_logs(order_id)',
    'SELECT "Index idx_payment_logs_order_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================
-- 3. Update existing payment logs with data from orders
-- ================================

-- Update payment_details with available information
UPDATE payment_logs pl
JOIN orders o ON pl.order_id = o.order_id
SET pl.payment_details = JSON_OBJECT(
    'payment_method', IFNULL(o.payment_method, 'Not specified'),
    'transaction_id', IFNULL(o.transaction_id, ''),
    'payment_status', IFNULL(o.payment_status, 'pending')
)
WHERE o.order_id IS NOT NULL;

-- Update numeric fields
UPDATE payment_logs pl
JOIN orders o ON pl.order_id = o.order_id
SET 
    pl.subtotal = IFNULL(o.subtotal, 0),
    pl.tax = IFNULL(o.tax, 0),
    pl.shipping = IFNULL(o.shipping, 0)
WHERE o.order_id IS NOT NULL;

-- ================================
-- 4. Create stored procedure for updating payment logs
-- ================================

-- Drop procedure if it exists
DROP PROCEDURE IF EXISTS update_payment_log_after_order_update;

-- Create stored procedure for updating payment logs
DELIMITER //
CREATE PROCEDURE update_payment_log_after_order_update(
    IN p_order_id INT, 
    IN p_payment_method VARCHAR(50),
    IN p_subtotal DECIMAL(10,2),
    IN p_tax DECIMAL(10,2),
    IN p_shipping DECIMAL(10,2),
    IN p_status VARCHAR(50),
    IN p_transaction_id VARCHAR(100)
)
BEGIN
    DECLARE log_exists INT DEFAULT 0;
    
    -- Check if a payment log exists for this order
    SELECT COUNT(*) INTO log_exists FROM payment_logs WHERE order_id = p_order_id;
    
    IF log_exists > 0 THEN
        -- Update the most recent payment log
        UPDATE payment_logs 
        SET 
            payment_method = IFNULL(p_payment_method, payment_method),
            subtotal = IFNULL(p_subtotal, subtotal),
            tax = IFNULL(p_tax, tax),
            shipping = IFNULL(p_shipping, shipping),
            status = IFNULL(p_status, status),
            payment_details = JSON_OBJECT(
                'transaction_id', p_transaction_id,
                'payment_method', p_payment_method,
                'payment_status', p_status
            )
        WHERE 
            order_id = p_order_id
        ORDER BY created_at DESC
        LIMIT 1;
    END IF;
END //
DELIMITER ;

-- ================================
-- 5. Create trigger for automatic updates
-- ================================

-- Drop trigger if it exists
DROP TRIGGER IF EXISTS after_order_update;

-- Create trigger to automatically update payment logs
DELIMITER //
CREATE TRIGGER after_order_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF (NEW.payment_method IS NOT NULL OR 
        NEW.subtotal IS NOT NULL OR 
        NEW.tax IS NOT NULL OR 
        NEW.shipping IS NOT NULL OR
        NEW.payment_status IS NOT NULL OR
        NEW.transaction_id IS NOT NULL) THEN
        
        CALL update_payment_log_after_order_update(
            NEW.order_id,
            NEW.payment_method,
            NEW.subtotal,
            NEW.tax,
            NEW.shipping,
            NEW.payment_status,
            NEW.transaction_id
        );
    END IF;
END //
DELIMITER ;

-- ================================
-- 6. Final confirmation message
-- ================================

SELECT 'Database update completed successfully' AS 'Result';

-- Add rating column to products table if not exists
ALTER TABLE products
ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1) DEFAULT NULL;

-- ================================
-- Analytics Tables
-- ================================

-- Check if analytics table exists and create it if not
DROP TABLE IF EXISTS analytics;
CREATE TABLE analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    product_id INT,
    session_id VARCHAR(100),
    user_id INT,
    user_ip VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (type),
    INDEX (product_id),
    INDEX (user_id),
    INDEX (created_at)
);

-- Check if product_search_logs table exists and create it if not
DROP TABLE IF EXISTS product_search_logs;
CREATE TABLE product_search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_term VARCHAR(255) NOT NULL,
    product_ids JSON,
    session_id VARCHAR(100),
    user_id INT,
    user_ip VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (search_term),
    INDEX (user_id),
    INDEX (created_at)
);

-- Check if product_visits table exists and create it if not
DROP TABLE IF EXISTS product_visits;
CREATE TABLE product_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    session_id VARCHAR(100) NOT NULL,
    user_ip VARCHAR(45),
    visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id),
    INDEX (user_id),
    INDEX (visit_date)
);

-- Check if analytics_extended table exists and create it if not
DROP TABLE IF EXISTS analytics_extended;
CREATE TABLE analytics_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    vendor_id INT,
    type VARCHAR(50) NOT NULL,
    product_id INT,
    category_id INT,
    quantity INT DEFAULT 1,
    session_id VARCHAR(100) NOT NULL,
    device_type VARCHAR(20),
    referrer VARCHAR(255),
    user_ip VARCHAR(45),
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (type),
    INDEX (product_id),
    INDEX (user_id),
    INDEX (vendor_id),
    INDEX (category_id),
    INDEX (created_at)
);

-- Create foreign key relationships
ALTER TABLE analytics
ADD CONSTRAINT fk_analytics_product
FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_analytics_user
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

ALTER TABLE product_visits
ADD CONSTRAINT fk_visits_product
FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_visits_user
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

ALTER TABLE analytics_extended
ADD CONSTRAINT fk_analytics_ext_product
FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_analytics_ext_user
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_analytics_ext_vendor
FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_analytics_ext_category
FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL;