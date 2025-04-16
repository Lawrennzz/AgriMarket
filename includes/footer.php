<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-copyright">
                &copy; <?php echo date("Y"); ?> AgriMarket. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="https://agrimarket.com/privacy">Privacy Policy</a>
                <a href="https://agrimarket.com/terms">Terms of Service</a>
                <a href="https://agrimarket.com/contact">Contact Us</a>
            </div>
        </div>
    </div>
</footer>

<style>
    .footer {
        background-color: #f5f5f5;
        padding: 2rem 0;
        margin-top: 3rem;
        border-top: 1px solid #e0e0e0;
    }
    
    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .footer-copyright {
        color: var(--medium-gray);
        font-size: 0.9rem;
    }
    
    .footer-links {
        display: flex;
        gap: 1.5rem;
    }
    
    .footer-links a {
        color: var(--medium-gray);
        font-size: 0.9rem;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .footer-links a:hover {
        color: var(--primary-color);
    }
    
    @media (max-width: 768px) {
        .footer-content {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        .footer-links {
            justify-content: center;
        }
    }
</style> 