        <!-- Analytics Section -->
        <li class="sidebar-dropdown <?php echo in_array($current_page, ['reports.php', 'advanced_reports.php', 'check_analytics.php', 'seed_analytics_data.php', 'most_viewed_products.php']) ? 'active' : ''; ?>">
            <a href="#"><i class="fas fa-chart-bar"></i> Analytics</a>
            <ul class="sidebar-submenu" style="display: <?php echo in_array($current_page, ['reports.php', 'advanced_reports.php', 'check_analytics.php', 'seed_analytics_data.php', 'most_viewed_products.php']) ? 'block' : 'none'; ?>;">
                <li class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $admin_prefix; ?>admin/reports.php"><i class="fas fa-file-alt"></i> Basic Reports</a>
                </li>
                <li class="<?php echo $current_page === 'advanced_reports.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $admin_prefix; ?>admin/advanced_reports.php"><i class="fas fa-chart-line"></i> Advanced Analytics</a>
                </li>
                <li class="<?php echo $current_page === 'most_viewed_products.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $admin_prefix; ?>admin/most_viewed_products.php"><i class="fas fa-eye"></i> Most Viewed Products</a>
                </li>
            </ul>
        </li> 