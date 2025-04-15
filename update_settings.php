<?php
session_start();
include 'config.php'; // Include your database connection

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare the update query
    $query = "UPDATE users SET username = ?, email = ?";
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query .= ", password = ?";
    }
    $query .= " WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $query);
    if (!empty($password)) {
        mysqli_stmt_bind_param($stmt, "sssi", $username, $email, $hashed_password, $user_id);
    } else {
        mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo "Settings updated successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>