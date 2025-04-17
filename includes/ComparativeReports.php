<?php
/**
 * ComparativeReports Class
 * 
 * Provides functionality for comparing metrics across different time periods
 */
class ComparativeReports {
    private $conn;
    
    /**
     * Constructor
     */
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get sales comparison between two periods
     * 
     * @param string $current_start Start date for current period
     * @param string $current_end End date for current period
     * @param string $previous_start Start date for previous period
     * @param string $previous_end End date for previous period
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Comparative data
     */
    public function getSalesComparison($current_start, $current_end, $previous_start, $previous_end, $vendor_id = null, $category_id = null) {
        // Current period
        $current_data = $this->getPeriodSales($current_start, $current_end, $vendor_id, $category_id);
        
        // Previous period
        $previous_data = $this->getPeriodSales($previous_start, $previous_end, $vendor_id, $category_id);
        
        // Calculate changes
        $sales_change = $this->calculateChange($current_data['sales'], $previous_data['sales']);
        $orders_change = $this->calculateChange($current_data['orders'], $previous_data['orders']);
        $avg_order_change = $this->calculateChange($current_data['avg_order'], $previous_data['avg_order']);
        
        return [
            'current_period' => $current_data,
            'previous_period' => $previous_data,
            'changes' => [
                'sales' => $sales_change,
                'orders' => $orders_change,
                'avg_order' => $avg_order_change
            ]
        ];
    }
    
    /**
     * Get product performance comparison
     * 
     * @param string $current_start Start date for current period
     * @param string $current_end End date for current period
     * @param string $previous_start Start date for previous period
     * @param string $previous_end End date for previous period
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Product performance comparison data
     */
    public function getProductComparison($current_start, $current_end, $previous_start, $previous_end, $vendor_id = null, $category_id = null) {
        // Current period top products
        $current_products = $this->getTopProducts($current_start, $current_end, $vendor_id, $category_id);
        
        // Previous period top products
        $previous_products = $this->getTopProducts($previous_start, $previous_end, $vendor_id, $category_id);
        
        // Merge data for comparison
        $product_comparison = [];
        
        foreach ($current_products as $product_id => $current) {
            $previous = $previous_products[$product_id] ?? ['quantity' => 0, 'sales' => 0];
            
            $product_comparison[$product_id] = [
                'name' => $current['name'],
                'current' => [
                    'quantity' => $current['quantity'],
                    'sales' => $current['sales']
                ],
                'previous' => [
                    'quantity' => $previous['quantity'],
                    'sales' => $previous['sales']
                ],
                'changes' => [
                    'quantity' => $this->calculateChange($current['quantity'], $previous['quantity']),
                    'sales' => $this->calculateChange($current['sales'], $previous['sales'])
                ]
            ];
        }
        
        // Add previous period products that aren't in current period
        foreach ($previous_products as $product_id => $previous) {
            if (!isset($product_comparison[$product_id])) {
                $product_comparison[$product_id] = [
                    'name' => $previous['name'],
                    'current' => [
                        'quantity' => 0,
                        'sales' => 0
                    ],
                    'previous' => [
                        'quantity' => $previous['quantity'],
                        'sales' => $previous['sales']
                    ],
                    'changes' => [
                        'quantity' => -100, // -100% change
                        'sales' => -100     // -100% change
                    ]
                ];
            }
        }
        
        // Sort by current period sales
        uasort($product_comparison, function($a, $b) {
            return $b['current']['sales'] <=> $a['current']['sales'];
        });
        
        return $product_comparison;
    }
    
    /**
     * Get category performance comparison
     * 
     * @param string $current_start Start date for current period
     * @param string $current_end End date for current period
     * @param string $previous_start Start date for previous period
     * @param string $previous_end End date for previous period
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Category performance comparison data
     */
    public function getCategoryComparison($current_start, $current_end, $previous_start, $previous_end, $vendor_id = null, $category_id = null) {
        // Current period categories
        $current_categories = $this->getCategorySales($current_start, $current_end, $vendor_id, $category_id);
        
        // Previous period categories
        $previous_categories = $this->getCategorySales($previous_start, $previous_end, $vendor_id, $category_id);
        
        // Merge data for comparison
        $category_comparison = [];
        
        foreach ($current_categories as $category_id => $current) {
            $previous = $previous_categories[$category_id] ?? ['quantity' => 0, 'sales' => 0];
            
            $category_comparison[$category_id] = [
                'name' => $current['name'],
                'current' => [
                    'quantity' => $current['quantity'],
                    'sales' => $current['sales']
                ],
                'previous' => [
                    'quantity' => $previous['quantity'],
                    'sales' => $previous['sales']
                ],
                'changes' => [
                    'quantity' => $this->calculateChange($current['quantity'], $previous['quantity']),
                    'sales' => $this->calculateChange($current['sales'], $previous['sales'])
                ]
            ];
        }
        
        // Add previous period categories that aren't in current period
        foreach ($previous_categories as $category_id => $previous) {
            if (!isset($category_comparison[$category_id])) {
                $category_comparison[$category_id] = [
                    'name' => $previous['name'],
                    'current' => [
                        'quantity' => 0,
                        'sales' => 0
                    ],
                    'previous' => [
                        'quantity' => $previous['quantity'],
                        'sales' => $previous['sales']
                    ],
                    'changes' => [
                        'quantity' => -100, // -100% change
                        'sales' => -100     // -100% change
                    ]
                ];
            }
        }
        
        // Sort by current period sales
        uasort($category_comparison, function($a, $b) {
            return $b['current']['sales'] <=> $a['current']['sales'];
        });
        
        return $category_comparison;
    }
    
    /**
     * Get sales metrics for a specific period
     * 
     * @param string $start_date Period start date
     * @param string $end_date Period end date
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Sales metrics
     */
    private function getPeriodSales($start_date, $end_date, $vendor_id = null, $category_id = null) {
        $vendor_condition = '';
        $category_condition = '';
        $params = [$start_date, $end_date];
        $types = 'ss';
        
        if ($vendor_id) {
            $vendor_condition = 'AND p.vendor_id = ?';
            $params[] = $vendor_id;
            $types .= 'i';
        }
        
        if ($category_id) {
            $category_condition = 'AND p.category_id = ?';
            $params[] = $category_id;
            $types .= 'i';
        }
        
        $query = "SELECT 
                    SUM(oi.quantity * oi.price) as total_sales,
                    COUNT(DISTINCT o.order_id) as total_orders
                 FROM orders o
                 JOIN order_items oi ON o.order_id = oi.order_id
                 JOIN products p ON oi.product_id = p.product_id
                 WHERE o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 AND o.status != 'cancelled'
                 $vendor_condition
                 $category_condition";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        $sales = (float)($data['total_sales'] ?? 0);
        $orders = (int)($data['total_orders'] ?? 0);
        $avg_order = $orders > 0 ? $sales / $orders : 0;
        
        return [
            'sales' => $sales,
            'orders' => $orders,
            'avg_order' => $avg_order
        ];
    }
    
    /**
     * Get top products for a specific period
     * 
     * @param string $start_date Period start date
     * @param string $end_date Period end date
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Top products data
     */
    private function getTopProducts($start_date, $end_date, $vendor_id = null, $category_id = null) {
        $vendor_condition = '';
        $category_condition = '';
        $params = [$start_date, $end_date];
        $types = 'ss';
        
        if ($vendor_id) {
            $vendor_condition = 'AND p.vendor_id = ?';
            $params[] = $vendor_id;
            $types .= 'i';
        }
        
        if ($category_id) {
            $category_condition = 'AND p.category_id = ?';
            $params[] = $category_id;
            $types .= 'i';
        }
        
        $query = "SELECT 
                    p.product_id,
                    p.name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.quantity * oi.price) as total_sales
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.order_id
                 JOIN products p ON oi.product_id = p.product_id
                 WHERE o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 AND o.status != 'cancelled'
                 $vendor_condition
                 $category_condition
                 GROUP BY p.product_id
                 ORDER BY total_sales DESC
                 LIMIT 20";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[$row['product_id']] = [
                'name' => $row['name'],
                'quantity' => (int)$row['total_quantity'],
                'sales' => (float)$row['total_sales']
            ];
        }
        
        return $products;
    }
    
    /**
     * Get sales by category for a specific period
     * 
     * @param string $start_date Period start date
     * @param string $end_date Period end date
     * @param int $vendor_id Optional vendor ID for filtering
     * @param int $category_id Optional category ID for filtering
     * @return array Category sales data
     */
    private function getCategorySales($start_date, $end_date, $vendor_id = null, $category_id = null) {
        $vendor_condition = '';
        $category_condition = '';
        $params = [$start_date, $end_date];
        $types = 'ss';
        
        if ($vendor_id) {
            $vendor_condition = 'AND p.vendor_id = ?';
            $params[] = $vendor_id;
            $types .= 'i';
        }
        
        if ($category_id) {
            $category_condition = 'AND p.category_id = ?';
            $params[] = $category_id;
            $types .= 'i';
        }
        
        $query = "SELECT 
                    c.category_id,
                    c.name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.quantity * oi.price) as total_sales
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.order_id
                 JOIN products p ON oi.product_id = p.product_id
                 JOIN categories c ON p.category_id = c.category_id
                 WHERE o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 AND o.status != 'cancelled'
                 $vendor_condition
                 $category_condition
                 GROUP BY c.category_id
                 ORDER BY total_sales DESC";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[$row['category_id']] = [
                'name' => $row['name'],
                'quantity' => (int)$row['total_quantity'],
                'sales' => (float)$row['total_sales']
            ];
        }
        
        return $categories;
    }
    
    /**
     * Calculate percentage change between two values
     * 
     * @param float $current Current value
     * @param float $previous Previous value
     * @return float Percentage change
     */
    private function calculateChange($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0; // 100% increase if previous was 0
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
} 