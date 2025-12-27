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
    <title>Booking Details - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .status {
            padding: 6px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }

        .status.pending { background-color: #fef3c7; color: #92400e; }
        .status.approved { background-color: #d1fae5; color: #065f46; }
        .status.ongoing { background-color: #dbeafe; color: #1e40af; }
        .status.completed { background-color: #e0e7ff; color: #3730a3; }
        .status.cancelled { background-color: #fee2e2; color: #991b1b; }
        .status.rejected { background-color: #fecaca; color: #7f1d1d; }

        .content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }

        .detail-value {
            color: #111827;
            font-weight: 600;
            text-align: right;
        }

        .user-link {
            color: #2563eb;
            text-decoration: none;
        }

        .user-link:hover {
            text-decoration: underline;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .full-width {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="manage_bookings.php" class="back-link">← Back to Bookings</a>
        
        <div class="header">
            <h1>Booking #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></h1>
            <span class="status <?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
            </span>
            
            <?php 
            // Check if this cancelled booking needs refund
            $needs_refund = (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && 
                           !empty($booking['PAYMENTID']) && 
                           strtoupper($booking['PAYMENT_STATUS']) == 'SUCCESS');
            
            if ($needs_refund): 
            ?>
                <div style="background-color: #fee2e2; border: 2px solid #dc2626; padding: 15px; margin-top: 20px; border-radius: 8px;">
                    <h3 style="color: #991b1b; margin-bottom: 10px;">⚠️ REFUND REQUIRED</h3>
                    <p style="color: #7f1d1d; margin: 0;">
                        This booking was cancelled AFTER payment was made. 
                        <strong>Refund amount: RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong>
                    </p>
                    <p style="color: #7f1d1d; margin-top: 10px; font-size: 14px;">
                        Please process the refund to: <?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?>
                    </p>
                </div>
            <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && empty($booking['PAYMENTID'])): ?>
                <div style="background-color: #e0f2fe; border: 2px solid #0284c7; padding: 15px; margin-top: 20px; border-radius: 8px;">
                    <p style="color: #075985; margin: 0;">
                        ℹ️ This booking was cancelled BEFORE payment. No refund required.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="content">
            <!-- Booking Information -->
            <div class="card">
                <h2>Booking Information</h2>
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

            <!-- Actions -->
            <?php if ($booking['BOOKING_STATUS'] != 'Cancelled' && $booking['BOOKING_STATUS'] != 'Completed'): ?>
            <div class="card full-width">
                <h2>Actions</h2>
                <div class="actions">
                    <a href="cancel_booking_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone and may require refund processing.');">
                        Cancel Booking (Admin Override)
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>