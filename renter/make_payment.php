<?php
/**
 * Make Payment Page
 * UC5: Make Payment
 * 
 * Complete payment for booking using available payment methods
 * SRS Pages 28-30
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if renter is logged in
check_authentication('renter');

$renter_id = get_user_id();
$renter_name = get_user_name();

// Get booking ID
if (!isset($_GET['bookingid'])) {
    header("Location: booking_history.php");
    exit();
}

$booking_id = $_GET['bookingid'];

// Precondition: Booking must be in "Pending Payment" status
$sql_booking = "SELECT b.*, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
                TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
                TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
                (b.END_DATE - b.START_DATE) as RENTAL_DAYS,
                l.NAME as LESSOR_NAME
                FROM BOOKING b
                JOIN CAR c ON b.CARID = c.CARID
                JOIN LESSOR l ON c.LESSORID = l.LESSORID
                WHERE b.BOOKINGID = :bookingid 
                AND b.RENTERID = :renterid
                AND b.BOOKING_STATUS = 'PENDING'";

$booking = db_fetch_one($sql_booking, [
    'bookingid' => $booking_id,
    'renterid' => $renter_id
]);

if (!$booking) {
    header("Location: booking_history.php?error=invalid_booking");
    exit();
}

// Check if payment already exists
$sql_check_payment = "SELECT PAYMENTID FROM PAYMENT WHERE BOOKINGID = :bookingid";
$existing_payment = db_fetch_one($sql_check_payment, ['bookingid' => $booking_id]);

if ($existing_payment) {
    header("Location: payment_success.php?bookingid=" . $booking_id);
    exit();
}

$error = "";
$selected_method = "";

// Step 2: Customer selects payment method
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_method'])) {
    $selected_method = $_POST['payment_method'];
    
    // Validate payment method (SRS: credit card, debit card, online banking, e-wallet)
    $valid_methods = ['CREDIT_CARD', 'DEBIT_CARD', 'ONLINE_BANKING', 'E_WALLET'];
    
    if (!in_array($selected_method, $valid_methods)) {
        $error = "Please select a valid payment method.";
        $selected_method = "";
    }
}

// Step 4: Process payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'];
    
    // Step 3: Customer enters payment details
    if ($payment_method === 'CREDIT_CARD' || $payment_method === 'DEBIT_CARD') {
        $card_number = trim($_POST['card_number']);
        $card_name = trim($_POST['card_name']);
        $expiry_month = trim($_POST['expiry_month']);
        $expiry_year = trim($_POST['expiry_year']);
        $cvv = trim($_POST['cvv']);
        
        // Exception 1: Invalid payment details
        if (empty($card_number) || empty($card_name) || empty($expiry_month) || empty($expiry_year) || empty($cvv)) {
            $error = "Invalid payment information. Please check your details.";
        }
        elseif (!preg_match('/^\d{16}$/', $card_number)) {
            $error = "Invalid payment information. Please check your details.";
        }
        elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
            $error = "Invalid payment information. Please check your details.";
        }
        else {
            // Simulate payment processing
            // In real system, would integrate with payment gateway
            
            // For simulation: Check if card number starts with 4 (Visa) or 5 (Mastercard)
            if (substr($card_number, 0, 1) === '4' || substr($card_number, 0, 1) === '5') {
                // Step 5: Payment successful
                
                // Step 6: Update booking status to "Confirmed"
                $sql_update_booking = "UPDATE BOOKING SET BOOKING_STATUS = 'CONFIRMED' 
                                      WHERE BOOKINGID = :bookingid";
                db_execute($sql_update_booking, ['bookingid' => $booking_id]);
                
                // Step 7: Generate booking confirmation receipt
                $sql_insert_payment = "INSERT INTO PAYMENT 
                                      (PAYMENTID, BOOKINGID, RENTERID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS, PAYMENT_DATE)
                                      VALUES (SEQ_PAYMENT.NEXTVAL, :bookingid, :renterid, :method, :amount, 'SUCCESS', SYSDATE)";
                
                $payment_result = db_execute($sql_insert_payment, [
                    'bookingid' => $booking_id,
                    'renterid' => $renter_id,
                    'method' => $payment_method,
                    'amount' => $booking['TOTAL_AMOUNT']
                ]);
                
                if ($payment_result) {
                    // Get payment ID
                    $payment_id = db_last_insert_id('SEQ_PAYMENT');
                    
                    // Store payment info in session for receipt
                    $_SESSION['payment_success'] = [
                        'bookingid' => $booking_id,
                        'paymentid' => $payment_id,
                        'method' => $payment_method,
                        'card_last4' => substr($card_number, -4)
                    ];
                    
                    // Step 8: Send confirmation email (simulated)
                    // In real system, would send actual email
                    
                    // Step 9: Display success message
                    header("Location: payment_success.php?bookingid=" . $booking_id);
                    exit();
                }
            } else {
                // Exception 2: Insufficient funds / Payment declined
                $error = "Payment declined. Please try another payment method.";
            }
        }
        
    } elseif ($payment_method === 'ONLINE_BANKING') {
        $bank = $_POST['bank'];
        
        if (empty($bank)) {
            $error = "Please select your bank.";
        } else {
            // Redirect to FPX simulation
            $_SESSION['pending_payment'] = [
                'bookingid' => $booking_id,
                'amount' => $booking['TOTAL_AMOUNT'],
                'bank' => $bank
            ];
            header("Location: fpx_simulation.php");
            exit();
        }
        
    } elseif ($payment_method === 'E_WALLET') {
        $wallet_type = $_POST['wallet_type'];
        $wallet_phone = trim($_POST['wallet_phone']);
        
        // Exception 1: Invalid payment details
        if (empty($wallet_type) || empty($wallet_phone)) {
            $error = "Invalid payment information. Please check your details.";
        }
        elseif (!preg_match('/^01\d{8,9}$/', $wallet_phone)) {
            $error = "Invalid phone number format. Please enter a valid Malaysian phone number.";
        }
        else {
            // Simulate e-wallet payment
            // In real system, would integrate with wallet provider API
            
            // For simulation: Always succeed
            
            // Update booking status
            $sql_update_booking = "UPDATE BOOKING SET BOOKING_STATUS = 'CONFIRMED' 
                                  WHERE BOOKINGID = :bookingid";
            db_execute($sql_update_booking, ['bookingid' => $booking_id]);
            
            // Insert payment record
            $sql_insert_payment = "INSERT INTO PAYMENT 
                                  (PAYMENTID, BOOKINGID, RENTERID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS, PAYMENT_DATE)
                                  VALUES (SEQ_PAYMENT.NEXTVAL, :bookingid, :renterid, :method, :amount, 'SUCCESS', SYSDATE)";
            
            $payment_result = db_execute($sql_insert_payment, [
                'bookingid' => $booking_id,
                'renterid' => $renter_id,
                'method' => 'E_WALLET',
                'amount' => $booking['TOTAL_AMOUNT']
            ]);
            
            if ($payment_result) {
                $payment_id = db_last_insert_id('SEQ_PAYMENT');
                
                $_SESSION['payment_success'] = [
                    'bookingid' => $booking_id,
                    'paymentid' => $payment_id,
                    'method' => 'E_WALLET',
                    'wallet' => $wallet_type
                ];
                
                header("Location: payment_success.php?bookingid=" . $booking_id);
                exit();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Car Rental</title>
    <script>
        function showPaymentForm(method) {
            // Hide all forms
            document.querySelectorAll('.payment-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Show selected form
            if (method) {
                document.getElementById(method + '_form').style.display = 'block';
            }
        }
    </script>
</head>
<body>
    <nav>
        <h2>Car Rental System</h2>
        <p>Welcome, <?php echo htmlspecialchars($renter_name); ?>!</p>
        <a href="dashboard.php">Dashboard</a>
        <a href="booking_history.php">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <main>
        <h1>Complete Payment</h1>
        <p>Booking ID: #<?php echo htmlspecialchars($booking_id); ?></p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Step 1: Display payment summary -->
        <div class="payment-summary">
            <h2>Payment Summary</h2>
            
            <?php if ($booking['PHOTO1_PATH']): ?>
            <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                 alt="Car" width="200">
            <?php endif; ?>
            
            <h3><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></h3>
            <p><strong>License Plate:</strong> <?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></p>
            <p><strong>Car Owner:</strong> <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></p>
            
            <hr>
            
            <p><strong>Rental Period:</strong><br>
               <?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' to ' . $booking['END_DATE_FMT']); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($booking['RENTAL_DAYS']); ?> day(s)</p>
            
            <hr>
            
            <h3>Amount to Pay:</h3>
            <p style="font-size: 24px;"><strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong></p>
        </div>
        
        <hr>
        
        <!-- Step 2: Customer selects payment method -->
        <?php if (empty($selected_method)): ?>
        
        <h2>Select Payment Method</h2>
        <p>Choose your preferred payment method:</p>
        
        <form method="POST" action="">
            <input type="hidden" name="select_method" value="1">
            
            <div>
                <input type="radio" id="credit_card" name="payment_method" value="CREDIT_CARD" required>
                <label for="credit_card"><strong>Credit Card</strong> (Visa, Mastercard)</label>
            </div>
            
            <div>
                <input type="radio" id="debit_card" name="payment_method" value="DEBIT_CARD">
                <label for="debit_card"><strong>Debit Card</strong> (Visa Debit, Mastercard Debit)</label>
            </div>
            
            <div>
                <input type="radio" id="online_banking" name="payment_method" value="ONLINE_BANKING">
                <label for="online_banking"><strong>Online Banking</strong> (FPX - All Malaysian Banks)</label>
            </div>
            
            <div>
                <input type="radio" id="e_wallet" name="payment_method" value="E_WALLET">
                <label for="e_wallet"><strong>E-Wallet</strong> (Touch 'n Go, GrabPay, Boost, ShopeePay)</label>
            </div>
            
            <br>
            <button type="submit">Continue to Payment</button>
            <a href="booking_history.php">Cancel</a>
        </form>
        
        <?php else: ?>
        
        <!-- Step 3: Customer enters payment details -->
        <h2>Enter Payment Details</h2>
        <p>Selected Method: <strong><?php echo htmlspecialchars(str_replace('_', ' ', $selected_method)); ?></strong></p>
        
        <!-- Credit Card Form -->
        <?php if ($selected_method === 'CREDIT_CARD' || $selected_method === 'DEBIT_CARD'): ?>
        <div id="<?php echo $selected_method; ?>_form" class="payment-form">
            <form method="POST" action="">
                <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($selected_method); ?>">
                <input type="hidden" name="process_payment" value="1">
                
                <h3><?php echo $selected_method === 'CREDIT_CARD' ? 'Credit Card' : 'Debit Card'; ?> Details</h3>
                
                <label for="card_number">Card Number *</label>
                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" 
                       maxlength="16" pattern="\d{16}" required>
                <p><em>Test cards: 4111111111111111 (Visa), 5555555555554444 (Mastercard)</em></p>
                
                <label for="card_name">Cardholder Name *</label>
                <input type="text" id="card_name" name="card_name" placeholder="JOHN DOE" required>
                
                <label for="expiry">Expiry Date *</label>
                <select id="expiry_month" name="expiry_month" required>
                    <option value="">Month</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                    <?php endfor; ?>
                </select>
                <select id="expiry_year" name="expiry_year" required>
                    <option value="">Year</option>
                    <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                
                <label for="cvv">CVV/CVC *</label>
                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" pattern="\d{3,4}" required>
                <p><em>3 or 4 digit security code on back of card</em></p>
                
                <br>
                <button type="submit">Pay RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></button>
                <a href="make_payment.php?bookingid=<?php echo $booking_id; ?>">Change Payment Method</a>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Online Banking Form -->
        <?php if ($selected_method === 'ONLINE_BANKING'): ?>
        <div id="ONLINE_BANKING_form" class="payment-form">
            <form method="POST" action="">
                <input type="hidden" name="payment_method" value="ONLINE_BANKING">
                <input type="hidden" name="process_payment" value="1">
                
                <h3>Select Your Bank</h3>
                <p>You will be redirected to your bank's secure payment page (FPX)</p>
                
                <select name="bank" required>
                    <option value="">-- Select Bank --</option>
                    <option value="Maybank">Maybank</option>
                    <option value="CIMB Bank">CIMB Bank</option>
                    <option value="Public Bank">Public Bank</option>
                    <option value="RHB Bank">RHB Bank</option>
                    <option value="Hong Leong Bank">Hong Leong Bank</option>
                    <option value="AmBank">AmBank</option>
                    <option value="Bank Islam">Bank Islam</option>
                    <option value="Bank Rakyat">Bank Rakyat</option>
                    <option value="OCBC Bank">OCBC Bank</option>
                    <option value="HSBC Bank">HSBC Bank</option>
                    <option value="Standard Chartered">Standard Chartered</option>
                    <option value="UOB Bank">UOB Bank</option>
                </select>
                
                <br><br>
                <button type="submit">Continue to Bank</button>
                <a href="make_payment.php?bookingid=<?php echo $booking_id; ?>">Change Payment Method</a>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- E-Wallet Form -->
        <?php if ($selected_method === 'E_WALLET'): ?>
        <div id="E_WALLET_form" class="payment-form">
            <form method="POST" action="">
                <input type="hidden" name="payment_method" value="E_WALLET">
                <input type="hidden" name="process_payment" value="1">
                
                <h3>E-Wallet Payment</h3>
                
                <label for="wallet_type">Select E-Wallet *</label>
                <select id="wallet_type" name="wallet_type" required>
                    <option value="">-- Select Wallet --</option>
                    <option value="Touch 'n Go eWallet">Touch 'n Go eWallet</option>
                    <option value="GrabPay">GrabPay</option>
                    <option value="Boost">Boost</option>
                    <option value="ShopeePay">ShopeePay</option>
                </select>
                
                <label for="wallet_phone">Phone Number (Registered with E-Wallet) *</label>
                <input type="tel" id="wallet_phone" name="wallet_phone" placeholder="0123456789" 
                       pattern="01\d{8,9}" required>
                <p><em>Enter the phone number registered with your e-wallet</em></p>
                
                <br>
                <button type="submit">Pay RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></button>
                <a href="make_payment.php?bookingid=<?php echo $booking_id; ?>">Change Payment Method</a>
            </form>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <!-- Security Notice -->
        <div class="security-notice">
            <h3>🔒 Secure Payment</h3>
            <p>Your payment information is encrypted and secure. We do not store your card details.</p>
        </div>
        
    </main>
</body>
</html>