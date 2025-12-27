<?php
/**
 * Admin - Cancel Booking (Override)
 * Admin can cancel any booking for issue resolution
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_test.php'; // your PHPMailer config

// Check if admin is logged in
require_login('admin');

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($booking_id)) {
    set_flash_message("Invalid booking ID.", "error");
    header("Location: manage_bookings.php");
    exit();
}

// Get booking details
$query = "SELECT B.*, 
          C.BRAND, C.MODEL, C.CARID,
          R.NAME as RENTER_NAME, R.EMAIL as RENTER_EMAIL,
          L.NAME as LESSOR_NAME, L.EMAIL as LESSOR_EMAIL
          FROM BOOKING B
          JOIN CAR C ON B.CARID = C.CARID
          JOIN RENTER R ON B.RENTERID = R.RENTERID
          JOIN LESSOR L ON C.LESSORID = L.LESSORID
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

// Check if already cancelled or completed
$current_status = strtoupper($booking['BOOKING_STATUS']);
if ($current_status == 'CANCELLED' || $current_status == 'COMPLETED') {
    set_flash_message("This booking is already " . $booking['BOOKING_STATUS'] . ".", "error");
    header("Location: booking_details_admin.php?id=" . $booking_id);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason = trim($_POST['reason']);
    
    if (empty($reason)) {
        set_flash_message("Please provide a reason for cancellation.", "error");
    } else {
        try {
            // Update booking status to CANCELLED
            $update_query = "UPDATE BOOKING 
                           SET BOOKING_STATUS = 'CANCELLED'
                           WHERE BOOKINGID = :booking_id";
            
            $update_stmt = oci_parse($conn, $update_query);
            oci_bind_by_name($update_stmt, ':booking_id', $booking_id);
            
            if (!oci_execute($update_stmt, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($update_stmt);
                throw new Exception("Failed to cancel booking: " . $e['message']);
            }
            
            // Update car availability back to AVAILABLE
            $car_update = "UPDATE CAR 
                          SET AVAILABILITY_STATUS = 'AVAILABLE'
                          WHERE CARID = :car_id";
            
            $car_stmt = oci_parse($conn, $car_update);
            $car_id = $booking['CARID'];
            oci_bind_by_name($car_stmt, ':car_id', $car_id);
            
            if (!oci_execute($car_stmt, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($car_stmt);
                throw new Exception("Failed to update car availability: " . $e['message']);
            }
            
            // Commit transaction
            oci_commit($conn);
            
            // ================= SEND EMAIL NOTIFICATIONS =================

// Common booking details
$carName = $booking['BRAND'] . ' ' . $booking['MODEL'];
$period = date('M d, Y', strtotime($booking['START_DATE'])) . 
          ' - ' . 
          date('M d, Y', strtotime($booking['END_DATE']));

// -------- Email to RENTER --------
$renter_subject = "Booking #$booking_id Has Been Cancelled by Admin";
$renter_body = "
    <p>Hello {$booking['RENTER_NAME']},</p>
    <p>We regret to inform you that your booking has been <strong>cancelled by the system administrator</strong>.</p>
    <p><strong>Booking Details:</strong></p>
    <ul>
        <li>Booking ID: <strong>$booking_id</strong></li>
        <li>Car: <strong>$carName</strong></li>
        <li>Rental Period: <strong>$period</strong></li>
    </ul>
    <p><strong>Reason Provided by Admin:</strong><br>" . htmlspecialchars($reason) . "</p>
    <p>If you have any questions, please contact support.</p>
    <p>Thank you,<br>Car Rental Platform Team</p>
";

send_email($booking['RENTER_EMAIL'], $renter_subject, $renter_body);


// -------- Email to LESSOR --------
$lessor_subject = "Your Car Booking #$booking_id Was Cancelled by Admin";
$lessor_body = "
    <p>Hello {$booking['LESSOR_NAME']},</p>
    <p>This is to notify you that a booking for your vehicle has been <strong>cancelled by the administrator</strong>.</p>
    <p><strong>Booking Details:</strong></p>
    <ul>
        <li>Booking ID: <strong>$booking_id</strong></li>
        <li>Car: <strong>$carName</strong></li>
        <li>Rental Period: <strong>$period</strong></li>
        <li>Renter: <strong>{$booking['RENTER_NAME']}</strong></li>
    </ul>
    <p><strong>Reason Provided by Admin:</strong><br>" . htmlspecialchars($reason) . "</p>
    <p>Your car has been set back to <strong>AVAILABLE</strong>.</p>
    <p>Thank you,<br>Car Rental Platform Team</p>
";

send_email($booking['LESSOR_EMAIL'], $lessor_subject, $lessor_body);


            set_flash_message("Booking #" . $booking_id . " has been cancelled successfully. Reason: " . htmlspecialchars($reason), "success");
            header("Location: manage_bookings.php");
            exit();
            
        } catch (Exception $e) {
            oci_rollback($conn);
            set_flash_message("Failed to cancel booking: " . $e->getMessage(), "error");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancel Booking - Admin</title>
</head>
<body>
    <h1>Cancel Booking (Admin Override)</h1>
    
    <p><a href="booking_details_admin.php?id=<?php echo $booking_id; ?>">← Back to Booking Details</a></p>
    
    <?php display_flash_message(); ?>
    
    <h2>⚠️ Warning: Admin Override Action</h2>
    <p>You are about to cancel this booking using admin privileges. This action cannot be undone.</p>
    
    <h3>Booking Information</h3>
    <table border="1">
        <tr>
            <th>Booking ID:</th>
            <td><?php echo htmlspecialchars($booking['BOOKINGID']); ?></td>
        </tr>
        <tr>
            <th>Car:</th>
            <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></td>
        </tr>
        <tr>
            <th>Renter:</th>
            <td><?php echo htmlspecialchars($booking['RENTER_NAME']); ?></td>
        </tr>
        <tr>
            <th>Lessor:</th>
            <td><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></td>
        </tr>
        <tr>
            <th>Rental Period:</th>
            <td>
                <?php 
                echo date('M d, Y', strtotime($booking['START_DATE'])) . ' - ' . 
                     date('M d, Y', strtotime($booking['END_DATE'])); 
                ?>
            </td>
        </tr>
        <tr>
            <th>Total Price:</th>
            <td>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
        </tr>
        <tr>
            <th>Current Status:</th>
            <td><?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <form method="POST">
        <label for="reason"><strong>Cancellation Reason (Required): *</strong></label><br>
        <textarea id="reason" name="reason" rows="5" cols="60" required 
                  placeholder="Enter the reason for cancelling this booking (e.g., system issue, payment dispute, user request, etc.)"></textarea>
        <br><br>
        
        <button type="submit" onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
            Confirm Cancellation
        </button>
        <a href="booking_details_admin.php?id=<?php echo $booking_id; ?>">Cancel</a>
    </form>
    
    <hr>
    <p><a href="manage_bookings.php">← Back to Manage Bookings</a></p>
</body>
</html>