<?php
// This script is intended to be run from the command line
// Example: php tools/backfill_audit_logs.php

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Load database configuration
$config_file = __DIR__ . '/../config.php';

if (!file_exists($config_file)) {
    die("Config file not found at: $config_file\n");
}

// Define SESSION variables needed by the included files
$_SESSION = ['user_id' => 1, 'role' => 'admin']; // Use admin user ID 1 as the actor

// Include configuration
require_once $config_file;
require_once __DIR__ . '/../classes/AuditLog.php';

// Initialize the audit logger
$auditLogger = new AuditLog();

echo "Starting audit log backfill process...\n";

// Function to generate a log message
function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

// Function to backfill logs for a specific table
function backfillTable($conn, $auditLogger, $table, $id_column, $search_fields = [], $action = 'create') {
    logMessage("Processing $table table...");
    
    $query = "SELECT * FROM $table";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        logMessage("Error: " . mysqli_error($conn));
        return false;
    }
    
    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $record_id = $row[$id_column];
        
        // Create details array with key fields
        $details = [];
        foreach ($search_fields as $field) {
            if (isset($row[$field])) {
                $details[$field] = $row[$field];
            }
        }
        
        // Add creation date if available
        if (isset($row['created_at'])) {
            $details['created_at'] = $row['created_at'];
        }
        
        // Log the record
        $success = $auditLogger->log(
            1, // Admin user ID
            $action,
            $table,
            $record_id,
            $details
        );
        
        if ($success) {
            $count++;
        } else {
            logMessage("Failed to log record $record_id in $table");
        }
    }
    
    logMessage("Successfully processed $count records from $table");
    return true;
}

// Backfill products table
backfillTable(
    $conn, 
    $auditLogger, 
    'products', 
    'product_id', 
    ['name', 'description', 'price', 'stock', 'vendor_id']
);

// Backfill vendors table
backfillTable(
    $conn, 
    $auditLogger, 
    'vendors', 
    'vendor_id',
    ['user_id', 'business_name', 'subscription_tier'] 
);

// Backfill orders table
backfillTable(
    $conn, 
    $auditLogger, 
    'orders', 
    'order_id',
    ['user_id', 'total', 'status']
);

// Backfill users table
backfillTable(
    $conn, 
    $auditLogger, 
    'users', 
    'user_id',
    ['username', 'email', 'role']
);

logMessage("Audit log backfill process completed!"); 