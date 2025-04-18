<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriMarket Footer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Dark footer styling to match the Knowledge Hub design */
        footer {
            background-color: #333;
            color: #f5f5f5;
            padding: 50px 0 20px;
            margin-top: 50px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .footer-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .footer-col {
            width: 25%;
            padding: 0 15px;
            margin-bottom: 30px;
        }

        .footer-col h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col h3:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: #4CAF50;
        }

        .footer-col p {
            color: #bbb;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .footer-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-col ul li {
            margin-bottom: 10px;
        }

        .footer-col ul li a {
            color: #bbb;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-col ul li a:hover {
            color: #4CAF50;
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #fff;
            text-align: center;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background-color: #4CAF50;
            transform: translateY(-3px);
        }

        .contact-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .contact-info p i {
            color: #4CAF50;
            width: 16px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
        }

        .footer-bottom .copyright {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-bottom p {
            color: #bbb;
            margin: 0;
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #4CAF50;
        }

        @media (max-width: 992px) {
            .footer-col {
                width: 50%;
            }
        }

        @media (max-width: 576px) {
            .footer-col {
                width: 100%;
                text-align: center;
            }
            
            .footer-col h3:after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .social-links, .contact-info p {
                justify-content: center;
            }
            
            .footer-bottom .copyright {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <footer>
        <div class="footer-container">
            <div class="footer-row">
                <div class="footer-col">
                    <h3>About AgriMarket</h3>
                    <p>AgriMarket is a comprehensive platform connecting farmers with buyers, providing agricultural resources, market insights, and knowledge.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Products</a></li>
                        <li><a href="knowledge_hub.php">Knowledge Hub</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Resources</h3>
                    <ul>
                        <li><a href="farming_techniques.php">Farming Techniques</a></li>
                        <li><a href="market_pricing.php">Market Pricing</a></li>
                        <li><a href="agricultural_workflows.php">Agricultural Workflows</a></li>
                        <li><a href="faq.php">FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-col contact-info">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Farm Road, Agriville</p>
                    <p><i class="fas fa-phone"></i> +1 (123) 456-7890</p>
                    <p><i class="fas fa-envelope"></i> info@agrimarket.com</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8am to 6pm</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; <?php echo date('Y'); ?> AgriMarket. All rights reserved. </p>
                    <p>This business is fictitious and part of a university course.</p>
                    <div class="footer-links">
                        <a href="privacy.php">Privacy Policy</a>
                        <a href="terms.php">Terms of Service</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

<?php
// Show comparison badge if there are products to compare
if (isset($_SESSION['compare_products']) && !empty($_SESSION['compare_products'])) {
    $compare_count = count($_SESSION['compare_products']);
    ?>
    <a href="compare_products.php" class="comparison-badge">
        <i class="fas fa-balance-scale"></i>
        <span>Compare Products</span>
        <span class="comparison-count"><?php echo $compare_count; ?></span>
    </a>
    <?php
}
?>

</body>
</html>