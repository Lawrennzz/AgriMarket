<?php
session_start();
include 'config.php'; // Include your database connection

$user_id = $_SESSION['user_id']; // Assuming user ID is stored in session

$query = "
    SELECT w.wishlist_id, p.name, p.image_url 
    FROM wishlist w 
    JOIN products p ON w.product_id = p.product_id 
    WHERE w.user_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Your Wishlist</title>
</head>
<body>
    <h1>Your Wishlist</h1>
    <div class="wishlist">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="wishlist-item">
                <img src="<?php echo $row['image_url']; ?>" alt="<?php echo $row['name']; ?>">
                <h2><?php echo $row['name']; ?></h2>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
