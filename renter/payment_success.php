<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    header("Location: dashboard.php");
    exit();
}

$bookingid = $_GET['bookingid'];

// Get payment success details from session
if (!isset($_SESSION['payment_success']) || $_SESSION['payment_success']['bookingid'] != $bookingid) {
    // If not in session, fetch from database
    $payment_sql = "SELECT p.*, b.TOTAL_AMOUNT, b.START_DATE, b.END_DATE, b.BOOKING_STATUS,
                    c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
                    l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
                    r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE
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
} else {
    $payment_details = $_SESSION['payment_success'];
    
    // Fetch full details from database
    $payment_sql = "SELECT p.*, b.TOTAL_AMOUNT, b.START_DATE, b.END_DATE, b.BOOKING_STATUS,
                    c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
                    l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
                    r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE,
                    TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
                    TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT
                    FROM PAYMENT p
                    JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                    JOIN CAR c ON b.CARID = c.CARID
                    JOIN LESSOR l ON c.LESSORID = l.LESSORID
                    JOIN RENTER r ON b.RENTERID = r.RENTERID
                    WHERE p.PAYMENTID = :paymentid";
    
    $stmt = oci_parse($conn, $payment_sql);
    oci_bind_by_name($stmt, ':paymentid', $payment_details['paymentid']);
    oci_execute($stmt);
    
    $payment = oci_fetch_assoc($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Car Rental System</title>
    <style>
        .success-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .success-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .success-body {
            padding: 30px;
        }
        .info-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .escrow-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px 5px;
            text-align: center;
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-secondary {
            background: #4CAF50;
            color: white;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">✓</div>
            <h1>Payment Successful!</h1>
            <p>Your booking has been confirmed</p>
        </div>
        
        <div class="success-body">
            <div style="text-align: center; margin: 20px 0;">
                <h2 style="color: #4CAF50; margin: 0;">Booking Confirmed</h2>
                <p style="color: #666;">Confirmation sent to <?php echo htmlspecialchars($payment['RENTER_EMAIL']); ?></p>
            </div>
            
            <!-- Escrow Notice -->
            <div class="escrow-notice">
                <h3 style="margin-top: 0;">🔒 Payment Escrow Protection</h3>
                <p><strong>Your payment is secure!</strong></p>
                <p>Your payment of <strong>RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></strong> 
                   is now being held safely by our platform. Here's what happens next:</p>
                <ol>
                    <li><strong>Now:</strong> Payment held by platform (secure holding)</li>
                    <li><strong>During Rental:</strong> Money remains protected</li>
                    <li><strong>After Return:</strong> You confirm car returned OK</li>
                    <li><strong>Lessor Marks Complete:</strong> Platform records completion</li>
                    <li><strong>Within 24 Hours:</strong> Platform manually transfers 90% to owner (RM <?php echo number_format($payment['TOTAL_AMOUNT'] * 0.90, 2); ?>)</li>
                    <li><strong>Commission:</strong> Platform keeps 10% service fee (RM <?php echo number_format($payment['TOTAL_AMOUNT'] * 0.10, 2); ?>)</li>
                </ol>
                <p style="margin-bottom: 0;">
                    <strong>This protects you:</strong> If there are any issues, the payment can be refunded before manual transfer.
                </p>
            </div>
            
            <!-- Booking Details -->
            <div class="info-box">
                <h3>Booking Details</h3>
                
                <?php if ($payment['PHOTO1_PATH']): ?>
                <div style="text-align: center; margin: 15px 0;">
                    <img src="../uploads/cars/<?php echo htmlspecialchars($payment['PHOTO1_PATH']); ?>" 
                         alt="Car" style="max-width: 300px; border-radius: 5px;">
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <strong>Booking ID:</strong>
                    <span>#<?php echo htmlspecialchars($payment['BOOKINGID']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Payment ID:</strong>
                    <span>#<?php echo htmlspecialchars($payment['PAYMENTID']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Car:</strong>
                    <span><?php echo htmlspecialchars($payment['BRAND'] . ' ' . $payment['MODEL'] . ' ' . $payment['YEAR']); ?></span>
                </div>
                <div class="info-row">
                    <strong>License Plate:</strong>
                    <span><?php echo htmlspecialchars($payment['LICENSE_PLATE']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Rental Period:</strong>
                    <span><?php echo htmlspecialchars($payment['START_DATE_FMT'] . ' to ' . $payment['END_DATE_FMT']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Status:</strong>
                    <span style="color: #4CAF50; font-weight: bold;">
                        <?php echo htmlspecialchars($payment['BOOKING_STATUS']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Payment Details -->
            <div class="info-box">
                <h3>Payment Details</h3>
                <div class="info-row">
                    <strong>Payment Method:</strong>
                    <span>FPX Online Banking</span>
                </div>
                <?php if (isset($_SESSION['payment_success'])): ?>
                <div class="info-row">
                    <strong>Bank:</strong>
                    <span><?php echo htmlspecialchars($_SESSION['payment_success']['bank']); ?></span>
                </div>
                <div class="info-row">
                    <strong>FPX Reference:</strong>
                    <span><?php echo htmlspecialchars($_SESSION['payment_success']['fpx_reference']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <strong>Payment Date:</strong>
                    <span><?php echo date('d M Y, h:i A'); ?></span>
                </div>
                <div class="info-row">
                    <strong>Payment Status:</strong>
                    <span style="color: #4CAF50; font-weight: bold;">SUCCESS</span>
                </div>
                <div class="info-row" style="border-top: 2px solid #333; font-size: 18px;">
                    <strong>Total Paid:</strong>
                    <strong style="color: #4CAF50;">RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></strong>
                </div>
            </div>
            
            <!-- Car Owner Details -->
            <div class="info-box">
                <h3>Car Owner Contact</h3>
                <div class="info-row">
                    <strong>Name:</strong>
                    <span><?php echo htmlspecialchars($payment['LESSOR_NAME']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($payment['LESSOR_EMAIL']); ?></span>
                </div>
                <p style="margin-top: 15px; font-size: 14px; color: #666;">
                    <strong>Note:</strong> The car owner will contact you before the rental period to arrange pickup details.
                </p>
            </div>
            
            <!-- Next Steps -->
            <div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0;">📋 Next Steps</h3>
                <ol>
                    <li>Wait for car owner to contact you via email/phone</li>
                    <li>Arrange pickup time and location</li>
                    <li>Bring your ID and driver's license</li>
                    <li>Inspect the car before driving</li>
                    <li>Enjoy your rental!</li>
                    <li>Return car on time and in good condition</li>
                    <li>After return, payment will be released to owner</li>
                </ol>
            </div>
            
            <!-- Action Buttons -->
            <div style="text-align: center; margin: 30px 0;" class="no-print">
                <a href="booking_history.php" class="btn btn-primary">View Booking History</a>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <button onclick="window.print()" class="btn" style="background: #666; color: white;">
                    🖨️ Print Receipt
                </button>
            </div>
            
            <p style="text-align: center; font-size: 12px; color: #666; margin-top: 30px;">
                Thank you for using our Car Rental Platform System!<br>
                If you have any questions, please contact our support team.
            </p>
        </div>
    </div>
    
    <?php
    // Clear payment success from session after displaying
    if (isset($_SESSION['payment_success'])) {
        unset($_SESSION['payment_success']);
    }
    ?>
</body>
</html>