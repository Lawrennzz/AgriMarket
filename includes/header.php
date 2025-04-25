<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/footer.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #2E7D32;
            --secondary-color: #FFC107;
            --light-gray: #f5f5f5;
            --medium-gray: #757575;
            --dark-gray: #333333;
            --danger: #F44336;
            --success: #4CAF50;
            --info: #2196F3;
            --warning: #FF9800;
            --border-radius: 4px;
            --shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-gray);
            background-color: #f8f8f8;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header */
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
        }
        
        .nav-item {
            margin-left: 25px;
            position: relative;
        }
        
        .nav-link {
            color: var(--dark-gray);
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            margin-right: 5px;
        }
        
        .nav-link:hover {
            color: var(--primary-color);
        }
        
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary-color);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 1;
            top: 100%;
            right: 0;
            margin-top: 10px;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background-color: #f5f5f5;
        }
        
        .dropdown-item i {
            margin-right: 10px;
            color: var(--medium-gray);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-secondary:hover {
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-gray);
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
                background-color: white;
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-item {
                margin: 10px 0;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .dropdown-content {
                position: static;
                box-shadow: none;
                margin-top: 10px;
                display: none;
            }
            
            .dropdown.active .dropdown-content {
                display: block;
            }
        }
        
        /* Alert styles for notifications */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            color: white;
        }
        
        .alert-success {
            background-color: var(--success);
        }
        
        .alert-danger {
            background-color: var(--danger);
        }
        
        .alert-info {
            background-color: var(--info);
        }
        
        .alert-warning {
            background-color: var(--warning);
        }
        
        /* Grid system */
        .grid {
            display: grid;
            gap: 20px;
        }
        
        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }
        
        @media (max-width: 992px) {
            .grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .grid-2, 
            .grid-3,
            .grid-4 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="logo">
                <i class="fas fa-seedling"></i>
                <span>AgriMarket</span>
            </a>
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="nav-links">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <i class="fas fa-shopping-basket"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a href="knowledge_hub.php" class="nav-link">
                        <i class="fas fa-book"></i> Knowledge Hub
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'customer'): ?>
                        <li class="nav-item">
                            <a href="cart.php" class="nav-link cart-icon">
                                <i class="fas fa-shopping-cart"></i>
                                <?php
                                // Display cart count if available
                                if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): 
                                ?>
                                    <span class="cart-count"><?php echo $_SESSION['cart_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Account'; ?>
                        </a>
                        <div class="dropdown-content">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="admin/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                                </a>
                            <?php elseif ($_SESSION['role'] === 'staff'): ?>
                                <a href="staff_dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Staff Dashboard
                                </a>
                            <?php elseif ($_SESSION['role'] === 'vendor'): ?>
                                <a href="vendor/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-store"></i> Vendor Dashboard
                                </a>
                            <?php else: ?>
                                <a href="my_orders.php" class="dropdown-item">
                                    <i class="fas fa-box"></i> My Orders
                                </a>
                            <?php endif; ?>
                            
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="login.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>
    
    <?php if (isset($_SESSION['alert_message'])): ?>
        <div class="container" style="margin-top: 20px;">
            <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>">
                <?php 
                    echo $_SESSION['alert_message'];
                    unset($_SESSION['alert_message']);
                    unset($_SESSION['alert_type']);
                ?>
            </div>
        </div>
    <?php endif; ?> 