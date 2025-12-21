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

// Total earnings (from completed bookings)
$sql_earnings = "SELECT SUM(TOTAL_AMOUNT) as total FROM BOOKING 
                 WHERE CARID IN (SELECT CARID FROM CAR WHERE LESSORID = :lessorid) 
                 AND BOOKING_STATUS IN ('COMPLETED', 'CONFIRMED')";
$earnings_result = db_fetch_one($sql_earnings, ['lessorid' => $lessor_id]);
$total_earnings = $earnings_result && $earnings_result['TOTAL'] ? $earnings_result['TOTAL'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessor Dashboard - Car Rental</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <h2>Car Rental System - Lessor Panel</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main>
        <div>
            <h1>Welcome to Your Dashboard!</h1>
            <p>Manage your car listings, bookings, and earnings</p>
        </div>
        
        <!-- Statistics Overview -->
        <div>
            <h2>Your Statistics</h2>
            
            <div>
                <div>
                    <h3>Total Cars Listed</h3>
                    <div><?php echo $total_cars; ?></div>
                    <p>All your vehicles</p>
                </div>
                
                <div>
                    <h3>Active Listings</h3>
                    <div><?php echo $total_active; ?></div>
                    <p>Available for booking</p>
                </div>
                
                <div>
                    <h3>Total Bookings</h3>
                    <div><?php echo $total_bookings; ?></div>
                    <p>All-time bookings</p>
                </div>
                
                <div>
                    <h3>Pending Bookings</h3>
                    <div><?php echo $total_pending; ?></div>
                    <p>Awaiting your approval</p>
                    <?php if ($total_pending > 0): ?>
                        <a href="bookings.php">Review Now</a>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h3>Total Earnings</h3>
                    <div>RM <?php echo number_format($total_earnings, 2); ?></div>
                    <p>From completed bookings</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div>
            <h2>Quick Actions</h2>
            
            <div>
                <a href="add_car.php">
                    <h3>Add New Car</h3>
                    <p>List a new vehicle for rent</p>
                </a>
                
                <a href="my_cars.php">
                    <h3>My Car Listings</h3>
                    <p>View and manage your vehicles</p>
                    <?php if ($total_cars > 0): ?>
                        <span><?php echo $total_cars; ?> cars</span>
                    <?php endif; ?>
                </a>
                
                <a href="bookings.php">
                    <h3>Manage Bookings</h3>
                    <p>View and respond to booking requests</p>
                    <?php if ($total_pending > 0): ?>
                        <span><?php echo $total_pending; ?> pending</span>
                    <?php endif; ?>
                </a>
                
                <a href="earnings.php">
                    <h3>Track Earnings</h3>
                    <p>View your revenue and payouts</p>
                </a>
            </div>
        </div>
        
        <!-- Getting Started Guide (if no cars) -->
        <?php if ($total_cars == 0): ?>
        <div style="background: #e7f3ff; padding: 20px; border-left: 4px solid #007bff; margin-top: 30px;">
            <h2 style="margin-top: 0; color: #004085;">🚗 Get Started</h2>
            <p>You haven't listed any cars yet. Here's how to get started:</p>
            <ol>
                <li><strong>Add Your First Car</strong> - Click "Add New Car" to list your vehicle</li>
                <li><strong>Upload Quality Photos</strong> - Clear photos attract more renters</li>
                <li><strong>Set Competitive Rates</strong> - Research similar cars in your area</li>
                <li><strong>Wait for Bookings</strong> - Renters will find your car and request bookings</li>
                <li><strong>Approve Bookings</strong> - Review and approve booking requests</li>
                <li><strong>Earn Money</strong> - Get paid for each completed rental</li>
            </ol>
            <a href="add_car.php" style="display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;">
                List Your First Car Now
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity (Optional - can implement later) -->
        <div>
            <h2>Recent Activity</h2>
            <p>Recent bookings and updates will appear here</p>
        </div>
    </main>
</body>
</html>