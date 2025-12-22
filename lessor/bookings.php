<?php
/**
 * UC20: Booking Management (SRS Pages 67-70)
 * Lessor views all bookings for their cars
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if lessor is logged in
require_login('lessor');

$lessorid = get_user_id();

// Get filter from URL (default: all)
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// UC20 Step 2: Retrieve all bookings for lessor's cars
$sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT, b.CREATED_AT,
        c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
        r.RENTERID, r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE,
        p.PAYMENT_STATUS, p.PAYMENT_METHOD,
        TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
        TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
        TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT
        FROM BOOKING b
        JOIN CAR c ON b.CARID = c.CARID
        JOIN RENTER r ON b.RENTERID = r.RENTERID
        LEFT JOIN PAYMENT p ON b.BOOKINGID = p.BOOKINGID
        WHERE c.LESSORID = :lessorid";

// UC20 Step 4: Filter by status
if ($status_filter != 'all') {
    $sql .= " AND b.BOOKING_STATUS = :status";
}

$sql .= " ORDER BY b.CREATED_AT DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':lessorid', $lessorid);

if ($status_filter != 'all') {
    $status_upper = strtoupper($status_filter);
    oci_bind_by_name($stmt, ':status', $status_upper);
}

oci_execute($stmt);

$bookings = [];
while ($row = oci_fetch_assoc($stmt)) {
    $bookings[] = $row;
}

// Get booking statistics
// NOTE: Total Earned = YOUR earnings (90%), not full booking amount
$stats_sql = "SELECT 
              COUNT(*) as TOTAL,
              COUNT(CASE WHEN b.BOOKING_STATUS = 'PENDING' THEN 1 END) as PENDING,
              COUNT(CASE WHEN b.BOOKING_STATUS = 'CONFIRMED' THEN 1 END) as CONFIRMED,
              COUNT(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED,
              COUNT(CASE WHEN b.BOOKING_STATUS = 'CANCELLED' THEN 1 END) as CANCELLED,
              SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as TOTAL_EARNED
              FROM BOOKING b
              JOIN CAR c ON b.CARID = c.CARID
              WHERE c.LESSORID = :lessorid";

$stmt_stats = oci_parse($conn, $stats_sql);
oci_bind_by_name($stmt_stats, ':lessorid', $lessorid);
oci_execute($stmt_stats);
$stats = oci_fetch_assoc($stmt_stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Lessor</title>
</head>
<body>
    <h1>Manage Bookings</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="my_cars.php">My Cars</a> |
        <a href="bookings.php">Bookings</a> |
        <a href="earnings.php">Earnings</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- UC20 Step 6: Display booking statistics -->
    <h2>Booking Statistics</h2>
    <p style="color: #666; font-size: 14px;">Note: Total Earned = Your actual earnings after 10% platform commission</p>
    <table border="1">
        <tr>
            <th>Total Bookings</th>
            <th>Pending</th>
            <th>Confirmed</th>
            <th>Completed</th>
            <th>Cancelled</th>
            <th>Total Earned</th>
        </tr>
        <tr>
            <td><?php echo $stats['TOTAL']; ?></td>
            <td><?php echo $stats['PENDING']; ?></td>
            <td><?php echo $stats['CONFIRMED']; ?></td>
            <td><?php echo $stats['COMPLETED']; ?></td>
            <td><?php echo $stats['CANCELLED']; ?></td>
            <td>RM <?php echo number_format($stats['TOTAL_EARNED'], 2); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- UC20 Step 4: Filter bookings -->
    <h2>Filter Bookings</h2>
    <a href="?status=all">All</a> |
    <a href="?status=pending">Pending</a> |
    <a href="?status=confirmed">Confirmed</a> |
    <a href="?status=completed">Completed</a> |
    <a href="?status=cancelled">Cancelled</a>
    
    <p>Showing: <strong><?php echo ucfirst($status_filter); ?></strong></p>
    
    <hr>
    
    <!-- UC20 Step 3: Display bookings -->
    <h2>All Bookings (<?php echo count($bookings); ?>)</h2>
    
    <?php if (empty($bookings)): ?>
        <p>No bookings found.</p>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <div style="border: 1px solid #ccc; padding: 15px; margin: 15px 0; background: #f9f9f9;">
                <h3>Booking #<?php echo $booking['BOOKINGID']; ?> - 
                    <span style="color: 
                        <?php 
                        echo $booking['BOOKING_STATUS'] == 'CONFIRMED' ? 'blue' : 
                             ($booking['BOOKING_STATUS'] == 'COMPLETED' ? 'green' : 
                             ($booking['BOOKING_STATUS'] == 'PENDING' ? 'orange' : 'red')); 
                        ?>">
                        <?php echo $booking['BOOKING_STATUS']; ?>
                    </span>
                </h3>
                
                <table border="1" style="width: 100%; margin: 10px 0;">
                    <tr>
                        <th colspan="2" style="background: #ddd;">Car Details</th>
                    </tr>
                    <tr>
                        <td><strong>Car:</strong></td>
                        <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>License Plate:</strong></td>
                        <td><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></td>
                    </tr>
                    
                    <tr>
                        <th colspan="2" style="background: #ddd;">Renter Details</th>
                    </tr>
                    <tr>
                        <td><strong>Renter Name:</strong></td>
                        <td><?php echo htmlspecialchars($booking['RENTER_NAME']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo htmlspecialchars($booking['RENTER_PHONE'] ?? 'N/A'); ?></td>
                    </tr>
                    
                    <tr>
                        <th colspan="2" style="background: #ddd;">Booking Details</th>
                    </tr>
                    <tr>
                        <td><strong>Rental Period:</strong></td>
                        <td><?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' to ' . $booking['END_DATE_FMT']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Amount:</strong></td>
                        <td><strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td><?php echo htmlspecialchars($booking['PAYMENT_STATUS'] ?? 'NOT PAID'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><?php echo htmlspecialchars($booking['PAYMENT_METHOD'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Booked On:</strong></td>
                        <td><?php echo htmlspecialchars($booking['CREATED_FMT']); ?></td>
                    </tr>
                </table>
                
                <!-- UC20 Step 7a, 7b: Actions based on status -->
                <div style="margin-top: 10px;">
                    <?php if ($booking['BOOKING_STATUS'] == 'PENDING'): ?>
                        <p style="color: orange;"><strong>⏳ Waiting for renter payment...</strong></p>
                    
                    <?php elseif ($booking['BOOKING_STATUS'] == 'CONFIRMED'): ?>
                        <p style="color: blue;"><strong>✓ Payment confirmed! Renter will contact you.</strong></p>
                        
                        <!-- UC20 Step 7a: Mark as completed -->
                        <form method="POST" action="manage_booking.php" style="display: inline;">
                            <input type="hidden" name="bookingid" value="<?php echo $booking['BOOKINGID']; ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" onclick="return confirm('Confirm that car has been returned in good condition?')">
                                ✓ Mark as Completed (Release Payment)
                            </button>
                        </form>
                        
                        <!-- UC20 Alternative Flow 4: Emergency cancellation -->
                        <form method="POST" action="manage_booking.php" style="display: inline;">
                            <input type="hidden" name="bookingid" value="<?php echo $booking['BOOKINGID']; ?>">
                            <input type="hidden" name="action" value="cancel_emergency">
                            <label>Reason:</label>
                            <input type="text" name="cancel_reason" required>
                            <button type="submit" onclick="return confirm('Cancel this booking? Renter will get full refund.')">
                                ✗ Emergency Cancel
                            </button>
                        </form>
                    
                    <?php elseif ($booking['BOOKING_STATUS'] == 'COMPLETED'): ?>
                        <p style="color: green;"><strong>✓ Completed! Payment released.</strong></p>
                        <p>Your earnings: RM <?php echo number_format($booking['TOTAL_AMOUNT'] * 0.90, 2); ?> (90%)</p>
                    
                    <?php elseif ($booking['BOOKING_STATUS'] == 'CANCELLED'): ?>
                        <p style="color: red;"><strong>✗ Cancelled</strong></p>
                    <?php endif; ?>
                    
                    <a href="booking_details.php?bookingid=<?php echo $booking['BOOKINGID']; ?>">
                        View Full Details
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <hr>
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>