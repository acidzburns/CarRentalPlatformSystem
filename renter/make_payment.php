<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();
$errors = [];

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    header("Location: dashboard.php");
    exit();
}

$bookingid = $_GET['bookingid'];

// Fetch booking details with car and lessor info
$sql = "SELECT b.*, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.PHOTO1_PATH,
        TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
        TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
        l.LESSORID, l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
        r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE
        FROM BOOKING b
        JOIN CAR c ON b.CARID = c.CARID
        JOIN LESSOR l ON c.LESSORID = l.LESSORID
        JOIN RENTER r ON b.RENTERID = r.RENTERID
        WHERE b.BOOKINGID = :bookingid 
        AND b.RENTERID = :renterid
        AND b.BOOKING_STATUS = 'PENDING'";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':bookingid', $bookingid);
oci_bind_by_name($stmt, ':renterid', $renterid);
oci_execute($stmt);

$booking = oci_fetch_assoc($stmt);

// Exception 1: Booking not found or payment already completed
if (!$booking) {
    set_flash_message("Booking not found or payment already completed.", "error");
    header("Location: dashboard.php");
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fpx_bank = trim($_POST['fpx_bank']);
    $amount = $booking['TOTAL_AMOUNT'];
    
    // Validate FPX bank selection
    if (empty($fpx_bank)) {
        $errors[] = "Please select your bank for FPX payment.";
    }
    
    // If no errors, process FPX payment
    if (empty($errors)) {
        // In real implementation, integrate with FPX gateway
        // For now, simulate FPX payment flow
        
        // Step 1: Generate FPX payment request
        $fpx_reference = 'FPX' . time() . rand(1000, 9999);
        
        // Store FPX transaction details temporarily
        $_SESSION['fpx_transaction'] = [
            'bookingid' => $bookingid,
            'fpx_reference' => $fpx_reference,
            'bank' => $fpx_bank,
            'amount' => $amount,
            'timestamp' => time()
        ];
        
        // In real FPX integration:
        // 1. POST to FPX gateway with merchant details
        // 2. Redirect user to bank login page
        // 3. User authenticates and approves payment
        // 4. FPX redirects back to callback URL
        
        // For simulation, redirect to FPX simulation page
        header("Location: fpx_simulation.php?ref=" . $fpx_reference);
        exit();
    }
}

// List of FPX participating banks (as of 2024)
$fpx_banks = [
    ['code' => 'ABB0233', 'name' => 'Affin Bank Berhad'],
    ['code' => 'ABMB0212', 'name' => 'Alliance Bank Malaysia Berhad'],
    ['code' => 'AMBB0209', 'name' => 'AmBank (M) Berhad'],
    ['code' => 'BIMB0340', 'name' => 'Bank Islam Malaysia Berhad'],
    ['code' => 'BMMB0341', 'name' => 'Bank Muamalat Malaysia Berhad'],
    ['code' => 'BKRM0602', 'name' => 'Bank Rakyat'],
    ['code' => 'BSN0601', 'name' => 'Bank Simpanan Nasional'],
    ['code' => 'BCBB0235', 'name' => 'CIMB Bank Berhad'],
    ['code' => 'HLB0224', 'name' => 'Hong Leong Bank Berhad'],
    ['code' => 'HSBC0223', 'name' => 'HSBC Bank Malaysia Berhad'],
    ['code' => 'KFH0346', 'name' => 'Kuwait Finance House'],
    ['code' => 'MB2U0227', 'name' => 'Maybank2u'],
    ['code' => 'OCBC0229', 'name' => 'OCBC Bank (Malaysia) Berhad'],
    ['code' => 'PBB0233', 'name' => 'Public Bank Berhad'],
    ['code' => 'RHB0218', 'name' => 'RHB Bank Berhad'],
    ['code' => 'SCB0216', 'name' => 'Standard Chartered Bank Malaysia Berhad'],
    ['code' => 'UOB0226', 'name' => 'United Overseas Bank (Malaysia) Berhad']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FPX Payment - Car Rental System</title>
    <style>
        .bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        .bank-option {
            border: 2px solid #ddd;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .bank-option:hover {
            border-color: #4CAF50;
            background: #f0f0f0;
        }
        .bank-option input[type="radio"] {
            display: none;
        }
        .bank-option input[type="radio"]:checked + label {
            background: #4CAF50;
            color: white;
        }
        .bank-option label {
            display: block;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>Make Payment - FPX Online Banking</h1>
    
    <?php display_flash_message(); ?>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
        <div style="background: #ffebee; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Booking Summary -->
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px 0; background: #f9f9f9;">
        <h2>Booking Summary</h2>
        
        <div style="display: flex; gap: 20px;">
            <?php if ($booking['PHOTO1_PATH']): ?>
                <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                     alt="Car" style="width: 200px; height: 150px; object-fit: cover;">
            <?php endif; ?>
            
            <div>
                <h3><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></h3>
                <p><strong>License Plate:</strong> <?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></p>
                <p><strong>Booking ID:</strong> #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></p>
                <p><strong>Rental Period:</strong> 
                    <?php echo htmlspecialchars($booking['START_DATE_FMT'] . ' to ' . $booking['END_DATE_FMT']); ?>
                </p>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></p>
            </div>
        </div>
        
        <hr>
        
       
        
        <h3>Payment Details</h3>
        <table style="width: 100%;">
            <tr>
                <td><strong>Total Amount:</strong></td>
                <td style="text-align: right; font-size: 24px; color: #4CAF50;">
                    <strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- FPX Payment Form -->
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
        <h2>Select Your Bank (FPX)</h2>
        
        
        
        <form method="POST" action="" id="fpxPaymentForm">
            <p><strong>Please select your bank to proceed with payment:</strong></p>
            
            <div class="bank-grid">
                <?php foreach ($fpx_banks as $bank): ?>
                    <div class="bank-option">
                        <input type="radio" name="fpx_bank" value="<?php echo htmlspecialchars($bank['code']); ?>" 
                               id="bank_<?php echo htmlspecialchars($bank['code']); ?>" required>
                        <label for="bank_<?php echo htmlspecialchars($bank['code']); ?>">
                            <?php echo htmlspecialchars($bank['name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            
            
            <div style="margin: 20px 0;">
                <button type="submit" style="background: #4CAF50; color: white; padding: 20px 40px; border: none; cursor: pointer; font-size: 18px; font-weight: bold; border-radius: 5px;">
                     Pay RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?> with FPX
                </button>
                
                <a href="create_booking.php?carid=<?php echo $booking['CARID']; ?>" 
                   style="display: inline-block; padding: 20px 40px; background: #f44336; color: white; text-decoration: none; margin-left: 10px; border-radius: 5px;">
                     Cancel Payment
                </a>
            </div>
            
            
        </form>
    </div>
    
    <script>
    // Highlight selected bank
    document.querySelectorAll('.bank-option input[type="radio"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            // Remove highlight from all
            document.querySelectorAll('.bank-option').forEach(function(option) {
                option.style.borderColor = '#ddd';
                option.style.background = '#fff';
            });
            
            // Highlight selected
            if (this.checked) {
                this.closest('.bank-option').style.borderColor = '#4CAF50';
                this.closest('.bank-option').style.background = '#e8f5e9';
            }
        });
    });
    
    // Confirm before payment
    document.getElementById('fpxPaymentForm').addEventListener('submit', function(e) {
        const selectedBank = document.querySelector('input[name="fpx_bank"]:checked');
        if (!selectedBank) {
            e.preventDefault();
            alert('Please select your bank to proceed.');
            return false;
        }
        
        const bankName = selectedBank.closest('.bank-option').querySelector('label').textContent.trim();
        if (!confirm('You will be redirected to ' + bankName + ' to complete payment of RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?>. Continue?')) {
            e.preventDefault();
            return false;
        }
    });
    </script>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>