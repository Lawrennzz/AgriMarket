<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Include the ProductsPage class
require_once 'classes/ProductsPage.php';

// Create an instance of the ProductsPage class
$productsPage = new ProductsPage();

// Render the products page
$productsPage->render();