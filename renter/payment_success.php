<?php
/**
 * Payment Success Page
 * Displays payment confirmation and booking details
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if renter is logged in
check_authentication('renter');

$renterid = get_user_id();

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    header("Location: dashboard.php");
    exit();
}

$bookingid = $_GET['bookingid'];

// Fetch payment and booking details
$payment_sql = "SELECT 
                p.PAYMENTID, p.PAYMENT_METHOD, p.AMOUNT, p.PAYMENT_STATUS,
                TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as PAYMENT_DATE_FMT,
                b.BOOKINGID, b.TOTAL_AMOUNT, b.BOOKING_STATUS,
                TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
                TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
                c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
                l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL, l.PHONE as LESSOR_PHONE,
                r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE
                FROM PAYMENT p
                JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                JOIN CAR c ON b.CARID = c.CARID
                JOIN LESSOR l ON c.LESSORID = l.LESSORID
                JOIN RENTER r ON b.RENTERID = r.RENTERID
                WHERE p.BOOKINGID = :bookingid 
                AND b.RENTERID = :renterid
                AND p.PAYMENT_STATUS = 'SUCCESS'";

$payment = db_fetch_one($payment_sql, [
    'bookingid' => $bookingid,
    'renterid' => $renterid
]);

if (!$payment) {
    header("Location: dashboard.php?error=payment_not_found");
    exit();
}

// Get payment details from session if available
$fpx_details = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Car Rental System</title>
</head>
<body>
    <nav>
        <h2>Car Rental System</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="booking_history.php">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <main>
        <div class="success-header">
            <h1>✓ Payment Successful!</h1>
            <p>Your booking has been confirmed</p>
        </div>
        
        <div class="success-message">
            <h2>Booking Confirmed</h2>
            <p>Confirmation sent to <?php echo htmlspecialchars($payment['RENTER_EMAIL']); ?></p>
        </div>
        
        <!-- Escrow Notice -->
        <div class="escrow-notice">
            <h3>🔒 Payment Escrow Protection</h3>
            <p><strong>Your payment is secure!</strong></p>
            <p>Your payment of <strong>RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></strong> 
               is now being held safely by our platform. Here's what happens next:</p>
            <ol>
                <li><strong>Now:</strong> Payment is held in escrow (secure holding)</li>
                <li><strong>During Rental:</strong> Money remains protected</li>
                <li><strong>After Return:</strong> Platform releases payment to car owner</li>
                <li><strong>Commission:</strong> Platform keeps 10% service fee</li>
                <li><strong>Owner Receives:</strong> 90% of payment (RM <?php echo number_format($payment['TOTAL_AMOUNT'] * 0.90, 2); ?>)</li>
            </ol>
            <p><strong>This protects you:</strong> If there are any issues, the payment can be refunded before release.</p>
        </div>
        
        <!-- Booking Details -->
        <div class="info-section">
            <h3>Booking Details</h3>
            
            <?php if ($payment['PHOTO1_PATH']): ?>
            <div>
                <img src="../uploads/cars/<?php echo htmlspecialchars($payment['PHOTO1_PATH']); ?>" 
                     alt="Car" width="300">
            </div>
            <?php endif; ?>
            
            <table>
                <tr>
                    <td><strong>Booking ID:</strong></td>
                    <td>#<?php echo htmlspecialchars($payment['BOOKINGID']); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment ID:</strong></td>
                    <td>#<?php echo htmlspecialchars($payment['PAYMENTID']); ?></td>
                </tr>
                <tr>
                    <td><strong>Car:</strong></td>
                    <td><?php echo htmlspecialchars($payment['BRAND'] . ' ' . $payment['MODEL'] . ' ' . $payment['YEAR']); ?></td>
                </tr>
                <tr>
                    <td><strong>License Plate:</strong></td>
                    <td><?php echo htmlspecialchars($payment['LICENSE_PLATE']); ?></td>
                </tr>
                <tr>
                    <td><strong>Rental Period:</strong></td>
                    <td><?php echo htmlspecialchars($payment['START_DATE_FMT'] . ' to ' . $payment['END_DATE_FMT']); ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><strong><?php echo htmlspecialchars($payment['BOOKING_STATUS']); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Details -->
        <div class="info-section">
            <h3>Payment Details</h3>
            <table>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td><?php echo htmlspecialchars($payment['PAYMENT_METHOD']); ?></td>
                </tr>
                
                <?php 
                // Only show bank/FPX details if payment method is ONLINE_BANKING
                if ($fpx_details && $payment['PAYMENT_METHOD'] === 'ONLINE_BANKING'): 
                ?>
                <tr>
                    <td><strong>Bank:</strong></td>
                    <td><?php echo htmlspecialchars($fpx_details['bank']); ?></td>
                </tr>
                <tr>
                    <td><strong>FPX Reference:</strong></td>
                    <td><?php echo htmlspecialchars($fpx_details['fpx_reference']); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php 
                // Show card details if payment method is CREDIT_CARD or DEBIT_CARD
                if ($fpx_details && ($payment['PAYMENT_METHOD'] === 'CREDIT_CARD' || $payment['PAYMENT_METHOD'] === 'DEBIT_CARD') && isset($fpx_details['card_last4'])): 
                ?>
                <tr>
                    <td><strong>Card Number:</strong></td>
                    <td>**** **** **** <?php echo htmlspecialchars($fpx_details['card_last4']); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php 
                // Show wallet details if payment method is E_WALLET
                if ($fpx_details && $payment['PAYMENT_METHOD'] === 'E_WALLET' && isset($fpx_details['wallet'])): 
                ?>
                <tr>
                    <td><strong>E-Wallet:</strong></td>
                    <td><?php echo htmlspecialchars($fpx_details['wallet']); ?></td>
                </tr>
                <?php endif; ?>
                
                <tr>
                    <td><strong>Payment Date:</strong></td>
                    <td><?php echo htmlspecialchars($payment['PAYMENT_DATE_FMT']); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Status:</strong></td>
                    <td><strong style="color: green;">SUCCESS</strong></td>
                </tr>
                <tr>
                    <td><strong>Total Paid:</strong></td>
                    <td><strong>RM <?php echo number_format($payment['TOTAL_AMOUNT'], 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <!-- Car Owner Details -->
        <div class="info-section">
            <h3>Car Owner Contact</h3>
            <table>
                <tr>
                    <td><strong>Name:</strong></td>
                    <td><?php echo htmlspecialchars($payment['LESSOR_NAME']); ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo htmlspecialchars($payment['LESSOR_EMAIL']); ?></td>
                </tr>
                <?php if ($payment['LESSOR_PHONE']): ?>
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td><?php echo htmlspecialchars($payment['LESSOR_PHONE']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <p><strong>Note:</strong> The car owner will contact you before the rental period to arrange pickup details.</p>
        </div>
        
        <!-- Next Steps -->
        <div class="info-section">
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
        <div class="action-buttons">
            <a href="booking_history.php">View Booking History</a>
            <a href="dashboard.php">Back to Dashboard</a>
            <button onclick="window.print()">🖨️ Print Receipt</button>
        </div>
        
        <p style="text-align: center; margin-top: 30px;">
            Thank you for using our Car Rental Platform System!<br>
            If you have any questions, please contact our support team.
        </p>
    </main>
    
    <?php
    // Clear payment success from session after displaying
    if (isset($_SESSION['payment_success'])) {
        unset($_SESSION['payment_success']);
    }
    ?>
</body>
</html>