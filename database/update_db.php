<?php
require_once '../includes/db_connect.php';

// Read the SQL file
$sql = file_get_contents('updates.sql');

// Execute the SQL statements
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "Database updates completed successfully!";
} else {
    echo "Error updating database: " . $conn->error;
}

$conn->close();
?> 