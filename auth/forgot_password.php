<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../config/database.php';
require_once '../config/email_test.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $sql_renter = "SELECT RENTERID FROM CAR.RENTER WHERE EMAIL = :email";
    $stmt_renter = oci_parse($conn, $sql_renter);
    oci_bind_by_name($stmt_renter, ':email', $email);
    oci_execute($stmt_renter);
    $renter = oci_fetch_assoc($stmt_renter);

    $sql_lessor = "SELECT LESSORID FROM CAR.LESSOR WHERE EMAIL = :email";
    $stmt_lessor = oci_parse($conn, $sql_lessor);
    oci_bind_by_name($stmt_lessor, ':email', $email);
    oci_execute($stmt_lessor);
    $lessor = oci_fetch_assoc($stmt_lessor);

    if ($renter || $lessor) {
        $token = rand(100000, 999999);

        $subject = "Car Rental Password Reset Code";
        $body = "<p>Your password reset code is: <b>$token</b></p><p>This code will expire in 10 minutes.</p>";

        if (send_email($email, $subject, $body)) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_token'] = $token;
            $_SESSION['reset_token_expiry'] = time() + 600;

            header("Location: verify_reset_code.php");
            exit();
        } else {
            $error = "Failed to send email. Please try again later.";
        }
    } else {
        $error = "Email not found in our system.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; flex-direction: column; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
        .navbar { background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 1rem 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav-logo { color: white; font-size: 1.5rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }
        .container { background: white; padding: 3rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%; animation: slideUp 0.5s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .header-section { text-align: center; margin-bottom: 2rem; }
        .icon-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 50%; margin-bottom: 1.5rem; }
        .icon-wrapper i { font-size: 40px; color: #2563eb; }
        h2 { color: #1e293b; font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 1rem; line-height: 1.6; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; font-size: 0.875rem; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; }
        input[type="email"] { width: 100%; padding: 0.875rem 1rem 0.875rem 3rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: all 0.3s; }
        input[type="email"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }
        .info-box { background: #f8fafc; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; border-left: 4px solid #2563eb; }
        .info-box p { color: #475569; font-size: 0.875rem; margin: 0; line-height: 1.6; }
        .back-link { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; }
        .back-link a { color: #2563eb; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .footer { background: #1e293b; color: white; text-align: center; padding: 1.5rem; margin-top: auto; }
        @media (max-width: 640px) { .container { padding: 2rem 1.5rem; margin: 1rem; } h2 { font-size: 1.5rem; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="nav-logo"><i class="fas fa-car"></i> Car Rental System</a>
        </div>
    </nav>
    <div class="main-content">
        <div class="container">
            <div class="header-section">
                <div class="icon-wrapper"><i class="fas fa-key"></i></div>
                <h2>Forgot Password?</h2>
                <p class="subtitle">No worries! Enter your email address and we'll send you a reset code.</p>
            </div>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required>
                    </div>
                </div>
                <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Reset Code</button>
            </form>
            <div class="info-box">
                <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> The reset code will be sent to your email and will expire in 10 minutes.</p>
            </div>
            <div class="back-link">
                <a href="renter_login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
    
</body>
</html>