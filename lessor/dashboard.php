<?php
/**
 * Lessor Dashboard
 * Main page for verified car owners
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as lessor
check_authentication('lessor');

// Get lessor information
$lessor_id = get_user_id();
$lessor_name = get_user_name();
$lessor_email = get_user_email();

// Check if lessor is verified
$sql_check = "SELECT VERIFICATION_STATUS FROM LESSOR WHERE LESSORID = :lessorid";
$lessor_status = db_fetch_one($sql_check, ['lessorid' => $lessor_id]);

// If not verified, redirect to re-submit page
if ($lessor_status && $lessor_status['VERIFICATION_STATUS'] !== 'VERIFIED') {
    header('Location: resubmit_verification.php');
    exit();
}

// Get statistics
// Total cars listed
$sql_cars = "SELECT COUNT(*) as total FROM CAR WHERE LESSORID = :lessorid";
$cars_count = db_fetch_one($sql_cars, ['lessorid' => $lessor_id]);
$total_cars = $cars_count ? $cars_count['TOTAL'] : 0;

// Total active cars
$sql_active = "SELECT COUNT(*) as total FROM CAR WHERE LESSORID = :lessorid AND AVAILABILITY_STATUS = 'AVAILABLE'";
$active_count = db_fetch_one($sql_active, ['lessorid' => $lessor_id]);
$total_active = $active_count ? $active_count['TOTAL'] : 0;

// Total bookings (all time)
$sql_bookings = "SELECT COUNT(*) as total FROM BOOKING 
                 WHERE CARID IN (SELECT CARID FROM CAR WHERE LESSORID = :lessorid)";
$bookings_count = db_fetch_one($sql_bookings, ['lessorid' => $lessor_id]);
$total_bookings = $bookings_count ? $bookings_count['TOTAL'] : 0;

// Pending bookings (waiting for approval)
$sql_pending = "SELECT COUNT(*) as total FROM BOOKING 
                 WHERE CARID IN (SELECT CARID FROM CAR WHERE LESSORID = :lessorid) 
                 AND BOOKING_STATUS = 'PENDING'";
$pending_count = db_fetch_one($sql_pending, ['lessorid' => $lessor_id]);
$total_pending = $pending_count ? $pending_count['TOTAL'] : 0;

// Total earnings (from completed bookings) - 90% after commission
$sql_earnings = "SELECT SUM(TOTAL_AMOUNT * 0.90) as total FROM BOOKING 
                 WHERE CARID IN (SELECT CARID FROM CAR WHERE LESSORID = :lessorid) 
                 AND BOOKING_STATUS = 'COMPLETED'";
$earnings_result = db_fetch_one($sql_earnings, ['lessorid' => $lessor_id]);
$total_earnings = $earnings_result && $earnings_result['TOTAL'] ? $earnings_result['TOTAL'] : 0;

// Get open support tickets count
$sql_tickets = "SELECT COUNT(*) as total FROM TICKET 
                 WHERE LESSORID = :lessorid AND STATUS = 'OPEN'";
$tickets_count = db_fetch_one($sql_tickets, ['lessorid' => $lessor_id]);
$open_tickets = $tickets_count ? $tickets_count['TOTAL'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessor Dashboard - Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <link href="../assets/css/lessor-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2>Car Rental System <span style="font-size:14px; opacity:0.8; font-weight:400;">| Lessor Panel</span></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</span>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="my_cars.php">My Cars</a>
                <a href="bookings.php">Bookings</a>
                <a href="earnings.php">Earnings</a>
                <a href="submit_ticket.php">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <div class="hero">
            <h1>Welcome to Your Dashboard!</h1>
            <p>Manage your car listings, bookings, and earnings</p>
        </div>
        
        <div class="content-wrapper">
            <h2>Your Statistics</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Cars Listed</h3>
                    <div class="stat-number"><?php echo $total_cars; ?></div>
                    <p>All your vehicles</p>
                </div>
                
                <div class="stat-card">
                    <h3>Active Listings</h3>
                    <div class="stat-number"><?php echo $total_active; ?></div>
                    <p>Available for booking</p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="stat-number"><?php echo $total_bookings; ?></div>
                    <p>All-time bookings</p>
                </div>
                
                <div class="stat-card">
                    <h3>Pending Bookings</h3>
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $total_pending; ?></div>
                    <p>Awaiting your approval</p>
                    <?php if ($total_pending > 0): ?>
                        <a href="bookings.php" class="card-link">Review Now</a>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <h3>Total Earnings</h3>
                    <div class="stat-number" style="color: #10b981;">RM <?php echo number_format($total_earnings, 2); ?></div>
                    <p>After 10% platform fee</p>
                </div>
                
                <div class="stat-card">
                    <h3>Support Tickets</h3>
                    <div class="stat-number"><?php echo $open_tickets; ?></div>
                    <p>Open tickets</p>
                    <?php if ($open_tickets > 0): ?>
                        <a href="submit_ticket.php" class="card-link">View Tickets</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="content-wrapper">
            <h2>Quick Actions</h2>
            
            <div class="actions-grid">
                <a href="add_car.php" class="action-card">
                    <h3>Add New Car</h3>
                    <p>List a new vehicle for rent</p>
                </a>
                
                <a href="my_cars.php" class="action-card">
                    <h3>My Car Listings</h3>
                    <p>View and manage your vehicles</p>
                </a>
                
                <a href="bookings.php" class="action-card">
                    <h3>Manage Bookings</h3>
                    <p>Respond to requests</p>
                </a>
                
                <a href="earnings.php" class="action-card">
                    <h3>Track Earnings</h3>
                    <p>View revenue and payouts</p>
                </a>
                
                <a href="submit_ticket.php" class="action-card">
                    <h3>Support Tickets</h3>
                    <p>Get help from support</p>
                </a>
            </div>
        </div>
        
        <?php if ($total_cars == 0): ?>
        <div class="alert-box info">
            <h3>🚗 Get Started</h3>
            <p>You haven't listed any cars yet. Here's how to get started:</p>
            <ol>
                <li>Click "Add New Car" to list your first vehicle</li>
                <li>Upload photos and set your rental rates</li>
                <li>Wait for booking requests from renters</li>
            </ol>
            <a href="add_car.php" class="btn-primary">List Your First Car Now</a>
        </div>
        <?php endif; ?>
        
    </main>
</body>
</html>