<div class="container">
    <div class="form-header">
        <h1 class="form-title">Manage Orders</h1>
        <p class="form-subtitle">View and manage customer orders</p>
    </div>
    
    <div class="action-bar" style="margin-bottom: 20px; text-align: right;">
        <a href="../update_payment_methods.php" class="btn btn-secondary">
            <i class="fas fa-money-check-alt"></i> Fix Missing Payment Methods
        </a>
        <a href="../update_existing_orders.php" class="btn btn-secondary" style="margin-left: 10px;">
            <i class="fas fa-calculator"></i> Fix Order Totals
        </a>
        <a href="../add_order_columns.php" class="btn btn-secondary" style="margin-left: 10px;">
            <i class="fas fa-database"></i> Update Database Structure
        </a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    // ... rest of the code ...
</div> 