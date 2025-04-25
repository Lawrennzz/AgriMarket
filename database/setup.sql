-- Create subscription_tier_limits table
CREATE TABLE IF NOT EXISTS subscription_tier_limits (
    tier VARCHAR(50) PRIMARY KEY,
    product_limit INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default tier limits
INSERT INTO subscription_tier_limits (tier, product_limit, price) VALUES
('basic', 10, 0),
('premium', 50, 29.99),
('enterprise', 999999, 99.99)
ON DUPLICATE KEY UPDATE product_limit = VALUES(product_limit);

-- Create subscription_change_requests table
CREATE TABLE IF NOT EXISTS subscription_change_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    current_tier VARCHAR(50) NOT NULL,
    requested_tier VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (current_tier) REFERENCES subscription_tier_limits(tier),
    FOREIGN KEY (requested_tier) REFERENCES subscription_tier_limits(tier)
); 