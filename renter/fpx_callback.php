<?php
/**
 * FPX Payment Callback Handler
 * Processes the response from FPX payment gateway simulation
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if renter is logged in
check_authentication('renter');

$renter_id = get_user_id();

// Check if FPX payment session exists
if (!isset($_SESSION['fpx_payment'])) {
    header("Location: dashboard.php?error=invalid_callback");
    exit();
}

$fpx_data = $_SESSION['fpx_payment'];
$booking_id = $fpx_data['bookingid'];
$amount = $fpx_data['amount'];
$bank = $fpx_data['bank'];
$fpx_reference = $fpx_data['fpx_reference'];
$status = $fpx_data['status'];

// Verify booking belongs to this renter and is pending
$sql_verify = "SELECT BOOKINGID, TOTAL_AMOUNT, BOOKING_STATUS 
              FROM BOOKING 
              WHERE BOOKINGID = :bookingid 
              AND RENTERID = :renterid 
              AND BOOKING_STATUS = 'PENDING'";

$booking = db_fetch_one($sql_verify, [
    'bookingid' => $booking_id,
    'renterid' => $renter_id
]);

if (!$booking) {
    unset($_SESSION['fpx_payment']);
    header("Location: dashboard.php?error=invalid_booking");
    exit();
}

// Check if payment already exists
$sql_check = "SELECT PAYMENTID FROM PAYMENT WHERE BOOKINGID = :bookingid";
$existing = db_fetch_one($sql_check, ['bookingid' => $booking_id]);

if ($existing) {
    unset($_SESSION['fpx_payment']);
    header("Location: payment_success.php?bookingid=" . $booking_id);
    exit();
}

// Process payment if successful
if ($status === 'SUCCESS') {
    
    // Update booking status to CONFIRMED
    $sql_update_booking = "UPDATE BOOKING SET BOOKING_STATUS = 'CONFIRMED' 
                          WHERE BOOKINGID = :bookingid";
    $update_result = db_execute($sql_update_booking, ['bookingid' => $booking_id]);
    
    if ($update_result) {
        // Insert payment record
        $sql_insert_payment = "INSERT INTO PAYMENT 
                              (PAYMENTID, BOOKINGID, RENTERID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS, PAYMENT_DATE)
                              VALUES (SEQ_PAYMENT.NEXTVAL, :bookingid, :renterid, 'ONLINE_BANKING', :amount, 'SUCCESS', SYSDATE)";
        
        $payment_result = db_execute($sql_insert_payment, [
            'bookingid' => $booking_id,
            'renterid' => $renter_id,
            'amount' => $amount
        ]);
        
        if ($payment_result) {
            // Get the payment ID
            $payment_id = db_last_insert_id('SEQ_PAYMENT');
            
            // Store payment details in session for success page
            $_SESSION['payment_success'] = [
                'bookingid' => $booking_id,
                'paymentid' => $payment_id,
                'method' => 'ONLINE_BANKING',
                'bank' => $bank,
                'fpx_reference' => $fpx_reference
            ];
            
            // Clear FPX payment session
            unset($_SESSION['fpx_payment']);
            
            // Redirect to success page
            header("Location: payment_success.php?bookingid=" . $booking_id);
            exit();
        }
    }
    
    // If we reach here, something went wrong
    $error = "Payment processing failed. Please contact support.";
    unset($_SESSION['fpx_payment']);
    header("Location: make_payment.php?bookingid=" . $booking_id . "&error=processing_failed");
    exit();
    
} else {
    // Payment failed
    unset($_SESSION['fpx_payment']);
    header("Location: make_payment.php?bookingid=" . $booking_id . "&error=payment_failed");
    exit();
}
?>