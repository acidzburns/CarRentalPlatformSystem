<?php
/**
 * Renter Login Page
 * UC1: Registration and Login - Customer Login
 * 
 * Allows customers to login to their account
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
    
    // Exception 3: Check if fields are empty
    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    }
    else {
        // Get user from database
        $sql = "SELECT RENTERID, NAME, EMAIL, PASSWORD FROM RENTER WHERE EMAIL = :email";
        $user = db_fetch_one($sql, ['email' => $email]);
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['PASSWORD'])) {
                // FIX 5: Add Suspension Check here after password verification
                // Check if renter is suspended
                $status_check_sql = "SELECT STATUS FROM RENTER WHERE RENTERID = :userid";
                $stmt_status = oci_parse($conn, $status_check_sql);
                oci_bind_by_name($stmt_status, ':userid', $user['RENTERID']);
                oci_execute($stmt_status);
                $status_result = oci_fetch_assoc($stmt_status);

                if ($status_result && $status_result['STATUS'] == 'SUSPENDED') {
                    header("Location: account_suspended.php?email=" . urlencode($email));
                    exit();
                }
                
                // Set session
                set_session($user['RENTERID'], $user['NAME'], $user['EMAIL'], 'renter');
                
                // Redirect to dashboard
                header('Location: ../renter/dashboard.php');
                exit();
            }
            else {
                // Exception 4: Invalid credentials
                $error = "Invalid email or password. Please try again.";
            }
        }
        else {
            // Exception 4: Invalid credentials (user not found)
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
    <title>Customer Login - Car Rental</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 450px;
            margin: 80px auto;
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .other-login {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .other-login a {
            color: #28a745;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .other-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customer Login</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="renter_register.php">Register here</a>
        </div>
        
        <div class="other-login">
            <a href="lessor_login.php">Login as Car Owner</a> | 
            <a href="admin_login.php">Admin Login</a>
        </div>
    </div>
</body>
</html>
