<?php
/**
 * FPX Payment Gateway Simulation
 * Simulates Malaysian FPX (Financial Process Exchange) online banking
 */

// Start session but don't check authentication yet (allow public access to payment gateway)
session_start();

// Check if payment session exists
if (!isset($_SESSION['pending_payment'])) {
    header("Location: dashboard.php?error=invalid_payment_session");
    exit();
}

$payment_data = $_SESSION['pending_payment'];
$booking_id = $payment_data['bookingid'];
$amount = $payment_data['amount'];
$bank = $payment_data['bank'];

// Generate FPX reference number
$fpx_reference = 'FPX' . time() . rand(1000, 9999);

$error = "";

// Process FPX payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_number = trim($_POST['account_number']);
    $password = $_POST['password'];
    
    // Simple validation (in real system, would verify with bank)
    if (empty($account_number) || empty($password)) {
        $error = "Please enter your account number and password.";
    } 
    elseif (strlen($account_number) < 10) {
        $error = "Invalid account number format.";
    }
    else {
        // Simulate payment success
        // Store payment details for callback
        $_SESSION['fpx_payment'] = [
            'bookingid' => $booking_id,
            'amount' => $amount,
            'bank' => $bank,
            'fpx_reference' => $fpx_reference,
            'account_number' => $account_number,
            'status' => 'SUCCESS'
        ];
        
        // Clear pending payment
        unset($_SESSION['pending_payment']);
        
        // Redirect to callback
        header("Location: fpx_callback.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FPX Payment Gateway - <?php echo htmlspecialchars($bank); ?></title>
</head>
<body>
    <header style="background: #003d82; color: white; padding: 20px; text-align: center;">
        <h1>🏦 FPX Payment Gateway</h1>
        <p>Financial Process Exchange - Secure Online Banking</p>
    </header>
    
    <main style="max-width: 600px; margin: 30px auto; padding: 20px;">
        
        <div style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2>Payment Summary</h2>
            <p><strong>Merchant:</strong> Car Rental Platform System</p>
            <p><strong>Bank:</strong> <?php echo htmlspecialchars($bank); ?></p>
            <p><strong>Amount:</strong> <span style="font-size: 24px; color: #d32f2f;">RM <?php echo number_format($amount, 2); ?></span></p>
            <p><strong>Reference:</strong> <?php echo htmlspecialchars($fpx_reference); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 5px;">
            <h2><?php echo htmlspecialchars($bank); ?> Login</h2>
            <p style="color: #666; margin-bottom: 20px;">Please enter your online banking credentials</p>
            
            <form method="POST" action="">
                
                <div style="margin-bottom: 20px;">
                    <label for="account_number" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        Account Number *
                    </label>
                    <input type="text" 
                           id="account_number" 
                           name="account_number" 
                           placeholder="Enter your account number"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                           required>
                    <small style="color: #666;">Test Account: 564123456789</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="password" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        Password *
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                           required>
                    <small style="color: #666;">Test Password: maybank123</small>
                </div>
                
                <div style="background: #fff3e0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 14px;">
                        🔒 <strong>Secure Transaction</strong><br>
                        Your information is encrypted and secure. This is a simulation for testing purposes.
                    </p>
                </div>
                
                <button type="submit" 
                        style="width: 100%; padding: 15px; background: #4caf50; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer;">
                    Pay RM <?php echo number_format($amount, 2); ?>
                </button>
                
                <p style="text-align: center; margin-top: 20px;">
                    <a href="make_payment.php?bookingid=<?php echo $booking_id; ?>" 
                       style="color: #666; text-decoration: none;">
                        ← Cancel Payment
                    </a>
                </p>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
            <h3>ℹ️ For Testing Purposes</h3>
            <p><strong>Test Credentials for any bank:</strong></p>
            <ul>
                <li>Account Number: 564123456789</li>
                <li>Password: maybank123</li>
            </ul>
            <p style="margin-top: 10px; font-size: 14px; color: #666;">
                This is a simulation of the FPX payment gateway. In a real system, you would be redirected to your actual bank's secure login page.
            </p>
        </div>
        
    </main>
    
    <footer style="text-align: center; padding: 20px; color: #666; font-size: 14px;">
        <p>Powered by FPX (Financial Process Exchange)</p>
        <p>© <?php echo date('Y'); ?> - Secure Payment Gateway Simulation</p>
    </footer>
</body>
</html>