<?php
/**
 * Renter Booking History Page
 * UC11: Booking History
 * 
 * Display customer's previous booking history including completed, ongoing, and cancelled bookings
 * SRS Page 42-43
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as renter
check_authentication('renter');

$renter_id = get_user_id();
$renter_name = get_user_name();

// Step 1: Requests booking history using customer ID
// Step 2: System queries bookings table
// Step 3: System retrieves all customer bookings

// Filter by status (optional)
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'ALL';
$valid_statuses = ['ALL', 'PENDING', 'CONFIRMED', 'ONGOING', 'COMPLETED', 'CANCELLED', 'REJECTED'];

if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'ALL';
}

// Build query based on filter
if ($status_filter === 'ALL') {
    $sql = "SELECT b.*, 
            c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH, c.DAILY_RATE,
            l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL, l.PHONE as LESSOR_PHONE,
            TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
            TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
            TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_FMT,
            (b.END_DATE - b.START_DATE) as RENTAL_DAYS,
            p.PAYMENT_STATUS, p.PAYMENT_METHOD,
            TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as PAYMENT_DATE_FMT
            FROM BOOKING b
            JOIN CAR c ON b.CARID = c.CARID
            JOIN LESSOR l ON c.LESSORID = l.LESSORID
            LEFT JOIN PAYMENT p ON b.BOOKINGID = p.BOOKINGID
            WHERE b.RENTERID = :renterid
            ORDER BY b.CREATED_AT DESC";
    $params = ['renterid' => $renter_id];
} else {
    $sql = "SELECT b.*, 
            c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH, c.DAILY_RATE,
            l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL, l.PHONE as LESSOR_PHONE,
            TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
            TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
            TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_FMT,
            (b.END_DATE - b.START_DATE) as RENTAL_DAYS,
            p.PAYMENT_STATUS, p.PAYMENT_METHOD,
            TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as PAYMENT_DATE_FMT
            FROM BOOKING b
            JOIN CAR c ON b.CARID = c.CARID
            JOIN LESSOR l ON c.LESSORID = l.LESSORID
            LEFT JOIN PAYMENT p ON b.BOOKINGID = p.BOOKINGID
            WHERE b.RENTERID = :renterid
            AND b.BOOKING_STATUS = :status
            ORDER BY b.CREATED_AT DESC";
    $params = ['renterid' => $renter_id, 'status' => $status_filter];
}

// Step 3: System returns booking history to calling use case
$bookings = db_query($sql, $params);

// Exception 1: No history
$has_no_bookings = empty($bookings);

// Get booking counts by status
$sql_counts = "SELECT 
               SUM(CASE WHEN BOOKING_STATUS = 'PENDING' THEN 1 ELSE 0 END) as PENDING_COUNT,
               SUM(CASE WHEN BOOKING_STATUS = 'CONFIRMED' THEN 1 ELSE 0 END) as CONFIRMED_COUNT,
               SUM(CASE WHEN BOOKING_STATUS = 'ONGOING' THEN 1 ELSE 0 END) as ONGOING_COUNT,
               SUM(CASE WHEN BOOKING_STATUS = 'COMPLETED' THEN 1 ELSE 0 END) as COMPLETED_COUNT,
               SUM(CASE WHEN BOOKING_STATUS = 'CANCELLED' THEN 1 ELSE 0 END) as CANCELLED_COUNT,
               SUM(CASE WHEN BOOKING_STATUS = 'REJECTED' THEN 1 ELSE 0 END) as REJECTED_COUNT,
               COUNT(*) as TOTAL_COUNT
               FROM BOOKING 
               WHERE RENTERID = :renterid";
$counts = db_fetch_one($sql_counts, ['renterid' => $renter_id]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Car Rental</title>
</head>
<body>
    <nav>
        <h2>Car Rental System - Customer Panel</h2>
        <p>Welcome, <?php echo htmlspecialchars($renter_name); ?>!</p>
        <a href="dashboard.php">Dashboard</a>
        <a href="search_cars.php">Search Cars</a>
        <a href="booking_history.php">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <main>
        <h1>My Booking History</h1>
        <p>View and manage all your car rental bookings</p>
        
        <!-- Booking Statistics -->
        <div class="stats">
            <h3>Booking Summary</h3>
            <p>Total Bookings: <?php echo $counts['TOTAL_COUNT']; ?></p>
            <p>Pending: <?php echo $counts['PENDING_COUNT']; ?></p>
            <p>Confirmed: <?php echo $counts['CONFIRMED_COUNT']; ?></p>
            <p>Ongoing: <?php echo $counts['ONGOING_COUNT']; ?></p>
            <p>Completed: <?php echo $counts['COMPLETED_COUNT']; ?></p>
            <p>Cancelled: <?php echo $counts['CANCELLED_COUNT']; ?></p>
            <p>Rejected: <?php echo $counts['REJECTED_COUNT']; ?></p>
        </div>
        
        <hr>
        
        <!-- Filter by Status -->
        <h3>Filter by Status:</h3>
        <nav>
            <a href="booking_history.php?status=ALL">All (<?php echo $counts['TOTAL_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=PENDING">Pending (<?php echo $counts['PENDING_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=CONFIRMED">Confirmed (<?php echo $counts['CONFIRMED_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=ONGOING">Ongoing (<?php echo $counts['ONGOING_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=COMPLETED">Completed (<?php echo $counts['COMPLETED_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=CANCELLED">Cancelled (<?php echo $counts['CANCELLED_COUNT']; ?>)</a> |
            <a href="booking_history.php?status=REJECTED">Rejected (<?php echo $counts['REJECTED_COUNT']; ?>)</a>
        </nav>
        
        <p>Current filter: <strong><?php echo htmlspecialchars($status_filter); ?></strong></p>
        
        <hr>
        
        <!-- Exception 1: No booking history -->
        <?php if ($has_no_bookings): ?>
            <div class="no-bookings">
                <h2>You have no booking history yet.</h2>
                <p>Start exploring and book your first car!</p>
                <a href="search_cars.php">Search Available Cars</a>
            </div>
        
        <?php else: ?>
        
        <!-- Display Bookings -->
        <h2>Your Bookings (<?php echo count($bookings); ?> found)</h2>
        
        <?php foreach ($bookings as $booking): ?>
        
        <div class="booking-card">
            <h3>Booking #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></h3>
            
            <!-- Car Photo -->
            <?php if ($booking['PHOTO1_PATH']): ?>
            <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                 alt="Car" width="200">
            <?php endif; ?>
            
            <!-- Car Details -->
            <h4><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></h4>
            <p><strong>License Plate:</strong> <?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></p>
            
            <!-- Booking Details -->
            <p><strong>Rental Period:</strong> <?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' to ' . $booking['END_DATE_FMT']); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($booking['RENTAL_DAYS']); ?> day(s)</p>
            <p><strong>Daily Rate:</strong> RM<?php echo number_format($booking['DAILY_RATE'], 2); ?></p>
            <p><strong>Total Amount:</strong> RM<?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></p>
            
            <!-- Booking Status -->
            <p><strong>Booking Status:</strong> 
                <span class="status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                    <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                </span>
            </p>
            
            <!-- Payment Status -->
            <?php if ($booking['PAYMENT_STATUS']): ?>
            <p><strong>Payment Status:</strong> 
                <span class="payment-<?php echo strtolower($booking['PAYMENT_STATUS']); ?>">
                    <?php echo htmlspecialchars($booking['PAYMENT_STATUS']); ?>
                </span>
            </p>
            <?php if ($booking['PAYMENT_METHOD']): ?>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($booking['PAYMENT_METHOD']); ?></p>
            <?php endif; ?>
            <?php if ($booking['PAYMENT_DATE_FMT']): ?>
            <p><strong>Payment Date:</strong> <?php echo htmlspecialchars($booking['PAYMENT_DATE_FMT']); ?></p>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Owner Details -->
            <p><strong>Car Owner:</strong> <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></p>
            <p><strong>Owner Email:</strong> <?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?></p>
            <?php if ($booking['LESSOR_PHONE']): ?>
            <p><strong>Owner Phone:</strong> <?php echo htmlspecialchars($booking['LESSOR_PHONE']); ?></p>
            <?php endif; ?>
            
            <!-- Booking Created -->
            <p><strong>Booked On:</strong> <?php echo htmlspecialchars($booking['CREATED_AT_FMT']); ?></p>
            
            <!-- Action Buttons based on status -->
            <div class="actions">
                
                <?php if ($booking['BOOKING_STATUS'] === 'PENDING' && !$booking['PAYMENT_STATUS']): ?>
                    <!-- Pending payment -->
                    <a href="make_payment.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">Complete Payment</a>
                    <a href="cancel_booking.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" 
                       onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel Booking</a>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'PENDING' && $booking['PAYMENT_STATUS'] === 'SUCCESS'): ?>
                    <!-- Waiting for lessor approval -->
                    <p><em>Waiting for car owner to confirm your booking...</em></p>
                    <a href="payment_success.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">View Payment Receipt</a>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'CONFIRMED'): ?>
                    <!-- Confirmed, waiting for pickup -->
                    <p><em>Confirmed! Car owner will contact you for pickup details.</em></p>
                    <a href="payment_success.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">View Booking Details</a>
                    <a href="contact_owner.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">Contact Owner</a>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'ONGOING'): ?>
                    <!-- Currently renting -->
                    <p><em>Enjoy your rental! Please return the car on time.</em></p>
                    <p><strong>Return by:</strong> <?php echo htmlspecialchars($booking['END_DATE_FMT']); ?></p>
                    <a href="payment_success.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">View Booking Details</a>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'COMPLETED'): ?>
                    <!-- Completed, can review -->
                    <p><em>Rental completed. Thank you!</em></p>
                    <a href="payment_success.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">View Receipt</a>
                    
                    <?php
                    // Check if already reviewed
                    $sql_check_review = "SELECT REVIEWID FROM REVIEW WHERE BOOKINGID = :bookingid";
                    $existing_review = db_fetch_one($sql_check_review, ['bookingid' => $booking['BOOKINGID']]);
                    ?>
                    
                    <?php if (!$existing_review): ?>
                        <a href="submit_review.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">Write a Review</a>
                    <?php else: ?>
                        <p><em>You have already reviewed this booking.</em></p>
                        <a href="view_review.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">View Your Review</a>
                    <?php endif; ?>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'CANCELLED'): ?>
                    <!-- Cancelled -->
                    <p><em>This booking was cancelled.</em></p>
                    <?php if ($booking['PAYMENT_STATUS'] === 'SUCCESS'): ?>
                        <p><em>Refund will be processed within 3-5 business days.</em></p>
                    <?php endif; ?>
                
                <?php elseif ($booking['BOOKING_STATUS'] === 'REJECTED'): ?>
                    <!-- Rejected by lessor -->
                    <p><em>This booking was rejected by the car owner.</em></p>
                    <?php if ($booking['PAYMENT_STATUS'] === 'SUCCESS'): ?>
                        <p><em>Full refund will be processed within 3-5 business days.</em></p>
                    <?php endif; ?>
                    <a href="search_cars.php">Search for Another Car</a>
                
                <?php endif; ?>
                
            </div>
            
            <hr>
        </div>
        
        <?php endforeach; ?>
        
        <?php endif; ?>
        
        <!-- Back to Dashboard -->
        <p><a href="dashboard.php">← Back to Dashboard</a></p>
        
    </main>
</body>
</html>