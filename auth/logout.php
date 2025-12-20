<?php
/**
 * Logout Page
 * Logs out user and destroys session
 */

require_once '../config/session.php';

// Perform logout
logout();

// Redirect to home page with message
header('Location: ../index.php?message=logged_out');
exit();
?>