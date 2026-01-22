<?php
/**
 * UC20: Booking Management - Mark as Completed (SRS Pages 67-70)
 * CRITICAL: This releases escrow payment to lessor!
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once "../config/email_test.php";

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
               c.LESSORID, c.BRAND, c.MODEL, c.YEAR
               FROM BOOKING b
               JOIN CAR c ON b.CARID = c.CARID
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
    // Can only complete CONFIRMED bookings
    if ($booking['BOOKING_STATUS'] != 'CONFIRMED') {
        set_flash_message("Can only complete confirmed bookings. Current status: " . $booking['BOOKING_STATUS'], "error");
        header("Location: bookings.php");
        exit();
    }

    try {
        // Start transaction
        oci_execute(oci_parse($conn, 'SET TRANSACTION READ WRITE'), OCI_NO_AUTO_COMMIT);

        // 1. Update booking status to COMPLETED
        $update_sql = "UPDATE BOOKING SET BOOKING_STATUS = 'COMPLETED' WHERE BOOKINGID = :bookingid";
        $stmt_update = oci_parse($conn, $update_sql);
        oci_bind_by_name($stmt_update, ':bookingid', $bookingid);
        if (!oci_execute($stmt_update, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to update booking status.");
        }

        // 2. Insert transaction record for lessor
$lessor_earnings = round($booking['TOTAL_AMOUNT'] * 0.90, 2);
$platform_commission = round($booking['TOTAL_AMOUNT'] * 0.10, 2);

$insert_sql = 'INSERT INTO "TRANSACTION"
               (TRANSACTIONID, BOOKINGID, LESSORID, AMOUNT, COMMISSION, TRANSACTION_DATE)
               VALUES (SEQ_TRANSACTION.NEXTVAL, :bookingid, :lessorid, :amount, :commission, SYSDATE)
               RETURNING TRANSACTIONID INTO :transid';

$stmt_trans = oci_parse($conn, $insert_sql);
oci_bind_by_name($stmt_trans, ':bookingid', $bookingid);
oci_bind_by_name($stmt_trans, ':lessorid', $lessorid);
oci_bind_by_name($stmt_trans, ':amount', $lessor_earnings);
oci_bind_by_name($stmt_trans, ':commission', $platform_commission);
oci_bind_by_name($stmt_trans, ':transid', $transid, 32);

if (!oci_execute($stmt_trans, OCI_NO_AUTO_COMMIT)) {
    throw new Exception("Failed to create transaction record.");
}


        // Commit all changes
        oci_commit($conn);

        set_flash_message(
            "Booking completed successfully! Transaction ID: $transid. Your earnings (RM " . number_format($lessor_earnings, 2) . ") will be transferred within 3-5 business days.", 
            "success"
        );

        header("Location: bookings.php");
        exit();

    } catch (Exception $e) {
        oci_rollback($conn);
        set_flash_message("Failed to complete booking: " . $e->getMessage(), "error");
        header("Location: bookings.php");
        exit();
    }

} elseif ($action == 'cancel_emergency') {
    // Emergency cancellation
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';

    if (empty($cancel_reason)) {
        set_flash_message("Please provide a reason for cancellation.", "error");
        header("Location: bookings.php");
        exit();
    }

    if ($booking['BOOKING_STATUS'] != 'CONFIRMED') {
        set_flash_message("Can only cancel confirmed bookings. Current status: " . $booking['BOOKING_STATUS'], "error");
        header("Location: bookings.php");
        exit();
    }

    try {
        oci_execute(oci_parse($conn, 'SET TRANSACTION READ WRITE'), OCI_NO_AUTO_COMMIT);

        $cancel_sql = "UPDATE BOOKING SET BOOKING_STATUS = 'CANCELLED' WHERE BOOKINGID = :bookingid";
        $stmt_cancel = oci_parse($conn, $cancel_sql);
        oci_bind_by_name($stmt_cancel, ':bookingid', $bookingid);
        if (!oci_execute($stmt_cancel, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to cancel booking.");
        }

        oci_commit($conn);

        set_flash_message(
            "Booking cancelled successfully. Reason: " . htmlspecialchars($cancel_reason),
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
