<?php
/**
 * Cancel Booking - Backend Only
 * Allows renter to cancel their pending or confirmed bookings
 */
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

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
                  (SELECT PAYMENT_STATUS FROM PAYMENT WHERE BOOKINGID = b.BOOKINGID AND ROWNUM = 1) as PAYMENT_STATUS
                  FROM BOOKING b
                  INNER JOIN CAR c ON b.CARID = c.CARID
                  INNER JOIN LESSOR l ON c.LESSORID = l.LESSORID
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
    // Check if booking is within cancellation period
    // Allow cancellation if start date is more than 24 hours away
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
        // Begin transaction-like operations
        
        // 1. Update booking status to CANCELLED
        $update_booking_query = "UPDATE BOOKING 
                                SET BOOKING_STATUS = 'CANCELLED' 
                                WHERE BOOKINGID = :booking_id";
        
        if (db_execute($update_booking_query, ['booking_id' => $booking_id])) {
            // 2. Process refund if payment was made
            if ($booking['PAYMENT_STATUS'] === 'SUCCESS') {
                // Calculate refund amount (full refund if >24 hours before)
                $refund_amount = $booking['TOTAL_AMOUNT'];
                
                // Update payment status (optional - mark as refunded)
                // You could add a REFUND_STATUS column to PAYMENT table
                // For now, we'll just log it
                
                // 3. Log cancellation (you could create a CANCELLATION table)
                // For now, we'll just set session message
                
                $_SESSION['success'] = 'Booking cancelled successfully. Refund of RM ' . 
                                      number_format($refund_amount, 2) . 
                                      ' will be processed within 5-7 business days.';
            } else {
                $_SESSION['success'] = 'Booking cancelled successfully. No payment was made.';
            }
            
            // 4. Send notification email to lessor (optional)
            if (file_exists('../config/email_test.php')) {
                require_once '../config/email_test.php';
                
                $subject = 'Booking Cancellation Notification';
                $message = "Dear {$booking['LESSOR_NAME']},\n\n";
                $message .= "A booking for your car has been cancelled.\n\n";
                $message .= "Booking ID: BK-" . $booking_id . "\n";
                $message .= "Car: {$booking['BRAND']} {$booking['MODEL']} ({$booking['LICENSE_PLATE']})\n";
                $message .= "Customer: {$renter_name}\n";
                $message .= "Booking Period: {$booking['START_DATE_F']} to {$booking['END_DATE_F']}\n";
                $message .= "Cancellation Reason: {$cancellation_reason}\n\n";
                $message .= "The booking has been cancelled and your car is now available for other bookings.\n\n";
                $message .= "Best regards,\nCar Rental Platform Team";
                
                send_test_email($booking['LESSOR_EMAIL'], $subject, $message);
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
<html>
<head>
    <title>Cancel Booking - Renter</title>
</head>
<body>
    <h1>Cancel Booking</h1>
    <p>Renter: <?php echo htmlspecialchars($renter_name); ?></p>
    <a href="booking_history.php">← Back to Booking History</a> | 
    <a href="dashboard.php">Dashboard</a> | 
    <a href="../auth/logout.php">Logout</a>
    <hr>
    
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
            <td>
                <?php if ($booking['BOOKING_STATUS'] === 'PENDING'): ?>
                    <span style="color: orange;">⚠ PENDING</span>
                <?php elseif ($booking['BOOKING_STATUS'] === 'CONFIRMED'): ?>
                    <span style="color: green;">✓ CONFIRMED</span>
                <?php elseif ($booking['BOOKING_STATUS'] === 'ONGOING'): ?>
                    <span style="color: blue;">🚗 ONGOING</span>
                <?php elseif ($booking['BOOKING_STATUS'] === 'COMPLETED'): ?>
                    <span style="color: gray;">✓ COMPLETED</span>
                <?php elseif ($booking['BOOKING_STATUS'] === 'CANCELLED'): ?>
                    <span style="color: red;">✗ CANCELLED</span>
                <?php else: ?>
                    <?php echo $booking['BOOKING_STATUS']; ?>
                <?php endif; ?>
            </td>
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
        
        <div style="background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffc107;">
            <strong>Cancellation Policy:</strong>
            <ul>
                <li>Cancellations made more than 24 hours before start time: <strong>Full refund</strong></li>
                <li>Cancellations made within 24 hours: Please contact support</li>
                <li>Refunds will be processed within 5-7 business days</li>
            </ul>
        </div>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
            <input type="hidden" name="confirm_cancel" value="1">
            
            <p><strong>Reason for Cancellation: *</strong></p>
            <textarea name="reason" rows="4" style="width: 500px; padding: 5px;" required
                      placeholder="Please provide a reason for cancelling this booking (e.g., change of plans, found alternative, etc.)"></textarea>
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
            
            <?php if (in_array($booking['BOOKING_STATUS'], ['ONGOING', 'COMPLETED'])): ?>
                <p>If you need assistance, please <a href="submit_ticket.php">submit a support ticket</a>.</p>
            <?php endif; ?>
            
            <a href="booking_history.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none;">
                Back to Booking History
            </a>
        </div>
    <?php endif; ?>
    
</body>
</html>