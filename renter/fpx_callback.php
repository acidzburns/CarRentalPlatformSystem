<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();

// Get FPX reference and status from URL
if (!isset($_GET['ref']) || !isset($_GET['status'])) {
    header("Location: dashboard.php");
    exit();
}

$fpx_reference = $_GET['ref'];
$payment_status = $_GET['status'];

// Get FPX transaction from session
if (!isset($_SESSION['fpx_transaction']) || $_SESSION['fpx_transaction']['fpx_reference'] != $fpx_reference) {
    set_flash_message("Invalid FPX transaction reference.", "error");
    header("Location: dashboard.php");
    exit();
}

$fpx_data = $_SESSION['fpx_transaction'];
$bookingid = $fpx_data['bookingid'];
$amount = $fpx_data['amount'];
$bank = $fpx_data['bank'];

// Process payment based on status
if ($payment_status == 'success') {
    // Payment successful - Process escrow and update database
    
    try {
        // Start transaction
        oci_execute(oci_parse($conn, 'SET TRANSACTION READ WRITE'), OCI_NO_AUTO_COMMIT);
        
        // Get booking and lessor details
        $booking_sql = "SELECT b.*, c.LESSORID 
                       FROM BOOKING b
                       JOIN CAR c ON b.CARID = c.CARID
                       WHERE b.BOOKINGID = :bookingid 
                       AND b.RENTERID = :renterid
                       AND b.BOOKING_STATUS = 'PENDING'";
        
        $stmt_booking = oci_parse($conn, $booking_sql);
        oci_bind_by_name($stmt_booking, ':bookingid', $bookingid);
        oci_bind_by_name($stmt_booking, ':renterid', $renterid);
        oci_execute($stmt_booking, OCI_NO_AUTO_COMMIT);
        
        $booking = oci_fetch_assoc($stmt_booking);
        
        if (!$booking) {
            throw new Exception("Booking not found or already processed.");
        }
        
        // UC5 Step 6: Update booking status to "CONFIRMED"
        $update_booking = "UPDATE BOOKING 
                          SET BOOKING_STATUS = 'CONFIRMED' 
                          WHERE BOOKINGID = :bookingid";
        
        $stmt_update = oci_parse($conn, $update_booking);
        oci_bind_by_name($stmt_update, ':bookingid', $bookingid);
        
        if (!oci_execute($stmt_update, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to update booking status.");
        }
        
        // UC5 Step 7: Create payment record (PAYMENT HELD IN ESCROW)
        $payment_sql = "INSERT INTO PAYMENT 
                       (PAYMENTID, BOOKINGID, RENTERID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS, PAYMENT_DATE)
                       VALUES 
                       (SEQ_PAYMENT.NEXTVAL, :bookingid, :renterid, 'FPX', :amount, 'SUCCESS', SYSDATE)
                       RETURNING PAYMENTID INTO :new_paymentid";
        
        $stmt_payment = oci_parse($conn, $payment_sql);
        oci_bind_by_name($stmt_payment, ':bookingid', $bookingid);
        oci_bind_by_name($stmt_payment, ':renterid', $renterid);
        oci_bind_by_name($stmt_payment, ':amount', $amount);
        oci_bind_by_name($stmt_payment, ':new_paymentid', $paymentid, 32);
        
        if (!oci_execute($stmt_payment, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to create payment record.");
        }
        
        // ESCROW SYSTEM: Create transaction record (money held by platform)
        // Money will be released to lessor AFTER booking completion
        $lessorid = $booking['LESSORID'];
        
        // Platform commission (10%)
        $commission = $amount * 0.10;
        $lessor_amount = $amount - $commission;
        
        // Create transaction with "HELD" status (escrow)
        $transaction_sql = "INSERT INTO TRANSACTION 
                           (TRANSACTIONID, BOOKINGID, LESSORID, AMOUNT, COMMISSION, TRANSACTION_DATE)
                           VALUES 
                           (SEQ_TRANSACTION.NEXTVAL, :bookingid, :lessorid, :amount, :commission, SYSDATE)
                           RETURNING TRANSACTIONID INTO :new_transactionid";
        
        $stmt_trans = oci_parse($conn, $transaction_sql);
        oci_bind_by_name($stmt_trans, ':bookingid', $bookingid);
        oci_bind_by_name($stmt_trans, ':lessorid', $lessorid);
        oci_bind_by_name($stmt_trans, ':amount', $lessor_amount);
        oci_bind_by_name($stmt_trans, ':commission', $commission);
        oci_bind_by_name($stmt_trans, ':new_transactionid', $transactionid, 32);
        
        if (!oci_execute($stmt_trans, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to create transaction record.");
        }
        
        // Commit all changes
        oci_commit($conn);
        
        // Store success details in session
        $_SESSION['payment_success'] = [
            'bookingid' => $bookingid,
            'paymentid' => $paymentid,
            'transactionid' => $transactionid,
            'amount' => $amount,
            'lessor_amount' => $lessor_amount,
            'commission' => $commission,
            'fpx_reference' => $fpx_reference,
            'bank' => $bank,
            'timestamp' => time()
        ];
        
        // Clear FPX transaction from session
        unset($_SESSION['fpx_transaction']);
        
        // UC5 Step 8: Send confirmation email (simulated)
        // In production: Send email to customer and lessor
        
        // Redirect to success page
        header("Location: payment_success.php?bookingid=" . $bookingid);
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        oci_rollback($conn);
        
        set_flash_message("Payment processing failed: " . $e->getMessage(), "error");
        header("Location: make_payment.php?bookingid=" . $bookingid);
        exit();
    }
    
} else {
    // Payment failed or cancelled
    
    // Clear FPX transaction from session
    unset($_SESSION['fpx_transaction']);
    
    set_flash_message("Payment was cancelled or failed. Please try again.", "error");
    header("Location: make_payment.php?bookingid=" . $bookingid);
    exit();
}
?>