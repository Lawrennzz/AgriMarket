document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.querySelector('select[name="role"]');
    const businessNameInput = document.querySelector('#business_name');
    
    if (roleSelect && businessNameInput) {
        roleSelect.addEventListener('change', () => {
            businessNameInput.style.display = roleSelect.value === 'vendor' ? 'block' : 'none';
        });
    }
});

function addToCart(productId) {
    alert('Product ' + productId + ' added to cart!');
    // Simulate cart update
}