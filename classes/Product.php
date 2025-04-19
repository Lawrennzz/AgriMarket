<?php
require_once 'Database.php';

class Product {
    private $db;
    private $conn;
    
    // Product properties
    private $id;
    private $name;
    private $description;
    private $price;
    private $stock;
    private $category;
    private $image_url;
    private $vendor_id;
    private $created_at;
    private $updated_at;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    // Getters and setters
    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
    
    public function getDescription() {
        return $this->description;
    }
    
    public function setDescription($description) {
        $this->description = $description;
    }
    
    public function getPrice() {
        return $this->price;
    }
    
    public function setPrice($price) {
        $this->price = $price;
    }
    
    public function getStock() {
        return $this->stock;
    }
    
    public function setStock($stock) {
        $this->stock = $stock;
    }
    
    public function getCategory() {
        return $this->category;
    }
    
    public function setCategory($category) {
        $this->category = $category;
    }
    
    public function getImageUrl() {
        return $this->image_url;
    }
    
    public function setImageUrl($image_url) {
        $this->image_url = $image_url;
    }
    
    public function getVendorId() {
        return $this->vendor_id;
    }
    
    public function setVendorId($vendor_id) {
        $this->vendor_id = $vendor_id;
    }
    
    // Load product data from database
    public function loadById($id) {
        $query = "SELECT * FROM products WHERE product_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $this->id = $row['product_id'];
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->stock = $row['stock'];
            $this->category = $row['category'];
            $this->image_url = $row['image_url'];
            $this->vendor_id = $row['vendor_id'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }
    
    // Create new product
    public function create() {
        $query = "INSERT INTO products (name, description, price, stock, category, image_url, vendor_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "ssdsisi", 
            $this->name, 
            $this->description, 
            $this->price, 
            $this->stock, 
            $this->category, 
            $this->image_url, 
            $this->vendor_id
        );
        
        $result = mysqli_stmt_execute($stmt);
        
        if ($result) {
            $this->id = mysqli_insert_id($this->conn);
            return true;
        }
        return false;
    }
    
    // Update existing product
    public function update() {
        $query = "UPDATE products SET 
                  name = ?, 
                  description = ?, 
                  price = ?, 
                  stock = ?, 
                  category = ?, 
                  image_url = ?, 
                  updated_at = NOW() 
                  WHERE product_id = ?";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "ssdissi", 
            $this->name, 
            $this->description, 
            $this->price, 
            $this->stock, 
            $this->category, 
            $this->image_url, 
            $this->id
        );
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Delete product
    public function delete() {
        $query = "DELETE FROM products WHERE product_id = ?";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $this->id);
        return mysqli_stmt_execute($stmt);
    }
    
    // Get all products
    public static function getAll($limit = null, $offset = 0) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $query = "SELECT p.*, v.company_name as vendor_name, u.name as vendor_user_name
                  FROM products p
                  LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                  LEFT JOIN users u ON v.user_id = u.user_id
                  WHERE p.deleted_at IS NULL
                  ORDER BY p.created_at DESC";
                  
        if ($limit !== null) {
            $query .= " LIMIT ?, ?";
        }
        
        $stmt = $db->prepare($query);
        
        if ($stmt === false) {
            return [];
        }
        
        if ($limit !== null) {
            mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    // Get products by vendor
    public static function getByVendor($vendor_id) {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM products WHERE vendor_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        
        if ($stmt === false) {
            return [];
        }
        
        mysqli_stmt_bind_param($stmt, "i", $vendor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    // Get products by category
    public static function getByCategory($category) {
        $db = Database::getInstance();
        
        $query = "SELECT p.*, v.company_name as vendor_name, u.name as vendor_user_name
                  FROM products p
                  LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                  LEFT JOIN users u ON v.user_id = u.user_id
                  WHERE p.category = ? AND p.deleted_at IS NULL
                  ORDER BY p.created_at DESC";
        $stmt = $db->prepare($query);
        
        if ($stmt === false) {
            return [];
        }
        
        mysqli_stmt_bind_param($stmt, "s", $category);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    // Search products
    public static function search($keyword) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $keyword = '%' . $db->escapeString($keyword) . '%';
        
        $query = "SELECT p.*, v.company_name as vendor_name, u.name as vendor_user_name
                  FROM products p
                  LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                  LEFT JOIN users u ON v.user_id = u.user_id
                  WHERE (p.name LIKE ? OR p.description LIKE ?) AND p.deleted_at IS NULL
                  ORDER BY p.created_at DESC";
        $stmt = $db->prepare($query);
        
        if ($stmt === false) {
            return [];
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    // Upload product image
    public function uploadImage($file) {
        $target_dir = "../uploads/products/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $filename = time() . '_' . basename($file["name"]);
        $target_file = $target_dir . $filename;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is an actual image
        $check = getimagesize($file["tmp_name"]);
        if ($check === false) {
            return ["error" => "File is not an image."];
        }
        
        // Check file size (5MB max)
        if ($file["size"] > 5000000) {
            return ["error" => "File is too large. Max 5MB allowed."];
        }
        
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            return ["error" => "Only JPG, JPEG, PNG & GIF files are allowed."];
        }
        
        // Upload file
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            $this->image_url = 'uploads/products/' . $filename;
            return ["success" => true, "image_url" => $this->image_url];
        } else {
            return ["error" => "There was an error uploading your file."];
        }
    }
} 