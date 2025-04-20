<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/ProductsPage.php';

// Get database connection
$conn = getConnection();

// Create an instance of the ProductsPage class
$productsPage = new ProductsPage();

// Get category ID from GET parameter if available
if (isset($_GET['category_id'])) {
    $productsPage->setCategoryId((int)$_GET['category_id']);
}

// Get search term from GET parameter if available
if (isset($_GET['search'])) {
    $productsPage->setSearch(trim($_GET['search']));
}

// Get sort option from GET parameter if available
if (isset($_GET['sort'])) {
    $productsPage->setSort($_GET['sort']);
}

// Get view option from GET parameter if available
if (isset($_GET['view'])) {
    $productsPage->setView($_GET['view']);
}

// Render the products page (analytics tracking happens inside the render method)
$productsPage->render();