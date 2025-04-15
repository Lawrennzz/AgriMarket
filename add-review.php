<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

$product_id = (int)$_GET['product_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // Validate inputs
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5.";
    } elseif (empty($comment)) {
        $error = "Comment cannot be empty.";
    } else {
        // Insert review into the database
        $sql = "INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt === false) {
            die('MySQL prepare error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "iiis", $product_id, $_SESSION['user_id'], $rating, $comment);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Review added successfully!";
            // Redirect back to the product page after successful submission
            header("Location: product.php?id=" . $product_id);
            exit(); // Ensure no further code is executed after the redirect
        } else {
            $error = "Error adding review: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Review</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .error-message, .success-message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        select, textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            resize: vertical; /* Allows vertical resizing for textarea */
        }

        textarea {
            height: 100px; /* Set a default height for the textarea */
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Review</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div>
                <label for="rating">Rating:</label>
                <select name="rating" id="rating" required>
                    <option value="">Select a rating</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>
            <div>
                <label for="comment">Comment:</label>
                <textarea name="comment" id="comment" required></textarea>
            </div>
            <button type="submit">Submit Review</button>
        </form>
    </div>
</body>
</html>