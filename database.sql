CREATE DATABASE agrimarket;
USE agrimarket;

-- Users table (customers, vendors, staff, admin)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'vendor', 'customer', 'staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendor profiles
CREATE TABLE vendors (
    vendor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    business_name VARCHAR(100) NOT NULL,
    subscription_tier ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Products
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    name VARCHAR(100) NOT NULL,
    category ENUM('livestock', 'crops', 'forestry', 'dairy', 'fish', 'misc') NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL,
    packaging VARCHAR(50),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
);

-- Orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Order items
CREATE TABLE order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Reviews
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    user_id INT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type ENUM('order', 'stock', 'promotion') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Analytics
CREATE TABLE analytics (
    analytic_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('search', 'visit', 'order') NOT NULL,
    product_id INT,
    count INT DEFAULT 1,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Update products table to add featured column 
ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0;

-- Order items
CREATE TABLE order_items (
       item_id INT AUTO_INCREMENT PRIMARY KEY,
       order_id INT,
       product_id INT,
       quantity INT NOT NULL,
       price DECIMAL(10,2) NOT NULL,
       FOREIGN KEY (order_id) REFERENCES orders(order_id),
       FOREIGN KEY (product_id) REFERENCES products(product_id)
   );

-- Add image_url column to products table
ALTER TABLE products ADD COLUMN image_url VARCHAR(255);

-- Add name column to users table
ALTER TABLE users ADD COLUMN name VARCHAR(100);

-- Wishlist
CREATE TABLE wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Add user_type column to users table
ALTER TABLE users ADD COLUMN user_type ENUM('admin', 'vendor', 'customer', 'staff') NOT NULL DEFAULT 'customer';

-- Categories
CREATE TABLE categories (
       category_id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(100) NOT NULL,
       description TEXT
   );

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Livestock', 'Cattle, poultry, hogs, etc.'),
('Crops', 'Corn, soybeans, hay, etc.'),
('Edible Forestry Products', 'Almonds, walnuts, etc.'),
('Dairy', 'Milk products'),
('Fish Farming', 'Aquaculture products'),
('Miscellaneous', 'Honey, etc.');