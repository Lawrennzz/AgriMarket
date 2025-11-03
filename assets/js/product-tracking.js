/**
 * Product View Tracking Script
 * 
 * This script automatically tracks product views when the product details page loads
 * and sets up tracking for click events on product links.
 */

$(document).ready(function() {
    // Track current product view (for product details page)
    const productId = $('#current-product-id').val();
    if (productId) {
        trackCurrentProductView(productId);
    }
    
    // Set up tracking for product links
    setupProductLinkTracking();
});

/**
 * Track the current product being viewed
 * 
 * @param {number} productId - The ID of the product being viewed
 */
function trackCurrentProductView(productId) {
    $.ajax({
        type: "POST",
        url: "track_view.php",
        data: { 
            product_id: productId,
            source: 'product_details' 
        },
        dataType: "json",
        success: function(response) {
            console.log("View tracked successfully");
        },
        error: function(xhr, status, error) {
            console.error("Error tracking view:", error);
        }
    });
}

/**
 * Set up tracking for all product links
 */
function setupProductLinkTracking() {
    // Track clicks on product links
    $('.product-link').on('click', function(e) {
        const productId = $(this).data('product-id');
        if (!productId) return true; // Continue with link if no product ID
        
        e.preventDefault(); // Prevent default action
        const linkHref = $(this).attr('href');
        
        // Send tracking request
        $.ajax({
            type: "POST",
            url: "track_view.php",
            data: { 
                product_id: productId,
                source: 'product_link' 
            },
            dataType: "json",
            complete: function() {
                // Navigate to the product page after tracking
                window.location.href = linkHref;
            }
        });
    });
} 