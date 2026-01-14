<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_test.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    header("Location: dashboard.php");
    exit();
}

$bookingid = $_GET['bookingid'];

// Fetch payment and booking details
$payment_sql = "SELECT p.*, b.TOTAL_AMOUNT, b.START_DATE, b.END_DATE, b.BOOKING_STATUS,
                c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
                l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
                r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE,
                TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
                TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
                TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI AM') as PAYMENT_DATE_FMT
                FROM PAYMENT p
                JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                JOIN CAR c ON b.CARID = c.CARID
                JOIN LESSOR l ON c.LESSORID = l.LESSORID
                JOIN RENTER r ON b.RENTERID = r.RENTERID
                WHERE p.BOOKINGID = :bookingid 
                AND b.RENTERID = :renterid
                AND p.PAYMENT_STATUS = 'SUCCESS'";

$stmt = oci_parse($conn, $payment_sql);
oci_bind_by_name($stmt, ':bookingid', $bookingid);
oci_bind_by_name($stmt, ':renterid', $renterid);
oci_execute($stmt);

$payment = oci_fetch_assoc($stmt);

if (!$payment) {
    set_flash_message("Payment confirmation not found.", "error");
    header("Location: dashboard.php");
    exit();
}

// Send email notifications
if ($payment['BOOKING_STATUS'] === 'CONFIRMED') {
    $renter_subject = "Your Car Rental Booking is Confirmed!";
    $renter_body = "
    <p>Dear {$payment['RENTER_NAME']},</p>
    <p>Thank you for your payment. Your booking has been <b>confirmed</b>.</p>
    <p><strong>Booking ID:</strong> {$payment['BOOKINGID']}<br>
       <strong>Car:</strong> {$payment['BRAND']} {$payment['MODEL']} {$payment['YEAR']}<br>
       <strong>Rental Period:</strong> {$payment['START_DATE_FMT']} to {$payment['END_DATE_FMT']}<br>
       <strong>Total Paid:</strong> RM ".number_format($payment['TOTAL_AMOUNT'], 2)."</p>
    <p>The car owner ({$payment['LESSOR_NAME']}) will contact you to arrange pickup.</p>";

    send_email($payment['RENTER_EMAIL'], $renter_subject, $renter_body);

    $lessor_subject = "New Booking Confirmed for Your Car!";
    $lessor_body = "
    <p>Dear {$payment['LESSOR_NAME']},</p>
    <p>A new booking has been confirmed for your car:</p>
    <p><strong>Booking ID:</strong> {$payment['BOOKINGID']}<br>
       <strong>Renter:</strong> {$payment['RENTER_NAME']} ({$payment['RENTER_EMAIL']})<br>
       <strong>Car:</strong> {$payment['BRAND']} {$payment['MODEL']} {$payment['YEAR']}<br>
       <strong>Rental Period:</strong> {$payment['START_DATE_FMT']} to {$payment['END_DATE_FMT']}<br>
       <strong>Total Paid:</strong> RM ".number_format($payment['TOTAL_AMOUNT'], 2)."</p>";

    send_email($payment['LESSOR_EMAIL'], $lessor_subject, $lessor_body);
}

// Clear specific session item
if (isset($_SESSION['payment_success'])) {
    unset($_SESSION['payment_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Car Rental System</title>
    <link rel="stylesheet" href="../assets/css/make-payment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .success-card { text-align: center; padding: 40px !important; max-width: 800px; margin: 0 auto; }
        .success-icon { width: 80px; height: 80px; background: #f0fdf4; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 30px; }
        .receipt-details { text-align: left; margin: 30px 0; background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .receipt-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .receipt-row:last-child { border: none; }
        .receipt-row label { color: #64748b; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .receipt-row span { font-weight: 600; color: #1e293b; font-size: 14px; }
        .action-flex { display: flex; gap: 15px; margin-top: 30px; }
        @media print { nav, .page-header, .no-print { display: none !important; } .container { margin-top: 0; padding: 0; } .details-card { box-shadow: none; border: none; } }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php" style="color:white; text-decoration:none;">Car Rental System</a></h2>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    <header class="page-header">
        <h1>Payment Confirmed!</h1>
        <p>Your booking has been successfully processed</p>
    </header>
    <main class="details-container">
        <div style="grid-column: 1 / -1;">
            <div class="details-card success-card">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h2 style="font-size: 28px; margin-bottom: 10px;">Thank you for your payment</h2>
                <p style="color: #64748b;">A confirmation email has been sent to <strong><?php echo htmlspecialchars($payment['RENTER_EMAIL']); ?></strong></p>
                <div class="receipt-details">
                    <h3 style="margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #1864FF; padding-bottom: 5px; display: inline-block;">Booking Receipt</h3>
                    <div class="receipt-row"><label>Booking ID</label><span>#<?php echo htmlspecialchars($payment['BOOKINGID']); ?></span></div>
                    <div class="receipt-row"><label>Car Reserved</label><span><?php echo htmlspecialchars($payment['BRAND'] . ' ' . $payment['MODEL'] . ' ' . $payment['YEAR']); ?></span></div>
                    <div class="receipt-row"><label>Rental Period</label><span><?php echo htmlspecialchars($payment['START_DATE_FMT'] . ' - ' . $payment['END_DATE_FMT']); ?></span></div>
                    <div class="receipt-row"><label>Payment Method</label><span><?php echo $payment['PAYMENT_METHOD'] == 'CARD' ? 'Credit/Debit Card' : 'FPX Online Banking'; ?></span></div>
                    <div class="receipt-row"><label>Transaction Time</label><span><?php echo $payment['PAYMENT_DATE_FMT']; ?></span></div>
                    <div class="receipt-row" style="border-top: 2px solid #e2e8f0; margin-top: 10px; padding-top: 15px;"><label style="color: #1e293b; font-size: 14px;">Total Paid</label><span style="color: #1864FF; font-size: 24px; font-weight: 800;">RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></span></div>
                </div>
                <div class="security-card" style="background: #fffbeb; border: 1px solid #fef3c7; text-align: left; margin-bottom: 30px;"><i class="fas fa-info-circle" style="color: #d97706;"></i><div class="security-text"><strong style="color: #92400e;">What's Next?</strong><p style="color: #92400e;">Your payment is securely held. The car owner <strong><?php echo htmlspecialchars($payment['LESSOR_NAME']); ?></strong> will contact you soon to arrange lookup.</p></div></div>
                <div class="action-flex no-print"><a href="booking_history.php" class="btn-primary" style="text-decoration:none;">Go to My Bookings</a><button onclick="window.print()" class="btn-primary" style="background:#f1f5f9; color:#1e293b; border:1px solid #e2e8f0; box-shadow:none;"><i class="fas fa-print"></i> Print Receipt</button></div>
            </div>
        </div>
    </main>
</body>
</html>