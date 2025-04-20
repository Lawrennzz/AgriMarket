<?php
require_once 'Database.php';
require_once 'Product.php';

class ProductsPage {
    private $db;
    private $conn;
    private $user_id;
    private $role;
    private $category_id;
    private $search;
    private $sort;
    private $view;
    private $products;
    private $categories;
    private $total_reviews;
    private $can_review;
    private $has_reviewed;
    private $purchase_date;
    
    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        
        // Get user information
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $this->role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
        
        // Get filter parameters
        $this->category_id = isset($_GET['category_id']) ? $this->db->escapeString($_GET['category_id']) : '';
        $this->search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $this->sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
        $this->view = isset($_GET['view']) ? $_GET['view'] : 'grid';
        
        // Load products and categories
        $this->loadProducts();
        $this->loadCategories();
        
        // Log search analytics if search term was provided
        if ($this->search) {
            // Include the functions file if needed
            if (!function_exists('logProductSearch')) {
                require_once 'includes/functions.php';
            }
            
            // Extract product IDs from the loaded products for better analytics tracking
            $product_ids = [];
            foreach ($this->products as $product) {
                $product_ids[] = $product['product_id'];
            }
            
            // Log the search with the product IDs found
            logProductSearch($this->conn, $this->search, $product_ids);
        }
    }
    
    private function loadProducts() {
        // Base query to get products
        $sql = "SELECT p.*, c.name as category_name, v.business_name as vendor_name, v.user_id as vendor_user_id, 
                (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                WHERE p.deleted_at IS NULL";
        
        $params = [];
        $types = "";
        
        // Add category filter if category is selected
        if ($this->category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $this->category_id;
            $types .= "i";
        }
        
        // Add search term filter if search is provided
        if ($this->search) {
            $search_term = '%' . $this->search . '%';
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= "sss";
        }
        
        // Add sorting
        switch ($this->sort) {
            case 'price_asc':
                $sql .= " ORDER BY p.price ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY p.price DESC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY p.name DESC";
                break;
            case 'name_asc':
            default:
                $sql .= " ORDER BY p.name ASC";
                break;
        }
        
        // Prepare and execute query
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) {
            $this->products = [];
            return;
        }
        
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $this->products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $this->products[] = $row;
        }
    }
    
    private function loadCategories() {
        // Get all categories for filter from categories table
        $categories_query = mysqli_query($this->conn, "SELECT category_id, name FROM categories ORDER BY name");
        $this->categories = [];
        if ($categories_query) {
            while ($cat = mysqli_fetch_assoc($categories_query)) {
                $this->categories[] = $cat;
            }
        }
    }
    
    public function getProducts() {
        return $this->products;
    }
    
    public function getCategories() {
        return $this->categories;
    }
    
    public function getTotalReviews() {
        return $this->total_reviews ?? 0;
    }
    
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function canReview() {
        return $this->can_review ?? false;
    }
    
    public function hasReviewed() {
        return $this->has_reviewed ?? false;
    }
    
    public function getPurchaseDate() {
        return $this->purchase_date ?? null;
    }
    
    // Setter methods for search parameters
    public function setCategoryId($category_id) {
        $this->category_id = (int)$category_id;
        return $this;
    }
    
    public function setSearch($search) {
        $this->search = trim($search);
        return $this;
    }
    
    public function setSort($sort) {
        $this->sort = $sort;
        return $this;
    }
    
    public function setView($view) {
        $this->view = $view;
        return $this;
    }
    
    public function getCategoryId() {
        return $this->category_id;
    }
    
    public function getSearch() {
        return $this->search;
    }
    
    public function getSort() {
        return $this->sort;
    }
    
    public function getView() {
        return $this->view;
    }
    
    public function render() {
        // Load products if not already loaded
        if (empty($this->products)) {
            $this->loadProducts();
        }
        
        // Track search analytics if search term was provided using the new tracking system
        if ($this->search) {
            // First check if the track_analytics file exists and include it
            if (file_exists(dirname(__DIR__) . '/includes/track_analytics.php')) {
                require_once dirname(__DIR__) . '/includes/track_analytics.php';
                
                // Format products data for analytics tracking
                $search_results = [];
                foreach ($this->products as $product) {
                    $search_results[] = [
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'vendor_id' => $product['vendor_id'],
                        'category_id' => $product['category_id']
                    ];
                }
                
                // Track the search
                track_product_search($this->search, $this->category_id, $search_results);
            }
        }
        
        // Include the products template to render the page
        include dirname(__DIR__) . '/templates/products_page.php';
    }
} 