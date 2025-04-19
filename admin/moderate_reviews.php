<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/reviews.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = getConnection();

// Handle review moderation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id']) && isset($_POST['action'])) {
    $review_id = (int)$_POST['review_id'];
    $action = $_POST['action'];
    
    $result = moderateReview($review_id, $action);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get pending reviews
$query = "SELECT r.*, u.name as user_name, p.name as product_name 
          FROM reviews r 
          JOIN users u ON r.user_id = u.user_id 
          JOIN products p ON r.product_id = p.product_id 
          WHERE r.status = 'pending' 
          ORDER BY r.created_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Reviews - AgriMarket Admin</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .review-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .review-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .rating {
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .review-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pending Reviews</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($review = mysqli_fetch_assoc($result)): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div>
                                                <h5><?php echo htmlspecialchars($review['product_name']); ?></h5>
                                                <div class="review-meta">
                                                    By <?php echo htmlspecialchars($review['user_name']); ?> on 
                                                    <?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="review-content">
                                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                        </div>
                                        
                                        <div class="review-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                <button type="submit" name="action" value="approved" class="btn btn-sm btn-approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="rejected" class="btn btn-sm btn-reject">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p>No pending reviews to moderate.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html> 