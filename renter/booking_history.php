<?php
/**
 * UC11: Booking History (Full Version)
 * Display all bookings with filters and actions
 * Styled: Royal Blue Design System
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();
$user_name = get_user_name(); // Needed for Nav

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        /* --- Royal Blue Design System --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Navigation handled by renter-dashboard.css */

        /* Page Header */
        .page-header {
            background-color: #1864FF;
            color: white;
            padding: 40px 20px 80px 20px; /* Extra padding for overlap */
            text-align: center;
        }
        .page-header h1 { font-size: 32px; font-weight: 700; margin-bottom: 10px; }
        .page-header p { color: rgba(255, 255, 255, 0.8); }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: -50px auto 40px auto; /* Negative margin for overlap */
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        /* --- Statistics Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            border-bottom: 3px solid transparent;
        }

        /* Color Coding Stats */
        .stat-card.total { border-color: #6c757d; }
        .stat-card.pending { border-color: #f59e0b; }
        .stat-card.confirmed { border-color: #1864FF; }
        .stat-card.completed { border-color: #10b981; }
        .stat-card.cancelled { border-color: #ef4444; }

        .stat-number { font-size: 36px; font-weight: 800; line-height: 1.2; margin-bottom: 5px; }
        .stat-label { font-size: 14px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        /* --- Filters --- */
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .filter-btn {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            background: #f1f5f9;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .filter-btn:hover { background: #e2e8f0; color: #333; }
        .filter-btn.active { background: #1864FF; color: white; box-shadow: 0 2px 5px rgba(24, 100, 255, 0.3); }

        /* --- Booking Card --- */
        .booking-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
            display: flex;
            border: 1px solid #eee;
            transition: transform 0.2s;
        }

        .booking-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }

        .booking-image-col { width: 280px; position: relative; }
        .booking-image { width: 100%; height: 100%; object-fit: cover; }

        .booking-content-col { flex: 1; padding: 25px; }
        
        .booking-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
        
        .car-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .booking-id { font-size: 13px; color: #94a3b8; }

        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #fffbeb; color: #b45309; }
        .status-confirmed { background: #eff6ff; color: #1d4ed8; }
        .status-completed { background: #ecfdf5; color: #047857; }
        .status-cancelled { background: #fef2f2; color: #b91c1c; }

        .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        .detail-item label { display: block; font-size: 12px; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
        .detail-item span { font-size: 14px; color: #333; font-weight: 500; }
        
        .price-tag { font-size: 18px; font-weight: 700; color: #1864FF; }

        .booking-actions-col {
            width: 220px;
            padding: 25px;
            background: #f8fafc;
            border-left: 1px solid #eee;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
        }

        /* Buttons */
        .btn { display: block; width: 100%; padding: 10px; text-align: center; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
        
        .btn-primary { background: #1864FF; color: white; box-shadow: 0 2px 4px rgba(24,100,255,0.2); }
        .btn-primary:hover { background: #0056e0; transform: translateY(-1px); }
        
        .btn-outline { background: white; color: #333; border: 1px solid #cbd5e1; }
        .btn-outline:hover { border-color: #333; background: #333; color: white; }

        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }

        .btn-danger { background: white; color: #ef4444; border: 1px solid #ef4444; }
        .btn-danger:hover { background: #ef4444; color: white; }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .empty-icon { font-size: 60px; color: #cbd5e1; margin-bottom: 20px; }
        
        /* Responsive */
        @media (max-width: 900px) {
            .booking-card { flex-direction: column; }
            .booking-image-col { width: 100%; height: 200px; }
            .booking-actions-col { width: 100%; border-left: none; border-top: 1px solid #eee; flex-direction: row; }
        }
        @media (max-width: 600px) {
            .booking-actions-col { flex-direction: column; }
            .details-grid { grid-template-columns: 1fr; }
            .nav-links { display: none; } /* Hide extra nav links on mobile if needed */
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php" style="text-decoration:none; color:white;">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="booking_history.php" class="active">My Bookings</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <h1>My Bookings</h1>
        <p>Track your rental history and manage upcoming trips</p>
    </div>

    <div class="container">
        
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['TOTAL']; ?></div>
                <div class="stat-label">All Bookings</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number" style="color: #d97706;"><?php echo $stats['PENDING']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card confirmed">
                <div class="stat-number" style="color: #1864FF;"><?php echo $stats['CONFIRMED']; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number" style="color: #10b981;"><?php echo $stats['COMPLETED']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-number" style="color: #ef4444;"><?php echo $stats['CANCELLED']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <?php if(function_exists('display_flash_message')) display_flash_message(); ?>

        <div class="filters-container">
            <h3 style="font-size: 16px; color:#64748b;">Filter Bookings:</h3>
            <div class="filter-buttons">
                <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?status=confirmed" class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                <a href="?status=completed" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                <a href="?status=cancelled" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                <h2 style="color: #333; margin-bottom: 10px;">No bookings found</h2>
                <p style="color: #64748b; margin-bottom: 25px;">
                    <?php 
                    if ($status_filter == 'all') echo "You haven't made any bookings yet.";
                    else echo "No bookings found with status: " . htmlspecialchars(ucfirst($status_filter));
                    ?>
                </p>
                <a href="search_cars.php" class="btn btn-primary" style="display:inline-block; width:auto; padding: 12px 30px;">
                    Find a Car to Rent
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-image-col">
                        <?php if ($booking['PHOTO1_PATH']): ?>
                            <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" alt="Car" class="booking-image">
                        <?php else: ?>
                            <div style="width:100%; height:100%; background:#eee; display:flex; align-items:center; justify-content:center; color:#999;">No Image</div>
                        <?php endif; ?>
                    </div>

                    <div class="booking-content-col">
                        <div class="booking-header">
                            <div>
                                <div class="car-title"><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></div>
                                <div class="booking-id">Booking ID: #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></div>
                            </div>
                            <div class="status-badge status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                                <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                            </div>
                        </div>

                        <div class="details-grid">
                            <div class="detail-item">
                                <label>License Plate</label>
                                <span><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Total Amount</label>
                                <span class="price-tag">RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Dates</label>
                                <span><?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' - ' . $booking['END_DATE_FMT']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Lessor</label>
                                <span><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                             <label>Booked On</label>
                             <span style="font-size: 13px; color: #64748b;"><?php echo date('d M Y, h:i A', strtotime($booking['CREATED_AT'])); ?></span>
                        </div>
                    </div>

                    <div class="booking-actions-col">
                        <a href="booking_details.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i> View Details
                        </a>

                        <?php if ($booking['BOOKING_STATUS'] == 'PENDING'): ?>
                            <a href="make_payment.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" class="btn btn-primary">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                            <button onclick="cancelBooking(<?php echo $booking['BOOKINGID']; ?>)" class="btn btn-danger">
                                <i class="fas fa-times"></i> Cancel
                            </button>

                        <?php elseif ($booking['BOOKING_STATUS'] == 'COMPLETED'): ?>
                            <?php if ($booking['HAS_REVIEW'] == 0): ?>
                                <a href="submit_review.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" class="btn btn-success">
                                    <i class="fas fa-star"></i> Review
                                </a>
                            <?php else: ?>
                                <div style="text-align:center; font-size:12px; color:#10b981; font-weight:bold;">
                                    <i class="fas fa-check-circle"></i> Reviewed
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function cancelBooking(bookingId) {
        if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
            window.location.href = 'cancel_booking.php?id=' + bookingId;
        }
    }
    </script>
</body>
</html>