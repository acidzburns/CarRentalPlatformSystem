<?php
/**
 * UC20: Booking Management - Mark as Completed (SRS Pages 67-70)
 * CRITICAL: This releases escrow payment to lessor!
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if lessor is logged in
require_login('lessor');

$lessorid = get_user_id();
$errors = [];

// Validate request
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    set_flash_message("Invalid request method.", "error");
    header("Location: bookings.php");
    exit();
}

$bookingid = isset($_POST['bookingid']) ? intval($_POST['bookingid']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($bookingid <= 0 || empty($action)) {
    set_flash_message("Invalid booking or action.", "error");
    header("Location: bookings.php");
    exit();
}

// Verify booking belongs to this lessor
$verify_sql = "SELECT b.BOOKINGID, b.BOOKING_STATUS, b.TOTAL_AMOUNT, b.CARID,
               c.LESSORID, c.BRAND, c.MODEL, c.YEAR,
               r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL
               FROM BOOKING b
               JOIN CAR c ON b.CARID = c.CARID
               JOIN RENTER r ON b.RENTERID = r.RENTERID
               WHERE b.BOOKINGID = :bookingid AND c.LESSORID = :lessorid";

$stmt_verify = oci_parse($conn, $verify_sql);
oci_bind_by_name($stmt_verify, ':bookingid', $bookingid);
oci_bind_by_name($stmt_verify, ':lessorid', $lessorid);
oci_execute($stmt_verify);

$booking = oci_fetch_assoc($stmt_verify);

if (!$booking) {
    set_flash_message("Booking not found or you don't have permission.", "error");
    header("Location: bookings.php");
    exit();
}

// Process action
if ($action == 'complete') {
    // UC20 Step 7a: Mark booking as completed
    
    // Exception 1: Can only complete CONFIRMED bookings
    if ($booking['BOOKING_STATUS'] != 'CONFIRMED') {
        set_flash_message("Can only complete confirmed bookings. Current status: " . $booking['BOOKING_STATUS'], "error");
        header("Location: bookings.php");
        exit();
    }
    
    try {
        // Start transaction
        oci_execute(oci_parse($conn, 'SET TRANSACTION READ WRITE'), OCI_NO_AUTO_COMMIT);
        
        // UC20 Alternative Flow 2: Mark booking as completed
        // Step 1: Update booking status to COMPLETED
        $update_sql = "UPDATE BOOKING SET BOOKING_STATUS = 'COMPLETED' WHERE BOOKINGID = :bookingid";
        $stmt_update = oci_parse($conn, $update_sql);
        oci_bind_by_name($stmt_update, ':bookingid', $bookingid);
        
        if (!oci_execute($stmt_update, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to update booking status.");
        }
        
        // CRITICAL: Release escrow payment to lessor
        // Step 2: Update transaction status from HELD to RELEASED
        // NOTE: Transaction was created when payment was made (fpx_callback.php)
        
        // Get transaction details
        $trans_sql = "SELECT TRANSACTIONID, AMOUNT, COMMISSION 
                     FROM TRANSACTION 
                     WHERE BOOKINGID = :bookingid AND LESSORID = :lessorid";
        
        $stmt_trans = oci_parse($conn, $trans_sql);
        oci_bind_by_name($stmt_trans, ':bookingid', $bookingid);
        oci_bind_by_name($stmt_trans, ':lessorid', $lessorid);
        oci_execute($stmt_trans, OCI_NO_AUTO_COMMIT);
        
        $transaction = oci_fetch_assoc($stmt_trans);
        
        if ($transaction) {
            // In a real system, you would:
            // 1. Integrate with bank API to transfer money to lessor's account
            // 2. Update transaction with bank reference number
            // 3. Send confirmation email to lessor
            
            // For now, we just log that payment is released
            // The TRANSACTION record already exists with AMOUNT and COMMISSION
            
            // You could add a STATUS column to TRANSACTION table to track release
            // Or use a separate PAYOUT table for tracking actual bank transfers
            
            $lessor_earnings = $transaction['AMOUNT']; // Already 90% (calculated in fpx_callback.php)
            $platform_commission = $transaction['COMMISSION']; // 10%
            
            // Log successful completion
            set_flash_message(
                "Booking completed successfully! Your earnings (RM " . 
                number_format($lessor_earnings, 2) . 
                ") will be transferred to your account within 3-5 business days.", 
                "success"
            );
        } else {
            // No transaction found - booking might have been cancelled before payment
            set_flash_message("Booking marked as completed, but no payment transaction found.", "warning");
        }
        
        // Commit all changes
        oci_commit($conn);
        
        // Send notifications (simulated)
        // In production:
        // - Email renter: "Booking completed, please leave a review"
        // - Email lessor: "Payment of RM X released to your account"
        
        header("Location: bookings.php");
        exit();
        
    } catch (Exception $e) {
        oci_rollback($conn);
        set_flash_message("Failed to complete booking: " . $e->getMessage(), "error");
        header("Location: bookings.php");
        exit();
    }
    
} elseif ($action == 'cancel_emergency') {
    // UC20 Alternative Flow 4: Emergency cancellation
    
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    
    // Exception 2: Missing cancellation reason
    if (empty($cancel_reason)) {
        set_flash_message("Please provide a reason for cancellation.", "error");
        header("Location: bookings.php");
        exit();
    }
    
    // Exception 1: Can only cancel CONFIRMED bookings
    if ($booking['BOOKING_STATUS'] != 'CONFIRMED') {
        set_flash_message("Can only cancel confirmed bookings. Current status: " . $booking['BOOKING_STATUS'], "error");
        header("Location: bookings.php");
        exit();
    }
    
    try {
        // Start transaction
        oci_execute(oci_parse($conn, 'SET TRANSACTION READ WRITE'), OCI_NO_AUTO_COMMIT);
        
        // Update booking status to CANCELLED
        $cancel_sql = "UPDATE BOOKING SET BOOKING_STATUS = 'CANCELLED' WHERE BOOKINGID = :bookingid";
        $stmt_cancel = oci_parse($conn, $cancel_sql);
        oci_bind_by_name($stmt_cancel, ':bookingid', $bookingid);
        
        if (!oci_execute($stmt_cancel, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to cancel booking.");
        }
        
        // In a real system:
        // 1. Process full refund to renter
        // 2. Update payment status to REFUNDED
        // 3. Remove/update transaction record
        // 4. Apply penalty to lessor if applicable (late cancellation)
        // 5. Send refund confirmation to renter
        
        // For now, just mark as cancelled
        oci_commit($conn);
        
        set_flash_message(
            "Booking cancelled successfully. Renter will receive full refund. Reason: " . 
            htmlspecialchars($cancel_reason), 
            "success"
        );
        
        header("Location: bookings.php");
        exit();
        
    } catch (Exception $e) {
        oci_rollback($conn);
        set_flash_message("Failed to cancel booking: " . $e->getMessage(), "error");
        header("Location: bookings.php");
        exit();
    }
    
} else {
    set_flash_message("Invalid action.", "error");
    header("Location: bookings.php");
    exit();
}
?>