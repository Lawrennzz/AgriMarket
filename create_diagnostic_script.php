<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/ProductsPage.php';

// Check if user is logged in and is admin
$is_admin = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Function to dump and format array/object content
function debug_dump($var, $label = null) {
    echo '<div style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd;">';
    if ($label) {
        echo '<h3 style="margin-top: 0;">' . htmlspecialchars($label) . '</h3>';
    }
    echo '<pre style="margin: 0; overflow: auto;">';
    var_dump($var);
    echo '</pre></div>';
}

// Get database connection
$conn = getConnection();

// Create an empty ProductsPage instance for debugging
$productsPage = new ProductsPage();

// Run query to get products directly
$direct_products_query = "SELECT p.*, c.name as category_name, v.name as vendor_name, v.user_id as vendor_user_id, 
                          (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
                          (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.category_id
                          LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
                          WHERE p.deleted_at IS NULL";

$direct_products_result = mysqli_query($conn, $direct_products_query);
$direct_products = [];

if ($direct_products_result) {
    while ($row = mysqli_fetch_assoc($direct_products_result)) {
        $direct_products[] = $row;
    }
} else {
    $direct_products_error = mysqli_error($conn);
}

// Check file permissions and paths
$templates_path = __DIR__ . '/templates';
$products_page_path = $templates_path . '/products_page.php';
$products_page_readable = is_readable($products_page_path);
$products_page_exists = file_exists($products_page_path);

// Check error log
$error_log_path = ini_get('error_log');
$error_log_content = '';
if (file_exists($error_log_path) && is_readable($error_log_path)) {
    $error_log_lines = file($error_log_path);
    if ($error_log_lines) {
        // Get the last 50 lines
        $error_log_content = implode('', array_slice($error_log_lines, -50));
    }
}

// Get loaded PHP extensions
$loaded_extensions = get_loaded_extensions();
$mysql_extension_loaded = extension_loaded('mysqli');
$gd_extension_loaded = extension_loaded('gd');

// Get PHP memory limit and max execution time
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');

// Check if the class methods used in the template exist
$products_page_methods = get_class_methods('ProductsPage');
$required_methods = ['getProducts', 'getCategories', 'getView', 'getUserId', 'getRole', 'getCategoryId', 'getSearch', 'getSort'];
$missing_methods = array_diff($required_methods, $products_page_methods);

// Check vendors table structure
$vendors_table_query = "DESCRIBE vendors";
$vendors_table_result = mysqli_query($conn, $vendors_table_query);
$vendors_columns = [];
if ($vendors_table_result) {
    while ($row = mysqli_fetch_assoc($vendors_table_result)) {
        $vendors_columns[] = $row;
    }
}

// Check the first few rows of the vendors table
$vendors_data_query = "SELECT * FROM vendors LIMIT 3";
$vendors_data_result = mysqli_query($conn, $vendors_data_query);
$vendors_data = [];
if ($vendors_data_result) {
    while ($row = mysqli_fetch_assoc($vendors_data_result)) {
        $vendors_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Products Display Diagnostic</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        h2 {
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success {
            color: #2e7d32;
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #2e7d32;
        }
        .error {
            color: #c62828;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #c62828;
        }
        .warning {
            color: #f57f17;
            background-color: #fff8e1;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #f57f17;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            overflow: auto;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .product-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        .product-card h3 {
            margin-top: 0;
            color: #4CAF50;
        }
        .product-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            margin-right: 15px;
            float: left;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .back-to-top:hover {
            background: #3e9142;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Products Display Diagnostic</h1>
        
        <?php if (!$is_admin): ?>
            <div class="error">
                <strong>Warning:</strong> You are not logged in as an admin. Some information may be limited.
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>System Information</h2>
            <table>
                <tr>
                    <th>PHP Version</th>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <th>MySQL Extension Loaded</th>
                    <td><?php echo $mysql_extension_loaded ? '<span class="success">Yes</span>' : '<span class="error">No - Critical Error</span>'; ?></td>
                </tr>
                <tr>
                    <th>GD Extension Loaded (for Images)</th>
                    <td><?php echo $gd_extension_loaded ? '<span class="success">Yes</span>' : '<span class="warning">No - Images may not display properly</span>'; ?></td>
                </tr>
                <tr>
                    <th>Memory Limit</th>
                    <td><?php echo $memory_limit; ?></td>
                </tr>
                <tr>
                    <th>Max Execution Time</th>
                    <td><?php echo $max_execution_time; ?> seconds</td>
                </tr>
                <tr>
                    <th>products_page.php Exists</th>
                    <td><?php echo $products_page_exists ? '<span class="success">Yes</span>' : '<span class="error">No - Critical Error</span>'; ?></td>
                </tr>
                <tr>
                    <th>products_page.php Readable</th>
                    <td><?php echo $products_page_readable ? '<span class="success">Yes</span>' : '<span class="error">No - Permission Error</span>'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <h2>Vendors Table Structure</h2>
            <?php if (!empty($vendors_columns)): ?>
                <div class="success">
                    <strong>Vendors Table Found</strong>
                </div>
                <table>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>
                    <?php foreach ($vendors_columns as $column): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($column['Field']); ?></td>
                            <td><?php echo htmlspecialchars($column['Type']); ?></td>
                            <td><?php echo htmlspecialchars($column['Null']); ?></td>
                            <td><?php echo htmlspecialchars($column['Key']); ?></td>
                            <td><?php echo htmlspecialchars($column['Default'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <?php if (!empty($vendors_data)): ?>
                    <h3>Sample Vendor Data</h3>
                    <table>
                        <tr>
                            <?php foreach (array_keys($vendors_data[0]) as $key): ?>
                                <th><?php echo htmlspecialchars($key); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <?php foreach ($vendors_data as $vendor): ?>
                            <tr>
                                <?php foreach ($vendor as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            <?php else: ?>
                <div class="error">
                    <strong>Vendors Table Structure Not Found</strong>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>ProductsPage Class Analysis</h2>
            
            <?php if (!empty($missing_methods)): ?>
                <div class="error">
                    <strong>Missing Required Methods:</strong> The following methods are used in the template but are not defined in the ProductsPage class:
                    <ul>
                        <?php foreach ($missing_methods as $method): ?>
                            <li><?php echo htmlspecialchars($method); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="success">
                    <strong>All Required Methods Found:</strong> The ProductsPage class has all the methods required by the template.
                </div>
            <?php endif; ?>
            
            <h3>Available Methods:</h3>
            <pre><?php print_r($products_page_methods); ?></pre>
        </div>
        
        <div class="section">
            <h2>Database Test</h2>
            
            <?php if (empty($direct_products)): ?>
                <div class="error">
                    <strong>No Products Found:</strong> Direct database query returned no products.
                    <?php if (isset($direct_products_error)): ?>
                        <p>Error: <?php echo htmlspecialchars($direct_products_error); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="success">
                    <strong>Products Found:</strong> Direct database query returned <?php echo count($direct_products); ?> products.
                </div>
                
                <h3>Sample Products (First 3):</h3>
                <?php foreach (array_slice($direct_products, 0, 3) as $product): ?>
                    <div class="product-card clearfix">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="product-image">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
                        <p>Category: <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                        <p>Vendor: <?php echo htmlspecialchars($product['vendor_name'] ?? 'Unknown Vendor'); ?></p>
                        <p>Stock: <?php echo $product['stock']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>ProductsPage Object Test</h2>
            
            <?php 
            $products_from_class = $productsPage->getProducts();
            ?>
            
            <?php if (empty($products_from_class)): ?>
                <div class="error">
                    <strong>No Products Found from Class:</strong> The ProductsPage object's getProducts() method returned no products.
                </div>
                
                <!-- Display SQL Statement from ProductsPage loadProducts method -->
                <h3>Debugging ProductsPage Class</h3>
                <div class="warning">
                    <strong>Potential Issue:</strong> Check if the loadProducts() method in ProductsPage class is using correct column names for vendor table.
                </div>
                <p>Important: The loadProducts() method likely has the same issue as we found above. It might be using <code>v.company_name as vendor_name</code> when it should be using <code>v.name as vendor_name</code>.</p>
            <?php else: ?>
                <div class="success">
                    <strong>Products Found from Class:</strong> The ProductsPage object's getProducts() method returned <?php echo count($products_from_class); ?> products.
                </div>
                
                <h3>Sample Products from Class (First 3):</h3>
                <?php foreach (array_slice($products_from_class, 0, 3) as $product): ?>
                    <div class="product-card clearfix">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="product-image">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
                        <p>Category: <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                        <p>Vendor: <?php echo htmlspecialchars($product['vendor_name'] ?? 'Unknown Vendor'); ?></p>
                        <p>Stock: <?php echo $product['stock']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error_log_content)): ?>
        <div class="section">
            <h2>Recent PHP Errors</h2>
            <pre><?php echo htmlspecialchars($error_log_content); ?></pre>
        </div>
        <?php endif; ?>
        
        <?php if ($is_admin): ?>
        <div class="section">
            <h2>Debug Information</h2>
            
            <h3>Session Variables:</h3>
            <?php debug_dump($_SESSION, 'SESSION'); ?>
            
            <h3>GET Variables:</h3>
            <?php debug_dump($_GET, 'GET'); ?>
            
            <h3>ProductsPage Object Properties:</h3>
            <?php 
            $reflection = new ReflectionClass('ProductsPage');
            $properties = $reflection->getProperties();
            $properties_data = [];
            
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $properties_data[$property->getName()] = $property->getValue($productsPage);
            }
            
            debug_dump($properties_data, 'ProductsPage Properties'); 
            ?>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Solution</h2>
            <div class="success">
                <strong>Identified Issue:</strong> The SQL query in the ProductsPage loadProducts method likely uses the wrong column name for vendors.
            </div>
            <p>The vendors table appears to have a column named <strong>'name'</strong> instead of <strong>'company_name'</strong> as the query is trying to use.</p>
            
            <h3>How to Fix:</h3>
            <ol>
                <li>Open the file <code>classes/ProductsPage.php</code></li>
                <li>Find the <code>loadProducts()</code> method</li>
                <li>Change the SQL query from using <code>v.company_name as vendor_name</code> to <code>v.name as vendor_name</code></li>
                <li>Save the file and refresh your products page</li>
            </ol>
            
            <pre>
// Change this line:
$sql = "SELECT p.*, c.name as category_name, v.company_name as vendor_name, v.user_id as vendor_user_id, ...

// To this:
$sql = "SELECT p.*, c.name as category_name, v.name as vendor_name, v.user_id as vendor_user_id, ...
            </pre>
        </div>
    </div>
    
    <a href="#" class="back-to-top">↑</a>
    
    <script>
        // Script for back to top button
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Show/hide back to top button based on scroll position
        window.addEventListener('scroll', function() {
            var backToTopButton = document.querySelector('.back-to-top');
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'flex';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        // Initially hide the button
        document.querySelector('.back-to-top').style.display = 'none';
    </script>
</body>
</html> 