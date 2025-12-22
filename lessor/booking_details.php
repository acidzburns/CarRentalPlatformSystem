<?php
/**
 * Lessor: View Booking Details
 * Full information about a single booking
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if lessor is logged in
require_login('lessor');

$lessorid = get_user_id();

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    set_flash_message("Invalid booking ID.", "error");
    header("Location: bookings.php");
    exit();
}

$bookingid = intval($_GET['bookingid']);

// Fetch complete booking details
$sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT, b.CREATED_AT,
        c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH, c.DAILY_RATE,
        r.RENTERID, r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE,
        p.PAYMENTID, p.PAYMENT_STATUS, p.PAYMENT_METHOD, p.PAYMENT_DATE,
        t.TRANSACTIONID, t.AMOUNT as LESSOR_AMOUNT, t.COMMISSION,
        TO_CHAR(b.START_DATE, 'DD-MON-YYYY HH24:MI') as START_DATE_FMT,
        TO_CHAR(b.END_DATE, 'DD-MON-YYYY HH24:MI') as END_DATE_FMT,
        TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT,
        TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as PAYMENT_DATE_FMT
        FROM BOOKING b
        JOIN CAR c ON b.CARID = c.CARID
        JOIN RENTER r ON b.RENTERID = r.RENTERID
        LEFT JOIN PAYMENT p ON b.BOOKINGID = p.BOOKINGID
        LEFT JOIN TRANSACTION t ON b.BOOKINGID = t.BOOKINGID
        WHERE b.BOOKINGID = :bookingid AND c.LESSORID = :lessorid";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':bookingid', $bookingid);
oci_bind_by_name($stmt, ':lessorid', $lessorid);
oci_execute($stmt);

$booking = oci_fetch_assoc($stmt);

if (!$booking) {
    set_flash_message("Booking not found or you don't have permission.", "error");
    header("Location: bookings.php");
    exit();
}

// Calculate rental duration
$start = new DateTime($booking['START_DATE']);
$end = new DateTime($booking['END_DATE']);
$duration = $start->diff($end)->days;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details #<?php echo $booking['BOOKINGID']; ?></title>
</head>
<body>
    <h1>Booking Details #<?php echo $booking['BOOKINGID']; ?></h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="bookings.php">← Back to Bookings</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- Booking Status -->
    <h2>Status: 
        <span style="color: 
            <?php 
            echo $booking['BOOKING_STATUS'] == 'CONFIRMED' ? 'blue' : 
                 ($booking['BOOKING_STATUS'] == 'COMPLETED' ? 'green' : 
                 ($booking['BOOKING_STATUS'] == 'PENDING' ? 'orange' : 'red')); 
            ?>">
            <?php echo $booking['BOOKING_STATUS']; ?>
        </span>
    </h2>
    
    <hr>
    
    <!-- Car Details -->
    <h2>Car Information</h2>
    <?php if ($booking['PHOTO1_PATH']): ?>
        <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
             alt="Car Photo" width="400" height="300">
    <?php endif; ?>
    
    <table border="1">
        <tr>
            <th>Car</th>
            <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></td>
        </tr>
        <tr>
            <th>License Plate</th>
            <td><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></td>
        </tr>
        <tr>
            <th>Daily Rate</th>
            <td>RM <?php echo number_format($booking['DAILY_RATE'], 2); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Renter Details -->
    <h2>Renter Information</h2>
    <table border="1">
        <tr>
            <th>Name</th>
            <td><?php echo htmlspecialchars($booking['RENTER_NAME']); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><a href="mailto:<?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?>">
                <?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?>
            </a></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td><?php echo htmlspecialchars($booking['RENTER_PHONE'] ?? 'N/A'); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Booking Details -->
    <h2>Booking Information</h2>
    <table border="1">
        <tr>
            <th>Booking ID</th>
            <td>#<?php echo $booking['BOOKINGID']; ?></td>
        </tr>
        <tr>
            <th>Booking Date</th>
            <td><?php echo htmlspecialchars($booking['CREATED_FMT']); ?></td>
        </tr>
        <tr>
            <th>Pickup Date</th>
            <td><?php echo htmlspecialchars($booking['START_DATE_FMT']); ?></td>
        </tr>
        <tr>
            <th>Return Date</th>
            <td><?php echo htmlspecialchars($booking['END_DATE_FMT']); ?></td>
        </tr>
        <tr>
            <th>Duration</th>
            <td><?php echo $duration; ?> day(s)</td>
        </tr>
        <tr>
            <th>Status</th>
            <td><strong><?php echo $booking['BOOKING_STATUS']; ?></strong></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Payment Details -->
    <h2>Payment Information</h2>
    <?php if ($booking['PAYMENTID']): ?>
        <table border="1">
            <tr>
                <th>Payment ID</th>
                <td>#<?php echo $booking['PAYMENTID']; ?></td>
            </tr>
            <tr>
                <th>Payment Method</th>
                <td><?php echo htmlspecialchars($booking['PAYMENT_METHOD']); ?></td>
            </tr>
            <tr>
                <th>Payment Status</th>
                <td><?php echo htmlspecialchars($booking['PAYMENT_STATUS']); ?></td>
            </tr>
            <tr>
                <th>Payment Date</th>
                <td><?php echo htmlspecialchars($booking['PAYMENT_DATE_FMT']); ?></td>
            </tr>
            <tr>
                <th>Total Amount</th>
                <td><strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong></td>
            </tr>
        </table>
    <?php else: ?>
        <p>Payment not completed yet.</p>
    <?php endif; ?>
    
    <hr>
    
    <!-- Earnings Breakdown (Escrow Info) -->
    <?php if ($booking['TRANSACTIONID']): ?>
        <h2>💰 Your Earnings (After Platform Commission)</h2>
        <table border="1">
            <tr>
                <th>Transaction ID</th>
                <td>#<?php echo $booking['TRANSACTIONID']; ?></td>
            </tr>
            <tr>
                <th>Total Payment</th>
                <td>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
            </tr>
            <tr>
                <th>Platform Commission (10%)</th>
                <td>- RM <?php echo number_format($booking['COMMISSION'], 2); ?></td>
            </tr>
            <tr style="background: #e8f5e9;">
                <th>Your Earnings (90%)</th>
                <td><strong>RM <?php echo number_format($booking['LESSOR_AMOUNT'], 2); ?></strong></td>
            </tr>
        </table>
        
        <?php if ($booking['BOOKING_STATUS'] == 'CONFIRMED'): ?>
            <p style="background: #fff3cd; padding: 15px; margin: 15px 0;">
                <strong>⏳ Escrow Status:</strong> Your earnings are currently HELD by the platform.
                They will be manually transferred to your bank account within 24 hours after you mark this booking as completed.
            </p>
        <?php elseif ($booking['BOOKING_STATUS'] == 'COMPLETED'): ?>
            <p style="background: #d4edda; padding: 15px; margin: 15px 0;">
                <strong>✓ Payment Released:</strong> Your earnings have been recorded. Platform team will manually 
                transfer RM <?php echo number_format($booking['LESSOR_AMOUNT'], 2); ?> to your bank account within 24 hours.
            </p>
        <?php endif; ?>
    <?php endif; ?>
    
    <hr>
    
    <!-- Actions -->
    <h2>Actions</h2>
    <?php if ($booking['BOOKING_STATUS'] == 'CONFIRMED'): ?>
        <form method="POST" action="manage_booking.php" style="display: inline;">
            <input type="hidden" name="bookingid" value="<?php echo $booking['BOOKINGID']; ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit" onclick="return confirm('Confirm that car has been returned in good condition?')">
                ✓ Mark as Completed (Release Payment)
            </button>
        </form>
        
        <br><br>
        
        <form method="POST" action="manage_booking.php">
            <input type="hidden" name="bookingid" value="<?php echo $booking['BOOKINGID']; ?>">
            <input type="hidden" name="action" value="cancel_emergency">
            <label>Emergency Cancellation Reason:</label><br>
            <input type="text" name="cancel_reason" size="50" required><br>
            <button type="submit" onclick="return confirm('Cancel this booking? Renter will get full refund.')">
                ✗ Emergency Cancel
            </button>
        </form>
    
    <?php elseif ($booking['BOOKING_STATUS'] == 'COMPLETED'): ?>
        <p style="color: green;"><strong>✓ This booking is completed.</strong></p>
    
    <?php elseif ($booking['BOOKING_STATUS'] == 'PENDING'): ?>
        <p style="color: orange;"><strong>⏳ Waiting for renter to complete payment...</strong></p>
    
    <?php else: ?>
        <p><strong>Status: <?php echo $booking['BOOKING_STATUS']; ?></strong></p>
    <?php endif; ?>
    
    <hr>
    
    <p><a href="bookings.php">← Back to All Bookings</a></p>
</body>
</html>