<?php
/**
 * Home/Landing Page
 * Entry point for the car rental system
 */

require_once 'config/session.php';

// Check if user is logged in and redirect to appropriate dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
}

// Check for logout message
$message = "";
if (isset($_GET['message']) && $_GET['message'] == 'logged_out') {
    $message = "You have been successfully logged out.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental Platform - Home</title>
</head>
<body>
    <h1>Welcome to Car Rental Platform</h1>
    <p>Your trusted platform for renting and listing vehicles</p>
    
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <h2>Login Options</h2>
    
    <div>
        <h3>I'm a Customer</h3>
        <p>Search and rent cars for your needs</p>
        <a href="auth/renter_login.php">Login</a>
        <p>Don't have an account? <a href="auth/renter_register.php">Register</a></p>
    </div>
    
    <div>
        <h3>I'm a Car Owner</h3>
        <p>List your vehicles and earn money</p>
        <a href="auth/lessor_login.php">Login</a>
        <p>Don't have an account? <a href="auth/lessor_register.php">Register</a></p>
    </div>
    
    <div>
        <h3>Administrator Access</h3>
        <p>Manage the platform and users</p>
        <a href="auth/admin_login.php">Admin Login</a>
    </div>
    
    <h2>Why Choose Us?</h2>
    <ul>
        <li>Wide Selection - Browse hundreds of vehicles</li>
        <li>Secure Payment - Safe and encrypted transactions</li>
        <li>24/7 Support - We're here to help anytime</li>
        <li>Easy Booking - Book in just a few clicks</li>
    </ul>
</body>
</html>