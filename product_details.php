                <div class="product-actions">
                    <?php if ($product['stock'] > 0): ?>
                        <form method="post" action="cart.php" class="add-to-cart-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" class="quantity-btn minus" onclick="decrementQuantity()">-</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                    <button type="button" class="quantity-btn plus" onclick="incrementQuantity()">+</button>
                                </div>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" class="add-cart-btn">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                
                                <button type="button" class="wishlist-btn" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-heart"></i> Add to Wishlist
                                </button>
                                
                                <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" class="compare-btn">
                                    <i class="fas fa-balance-scale"></i> Add to Compare
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="out-of-stock-notice">
                            <i class="fas fa-exclamation-circle"></i> Currently Out of Stock
                        </div>
                        <div class="button-group">
                            <button type="button" class="wishlist-btn" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                                <i class="fas fa-heart"></i> Add to Wishlist
                            </button>
                            
                            <a href="compare_products.php?add=<?php echo $product['product_id']; ?>" class="compare-btn">
                                <i class="fas fa-balance-scale"></i> Add to Compare
                            </a>
                        </div>
                    <?php endif; ?>
                </div> 