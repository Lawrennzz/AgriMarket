<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the ProductPage class
require_once 'classes/ProductPage.php';

// Create an instance of the ProductPage class
$productPage = new ProductPage();

// Render the product page
$productPage->render();
?>