<?php
/**
 * UC11: Booking History (Full Version)
 * Display all bookings with filters and actions
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();

// Get filter from URL (default: all)
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build SQL query based on filter
$sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT, b.CREATED_AT,
        c.BRAND, c.MODEL, c.YEAR, c.PHOTO1_PATH, c.LICENSE_PLATE,
        l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
        (SELECT COUNT(*) FROM REVIEW WHERE BOOKINGID = b.BOOKINGID) as HAS_REVIEW,
        TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
        TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT
        FROM BOOKING b
        JOIN CAR c ON b.CARID = c.CARID
        JOIN LESSOR l ON c.LESSORID = l.LESSORID
        WHERE b.RENTERID = :renterid";

// Add status filter
if ($status_filter != 'all') {
    $sql .= " AND b.BOOKING_STATUS = :status";
}

$sql .= " ORDER BY b.CREATED_AT DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':renterid', $renterid);

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
$stats_sql = "SELECT 
              COUNT(*) as TOTAL,
              COUNT(CASE WHEN BOOKING_STATUS = 'PENDING' THEN 1 END) as PENDING,
              COUNT(CASE WHEN BOOKING_STATUS = 'CONFIRMED' THEN 1 END) as CONFIRMED,
              COUNT(CASE WHEN BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED,
              COUNT(CASE WHEN BOOKING_STATUS = 'CANCELLED' THEN 1 END) as CANCELLED
              FROM BOOKING WHERE RENTERID = :renterid";

$stmt_stats = oci_parse($conn, $stats_sql);
oci_bind_by_name($stmt_stats, ':renterid', $renterid);
oci_execute($stmt_stats);
$stats = oci_fetch_assoc($stmt_stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Car Rental System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2196F3;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        .filter-btn:hover {
            border-color: #2196F3;
            color: #2196F3;
        }
        .filter-btn.active {
            background: #2196F3;
            border-color: #2196F3;
            color: white;
        }
        .booking-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .booking-id {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .booking-content {
            display: flex;
            gap: 20px;
        }
        .booking-image {
            width: 250px;
            height: 180px;
            object-fit: cover;
            border-radius: 5px;
        }
        .booking-details {
            flex: 1;
        }
        .booking-details h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-label {
            width: 150px;
            color: #666;
            font-weight: bold;
        }
        .detail-value {
            color: #333;
        }
        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            text-align: center;
        }
        .btn-review {
            background: #4CAF50;
            color: white;
        }
        .btn-review:hover {
            background: #45a049;
        }
        .btn-details {
            background: #2196F3;
            color: white;
        }
        .btn-details:hover {
            background: #0b7dda;
        }
        .btn-cancel {
            background: #f44336;
            color: white;
        }
        .btn-cancel:hover {
            background: #da190b;
        }
        .empty-state {
            background: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .nav-links {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav-links a {
            color: #2196F3;
            text-decoration: none;
            margin-right: 20px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <div class="nav-links">
            <a href="dashboard.php">← Dashboard</a>
            <a href="search_cars.php">Search Cars</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
        
        <h1>📚 My Bookings</h1>
        
        <?php display_flash_message(); ?>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['TOTAL']; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #FF9800;"><?php echo $stats['PENDING']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #2196F3;"><?php echo $stats['CONFIRMED']; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #4CAF50;"><?php echo $stats['COMPLETED']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f44336;"><?php echo $stats['CANCELLED']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <h3 style="margin-bottom: 15px;">Filter by Status:</h3>
            <div class="filter-buttons">
                <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    All Bookings
                </a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=confirmed" class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
                    Confirmed
                </a>
                <a href="?status=completed" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                    Completed
                </a>
                <a href="?status=cancelled" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                    Cancelled
                </a>
            </div>
        </div>
        
        <!-- Bookings List -->
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h2>No bookings found</h2>
                <p style="color: #666; margin: 15px 0;">
                    <?php 
                    if ($status_filter == 'all') {
                        echo "You haven't made any bookings yet.";
                    } else {
                        echo "No bookings with status: " . htmlspecialchars($status_filter);
                    }
                    ?>
                </p>
                <a href="search_cars.php" class="btn btn-details" style="margin-top: 20px;">
                    Search Cars
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-id">
                            Booking #<?php echo htmlspecialchars($booking['BOOKINGID']); ?>
                        </div>
                        <div class="status-badge status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                            <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                        </div>
                    </div>
                    
                    <div class="booking-content">
                        <?php if ($booking['PHOTO1_PATH']): ?>
                            <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                                 alt="Car" class="booking-image">
                        <?php endif; ?>
                        
                        <div class="booking-details">
                            <h3><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></h3>
                            
                            <div class="detail-row">
                                <div class="detail-label">License Plate:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Rental Period:</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' to ' . $booking['END_DATE_FMT']); ?>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Owner:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Total Amount:</div>
                                <div class="detail-value" style="font-weight: bold; color: #4CAF50;">
                                    RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Booked On:</div>
                                <div class="detail-value">
                                    <?php echo date('d M Y, h:i A', strtotime($booking['CREATED_AT'])); ?>
                                </div>
                            </div>
                            
                            <!-- Actions based on booking status -->
                            <div class="booking-actions">
                                <?php if ($booking['BOOKING_STATUS'] == 'PENDING'): ?>
                                    <a href="make_payment.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" 
                                       class="btn btn-review">
                                        💳 Complete Payment
                                    </a>
                                    <a href="#" onclick="cancelBooking(<?php echo $booking['BOOKINGID']; ?>)" 
                                       class="btn btn-cancel">
                                        ❌ Cancel Booking
                                    </a>
                                
                                <?php elseif ($booking['BOOKING_STATUS'] == 'CONFIRMED'): ?>
                                    <span style="color: #4CAF50; font-weight: bold;">
                                        ✓ Payment Confirmed - Owner will contact you soon
                                    </span>
                                
                                <?php elseif ($booking['BOOKING_STATUS'] == 'COMPLETED'): ?>
                                    <?php if ($booking['HAS_REVIEW'] == 0): ?>
                                        <!-- UC6: Leave Review Button -->
                                        <a href="submit_review.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" 
                                           class="btn btn-review">
                                            ⭐ Leave a Review
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #4CAF50; font-weight: bold;">
                                            ✓ Review Submitted
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="booking_details.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" 
                                   class="btn btn-details">
                                    👁️ View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
    function cancelBooking(bookingId) {
        if (confirm('Are you sure you want to cancel this booking?')) {
            
             window.location.href = 'cancel_booking.php?id=' + bookingId;
        }
    }
    </script>
</body>
</html>