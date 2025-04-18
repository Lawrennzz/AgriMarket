<footer class="footer">
    <div class="footer-columns">
        <div>
            <h3>About AgriMarket</h3>
            <p>AgriMarket is a comprehensive platform connecting farmers with buyers, providing agricultural resources, market insights, and knowledge.</p>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div>
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="knowledge_hub.php">Knowledge Hub</a></li>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="about.php">About Us</a></li>
            </ul>
        </div>
        
        <div>
            <h3>Resources</h3>
            <ul>
                <li><a href="farming_techniques.php">Farming Techniques</a></li>
                <li><a href="market_pricing.php">Market Pricing</a></li>
                <li><a href="agricultural_workflows.php">Agricultural Workflows</a></li>
                <li><a href="faq.php">FAQs</a></li>
            </ul>
        </div>
        
        <div class="contact-info">
            <h3>Contact Info</h3>
            <p><i class="fas fa-map-marker-alt"></i> 123 Farm Road, Agriville</p>
            <p><i class="fas fa-phone"></i> +1 (123) 456-7890</p>
            <p><i class="fas fa-envelope"></i> info@agrimarket.com</p>
            <p><i class="fas fa-clock"></i> Mon-Fri: 8am to 6pm</p>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> AgriMarket. All rights reserved.</p>
        <p>This business is fictitious and part of a university course.</p>
        <div class="footer-links">
            <a href="privacy.php">Privacy Policy</a>
            <a href="terms.php">Terms of Service</a>
        </div>
    </div>
</footer>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }
</script>
</body>
</html> 