<td>
    <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" 
       class="btn btn-danger btn-sm" 
       onclick="return confirm('Are you sure you want to delete this product?')">
        <i class="fas fa-trash"></i> Delete
    </a>
</td> 