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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <link href="../assets/css/renter-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <header class="page-header">
            <h1>Welcome to Your Dashboard!</h1>
            <p>Manage your rentals and find your next perfect car easily.</p>
        </header>
        
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>My Bookings</h3>
                    <div class="stat-number"><?php echo $total_bookings; ?></div>
                    <p>Total cars you have rented</p>
                </div>

                <a href="search_cars.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="action-content">
                        <h3>Find a Car</h3>
                        <p>Browse available vehicles</p>
                    </div>
                </a>

                <a href="booking_history.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="action-content">
                        <h3>Booking History</h3>
                        <p>View past and active rentals</p>
                    </div>
                </a>
            </div>
            
            <h2 class="section-title">Quick Actions</h2>
            <div class="action-buttons-grid">
                <a href="search_cars.php" class="btn-primary">
                    <i class="fas fa-car"></i> Search Available Cars
                </a>
                <a href="booking_history.php" class="btn-secondary">
                    <i class="fas fa-calendar-alt"></i> View My Bookings
                </a>
                <a href="submit_ticket.php" class="btn-secondary">
                    <i class="fas fa-headset"></i> Get Support
                </a>
            </div>
        </div>
    </main>
</body>
</html>