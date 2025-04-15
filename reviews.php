<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    $sql = "INSERT INTO reviews (product_id, user_id, rating, comment) 
            VALUES ($product_id, $user_id, $rating, '$comment')";
    mysqli_query($conn, $sql);
}

$product_id = $_GET['product_id'] ?? 0;
$reviews = mysqli_query($conn, "SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id=u.user_id WHERE product_id=$product_id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reviews - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'index.php'; ?>
    <div class="container">
        <h2>Product Reviews</h2>
        <form method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
            <select name="rating" required>
                <option value="1">1 Star</option>
                <option value="2">2 Stars</option>
                <option value="3">3 Stars</option>
                <option value="4">4 Stars</option>
                <option value="5">5 Stars</option>
            </select>
            <textarea name="comment" placeholder="Your review"></textarea>
            <button type="submit">Submit Review</button>
        </form>
        <?php while ($review = mysqli_fetch_assoc($reviews)): ?>
            <div>
                <p><?php echo $review['username']; ?> - <?php echo $review['rating']; ?> Stars</p>
                <p><?php echo $review['comment']; ?></p>
            </div>
        <?php endwhile; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>