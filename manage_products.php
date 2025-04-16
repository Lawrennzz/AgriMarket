<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the ManageProductsPage class
require_once 'classes/ManageProductsPage.php';

// Create an instance of the ManageProductsPage class
$manageProductsPage = new ManageProductsPage();

// Render the manage products page
$manageProductsPage->render();