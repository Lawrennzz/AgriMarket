# Analytics System Implementation Plan

## Overview

Based on the analysis of the codebase, the AgriMarket project has two parallel analytics systems:

1. **Legacy Analytics System (`analytics` table)**: A simpler system that tracks three types of activities:
   - Product visits
   - Product searches
   - Product orders

2. **Enhanced Analytics System (`analytics_extended` table)**: A more detailed system that tracks additional activities with more contextual information:
   - All legacy activities plus: cart actions, wishlist actions, product comparisons
   - Additional metadata like device type, session, referrer, and detailed JSON data

## Current Status

The system has two main components:

### 1. `includes/analytics_tracking.php`
- Legacy tracking system from older versions of the application
- Functions: `recordProductView()`, `recordProductSearch()`, `recordOrder()`
- Has basic integration with the extended system but is not optimized

### 2. `includes/track_analytics.php`
- Enhanced tracking system with more capabilities
- Functions like `track_activity()`, `track_product_view()`, `track_cart_action()`, etc.
- Handles both tables for backward compatibility
- **Issue**: Does not appear to be included anywhere in the codebase

### 3. Admin Utilities
- `admin/fix_analytics_tables.php`: Used to create/fix the analytics tables
- `admin/check_analytics.php`: Displays the current state of the analytics tables

## Implementation Plan

### Phase 1: Database Migration

1. **Run the Fix Analytics Tables Utility**
   - Use the `admin/fix_analytics_tables.php` tool to ensure both tables exist
   - Add the `user_id` column to the legacy table if missing
   - Add proper indexes for performance optimization

2. **Verify Schema Structure**
   - Ensure the `analytics` table has: `analytic_id`, `user_id`, `type`, `product_id`, `count`, `recorded_at`
   - Ensure the `analytics_extended` table has all required fields including the JSON details column

### Phase 2: Code Integration

1. **Include the Enhanced Tracking System**
   - Add proper includes for `track_analytics.php` in key files, particularly:
     - In `includes/functions.php` to make it globally available
     - Or at the top of key files that need tracking (product_details.php, search.php, etc.)

2. **Replace Legacy Function Calls**
   - Identify where `recordProductView()`, `recordProductSearch()`, and `recordOrder()` are used
   - Replace with the enhanced versions: `track_product_view()`, `track_product_search()`, `track_order_placement()`

3. **Add New Tracking Points**
   - Add calls to `track_cart_action()` in cart.php operations
   - Add calls to `track_wishlist_action()` in wishlist operations
   - Add calls to `track_product_comparison()` in product comparison functionality

### Phase 3: Reporting and Visualization

1. **Create Analytics Dashboard**
   - Develop a dashboard in the admin area to display key metrics
   - Charts for most viewed/searched/purchased products
   - User activity trends over time
   - Device type distribution

2. **Create Detailed Reports**
   - Product performance reports
   - Search term effectiveness
   - User journey analysis
   - Cart abandonment tracking

### Phase 4: Testing and Optimization

1. **Testing**
   - Verify all analytics are being properly recorded
   - Check for any performance issues during peak loads
   - Test on multiple device types

2. **Optimization**
   - Add batch processing for high-volume operations
   - Consider adding a queue system for analytics if performance is an issue
   - Implement a purge/archiving system for older analytics data

## Implementation Schedule

1. **Phase 1**: 1-2 days
2. **Phase 2**: 3-5 days
3. **Phase 3**: 5-7 days
4. **Phase 4**: 2-3 days

Total estimated time: 11-17 days

## Implementation Examples

### 1. Including the Enhanced Analytics System Globally

To make the enhanced analytics functions available across the application, we added a require statement in `includes/functions.php`:

```php
<?php
/**
 * Utility functions for the AgriMarket website
 */

// Include analytics tracking system
require_once __DIR__ . '/track_analytics.php';

// Other functions...
```

### 2. Updating Product View Tracking

In `product_details.php`, we've enhanced the tracking code to use the new functions while maintaining backward compatibility:

```php
// Track product view in analytics
if (function_exists('track_product_view')) {
    track_product_view($product_id, array(
        'vendor_id' => $product['vendor_id'],
        'category_id' => $product['category_id'],
        'name' => $product['name'],
        'price' => $product['price']
    ));
} else if (file_exists('includes/analytics_tracking.php')) {
    require_once 'includes/analytics_tracking.php';
    recordProductView($product_id);
}
```

### 3. Adding Cart Tracking

To track cart actions, we would add the following code to `cart.php` (or similar file) where cart actions are handled:

```php
// Example: When adding a product to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Get product details for tracking
    $product_query = "SELECT p.*, c.category_id FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.category_id
                      WHERE p.product_id = ?";
    $stmt = mysqli_prepare($conn, $product_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product_data = mysqli_fetch_assoc($result);
    
    // Add to cart logic here...
    
    // Track the cart action
    if (function_exists('track_cart_action')) {
        track_cart_action('add', $product_id, $quantity, $product_data);
    }
}
```

### 4. Tracking Search Queries

To enhance search tracking in a search results page:

```php
// Search form handling
$search_query = isset($_GET['q']) ? $_GET['q'] : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Execute search and get results...
$results = []; // Array of product IDs from search results

// Track the search
if (function_exists('track_product_search')) {
    track_product_search($search_query, $category_id, $results);
} else if (file_exists('includes/analytics_tracking.php')) {
    require_once 'includes/analytics_tracking.php';
    recordProductSearch($search_query, $results, $category_id);
}
``` 