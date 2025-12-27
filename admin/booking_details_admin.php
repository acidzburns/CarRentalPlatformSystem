<?php
/**
 * Admin - View Booking Details
 * View complete booking information with renter and lessor details
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($booking_id)) {
    set_flash_message("Invalid booking ID.", "error");
    header("Location: manage_bookings.php");
    exit();
}

// Get booking details (only columns that exist in your database)
$query = "SELECT 
            B.BOOKINGID,
            B.RENTERID,
            B.CARID,
            B.START_DATE,
            B.END_DATE,
            B.BOOKING_STATUS,
            B.TOTAL_AMOUNT,
            B.CREATED_AT,
            C.BRAND,
            C.MODEL,
            C.YEAR,
            C.LICENSE_PLATE,
            C.DAILY_RATE,
            C.TRANSMISSION,
            C.FUEL_TYPE,
            C.SEATS,
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

if (!$booking) {
    set_flash_message("Booking not found.", "error");
    header("Location: manage_bookings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Details #<?php echo $booking['BOOKINGID']; ?> - Admin</title>
</head>
<body>
    <h1>Booking Details</h1>
    
    <p><a href="manage_bookings.php">← Back to Manage Bookings</a></p>
    
    <?php display_flash_message(); ?>
    
    <hr>
    
    <h2>Booking #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></h2>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?></p>
    
    <hr>
    
    <!-- Booking Information -->
    <h3>📅 Booking Information</h3>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Booking ID:</th>
            <td><?php echo htmlspecialchars($booking['BOOKINGID']); ?></td>
        </tr>
        <tr>
            <th>Start Date:</th>
            <td><?php echo date('M d, Y', strtotime($booking['START_DATE'])); ?></td>
        </tr>
        <tr>
            <th>End Date:</th>
            <td><?php echo date('M d, Y', strtotime($booking['END_DATE'])); ?></td>
        </tr>
        <tr>
            <th>Total Price:</th>
            <td>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
        </tr>
        <tr>
            <th>Created:</th>
            <td><?php echo date('M d, Y H:i', strtotime($booking['CREATED_AT'])); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Car Information -->
    <h3>🚗 Car Information</h3>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Car:</th>
            <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></td>
        </tr>
        <tr>
            <th>Year:</th>
            <td><?php echo htmlspecialchars($booking['YEAR']); ?></td>
        </tr>
        <tr>
            <th>Plate Number:</th>
            <td><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></td>
        </tr>
        <tr>
            <th>Transmission:</th>
            <td><?php echo htmlspecialchars($booking['TRANSMISSION']); ?></td>
        </tr>
        <tr>
            <th>Fuel Type:</th>
            <td><?php echo htmlspecialchars($booking['FUEL_TYPE']); ?></td>
        </tr>
        <tr>
            <th>Seats:</th>
            <td><?php echo htmlspecialchars($booking['SEATS']); ?></td>
        </tr>
        <tr>
            <th>Price/Day:</th>
            <td>RM <?php echo number_format($booking['DAILY_RATE'], 2); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Renter Information -->
    <h3>👤 Renter Information</h3>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Name:</th>
            <td>
                <a href="view_user.php?type=renter&id=<?php echo $booking['RENTERID']; ?>">
                    <?php echo htmlspecialchars($booking['RENTER_NAME']); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th>Email:</th>
            <td><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></td>
        </tr>
        <tr>
            <th>Phone:</th>
            <td><?php echo htmlspecialchars($booking['RENTER_PHONE'] ?? 'N/A'); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Lessor Information -->
    <h3>🏢 Lessor Information</h3>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Name:</th>
            <td>
                <a href="view_user.php?type=lessor&id=<?php echo $booking['LESSORID']; ?>">
                    <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th>Email:</th>
            <td><?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?></td>
        </tr>
        <tr>
            <th>Phone:</th>
            <td><?php echo htmlspecialchars($booking['LESSOR_PHONE'] ?? 'N/A'); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Payment Information -->
    <?php if ($booking['PAYMENTID']): ?>
    <h3>💳 Payment Information</h3>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Payment ID:</th>
            <td><?php echo htmlspecialchars($booking['PAYMENTID']); ?></td>
        </tr>
        <tr>
            <th>Method:</th>
            <td><?php echo htmlspecialchars($booking['PAYMENT_METHOD']); ?></td>
        </tr>
        <tr>
            <th>Status:</th>
            <td><?php echo htmlspecialchars($booking['PAYMENT_STATUS']); ?></td>
        </tr>
        <tr>
            <th>Payment Date:</th>
            <td><?php echo $booking['PAYMENT_DATE'] ? date('M d, Y H:i', strtotime($booking['PAYMENT_DATE'])) : 'N/A'; ?></td>
        </tr>
    </table>
    <?php else: ?>
    <h3>💳 Payment Information</h3>
    <p>No payment recorded for this booking.</p>
    <?php endif; ?>
    
    <hr>
    
    <!-- Actions -->
    <?php 
    $current_status = strtoupper($booking['BOOKING_STATUS']);
    if ($current_status != 'CANCELLED' && $current_status != 'COMPLETED'): 
    ?>
    <h3>⚙️ Admin Actions</h3>
    <a href="cancel_booking_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" 
       onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
        <button type="button">🚫 Cancel Booking (Admin Override)</button>
    </a>
    <?php endif; ?>
    
    <hr>
    <p><a href="manage_bookings.php">← Back to Manage Bookings</a></p>
</body>
</html>