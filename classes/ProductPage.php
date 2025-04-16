<?php
require_once 'Database.php';
require_once 'Product.php';

class ProductPage {
    private $db;
    private $conn;
    private $user_id;
    private $role;
    private $product_id;
    private $product;
    private $related_products;
    private $reviews;
    private $avg_rating;
    private $total_reviews;
    
    public function __construct($product_id = null) {
        // Initialize database connection
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        
        // Get user information
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $this->role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
        
        // Get product ID from URL parameter or constructor
        if ($product_id !== null) {
            $this->product_id = $product_id;
        } else {
            if (!isset($_GET['id'])) {
                header("Location: products.php");
                exit();
            }
            $this->product_id = (int)$_GET['id'];
        }
        
        // Load product data
        $this->loadProduct();
        
        // If product not found, redirect to products page
        if (!$this->product) {
            header("Location: products.php");
            exit();
        }
        
        // Load related data
        $this->loadRelatedProducts();
        $this->loadReviews();
        
        // Log product view
        $this->logProductView();
    }
    
    private function loadProduct() {
        // Get product details with vendor and category information
        $sql = "SELECT p.*, u.name as vendor_name, u.email as vendor_email, c.name as category_name, v.user_id as vendor_user_id 
                FROM products p 
                JOIN vendors v ON p.vendor_id = v.vendor_id 
                JOIN users u ON v.user_id = u.user_id 
                JOIN categories c ON p.category_id = c.category_id 
                WHERE p.product_id = ?";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $this->product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $this->product = mysqli_fetch_assoc($result);
    }
    
    private function loadRelatedProducts() {
        // Get related products
        $sql = "SELECT * FROM products 
                WHERE category_id = ? AND product_id != ? 
                LIMIT 4";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false || !$this->product) {
            $this->related_products = [];
            return;
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $this->product['category_id'], $this->product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $this->related_products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $this->related_products[] = $row;
        }
    }
    
    private function loadReviews() {
        // Get product reviews
        $sql = "SELECT r.*, u.name as reviewer_name 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.user_id 
                WHERE r.product_id = ? 
                ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) {
            $this->reviews = [];
            $this->avg_rating = 0;
            $this->total_reviews = 0;
            return;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $this->product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // Calculate average rating
        $this->reviews = [];
        $this->total_reviews = mysqli_num_rows($result);
        $this->avg_rating = 0;
        
        if ($this->total_reviews > 0) {
            $ratings_sum = 0;
            while ($review = mysqli_fetch_assoc($result)) {
                $ratings_sum += $review['rating'];
                $this->reviews[] = $review;
            }
            $this->avg_rating = round($ratings_sum / $this->total_reviews, 1);
        }
    }
    
    private function logProductView() {
        // Log product view in analytics
        $sql = "INSERT INTO analytics (type, product_id, count) VALUES ('view', ?, 1)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, "i", $this->product_id);
            mysqli_stmt_execute($stmt);
        }
    }
    
    public function getProduct() {
        return $this->product;
    }
    
    public function getRelatedProducts() {
        return $this->related_products;
    }
    
    public function getReviews() {
        return $this->reviews;
    }
    
    public function getAverageRating() {
        return $this->avg_rating;
    }
    
    public function getTotalReviews() {
        return $this->total_reviews;
    }
    
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function render() {
        include 'templates/product_page.php';
    }
} 