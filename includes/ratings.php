<?php
require_once 'db_connect.php';
require_once 'functions.php';

class RatingSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Check if a user has already reviewed a product
     * @param int $user_id The user ID
     * @param int $product_id The product ID
     * @return bool True if user has reviewed, false otherwise
     */
    public function hasUserReviewedProduct($user_id, $product_id) {
        $query = "SELECT COUNT(*) as count 
                 FROM reviews 
                 WHERE user_id = ? AND product_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparing statement: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] > 0;
    }
}
?> 