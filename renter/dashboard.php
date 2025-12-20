<?php
/**
 * Renter Dashboard
 * Main page after customer login
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as renter
check_authentication('renter');

// Get user information
$user_name = get_user_name();
$user_email = get_user_email();
$user_id = get_user_id();

// Get some statistics
$sql_bookings = "SELECT COUNT(*) as total FROM BOOKING WHERE RENTERID = :renterid";
$booking_count = db_fetch_one($sql_bookings, ['renterid' => $user_id]);
$total_bookings = $booking_count ? $booking_count['TOTAL'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Car Rental</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <h2>Car Rental System</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main>
        <div>
            <h1>Welcome to Your Dashboard!</h1>
            <p>Manage your car rentals, view booking history, and more.</p>
        </div>
        
        <!-- Dashboard Cards -->
        <div>
            <a href="search_cars.php">
                <h3>Search Cars</h3>
                <p>Find and book available cars</p>
            </a>
            
            <div>
                <h3>My Bookings</h3>
                <div><?php echo $total_bookings; ?></div>
                <p>Total bookings made</p>
            </div>
            
            <a href="booking_history.php">
                <h3>Booking History</h3>
                <p>View all your past bookings</p>
            </a>
            
            <a href="submit_ticket.php">
                <h3>Support</h3>
                <p>Need help? Submit a ticket</p>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div>
            <h2>Quick Actions</h2>
            <div>
                <a href="search_cars.php">Search Available Cars</a>
                <a href="booking_history.php">View My Bookings</a>
            </div>
        </div>
    </main>
</body>
</html>