<footer class="footer">
    <div class="container footer-content">
        <div class="footer-section">
            <h3 class="mb-2">AgriMarket</h3>
            <p class="footer-text">Connecting farmers and consumers directly.</p>
        </div>
        <div class="footer-section">
            <h3 class="mb-2">Quick Links</h3>
            <ul class="footer-links">
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
                <li><a href="terms.php">Terms of Service</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3 class="mb-2">Connect With Us</h3>
            <div class="social-links">
                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p class="footer-text text-center">© <?php echo date('Y'); ?> AgriMarket. All rights reserved.</p>
        </div>
    </div>
</footer>

<style>
.footer-section {
    flex: 1;
    min-width: 250px;
}

.footer-links {
    list-style: none;
    padding: 0;
}

.footer-links li {
    margin-bottom: 0.5rem;
}

.footer-links a {
    color: var(--medium-gray);
    text-decoration: none;
    transition: var(--transition);
}

.footer-links a:hover {
    color: var(--primary-color);
}

.social-links {
    display: flex;
    gap: 1rem;
}

.social-link {
    color: var(--medium-gray);
    font-size: 1.5rem;
    transition: var(--transition);
}

.social-link:hover {
    color: var(--primary-color);
}

.footer-bottom {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--light-gray);
}

@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
        gap: 2rem;
    }

    .social-links {
        justify-content: center;
    }
}
</style>