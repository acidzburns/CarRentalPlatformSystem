<?php
/**
 * Lessor Login Page
 * Allows car owners to login to their account
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirect_if_logged_in();

// Initialize variables
$error = "";
$email = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Check if fields are empty
    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    }
    else {
        // Get user from database
        $sql = "SELECT LESSORID, NAME, EMAIL, PASSWORD, VERIFICATION_STATUS FROM LESSOR WHERE EMAIL = :email";
        $user = db_fetch_one($sql, ['email' => $email]);
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['PASSWORD'])) {
                // Check verification status
                if ($user['VERIFICATION_STATUS'] === 'PENDING') {
                    $error = "Your account is pending verification. Please wait for admin approval.";
                }
                elseif ($user['VERIFICATION_STATUS'] === 'REJECTED') {
                    // Allow rejected lessors to login so they can re-submit
                    set_session($user['LESSORID'], $user['NAME'], $user['EMAIL'], 'lessor');
                    
                    // Redirect to re-submission page
                    header('Location: ../lessor/resubmit_verification.php');
                    exit();
                }
                // ✅ NEW: Check for SUSPENDED status
                elseif ($user['VERIFICATION_STATUS'] === 'SUSPENDED') {
                    // Redirect to suspension notice page with email
                    header("Location: account_suspended.php?email=" . urlencode($email));
                    exit();
                }
                elseif ($user['VERIFICATION_STATUS'] === 'VERIFIED') {
                    // Set session
                    set_session($user['LESSORID'], $user['NAME'], $user['EMAIL'], 'lessor');
                    
                    // Redirect to dashboard
                    header('Location: ../lessor/dashboard.php');
                    exit();
                }
                else {
                    $error = "Account status unknown. Please contact support.";
                }
            }
            else {
                // Invalid credentials
                $error = "Invalid email or password. Please try again.";
            }
        }
        else {
            // Invalid credentials (user not found)
            $error = "Invalid email or password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Owner Login - Car Rental</title>
</head>
<body>
    <div>
        <h1>Car Owner Login</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div>
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div>
            Don't have an account? <a href="lessor_register.php">Register here</a>
        </div>
        <p><a href="forgot_password.php">Forgot Password?</a></p>

        <div>
            <a href="renter_login.php">Login as Customer</a> | 
            <a href="admin_login.php">Admin Login</a>
        </div>
    </div>
</body>
</html>