<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!isset($_SESSION['reset_email'])) {
        $error = "No reset request found.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];

        $sql_renter = "UPDATE CAR.RENTER SET PASSWORD = :pass WHERE EMAIL = :email";
        $stmt_renter = oci_parse($conn, $sql_renter);
        oci_bind_by_name($stmt_renter, ':pass', $hashed);
        oci_bind_by_name($stmt_renter, ':email', $email);
        oci_execute($stmt_renter);

        $sql_lessor = "UPDATE CAR.LESSOR SET PASSWORD = :pass WHERE EMAIL = :email";
        $stmt_lessor = oci_parse($conn, $sql_lessor);
        oci_bind_by_name($stmt_lessor, ':pass', $hashed);
        oci_bind_by_name($stmt_lessor, ':email', $email);
        oci_execute($stmt_lessor);

        unset($_SESSION['reset_email'], $_SESSION['reset_token'], $_SESSION['reset_token_expiry']);

        $success = "Password reset successfully! <a href='index.php'>Login here</a>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; flex-direction: column; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
        .navbar { background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 1rem 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
        .nav-logo { color: white; font-size: 1.5rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }
        .container { background: white; padding: 3rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%; }
        .header-section { text-align: center; margin-bottom: 2rem; }
        .icon-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 50%; margin-bottom: 1.5rem; }
        .icon-wrapper i { font-size: 40px; color: #10b981; }
        h2 { color: #1e293b; font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 1rem; line-height: 1.6; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-success a { color: #047857; font-weight: 700; text-decoration: underline; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; font-size: 0.875rem; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; }
        input[type="password"] { width: 100%; padding: 0.875rem 1rem 0.875rem 3rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: all 0.3s; }
        input[type="password"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); }
        .info-box { background: #f8fafc; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; border-left: 4px solid #10b981; }
        .info-box ul { margin: 0.5rem 0 0 1.5rem; color: #475569; font-size: 0.875rem; line-height: 1.8; }
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
                <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                <h2>Set New Password</h2>
                <p class="subtitle">Create a strong password to secure your account</p>
            </div>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?= $success ?></span></div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Enter new password" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-check"></i> Reset Password</button>
                </form>
                <div class="info-box">
                    <p style="color: #475569; font-size: 0.875rem; margin: 0 0 0.5rem 0;"><strong><i class="fas fa-shield-alt"></i> Password Requirements:</strong></p>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Include letters and numbers</li>
                        <li>Use a unique password</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</body>
</html>