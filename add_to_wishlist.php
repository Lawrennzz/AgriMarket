<?php
session_start();
include 'config.php'; // Include your database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id']; // Assuming user ID is stored in session
    $product_id = $_POST['product_id']; // Get product ID from the form submission

    // Check if the product is already in the wishlist
    $check_query = "SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($result) > 0) {
        echo "This product is already in your wishlist.";
    } else {
        // Insert the product into the wishlist
        $insert_query = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ii", $user_id, $product_id);

        if (mysqli_stmt_execute($insert_stmt)) {
            echo "Product added to wishlist!";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}
?>