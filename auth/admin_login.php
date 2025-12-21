<?php
/**
 * Admin Login Page
 * UC22: Admin Login
 * 
 * Allows administrators to login to the admin panel
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
    
    // Exception 1 & 2: Check if fields are empty
    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    }
    else {
        // Get admin from database
        $sql = "SELECT ADMINID, NAME, EMAIL, PASSWORD FROM ADMIN WHERE EMAIL = :email";
        $admin = db_fetch_one($sql, ['email' => $email]);
        
        if ($admin) {
            // Check if password is hashed or plain text (for migration purposes)
            $password_valid = false;
            
            // Try verifying as hashed password first
            if (password_verify($password, $admin['PASSWORD'])) {
                $password_valid = true;
            }
            // Fallback: Check if it's plain text (for existing admin123)
            elseif ($password === $admin['PASSWORD']) {
                $password_valid = true;
                
                // Update to hashed password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE ADMIN SET PASSWORD = :password WHERE ADMINID = :adminid";
                db_execute($sql_update, ['password' => $hashed_password, 'adminid' => $admin['ADMINID']]);
            }
            
            if ($password_valid) {
                // Set session
                set_session($admin['ADMINID'], $admin['NAME'], $admin['EMAIL'], 'admin');
                
                // Redirect to admin dashboard
                header('Location: ../admin/dashboard.php');
                exit();
            }
            else {
                // Exception 1: Invalid credentials
                $error = "Invalid email or password. Please try again.";
            }
        }
        else {
            // Exception 2: Admin account not found
            $error = "Admin account not found. Please contact system administrator.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Car Rental</title>
</head>
<body>
    <div>
        <h1>Administrator Login</h1>
        <p>Secure access to system administration</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div>
                <label for="email">Admin Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            
            <div>
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div>
            <a href="renter_login.php">Customer Login</a> | 
            <a href="lessor_login.php">Car Owner Login</a> | 
            <a href="../index.php">Back to Home</a>
        </div>
        
        
    </div>
</body>
</html>