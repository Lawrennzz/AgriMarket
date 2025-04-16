<?php
require_once 'Database.php';

class AuditLog {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Log an action in the audit_logs table
     * 
     * @param int|null $user_id The ID of the user who performed the action
     * @param string $action The action performed (e.g., 'create', 'update', 'delete')
     * @param string $table_name The name of the table affected (e.g., 'products', 'vendors')
     * @param int|null $record_id The ID of the record affected
     * @param string|array $details Additional details about the action (can be a string or an array)
     * @return bool True if logging was successful, false otherwise
     */
    public function log($user_id, $action, $table_name, $record_id = null, $details = '') {
        // Convert array details to JSON
        if (is_array($details)) {
            $details = json_encode($details);
        }
        
        $query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            error_log("Failed to prepare audit log query: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "issis", $user_id, $action, $table_name, $record_id, $details);
        $result = mysqli_stmt_execute($stmt);
        
        if (!$result) {
            error_log("Failed to insert audit log: " . mysqli_error($this->conn));
        }
        
        mysqli_stmt_close($stmt);
        return $result;
    }
    
    /**
     * Get audit logs for a specific table and/or record
     * 
     * @param string|null $table_name The name of the table to filter by (optional)
     * @param int|null $record_id The ID of the record to filter by (optional)
     * @param int|null $limit Maximum number of logs to retrieve (optional)
     * @param int $offset Offset for pagination (optional)
     * @return array Array of audit log records
     */
    public function getLogs($table_name = null, $record_id = null, $limit = null, $offset = 0) {
        $query = "SELECT l.*, u.username 
                  FROM audit_logs l 
                  LEFT JOIN users u ON l.user_id = u.user_id";
        
        $params = [];
        $types = "";
        
        // Add filters
        $conditions = [];
        if ($table_name) {
            $conditions[] = "l.table_name = ?";
            $params[] = $table_name;
            $types .= "s";
        }
        
        if ($record_id) {
            $conditions[] = "l.record_id = ?";
            $params[] = $record_id;
            $types .= "i";
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        // Add order and limit
        $query .= " ORDER BY l.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            error_log("Failed to prepare getLogs query: " . mysqli_error($this->conn));
            return [];
        }
        
        if (!empty($params)) {
            // Create a reference array for bind_param
            $bind_params = [];
            $bind_params[] = $stmt;
            $bind_params[] = $types;
            
            // Add references to each parameter
            for ($i = 0; $i < count($params); $i++) {
                $bind_params[] = &$params[$i];
            }
            
            // Call bind_param with the references array
            call_user_func_array('mysqli_stmt_bind_param', $bind_params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $logs = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Parse JSON details if stored as JSON
            if (isset($row['details']) && $this->isJson($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
            $logs[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $logs;
    }
    
    /**
     * Check if a string is valid JSON
     * 
     * @param string $string The string to check
     * @return bool True if the string is valid JSON, false otherwise
     */
    private function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
} 