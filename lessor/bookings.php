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

// Pagination settings
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// UC20 Step 2: Retrieve all bookings for lessor's cars (Count for pagination)
$count_sql = "SELECT COUNT(*) as TOTAL
              FROM BOOKING b
              JOIN CAR c ON b.CARID = c.CARID
              WHERE c.LESSORID = :lessorid";

if ($status_filter != 'all') {
    $count_sql .= " AND b.BOOKING_STATUS = :status";
}

$stmt_count = oci_parse($conn, $count_sql);
oci_bind_by_name($stmt_count, ':lessorid', $lessorid);
if ($status_filter != 'all') {
    $status_upper = strtoupper($status_filter);
    oci_bind_by_name($stmt_count, ':status', $status_upper);
}
oci_execute($stmt_count);
$row_count = oci_fetch_assoc($stmt_count);
$total_items = $row_count['TOTAL'];
$total_pages = ceil($total_items / $limit);

// Main Query with Pagination
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

if ($status_filter != 'all') {
    $sql .= " AND b.BOOKING_STATUS = :status";
}

$sql .= " ORDER BY b.CREATED_AT DESC 
          OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':lessorid', $lessorid);
oci_bind_by_name($stmt, ':offset', $offset);
oci_bind_by_name($stmt, ':limit', $limit);

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
    <link rel="stylesheet" href="../assets/css/booking-management.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2>Car Rental System <span style="font-size:14px; font-weight:400; opacity:0.8;">| Lessor Panel</span></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars(get_user_name()); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_cars.php">My Cars</a>
                <a href="bookings.php" class="active">Bookings</a>
                <a href="earnings.php">Earnings</a>
                <a href="profile.php">Profile</a>
                <a href="submit_ticket.php">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="page-header">
        <h1>Manage Bookings</h1>
        <p>View and manage all booking requests for your vehicles</p>
    </div>
    
    <div class="content-container">
        <?php display_flash_message(); ?>
        
        <!-- Booking Statistics Card -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <h2 style="margin-top: 0;">Booking Statistics</h2>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">Note: Total Earned = Your actual earnings after 10% platform commission</p>
            <table border="1" class="stats-table">
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
        </div>
        
        <!-- Filter Bookings -->
        <h2>Filter Bookings</h2>
        <div class="filter-bar">
            <a href="?status=all" class="<?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=pending" class="<?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=confirmed" class="<?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?status=completed" class="<?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?status=cancelled" class="<?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>
        <p style="margin: 15px 0;">Showing: <strong><?php echo ucfirst($status_filter); ?></strong></p>
        
        <!-- All Bookings -->
        <h2>All Bookings</h2>
    
    <?php if (empty($bookings)): ?>
        <p>No bookings found.</p>
    <?php else: ?>
        <?php foreach ($bookings as $booking): 
            // Determine status class
            $status_class = 'status-' . strtolower($booking['BOOKING_STATUS']);
        ?>
            <div class="booking-card">
                <h3>Booking #<?php echo $booking['BOOKINGID']; ?> - 
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $booking['BOOKING_STATUS']; ?>
                    </span>
                </h3>
                
                <table border="1" style="width: 100%; margin: 10px 0;">
                    <tr>
                        <th colspan="2" class="section-header">Car Details</th>
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
                        <th colspan="2" class="section-header">Renter Details</th>
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
                        <th colspan="2" class="section-header">Booking Details</th>
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

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page - 1; ?>" class="page-link">&laquo; Previous</a>
            <?php else: ?>
                <span class="page-link disabled">&laquo; Previous</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>" 
                   class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page + 1; ?>" class="page-link">Next &raquo;</a>
            <?php else: ?>
                <span class="page-link disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    
    
    </div>
</body>
</html>