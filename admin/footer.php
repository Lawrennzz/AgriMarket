<footer class="admin-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-copyright">
                &copy; <?php echo date("Y"); ?> AgriMarket Admin. All rights reserved.
            </div>
        </div>
    </div>
</footer>

<style>
    .admin-footer {
        background-color: #f5f5f5;
        padding: 1.5rem 0;
        margin-top: 3rem;
        border-top: 1px solid #e0e0e0;
    }
    
    .footer-content {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .footer-copyright {
        color: var(--medium-gray);
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .footer-content {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
    }
</style> 