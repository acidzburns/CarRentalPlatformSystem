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

// Fetch payment and booking details from database
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

// ✅ Get additional payment details from session (if available)
$payment_session = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : [];

// ✅ Determine payment method display name
$payment_method_display = '';
switch ($payment['PAYMENT_METHOD']) {
    case 'FPX':
        $payment_method_display = 'FPX Online Banking';
        break;
    case 'CARD':
        $payment_method_display = 'Credit/Debit Card';
        break;
    case 'E_WALLET':
        $payment_method_display = 'E-Wallet';
        break;
    default:
        $payment_method_display = htmlspecialchars($payment['PAYMENT_METHOD']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Car Rental System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .success-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
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
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .success-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .success-body {
            padding: 30px;
        }
        .info-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .info-box h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row strong {
            color: #555;
        }
        .escrow-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .escrow-notice h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .escrow-notice ol {
            margin-left: 20px;
            margin-top: 10px;
        }
        .escrow-notice li {
            margin: 8px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 5px;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-secondary {
            background: #4CAF50;
            color: white;
        }
        .btn-print {
            background: #666;
            color: white;
        }
        .next-steps {
            background: #e3f2fd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
        }
        .next-steps h3 {
            color: #1565c0;
            margin-bottom: 15px;
        }
        .next-steps ol {
            margin-left: 20px;
        }
        .next-steps li {
            margin: 10px 0;
            color: #333;
        }
        @media print {
            body {
                background: white;
            }
            .no-print {
                display: none;
            }
            .success-container {
                box-shadow: none;
            }
        }
        @media (max-width: 768px) {
            .success-container {
                margin: 10px;
            }
            .success-body {
                padding: 20px;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
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
                <h2 style="color: #4CAF50; margin-bottom: 10px;">Booking Confirmed</h2>
                <p style="color: #666;">Confirmation sent to <?php echo htmlspecialchars($payment['RENTER_EMAIL']); ?></p>
            </div>
            
            <!-- Escrow Notice -->
            <div class="escrow-notice">
                <h3>🔒 Payment Escrow Protection</h3>
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
                <p style="margin-top: 10px;">
                    <strong>This protects you:</strong> If there are any issues, the payment can be refunded before manual transfer.
                </p>
            </div>
            
            <!-- Booking Details -->
            <div class="info-box">
                <h3>📋 Booking Details</h3>
                
                <?php if (!empty($payment['PHOTO1_PATH'])): ?>
                <div style="text-align: center; margin: 15px 0;">
                    <img src="../uploads/cars/<?php echo htmlspecialchars($payment['PHOTO1_PATH']); ?>" 
                         alt="Car" style="max-width: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
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
                <h3>💳 Payment Details</h3>
                <div class="info-row">
                    <strong>Payment Method:</strong>
                    <span><?php echo $payment_method_display; ?></span>
                </div>
                
                <?php if ($payment['PAYMENT_METHOD'] == 'FPX' && isset($payment_session['bank'])): ?>
                    <!-- ✅ Show bank details only for FPX -->
                    <div class="info-row">
                        <strong>Bank:</strong>
                        <span><?php echo htmlspecialchars($payment_session['bank']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($payment['PAYMENT_METHOD'] == 'FPX' && isset($payment_session['fpx_reference'])): ?>
                    <!-- ✅ Show FPX reference only for FPX -->
                    <div class="info-row">
                        <strong>FPX Reference:</strong>
                        <span><?php echo htmlspecialchars($payment_session['fpx_reference']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($payment['PAYMENT_METHOD'] == 'CARD' && isset($payment_session['card_type'])): ?>
                    <!-- ✅ Show card details only for Card payments -->
                    <div class="info-row">
                        <strong>Card Type:</strong>
                        <span><?php echo htmlspecialchars($payment_session['card_type']); ?></span>
                    </div>
                    <?php if (isset($payment_session['card_last4'])): ?>
                    <div class="info-row">
                        <strong>Card Number:</strong>
                        <span>**** **** **** <?php echo htmlspecialchars($payment_session['card_last4']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($payment['PAYMENT_METHOD'] == 'E_WALLET' && isset($payment_session['wallet_type'])): ?>
                    <!-- ✅ Show wallet details only for E-Wallet payments -->
                    <div class="info-row">
                        <strong>E-Wallet:</strong>
                        <span><?php echo htmlspecialchars($payment_session['wallet_type']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($payment['TRANSACTION_ID'])): ?>
                <div class="info-row">
                    <strong>Transaction ID:</strong>
                    <span><?php echo htmlspecialchars($payment['TRANSACTION_ID']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <strong>Payment Date:</strong>
                    <span><?php echo htmlspecialchars($payment['PAYMENT_DATE_FMT']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Payment Status:</strong>
                    <span style="color: #4CAF50; font-weight: bold;">✓ SUCCESS</span>
                </div>
                <div class="info-row" style="border-top: 2px solid #333; margin-top: 10px; padding-top: 15px; font-size: 18px;">
                    <strong>Total Paid:</strong>
                    <strong style="color: #4CAF50;">RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></strong>
                </div>
            </div>
            
            <!-- Car Owner Details -->
            <div class="info-box">
                <h3>👤 Car Owner Contact</h3>
                <div class="info-row">
                    <strong>Name:</strong>
                    <span><?php echo htmlspecialchars($payment['LESSOR_NAME']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($payment['LESSOR_EMAIL']); ?></span>
                </div>
                <p style="margin-top: 15px; font-size: 14px; color: #666; background: #fff3cd; padding: 10px; border-radius: 5px;">
                    <strong>📞 Note:</strong> The car owner will contact you before the rental period to arrange pickup details.
                </p>
            </div>
            
            <!-- Next Steps -->
            <div class="next-steps">
                <h3>📋 Next Steps</h3>
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
                <a href="booking_history.php" class="btn btn-primary">📚 View Booking History</a>
                <a href="dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
                <button onclick="window.print()" class="btn btn-print">
                    🖨️ Print Receipt
                </button>
            </div>
            
            <p style="text-align: center; font-size: 12px; color: #666; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
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