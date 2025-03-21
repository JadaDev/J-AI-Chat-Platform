<?php
require_once 'config.php';
require_once 'auth.php';

// Log the user out
logoutUser();

// Set a logout message if you want to (optional)

// Redirect to login page with a success message
header('Location: login.php?tab=login');
exit;
?>