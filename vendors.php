<?php
include 'config.php';

// Update session last activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    $user_role = $_SESSION['role'] ?? '';
} else {
    // Non-logged in users can still view the page
    $user_role = 'guest';
}

// Fetch all active vendors
$vendors_query = "SELECT v.vendor_id, v.business_name, v.subscription_tier, 
                 u.name, u.email, u.created_at as joined_date,
                 (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.vendor_id AND p.deleted_at IS NULL) as product_count,
                 (SELECT AVG(r.rating) FROM reviews r 
                  JOIN products p ON r.product_id = p.product_id 
                  WHERE p.vendor_id = v.vendor_id) as avg_rating
                 FROM vendors v 
                 JOIN users u ON v.user_id = u.user_id 
                 WHERE v.deleted_at IS NULL
                 ORDER BY product_count DESC, v.created_at DESC";

$vendors_result = mysqli_query($conn, $vendors_query);

// Handle filtering
$selected_category = '';
if (isset($_GET['category'])) {
    $selected_category = $_GET['category'];
    // Modify the query to filter by category
    $vendors_query = "SELECT v.vendor_id, v.business_name, v.subscription_tier, 
                     u.name, u.email, u.created_at as joined_date,
                     (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.vendor_id AND p.deleted_at IS NULL) as product_count,
                     (SELECT AVG(r.rating) FROM reviews r 
                      JOIN products p ON r.product_id = p.product_id 
                      WHERE p.vendor_id = v.vendor_id) as avg_rating
                     FROM vendors v 
                     JOIN users u ON v.user_id = u.user_id 
                     JOIN products p ON v.vendor_id = p.vendor_id
                     JOIN categories c ON p.category_id = c.category_id
                     WHERE v.deleted_at IS NULL AND c.category_id = ?
                     GROUP BY v.vendor_id
                     ORDER BY product_count DESC, v.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $vendors_query);
    mysqli_stmt_bind_param($stmt, "i", $selected_category);
    mysqli_stmt_execute($stmt);
    $vendors_result = mysqli_stmt_get_result($stmt);
}

// Get all categories for filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Browse Vendors - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .vendors-container {
            padding: 2rem 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2.2rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--medium-gray);
            font-size: 1.1rem;
        }

        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background-color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-label {
            font-weight: 500;
        }

        .vendors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .vendor-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .vendor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .vendor-header {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            padding: 1.5rem;
            color: white;
            text-align: center;
            position: relative;
        }

        .vendor-avatar {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 3px solid white;
            font-size: 2rem;
            color: var(--primary-color);
        }

        .vendor-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .vendor-business {
            font-size: 1rem;
            opacity: 0.9;
        }

        .vendor-tier {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background-color: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            text-transform: capitalize;
        }

        .vendor-body {
            padding: 1.5rem;
        }

        .vendor-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 1.5rem;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
        }

        .vendor-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .rating-stars {
            color: #FFD700;
        }

        .vendor-meta {
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--medium-gray);
        }

        .meta-item i {
            color: var(--primary-color);
            width: 20px;
        }

        .vendor-actions {
            text-align: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #2196F3;
        }
        
        .btn-secondary:hover {
            background-color: #0b7dda;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--medium-gray);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container vendors-container">
        <div class="section-header">
            <h1 class="section-title">Our Vendors</h1>
            <p class="section-subtitle">Discover quality agricultural products from trusted vendors</p>
        </div>

        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Filter by category:</span>
                <form method="GET" action="vendors.php">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo ($selected_category == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            <?php if (!empty($selected_category)): ?>
                <a href="vendors.php" class="btn btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>

        <?php if (mysqli_num_rows($vendors_result) > 0): ?>
            <div class="vendors-grid">
                <?php while ($vendor = mysqli_fetch_assoc($vendors_result)): ?>
                    <div class="vendor-card">
                        <div class="vendor-header">
                            <div class="vendor-tier"><?php echo htmlspecialchars($vendor['subscription_tier']); ?></div>
                            <div class="vendor-avatar">
                                <i class="fas fa-store"></i>
                            </div>
                            <h2 class="vendor-name"><?php echo htmlspecialchars($vendor['name']); ?></h2>
                            <div class="vendor-business"><?php echo htmlspecialchars($vendor['business_name']); ?></div>
                        </div>
                        <div class="vendor-body">
                            <div class="vendor-stats">
                                <div class="stat">
                                    <div class="stat-value"><?php echo $vendor['product_count']; ?></div>
                                    <div class="stat-label">Products</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo number_format($vendor['avg_rating'] ?? 0, 1); ?></div>
                                    <div class="stat-label">Rating</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo date('Y', strtotime($vendor['joined_date'])); ?></div>
                                    <div class="stat-label">Joined</div>
                                </div>
                            </div>
                            <div class="vendor-rating">
                                <div class="rating-stars">
                                    <?php 
                                    $rating = round($vendor['avg_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $rating): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                                <span><?php echo number_format($vendor['avg_rating'] ?? 0, 1); ?> / 5</span>
                            </div>
                            <div class="vendor-meta">
                                <div class="meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($vendor['email']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Joined <?php echo date('M Y', strtotime($vendor['joined_date'])); ?></span>
                                </div>
                            </div>
                            <div class="vendor-actions">
                                <?php if ($user_role === 'admin'): ?>
                                    <!-- For admins: View, Edit, and Manage buttons -->
                                    <a href="vendor_profile.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn">View Vendor</a>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                        <a href="edit_vendor.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn btn-secondary" style="flex: 1;">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="manage_vendors.php?action=manage&id=<?php echo $vendor['vendor_id']; ?>" class="btn btn-secondary" style="flex: 1;">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    </div>
                                <?php elseif ($user_role === 'vendor'): ?>
                                    <!-- For vendors: View and Compare buttons -->
                                    <a href="vendor_profile.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn">View Vendor</a>
                                    <a href="compare_products.php?vendor=<?php echo $vendor['vendor_id']; ?>" class="btn btn-secondary" style="margin-top: 0.5rem;">
                                        <i class="fas fa-balance-scale"></i> Compare Products
                                    </a>
                                <?php elseif ($user_role === 'customer'): ?>
                                    <!-- For customers: View button -->
                                    <a href="vendor_profile.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn">View Vendor</a>
                                <?php else: ?>
                                    <!-- For guests: View button with signup encouragement -->
                                    <a href="vendor_profile.php?id=<?php echo $vendor['vendor_id']; ?>" class="btn">View Vendor</a>
                                    <div style="margin-top: 0.8rem; font-size: 0.9rem; color: var(--medium-gray);">
                                        <a href="login.php" style="color: var(--primary-color);">Sign in</a> to interact with vendors
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-store-slash"></i>
                <h3>No Vendors Found</h3>
                <p>We couldn't find any vendors matching your criteria.</p>
                <?php if (!empty($selected_category)): ?>
                    <a href="vendors.php" class="btn">View All Vendors</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 