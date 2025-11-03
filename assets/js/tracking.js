/**
 * JavaScript functions for tracking product view analytics
 */

/**
 * Initialize product view tracking for product links with the class "product-link"
 */
function initProductViewTracking() {
    // Find all product links
    const productLinks = document.querySelectorAll('.product-link');
    
    // Add click event listeners to track views
    productLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Get product ID from data attribute
            const productId = this.getAttribute('data-product-id');
            if (productId) {
                // Prevent default only long enough to record the view
                e.preventDefault();
                
                // Track the view
                trackProductView(productId, 'product_link_click')
                    .then(() => {
                        // Continue with the navigation after tracking
                        window.location.href = this.href;
                    })
                    .catch(error => {
                        console.error('Error tracking product view:', error);
                        // Still navigate even if tracking fails
                        window.location.href = this.href;
                    });
            }
        });
    });
}

/**
 * Track a product view via AJAX
 * 
 * @param {number} productId - The ID of the product being viewed
 * @param {string} source - The source of the view (optional)
 * @returns {Promise} - Promise that resolves when tracking is complete
 */
function trackProductView(productId, source = '') {
    return new Promise((resolve, reject) => {
        // Create form data to send
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('source', source);
        
        // Send tracking data to server
        fetch('track_view.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                resolve();
            } else {
                reject('Server returned error status: ' + response.status);
            }
        })
        .catch(error => {
            console.error('Error sending tracking data:', error);
            reject(error);
        });
    });
}

// Initialize tracking when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    initProductViewTracking();
}); 