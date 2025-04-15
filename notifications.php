<?php
include 'config.php';

$notifications = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id={$_SESSION['user_id']}");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'index.php'; ?>
    <div class="container">
        <h2>Notifications</h2>
        <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
            <p><?php echo $notification['message']; ?> (<?php echo $notification['type']; ?>)</p>
        <?php endwhile; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>