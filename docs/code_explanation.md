# AgriMarket Code Implementation Guide

## 1. Ratings & Reviews System
```php
// File: includes/ratings.php

// Handle product review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit_review') {
    $product_id = sanitize_input($_POST['product_id']);
    $rating = sanitize_input($_POST['rating']);
    $comment = sanitize_input($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    if (empty($rating) || empty($comment)) {
        $_SESSION['error'] = "Rating and comment are required.";
    } else {
        // Insert review
        $query = "INSERT INTO product_reviews (product_id, user_id, rating, comment, status) 
                 VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iids", $product_id, $user_id, $rating, $comment);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Review submitted successfully.";
        }
    }
}

// Handle review moderation (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'moderate_review') {
    if ($_SESSION['user_type'] === 'admin') {
        $review_id = sanitize_input($_POST['review_id']);
        $status = sanitize_input($_POST['status']);
        
        $query = "UPDATE product_reviews 
                 SET status = ?, moderated_by = ?, moderated_at = CURRENT_TIMESTAMP 
                 WHERE review_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $status, $_SESSION['user_id'], $review_id);
        $stmt->execute();
    }
}
```

## 2. Notifications & Alerts System
```php
// File: includes/notifications.php

// Send order confirmation notification
function sendOrderNotification($order_id, $user_id) {
    // Get order details
    $query = "SELECT * FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    // Get user's notification preferences
    $query = "SELECT notification_type FROM user_settings WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $preferences = $stmt->get_result()->fetch_assoc();

    // Send notifications based on preferences
    if ($preferences['notification_type'] === 'email') {
        sendEmail($user_id, "Order #$order_id Confirmation", $emailTemplate);
    } elseif ($preferences['notification_type'] === 'sms') {
        sendSMS($user_id, "Your order #$order_id has been confirmed");
    }
}

// Check low stock and send alerts
function checkLowStockAlerts() {
    $query = "SELECT p.*, v.user_id as vendor_id 
             FROM products p 
             JOIN vendors v ON p.vendor_id = v.vendor_id 
             WHERE p.stock <= p.stock_threshold";
    $result = $conn->query($query);

    while ($product = $result->fetch_assoc()) {
        sendVendorNotification(
            $product['vendor_id'],
            "Low Stock Alert",
            "Product {$product['name']} is running low (Current stock: {$product['stock']})"
        );
    }
}
```

## 3. Account & System Settings
```php
// File: includes/settings.php

// Update user profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_profile') {
    $user_id = $_SESSION['user_id'];
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
    } else {
        $query = "UPDATE users 
                 SET name = ?, email = ?, phone_number = ? 
                 WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully";
        }
    }
}

// Update vendor settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_vendor_settings') {
    if ($_SESSION['user_type'] === 'vendor') {
        $vendor_id = $_SESSION['vendor_id'];
        $business_name = sanitize_input($_POST['business_name']);
        $notification_preferences = $_POST['notifications'];

        $query = "UPDATE vendors 
                 SET business_name = ? 
                 WHERE vendor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $business_name, $vendor_id);
        $stmt->execute();

        // Update notification preferences
        foreach ($notification_preferences as $type => $value) {
            $query = "INSERT INTO vendor_settings (vendor_id, setting_key, setting_value) 
                     VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isss", $vendor_id, $type, $value, $value);
            $stmt->execute();
        }
    }
}
```

## 4. Reports & Analytics
```php
// File: includes/analytics.php

// Get most searched products
function getMostSearchedProducts($start_date = null, $end_date = null) {
    $query = "SELECT p.name, COUNT(*) as search_count 
             FROM product_search_logs psl 
             JOIN products p ON JSON_CONTAINS(psl.product_ids, CAST(p.product_id AS JSON))
             WHERE 1=1";
    
    if ($start_date) {
        $query .= " AND psl.created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $query .= " AND psl.created_at <= ?";
        $params[] = $end_date;
    }
    
    $query .= " GROUP BY p.product_id 
                ORDER BY search_count DESC 
                LIMIT 10";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Generate sales report
function generateSalesReport($period_type, $start_date, $end_date) {
    $query = "SELECT 
             DATE(o.created_at) as date,
             COUNT(DISTINCT o.order_id) as total_orders,
             SUM(o.total) as total_revenue,
             COUNT(DISTINCT o.user_id) as unique_customers
             FROM orders o
             WHERE o.status = 'delivered'
             AND o.created_at BETWEEN ? AND ?
             GROUP BY DATE(o.created_at)
             ORDER BY date";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    
    $report = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate period totals
    $totals = [
        'total_orders' => array_sum(array_column($report, 'total_orders')),
        'total_revenue' => array_sum(array_column($report, 'total_revenue')),
        'avg_order_value' => array_sum(array_column($report, 'total_revenue')) / array_sum(array_column($report, 'total_orders')),
        'unique_customers' => count(array_unique(array_column($report, 'unique_customers')))
    ];
    
    return ['daily_data' => $report, 'totals' => $totals];
}
```

## Usage Examples

1. **Submit a Product Review**:
```php
// In your product page
if (isset($_SESSION['user_id'])) {
    echo '<form method="POST">
        <input type="hidden" name="action" value="submit_review">
        <input type="hidden" name="product_id" value="' . $product_id . '">
        <select name="rating" required>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
        </select>
        <textarea name="comment" required></textarea>
        <button type="submit">Submit Review</button>
    </form>';
}
```

2. **Send Notification**:
```php
// After order confirmation
$order_id = $result->insert_id;
sendOrderNotification($order_id, $_SESSION['user_id']);
```

3. **Update Profile**:
```php
// In profile page
echo '<form method="POST">
    <input type="hidden" name="action" value="update_profile">
    <input type="text" name="name" value="' . htmlspecialchars($user['name']) . '">
    <input type="email" name="email" value="' . htmlspecialchars($user['email']) . '">
    <input type="tel" name="phone" value="' . htmlspecialchars($user['phone_number']) . '">
    <button type="submit">Update Profile</button>
</form>';
```

4. **Generate Reports**:
```php
// In admin dashboard
$monthly_report = generateSalesReport(
    'monthly',
    date('Y-m-01'), // First day of current month
    date('Y-m-t')   // Last day of current month
);
```

## Security Considerations

1. Always sanitize user inputs
2. Use prepared statements for SQL queries
3. Validate user permissions before actions
4. Implement CSRF protection
5. Validate file uploads for reviews
6. Rate limit API endpoints
7. Encrypt sensitive data
8. Log important actions

## Performance Optimization

1. Cache frequently accessed data
2. Index commonly queried columns
3. Use pagination for large datasets
4. Optimize database queries
5. Implement lazy loading
6. Use background jobs for notifications
7. Compress images in reviews
8. Cache report results 