<?php
/**
 * Admin Dashboard
 * Main page after admin login
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as admin
check_authentication('admin');

// Get admin information
$admin_name = get_user_name();
$admin_email = get_user_email();

// Get statistics
// Pending lessor verifications
$sql_pending_lessors = "SELECT COUNT(*) as total FROM LESSOR WHERE VERIFICATION_STATUS = 'PENDING'";
$pending_lessors = db_fetch_one($sql_pending_lessors);
$total_pending_lessors = $pending_lessors ? $pending_lessors['TOTAL'] : 0;

// Total verified lessors
$sql_verified_lessors = "SELECT COUNT(*) as total FROM LESSOR WHERE VERIFICATION_STATUS = 'VERIFIED'";
$verified_lessors = db_fetch_one($sql_verified_lessors);
$total_verified_lessors = $verified_lessors ? $verified_lessors['TOTAL'] : 0;

// Total renters
$sql_renters = "SELECT COUNT(*) as total FROM RENTER";
$renters = db_fetch_one($sql_renters);
$total_renters = $renters ? $renters['TOTAL'] : 0;

// Total cars
$sql_cars = "SELECT COUNT(*) as total FROM CAR";
$cars = db_fetch_one($sql_cars);
$total_cars = $cars ? $cars['TOTAL'] : 0;

// Total bookings
$sql_bookings = "SELECT COUNT(*) as total FROM BOOKING";
$bookings = db_fetch_one($sql_bookings);
$total_bookings = $bookings ? $bookings['TOTAL'] : 0;

// Pending support tickets
$sql_tickets = "SELECT COUNT(*) as total FROM TICKET WHERE STATUS = 'OPEN'";
$tickets = db_fetch_one($sql_tickets);
$total_tickets = $tickets ? $tickets['TOTAL'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Car Rental</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <h2>Car Rental System - Admin Panel</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main>
        <div>
            <h1>Admin Dashboard</h1>
            <p>Manage and monitor the car rental platform</p>
        </div>
        
        <!-- Statistics Overview -->
        <div>
            <h2>System Overview</h2>
            
            <div>
                <div>
                    <h3>Pending Verifications</h3>
                    <div><?php echo $total_pending_lessors; ?></div>
                    <p>Lessors awaiting verification</p>
                    <?php if ($total_pending_lessors > 0): ?>
                        <a href="verify_lessors.php">Review Now</a>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h3>Total Renters</h3>
                    <div><?php echo $total_renters; ?></div>
                    <p>Registered customers</p>
                </div>
                
                <div>
                    <h3>Verified Lessors</h3>
                    <div><?php echo $total_verified_lessors; ?></div>
                    <p>Active car owners</p>
                </div>
                
                <div>
                    <h3>Total Cars</h3>
                    <div><?php echo $total_cars; ?></div>
                    <p>Listed vehicles</p>
                </div>
                
                <div>
                    <h3>Total Bookings</h3>
                    <div><?php echo $total_bookings; ?></div>
                    <p>All-time bookings</p>
                </div>
                
                <div>
                    <h3>Open Tickets</h3>
                    <div><?php echo $total_tickets; ?></div>
                    <p>Support requests</p>
                    <?php if ($total_tickets > 0): ?>
                        <a href="manage_tickets.php">View Tickets</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div>
            <h2>Quick Actions</h2>
            
            <div>
                <a href="verify_lessors.php">
                    <h3>Verify Lessors</h3>
                    <p>Review and approve car owner registrations</p>
                    <?php if ($total_pending_lessors > 0): ?>
                        <span><?php echo $total_pending_lessors; ?> pending</span>
                    <?php endif; ?>
                </a>
                
                <a href="manage_users.php">
                    <h3>Manage Users</h3>
                    <p>View and manage renters and lessors</p>
                </a>
                
                <a href="manage_tickets.php">
                    <h3>Customer Support</h3>
                    <p>Handle support tickets and inquiries</p>
                    <?php if ($total_tickets > 0): ?>
                        <span><?php echo $total_tickets; ?> open</span>
                    <?php endif; ?>
                </a>
                
                <a href="reports.php">
                    <h3>Generate Reports</h3>
                    <p>View booking, financial, and performance reports</p>
                </a>

                <a href="manage_bookings.php">
                    <h3>Manage Bookings</h3>
                    <p>View booking details</p>
                </a>
            </div>
        </div>
        
       
    </main>
</body>
</html>