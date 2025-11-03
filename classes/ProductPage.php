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
    private $can_review;
    private $has_reviewed;
    private $purchase_date;
    
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
        $this->checkReviewEligibility();
        
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
                WHERE p.product_id = ? AND p.deleted_at IS NULL";
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
                WHERE category_id = ? AND product_id != ? AND deleted_at IS NULL
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
        $sql = "SELECT r.*, u.name as reviewer_name, 
                (SELECT COUNT(*) FROM reviews WHERE user_id = r.user_id) as total_reviews,
                CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END as verified_purchase
                FROM reviews r 
                JOIN users u ON r.user_id = u.user_id
                LEFT JOIN orders o ON r.user_id = o.user_id AND o.status = 'delivered'
                WHERE r.product_id = ?
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $this->product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $this->reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
            
            // Calculate average rating and total reviews
            $this->total_reviews = count($this->reviews);
            if ($this->total_reviews > 0) {
                $total_rating = array_sum(array_column($this->reviews, 'rating'));
                $this->avg_rating = $total_rating / $this->total_reviews;
            } else {
                $this->avg_rating = 0;
            }
        }
    }
    
    private function checkReviewEligibility() {
        if (!$this->user_id) {
            $this->can_review = false;
            $this->has_reviewed = false;
            return;
        }
        
        // Check if user has already reviewed
        $sql = "SELECT created_at FROM reviews 
                WHERE user_id = ? AND product_id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $this->user_id, $this->product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $this->has_reviewed = mysqli_num_rows($result) > 0;
            mysqli_stmt_close($stmt);
        }
        
        // Check if user has purchased and received the product
        if (!$this->has_reviewed) {
            $sql = "SELECT o.created_at 
                    FROM orders o 
                    JOIN order_items oi ON o.order_id = oi.order_id 
                    WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
                    ORDER BY o.created_at DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $this->user_id, $this->product_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    $this->can_review = true;
                    $this->purchase_date = $row['created_at'];
                } else {
                    $this->can_review = false;
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $this->can_review = false;
        }
    }
    
    private function logProductView() {
        // Include the track_analytics file
        if (!function_exists('track_product_view_click')) {
            require_once dirname(__DIR__) . '/includes/track_analytics.php';
        }
        
        // Track the product view click with source information
        $source = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'direct';
        track_product_view_click($this->product_id, $source);
    }
    
    public function getProduct() {
        return $this->product;
    }
    
    public function getRelatedProducts() {
        return $this->related_products;
    }
    
    public function getReviews() {
        return $this->reviews ?? [];
    }
    
    public function getAverageRating() {
        return $this->avg_rating ?? 0;
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
        return $this->purchase_date;
    }
    
    public function render() {
        include 'templates/product_page.php';
    }
} 