<?php
// Setup script to create knowledge hub database tables

// Include database connection
require_once 'includes/db_connection.php';

// Create article_categories table
$create_categories_table = "
CREATE TABLE IF NOT EXISTS `article_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Create articles table
$create_articles_table = "
CREATE TABLE IF NOT EXISTS `articles` (
  `article_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `summary` text,
  `content` longtext NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT '0',
  `published_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`),
  KEY `category_id` (`category_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `article_categories` (`category_id`),
  CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute the queries
$success = true;
$messages = [];

if (mysqli_query($conn, $create_categories_table)) {
    $messages[] = "Article categories table created successfully";
} else {
    $success = false;
    $messages[] = "Error creating article categories table: " . mysqli_error($conn);
}

if (mysqli_query($conn, $create_articles_table)) {
    $messages[] = "Articles table created successfully";
} else {
    $success = false;
    $messages[] = "Error creating articles table: " . mysqli_error($conn);
}

// Insert some sample categories if the table was created successfully
if ($success) {
    $sample_categories = [
        ["Modern Farming", "Cutting-edge farming methods and technologies", "fas fa-seedling"],
        ["Market Pricing", "Current market trends and pricing analysis", "fas fa-chart-line"],
        ["Agricultural Workflows", "Efficient farm management processes", "fas fa-tasks"]
    ];
    
    $category_insert_query = "INSERT INTO article_categories (name, description, icon) VALUES (?, ?, ?)";
    $category_stmt = mysqli_prepare($conn, $category_insert_query);
    
    if ($category_stmt) {
        mysqli_stmt_bind_param($category_stmt, "sss", $name, $description, $icon);
        
        foreach ($sample_categories as $category) {
            $name = $category[0];
            $description = $category[1];
            $icon = $category[2];
            
            if (mysqli_stmt_execute($category_stmt)) {
                $messages[] = "Category '$name' added successfully";
            } else {
                $messages[] = "Error adding category '$name': " . mysqli_stmt_error($category_stmt);
            }
        }
        
        mysqli_stmt_close($category_stmt);
    }
    
    // Insert a sample article if users table exists
    $check_users_table = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($check_users_table) > 0) {
        // Get the first user from the users table
        $user_query = "SELECT user_id FROM users LIMIT 1";
        $user_result = mysqli_query($conn, $user_query);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user = mysqli_fetch_assoc($user_result);
            $author_id = $user['user_id'];
            
            // Get the first category
            $category_query = "SELECT category_id FROM article_categories LIMIT 1";
            $category_result = mysqli_query($conn, $category_query);
            
            if ($category_result && mysqli_num_rows($category_result) > 0) {
                $category = mysqli_fetch_assoc($category_result);
                $category_id = $category['category_id'];
                
                $sample_article = [
                    "title" => "Introduction to Sustainable Farming",
                    "summary" => "Learn about sustainable farming practices that can improve your crop yields while protecting the environment.",
                    "content" => "<p>Sustainable farming is an approach to producing food that balances the need for high yields with environmental stewardship and social responsibility.</p><p>This article explores various sustainable farming techniques including crop rotation, integrated pest management, and water conservation methods.</p><p>Implementing these practices can lead to improved soil health, reduced chemical inputs, and long-term farm viability.</p>",
                    "image_url" => "images/sustainable-farming.jpg",
                    "is_featured" => 1,
                    "status" => "published",
                    "published_date" => date('Y-m-d H:i:s')
                ];
                
                $article_insert_query = "INSERT INTO articles (title, summary, content, image_url, category_id, author_id, is_featured, status, published_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $article_stmt = mysqli_prepare($conn, $article_insert_query);
                
                if ($article_stmt) {
                    mysqli_stmt_bind_param($article_stmt, "ssssiisss", 
                        $sample_article['title'], 
                        $sample_article['summary'], 
                        $sample_article['content'], 
                        $sample_article['image_url'], 
                        $category_id, 
                        $author_id, 
                        $sample_article['is_featured'], 
                        $sample_article['status'], 
                        $sample_article['published_date']
                    );
                    
                    if (mysqli_stmt_execute($article_stmt)) {
                        $messages[] = "Sample article added successfully";
                    } else {
                        $messages[] = "Error adding sample article: " . mysqli_stmt_error($article_stmt);
                    }
                    
                    mysqli_stmt_close($article_stmt);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Hub Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        
        h1 {
            color: #2e7d32;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .btn {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .btn:hover {
            background-color: #1b5e20;
        }
    </style>
</head>
<body>
    <h1>Knowledge Hub Database Setup</h1>
    
    <?php foreach ($messages as $message): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endforeach; ?>
    
    <p>
        <?php if ($success): ?>
            Database tables for the Knowledge Hub have been created successfully. You can now start using the Knowledge Hub features.
        <?php else: ?>
            There were errors in setting up the database tables. Please check the error messages above.
        <?php endif; ?>
    </p>
    
    <a href="knowledge_hub.php" class="btn">Go to Knowledge Hub</a>
</body>
</html> 