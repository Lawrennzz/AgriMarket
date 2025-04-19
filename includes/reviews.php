<?php
require_once 'config.php';
require_once 'db_connection.php';
require_once 'functions.php';

class ReviewSystem {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    public function canUserReview($user_id, $product_id) {
        // Check if user has purchased and received the product
        $sql = "SELECT COUNT(*) as purchase_count 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.order_id 
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            return $row['purchase_count'] > 0;
        }
        return false;
    }
    
    public function hasUserReviewed($user_id, $product_id) {
        // Check if user has already reviewed the product
        $sql = "SELECT COUNT(*) as review_count 
                FROM reviews 
                WHERE user_id = ? AND product_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            return $row['review_count'] > 0;
        }
        return false;
    }
    
    public function getPurchaseDate($user_id, $product_id) {
        // Get the purchase date of the product
        $sql = "SELECT o.created_at 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.order_id 
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
                ORDER BY o.created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            return $row ? $row['created_at'] : null;
        }
        return null;
    }
    
    public function getProductReviews($product_id) {
        // Get all reviews for a product with verified purchase status
        $sql = "SELECT r.*, u.name as reviewer_name,
                    (SELECT COUNT(*) FROM reviews WHERE user_id = r.user_id) as total_reviews,
                    (SELECT COUNT(*) 
                     FROM order_items oi 
                     JOIN orders o ON oi.order_id = o.order_id 
                     WHERE o.user_id = r.user_id 
                     AND oi.product_id = r.product_id 
                     AND o.status = 'delivered') as verified_purchase
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.user_id 
                WHERE r.product_id = ? 
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            return mysqli_stmt_get_result($stmt);
        }
        return false;
    }
    
    public function addReview($user_id, $product_id, $rating, $comment) {
        // Verify user can review
        if (!$this->canUserReview($user_id, $product_id)) {
            return [
                'success' => false,
                'message' => 'You can only review products you have purchased and received.'
            ];
        }
        
        // Check if already reviewed
        if ($this->hasUserReviewed($user_id, $product_id)) {
            return [
                'success' => false,
                'message' => 'You have already reviewed this product.'
            ];
        }
        
        // Add the review
        $sql = "INSERT INTO reviews (user_id, product_id, rating, comment, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiis", $user_id, $product_id, $rating, $comment);
            $success = mysqli_stmt_execute($stmt);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Your review has been added successfully.'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to add review. Please try again.'
        ];
    }
    
    public function getAverageRating($product_id) {
        // Get average rating for a product
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                FROM reviews 
                WHERE product_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            return mysqli_fetch_assoc($result);
        }
        return ['avg_rating' => 0, 'total_reviews' => 0];
    }
}

// Create global functions for easier access
function canUserReview($user_id, $product_id) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->canUserReview($user_id, $product_id);
}

function hasUserReviewed($user_id, $product_id) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->hasUserReviewed($user_id, $product_id);
}

function getPurchaseDate($user_id, $product_id) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->getPurchaseDate($user_id, $product_id);
}

function getProductReviews($product_id) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->getProductReviews($product_id);
}

function addReview($user_id, $product_id, $rating, $comment) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->addReview($user_id, $product_id, $rating, $comment);
}

function getAverageRating($product_id) {
    $reviewSystem = new ReviewSystem();
    return $reviewSystem->getAverageRating($product_id);
}

// Function to update product's average rating
function updateProductRating($product_id) {
    $conn = getConnection();
    $query = "UPDATE products p 
              SET rating = (
                  SELECT AVG(rating) 
                  FROM reviews 
                  WHERE product_id = ? 
                  AND status = 'approved'
              )
              WHERE product_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $product_id, $product_id);
    mysqli_stmt_execute($stmt);
}

// Function to moderate a review
function moderateReview($review_id, $status) {
    $conn = getConnection();
    $valid_statuses = ['approved', 'rejected'];
    
    if (!in_array($status, $valid_statuses)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }
    
    $query = "UPDATE reviews SET status = ?, moderated_at = NOW() WHERE review_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $review_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // If approved/rejected, update product rating
        $product_query = "SELECT product_id FROM reviews WHERE review_id = ?";
        $stmt = mysqli_prepare($conn, $product_query);
        mysqli_stmt_bind_param($stmt, "i", $review_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);
        
        if ($product) {
            updateProductRating($product['product_id']);
        }
        
        return ['success' => true, 'message' => 'Review ' . $status . ' successfully.'];
    }
    
    return ['success' => false, 'message' => 'Error moderating review.'];
}
?> 