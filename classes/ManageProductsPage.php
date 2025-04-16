<?php
require_once 'Database.php';
require_once 'Product.php';

class ManageProductsPage {
    private $db;
    private $conn;
    private $products;
    private $success;
    private $error;
    
    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        
        // Check if user is admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $_SESSION['last_activity'] = time();
        
        // Handle product deletion
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
            $this->deleteProduct();
        }
        
        // Load products
        $this->loadProducts();
    }
    
    private function loadProducts() {
        // Fetch products with category name
        $query = "SELECT p.product_id, p.name, p.price, p.stock, c.name AS category_name, p.created_at 
                  FROM products p 
                  JOIN categories c ON p.category_id = c.category_id 
                  ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            $this->error = "Failed to prepare query: " . mysqli_error($this->conn);
            $this->products = [];
            return;
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $this->products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $this->products[] = $row;
        }
    }
    
    private function deleteProduct() {
        $product_id = (int)$_POST['product_id'];
        
        // Use the Product class to delete the product
        $product = new Product();
        $product->setId($product_id);
        
        if ($product->delete()) {
            $this->success = "Product deleted successfully!";
            header("Location: manage_products.php");
            exit();
        } else {
            $this->error = "Failed to delete product.";
        }
    }
    
    public function getProducts() {
        return $this->products;
    }
    
    public function getSuccess() {
        return $this->success;
    }
    
    public function getError() {
        return $this->error;
    }
    
    public function render() {
        include 'templates/manage_products_page.php';
    }
} 