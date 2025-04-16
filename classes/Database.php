<?php

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        // Database connection
        $this->conn = mysqli_connect('localhost', 'root', '', 'agrimarket');
        if (!$this->conn) {
            error_log("Database connection failed: " . mysqli_connect_error());
            die("An error occurred. Please try again later.");
        }
    }
    
    // Singleton pattern to ensure only one database connection
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    // Get the database connection
    public function getConnection() {
        return $this->conn;
    }
    
    // Close the database connection
    public function closeConnection() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
    
    // Helper method for preparing statements
    public function prepare($query) {
        $stmt = mysqli_prepare($this->conn, $query);
        if ($stmt === false) {
            error_log("Prepare failed: " . mysqli_error($this->conn));
            return false;
        }
        return $stmt;
    }
    
    // Helper method for escaping strings
    public function escapeString($string) {
        return mysqli_real_escape_string($this->conn, $string);
    }
} 