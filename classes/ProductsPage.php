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
        // Build the SQL query
        $sql = "SELECT p.*, u.username as vendor_name, c.name as category_name, v.user_id as vendor_user_id 
                FROM products p 
                JOIN vendors v ON p.vendor_id = v.vendor_id 
                LEFT JOIN users u ON v.user_id = u.user_id 
                LEFT JOIN categories c ON p.category_id = c.category_id";
        
        // Add where conditions
        $where_conditions = [];
        if ($this->category_id) {
            $where_conditions[] = "p.category_id = '{$this->category_id}'";
        }
        if ($this->search) {
            $escaped_search = $this->db->escapeString($this->search);
            $where_conditions[] = "(p.name LIKE '%{$escaped_search}%' OR p.description LIKE '%{$escaped_search}%')";
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
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
            default:
                $sql .= " ORDER BY p.name ASC";
        }
        
        // Execute query
        $result = mysqli_query($this->conn, $sql);
        
        // Store products
        $this->products = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->products[] = $row;
            }
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
    
    public function getCategoryId() {
        return $this->category_id;
    }
    
    public function getSearch() {
        return htmlspecialchars_decode($this->search);
    }
    
    public function getSort() {
        return $this->sort;
    }
    
    public function getView() {
        return $this->view;
    }
    
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function render() {
        include 'templates/products_page.php';
    }
} 