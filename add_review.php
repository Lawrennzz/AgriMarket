<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/ProductPage.php';

// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if we have the required parameters
if (!isset($_GET['order_id']) || !isset($_GET['product_id'])) {
    header('Location: my_orders.php');
    exit();
}

$order_id = (int)$_GET['order_id'];
$product_id = (int)$_GET['product_id'];
$user_id = $_SESSION['user_id'];

$db = Database::getInstance();
$conn = $db->getConnection();

// Verify that this order belongs to the user and is delivered
$sql = "SELECT o.*, p.name as product_name, p.image_url 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE o.order_id = ? AND o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'";

$stmt = $db->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = "System error: Unable to verify order. Please try again later.";
    error_log("Failed to prepare order verification statement: " . mysqli_error($conn));
    header('Location: my_orders.php');
    exit();
}

mysqli_stmt_bind_param($stmt, "iii", $order_id, $user_id, $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    $_SESSION['error'] = "This order doesn't exist, isn't delivered yet, or doesn't belong to you.";
    header('Location: my_orders.php');
    exit();
}
mysqli_stmt_close($stmt);

// Check if user has already reviewed this product
$sql = "SELECT * FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?";
$stmt = $db->prepare($sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_fetch_assoc($result)) {
        $_SESSION['error'] = "You have already reviewed this product for this order.";
        header('Location: my_orders.php');
        exit();
    }
    mysqli_stmt_close($stmt);
} else {
    // Log the error and continue
    error_log("Failed to prepare review check statement: " . mysqli_error($conn));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($comment)) {
        $error = "Please write a review comment.";
    } else {
        // Insert the review
        $sql = "INSERT INTO reviews (user_id, product_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiiis", $user_id, $product_id, $order_id, $rating, $comment);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Thank you for your review!";
                header('Location: my_orders.php');
                exit();
            } else {
                $error = "Error saving your review: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Error preparing review submission: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Write a Review - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .review-form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .product-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            gap: 0.5rem;
            font-size: 2rem;
            justify-content: flex-end;
            margin: 1rem 0;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }

        .rating-input label:hover,
        .rating-input label:hover ~ label,
        .rating-input input:checked ~ label {
            color: #ffc107;
        }

        .review-textarea {
            width: 100%;
            min-height: 150px;
            padding: 1rem;
            margin: 1rem 0;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            resize: vertical;
        }

        .error-message {
            color: var(--danger-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="review-form-container">
            <h1>Write a Review</h1>
            
            <div class="product-preview">
                <img src="<?php echo htmlspecialchars($row['image_url'] ?? 'images/default-product.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                <div>
                    <h3><?php echo htmlspecialchars($row['product_name']); ?></h3>
                    <p>Order #<?php echo $order_id; ?></p>
                    <p>Purchased on <?php echo date('F j, Y', strtotime($row['created_at'])); ?></p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div>
                    <label>Your Rating</label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" 
                                   <?php echo isset($_POST['rating']) && $_POST['rating'] == $i ? 'checked' : ''; ?>>
                            <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div>
                    <label for="comment">Your Review</label>
                    <textarea name="comment" id="comment" class="review-textarea" 
                              placeholder="Share your experience with this product..."><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 