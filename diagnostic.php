<?php
// Database connection information
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'agrimarket';

// Test connection
echo "<h2>Testing Database Connection</h2>";
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    echo "<p style='color:red'>Connection failed: " . mysqli_connect_error() . "</p>";
    die();
} else {
    echo "<p style='color:green'>Connection successful!</p>";
}

// Check users table structure
echo "<h2>Checking users Table</h2>";
$user_query = "SHOW TABLES LIKE 'users'";
$user_result = mysqli_query($conn, $user_query);

if (mysqli_num_rows($user_result) > 0) {
    echo "<p style='color:green'>users table exists!</p>";
    
    // Check table structure
    echo "<h3>users Table Structure:</h3>";
    $user_structure_query = "DESCRIBE users";
    $user_structure_result = mysqli_query($conn, $user_structure_query);
    
    if ($user_structure_result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = mysqli_fetch_assoc($user_structure_result)) {
            echo "<tr>";
            echo "<td>".$row['Field']."</td>";
            echo "<td>".$row['Type']."</td>";
            echo "<td>".$row['Null']."</td>";
            echo "<td>".$row['Key']."</td>";
            echo "<td>".$row['Default']."</td>";
            echo "<td>".$row['Extra']."</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>Error describing users table: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:red'>users table does not exist!</p>";
}

// Test if staff_details table exists
echo "<h2>Checking staff_details Table</h2>";
$query = "SHOW TABLES LIKE 'staff_details'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color:green'>staff_details table exists!</p>";
    
    // Check table structure
    echo "<h3>staff_details Table Structure:</h3>";
    $structure_query = "DESCRIBE staff_details";
    $structure_result = mysqli_query($conn, $structure_query);
    
    if ($structure_result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = mysqli_fetch_assoc($structure_result)) {
            echo "<tr>";
            echo "<td>".$row['Field']."</td>";
            echo "<td>".$row['Type']."</td>";
            echo "<td>".$row['Null']."</td>";
            echo "<td>".$row['Key']."</td>";
            echo "<td>".$row['Default']."</td>";
            echo "<td>".$row['Extra']."</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>Error describing table: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:red'>staff_details table does not exist!</p>";
}

// Test SQL statement preparation for users
echo "<h2>Testing SQL Statement Preparation</h2>";
$test_user_query = "INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'staff', ?)";
$test_user_stmt = mysqli_prepare($conn, $test_user_query);

if ($test_user_stmt) {
    echo "<p style='color:green'>User statement preparation successful!</p>";
    mysqli_stmt_close($test_user_stmt);
} else {
    echo "<p style='color:red'>User statement preparation failed: " . mysqli_error($conn) . "</p>";
}

// Test SQL statement preparation for staff details
$test_staff_query = "INSERT INTO staff_details (user_id, position) VALUES (?, ?)";
$test_staff_stmt = mysqli_prepare($conn, $test_staff_query);

if ($test_staff_stmt) {
    echo "<p style='color:green'>Staff details statement preparation successful!</p>";
    mysqli_stmt_close($test_staff_stmt);
} else {
    echo "<p style='color:red'>Staff details statement preparation failed: " . mysqli_error($conn) . "</p>";
}

// Check if audit_logs table exists
echo "<h2>Checking audit_logs Table</h2>";
$audit_query = "SHOW TABLES LIKE 'audit_logs'";
$audit_result = mysqli_query($conn, $audit_query);

if (mysqli_num_rows($audit_result) > 0) {
    echo "<p style='color:green'>audit_logs table exists!</p>";
    
    // Check table structure
    echo "<h3>audit_logs Table Structure:</h3>";
    $audit_structure_query = "DESCRIBE audit_logs";
    $audit_structure_result = mysqli_query($conn, $audit_structure_query);
    
    if ($audit_structure_result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = mysqli_fetch_assoc($audit_structure_result)) {
            echo "<tr>";
            echo "<td>".$row['Field']."</td>";
            echo "<td>".$row['Type']."</td>";
            echo "<td>".$row['Null']."</td>";
            echo "<td>".$row['Key']."</td>";
            echo "<td>".$row['Default']."</td>";
            echo "<td>".$row['Extra']."</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>Error describing audit_logs table: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:red'>audit_logs table does not exist!</p>";
}

// Finally, close the connection
mysqli_close($conn);
echo "<p>Connection closed.</p>";
?> 