<?php
/**
 * Admin - View Booking Details
 * View complete booking information with renter and lessor details
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

check_authentication('admin');

// Use the global $conn from database.php
global $conn;

$booking_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($booking_id)) {
    header("Location: manage_bookings.php");
    exit();
}

// Get booking details
$query = "SELECT 
            B.*,
            C.CARID,
            C.BRAND,
            C.MODEL,
            C.YEAR,
            C.LICENSE_PLATE,
            C.DAILY_RATE,
            C.TRANSMISSION,
            C.FUEL_TYPE,
            C.SEATS,
            R.RENTERID,
            R.NAME as RENTER_NAME,
            R.EMAIL as RENTER_EMAIL,
            R.PHONE as RENTER_PHONE,
            L.LESSORID,
            L.NAME as LESSOR_NAME,
            L.EMAIL as LESSOR_EMAIL,
            L.PHONE as LESSOR_PHONE,
            P.PAYMENTID,
            P.PAYMENT_METHOD,
            P.PAYMENT_STATUS,
            P.PAYMENT_DATE
          FROM BOOKING B
          JOIN CAR C ON B.CARID = C.CARID
          JOIN RENTER R ON B.RENTERID = R.RENTERID
          JOIN LESSOR L ON C.LESSORID = L.LESSORID
          LEFT JOIN PAYMENT P ON B.BOOKINGID = P.BOOKINGID
          WHERE B.BOOKINGID = :booking_id";

$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':booking_id', $booking_id);
oci_execute($stmt);

$booking = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$booking) {
    header("Location: manage_bookings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/booking_details_admin.css?v=<?php echo time(); ?>">
</head>
<body>

    <aside class="sidebar">
        <h4>
            Royal Blue
            <span>Admin Control Panel</span>
        </h4>
        
        <a href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <a href="verify_lessors.php">
            <i class="fas fa-user-check"></i> <span>Verify Lessors</span>
        </a>
        
        <a href="manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php" class="active">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php">
            <i class="fas fa-headset"></i> <span>Support</span>
        </a>
        
        <a href="reports.php">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-file-invoice"></i> Booking #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></h2>
            <span class="status-badge status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
            </span>
            
            <?php 
            // Check if this cancelled booking needs refund
            $needs_refund = (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && 
                           !empty($booking['PAYMENTID']) && 
                           strtoupper($booking['PAYMENT_STATUS']) == 'SUCCESS');
            
            if ($needs_refund): 
            ?>
                <div class="alert alert-danger">
                    <h3><i class="fas fa-exclamation-triangle"></i> REFUND REQUIRED</h3>
                    <p>
                        This booking was cancelled AFTER payment was made. 
                        <strong>Refund amount: RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong>
                    </p>
                    <p style="margin-top: 10px; font-size: 14px;">
                        Please process the refund to: <?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?>
                    </p>
                </div>
            <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && empty($booking['PAYMENTID'])): ?>
                <div class="alert alert-info">
                    <p>
                        <i class="fas fa-info-circle"></i> This booking was cancelled BEFORE payment. No refund required.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
            <!-- Booking Information -->
            <div class="detail-card">
                <h3>Booking Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Booking ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['BOOKINGID']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Start Date</span>
                    <span class="detail-value"><?php echo date('M d, Y', strtotime($booking['START_DATE'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">End Date</span>
                    <span class="detail-value"><?php echo date('M d, Y', strtotime($booking['END_DATE'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Price</span>
                    <span class="detail-value">RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($booking['CREATED_AT'])); ?></span>
                </div>
            </div>

            <!-- Car Information -->
            <div class="card">
                <h2>Car Information</h2>
                <div class="detail-row">
                    <span class="detail-label">Car</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Year</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['YEAR']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Plate Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Transmission</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['TRANSMISSION']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fuel Type</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['FUEL_TYPE']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Price/Day</span>
                    <span class="detail-value">RM <?php echo number_format($booking['DAILY_RATE'], 2); ?></span>
                </div>
            </div>

            <!-- Renter Information -->
            <div class="card">
                <h2>Renter Information</h2>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">
                        <a href="view_user.php?type=renter&id=<?php echo $booking['RENTERID']; ?>" class="user-link">
                            <?php echo htmlspecialchars($booking['RENTER_NAME']); ?>
                        </a>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['RENTER_PHONE']); ?></span>
                </div>
            </div>

            <!-- Lessor Information -->
            <div class="card">
                <h2>Lessor Information</h2>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">
                        <a href="view_user.php?type=lessor&id=<?php echo $booking['LESSORID']; ?>" class="user-link">
                            <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?>
                        </a>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['LESSOR_PHONE']); ?></span>
                </div>
            </div>

            <!-- Payment Information -->
            <?php if ($booking['PAYMENTID']): ?>
            <div class="card full-width">
                <h2>Payment Information</h2>
                <?php if ($needs_refund): ?>
                <div style="background-color: #fee2e2; padding: 10px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #dc2626;">
                    <strong style="color: #991b1b;">⚠️ REFUND NEEDED: RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong>
                </div>
                <?php endif; ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="detail-row">
                        <span class="detail-label">Payment ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['PAYMENTID']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Method</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['PAYMENT_METHOD']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['PAYMENT_STATUS']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value"><?php echo $booking['PAYMENT_DATE'] ? date('M d, Y H:i', strtotime($booking['PAYMENT_DATE'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card full-width">
                <h2>Payment Information</h2>
                <div style="background-color: #f3f4f6; padding: 15px; border-radius: 4px; text-align: center;">
                    <p style="color: #6b7280; margin: 0;">No payment recorded for this booking.</p>
                    <?php if (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED'): ?>
                    <p style="color: #059669; margin-top: 10px; font-weight: 600;">✓ Booking cancelled before payment - No refund required</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            

            


            <div style="margin-top: 20px;">
                <a href="manage_bookings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</body>
</html>