<?php
/**
 * Enhanced Analytics Tracking System for AgriMarket
 * 
 * This script provides functions to track various user activities
 * in a more detailed and comprehensive way.
 */

require_once 'config.php';

/**
 * Track user activity in the extended analytics system
 * 
 * @param string $type Type of activity (search, visit, order, cart, wishlist, compare)
 * @param array $data Associated data with the activity
 * @return bool Success status
 */
function track_activity($type, $data = []) {
    global $conn;
    
    // Common data
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();
    $device_type = detect_device();
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
    
    // Extract specific data
    $product_id = isset($data['product_id']) ? $data['product_id'] : null;
    $vendor_id = isset($data['vendor_id']) ? $data['vendor_id'] : null;
    $category_id = isset($data['category_id']) ? $data['category_id'] : null;
    $quantity = isset($data['quantity']) ? $data['quantity'] : 1;
    $details = isset($data['details']) ? json_encode($data['details']) : null;
    
    // Insert into analytics_extended
    $query = "INSERT INTO analytics_extended 
              (user_id, vendor_id, type, product_id, category_id, quantity, session_id, device_type, referrer, details) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare analytics statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param(
        $stmt, 
        "iisisissss", 
        $user_id, 
        $vendor_id, 
        $type, 
        $product_id, 
        $category_id, 
        $quantity, 
        $session_id, 
        $device_type, 
        $referrer, 
        $details
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        error_log("Failed to track analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    
    // For backward compatibility, also track in the original analytics table
    track_legacy_analytics($type, $product_id, $quantity);
    
    return $success;
}

/**
 * Track analytics in the original analytics table for backward compatibility
 * 
 * @param string $type Activity type
 * @param int $product_id Product ID
 * @param int $quantity Count/quantity
 * @return bool Success status
 */
function track_legacy_analytics($type, $product_id = null, $quantity = 1) {
    global $conn;
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Map to legacy types if needed
    $legacy_type = $type;
    if (!in_array($type, ['search', 'visit', 'order'])) {
        // For backward compatibility, new activity types are logged as 'visit'
        $legacy_type = 'visit';
    }
    
    $query = "INSERT INTO analytics (user_id, type, product_id, count) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Failed to prepare legacy analytics statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "isii", $user_id, $legacy_type, $product_id, $quantity);
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        error_log("Failed to track legacy analytics: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Helper function to detect device type
 * 
 * @return string Device type (desktop, mobile, tablet)
 */
function detect_device() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($user_agent, 0, 4))) {
        return 'mobile';
    }
    
    if (preg_match('/android|ipad|playbook|silk/i', $user_agent)) {
        return 'tablet';
    }
    
    return 'desktop';
}

/**
 * Track product view
 * 
 * @param int $product_id Product ID
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_product_view($product_id, $product_data = []) {
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'details' => $product_data
    ];
    
    return track_activity('visit', $data);
}

/**
 * Track product search
 * 
 * @param string $search_query Search query
 * @param int $category_id Category ID if filtered
 * @param array $results_data Data about search results
 * @return bool Success status
 */
function track_product_search($search_query, $category_id = null, $results_data = []) {
    $data = [
        'category_id' => $category_id,
        'details' => [
            'query' => $search_query,
            'results_count' => count($results_data),
            'filters' => $_GET['filter'] ?? null
        ]
    ];
    
    return track_activity('search', $data);
}

/**
 * Track order placement
 * 
 * @param int $order_id Order ID
 * @param array $order_items Order items
 * @return bool Success status
 */
function track_order_placement($order_id, $order_items = []) {
    foreach ($order_items as $item) {
        $data = [
            'product_id' => $item['product_id'],
            'vendor_id' => $item['vendor_id'] ?? null,
            'category_id' => $item['category_id'] ?? null,
            'quantity' => $item['quantity'],
            'details' => [
                'order_id' => $order_id,
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price']
            ]
        ];
        
        track_activity('order', $data);
    }
    
    return true;
}

/**
 * Track cart actions
 * 
 * @param string $action Action (add, remove, update)
 * @param int $product_id Product ID
 * @param int $quantity Quantity
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_cart_action($action, $product_id, $quantity = 1, $product_data = []) {
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'quantity' => $quantity,
        'details' => [
            'action' => $action,
            'price' => $product_data['price'] ?? null
        ]
    ];
    
    return track_activity('cart_' . $action, $data);
}

/**
 * Track wishlist actions
 * 
 * @param string $action Action (add, remove)
 * @param int $product_id Product ID
 * @param array $product_data Additional product data
 * @return bool Success status
 */
function track_wishlist_action($action, $product_id, $product_data = []) {
    $data = [
        'product_id' => $product_id,
        'vendor_id' => $product_data['vendor_id'] ?? null,
        'category_id' => $product_data['category_id'] ?? null,
        'details' => [
            'action' => $action
        ]
    ];
    
    return track_activity('wishlist_' . $action, $data);
}

/**
 * Track product comparison
 * 
 * @param array $product_ids Array of product IDs being compared
 * @return bool Success status
 */
function track_product_comparison($product_ids) {
    $data = [
        'details' => [
            'products_compared' => $product_ids,
            'comparison_count' => count($product_ids)
        ]
    ];
    
    return track_activity('compare', $data);
} 