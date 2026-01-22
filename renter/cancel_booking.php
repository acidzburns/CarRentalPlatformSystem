<?php
/**
 * Cancel Booking - Backend Only
 * Allows renter to cancel their pending or confirmed bookings
 */

session_start();
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';
require_once '../config/email_test.php'; // Use existing email function

check_authentication('renter');

$renter_id = get_user_id();
$renter_name = get_user_name();

// Get booking ID
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id === 0) {
    $_SESSION['error'] = 'Invalid booking ID';
    header('Location: booking_history.php');
    exit;
}

// Fetch booking details
$booking_query = "SELECT b.*, c.BRAND, c.MODEL, c.LICENSE_PLATE,
                  TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_F,
                  TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_F,
                  TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_F,
                  l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
                  r.EMAIL as RENTER_EMAIL,
                  (SELECT PAYMENT_STATUS FROM PAYMENT WHERE BOOKINGID = b.BOOKINGID AND ROWNUM = 1) as PAYMENT_STATUS
                  FROM BOOKING b
                  INNER JOIN CAR c ON b.CARID = c.CARID
                  INNER JOIN LESSOR l ON c.LESSORID = l.LESSORID
                  INNER JOIN RENTER r ON b.RENTERID = r.RENTERID
                  WHERE b.BOOKINGID = :booking_id 
                  AND b.RENTERID = :renter_id";


$booking = db_fetch_one($booking_query, ['booking_id' => $booking_id, 'renter_id' => $renter_id]);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found or you do not have permission to cancel it';
    header('Location: booking_history.php');
    exit;
}

// Check if booking can be cancelled
$can_cancel = false;
$cancel_message = '';

if ($booking['BOOKING_STATUS'] === 'COMPLETED') {
    $cancel_message = 'Cannot cancel completed bookings';
} elseif ($booking['BOOKING_STATUS'] === 'CANCELLED') {
    $cancel_message = 'This booking is already cancelled';
} elseif ($booking['BOOKING_STATUS'] === 'REJECTED') {
    $cancel_message = 'This booking has been rejected';
} elseif ($booking['BOOKING_STATUS'] === 'ONGOING') {
    $cancel_message = 'Cannot cancel ongoing bookings. Please contact support';
} else {
    $start_timestamp = strtotime($booking['START_DATE']);
    $current_timestamp = time();
    $hours_until_start = ($start_timestamp - $current_timestamp) / 3600;
    
    if ($hours_until_start < 24) {
        $cancel_message = 'Cannot cancel bookings within 24 hours of start time. Please contact support for assistance';
    } else {
        $can_cancel = true;
    }
}

// Process cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    if (!$can_cancel) {
        $_SESSION['error'] = 'This booking cannot be cancelled';
        header('Location: booking_history.php');
        exit;
    }
    
    $cancellation_reason = trim($_POST['reason'] ?? '');
    
    if (empty($cancellation_reason)) {
        $_SESSION['error'] = 'Please provide a reason for cancellation';
    } else {
        // Update booking status to CANCELLED
        $update_booking_query = "UPDATE BOOKING 
                                SET BOOKING_STATUS = 'CANCELLED' 
                                WHERE BOOKINGID = :booking_id";
        
        if (db_execute($update_booking_query, ['booking_id' => $booking_id])) {
            // Handle refund message
            if ($booking['PAYMENT_STATUS'] === 'SUCCESS') {
                $refund_amount = $booking['TOTAL_AMOUNT'];
                $_SESSION['success'] = 'Booking cancelled successfully. Refund of RM ' . 
                                      number_format($refund_amount, 2) . 
                                      ' will be processed within 5-7 business days.';
            } else {
                $_SESSION['success'] = 'Booking cancelled successfully. No payment was made.';
            }
            
            // Send email to renter confirming cancellation using existing function
            $subject = "Booking Cancellation Confirmation - BK-{$booking_id}";
            $body = "
            <p>Dear {$renter_name},</p>
            <p>Your booking <strong>BK-{$booking_id}</strong> for the car <strong>{$booking['BRAND']} {$booking['MODEL']}</strong> (License: {$booking['LICENSE_PLATE']}) has been successfully cancelled.</p>
            <p><strong>Booking Period:</strong> {$booking['START_DATE_F']} to {$booking['END_DATE_F']}</p>
            <p><strong>Reason for Cancellation:</strong> {$cancellation_reason}</p>";
            if ($booking['PAYMENT_STATUS'] === 'SUCCESS') {
                $body .= "<p>A refund of <strong>RM " . number_format($booking['TOTAL_AMOUNT'], 2) . "</strong> will be processed within 5-7 business days.</p>";
            } else {
                $body .= "<p>No payment was made for this booking.</p>";
            }
            $body .= "<p>Thank you for using our Car Rental Platform!</p>";

            if (!send_email($booking['RENTER_EMAIL'], $subject, $body)) {
                error_log("Cancellation email to renter ({$booking['RENTER_EMAIL']}) failed.");
            }

            header('Location: booking_history.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to cancel booking. Please try again or contact support.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - Renter</title>
    <link rel="stylesheet" href="../assets/css/cancel-booking.css">
</head>
<body>
    <nav>
        <div>
            <h2>Car Rental System</h2>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($renter_name); ?>!</span>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <div class="hero-section">
            <h1>Cancel Booking</h1>
            <p>Review your booking details and confirm cancellation if needed</p>
        </div>
        
        <div class="content-container">

<?php if (isset($_SESSION['error'])): ?>
    <div style="background: #ffcccc; padding: 10px; margin: 10px 0; border: 1px solid #cc0000;">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<h2>Booking Details</h2>
<table border="1" cellpadding="10" style="border-collapse: collapse;">
    <tr>
        <th>Booking Reference:</th>
        <td><strong>BK-<?php echo $booking_id; ?></strong></td>
    </tr>
    <tr>
        <th>Car:</th>
        <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></td>
    </tr>
    <tr>
        <th>License Plate:</th>
        <td><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></td>
    </tr>
    <tr>
        <th>Rental Period:</th>
        <td><?php echo $booking['START_DATE_F']; ?> to <?php echo $booking['END_DATE_F']; ?></td>
    </tr>
    <tr>
        <th>Total Amount:</th>
        <td><strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong></td>
    </tr>
    <tr>
        <th>Status:</th>
        <td><?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?></td>
    </tr>
    <tr>
        <th>Payment Status:</th>
        <td><?php echo $booking['PAYMENT_STATUS'] ?? 'Not Paid'; ?></td>
    </tr>
    <tr>
        <th>Booked On:</th>
        <td><?php echo $booking['CREATED_AT_F']; ?></td>
    </tr>
</table>

<hr>

<?php if ($can_cancel): ?>
    <h2>⚠ Confirm Cancellation</h2>
    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
        <input type="hidden" name="confirm_cancel" value="1">
        <p><strong>Reason for Cancellation: *</strong></p>
        <textarea name="reason" rows="4" style="width: 500px;" required placeholder="Provide a reason..."></textarea>
        <br><br>
        <button type="submit" style="padding: 10px 20px; background: #dc3545; color: white; border: none; font-size: 16px;">
            Cancel This Booking
        </button>
        <a href="booking_history.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none;">
            Keep Booking
        </a>
    </form>
<?php else: ?>
    <div style="background: #f8d7da; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb;">
        <h3>❌ Cannot Cancel This Booking</h3>
        <p><strong>Reason:</strong> <?php echo $cancel_message; ?></p>
        <a href="booking_history.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none;">
            Back to Booking History
        </a>
    </div>
<?php endif; ?>

        </div>
    </main>
</body>
</html>
