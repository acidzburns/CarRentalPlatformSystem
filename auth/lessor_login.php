<?php
/**
 * Lessor Login Page
 * Allows car owners to login to their account
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirect_if_logged_in();

$error = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } 
    else {

        // ✅ FIXED: Oracle bind variable
        $sql = "SELECT LESSORID, NAME, EMAIL, PASSWORD, VERIFICATION_STATUS
                FROM LESSOR
                WHERE EMAIL = :email";

        $user = db_fetch_one($sql, ['email' => $email]);

        if ($user && password_verify($password, $user['PASSWORD'])) {

            switch ($user['VERIFICATION_STATUS']) {

                case 'PENDING':
                    $error = "Your account is pending verification. Please wait for admin approval.";
                    break;

                case 'REJECTED':
                    set_session($user['LESSORID'], $user['NAME'], $user['EMAIL'], 'lessor');
                    header('Location: ../lessor/resubmit_verification.php');
                    exit();

                case 'SUSPENDED':
                    header("Location: account_suspended.php?email=" . urlencode($email));
                    exit();

                case 'VERIFIED':
                    set_session($user['LESSORID'], $user['NAME'], $user['EMAIL'], 'lessor');
                    header('Location: ../lessor/dashboard.php');
                    exit();

                default:
                    $error = "Account status unknown. Please contact support.";
            }

        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Car Owner Login | Car Rental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/sys_globals.css">
    <link rel="stylesheet" href="../assets/css/sys_components.css">
    <link rel="stylesheet" href="../assets/css/sys_auth.css">

    <style>
        body.auth-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #020617);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, sans-serif;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .auth-card h1 {
            text-align: center;
            margin-bottom: 6px;
        }

        .auth-card p {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
        }

        .auth-card label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }

        .auth-card input {
            width: 100%;
            padding: 10px;
            margin-bottom: 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .auth-card button {
            width: 100%;
            padding: 12px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        .auth-card button:hover {
            background: #15803d;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }

        .auth-links {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }

        .auth-links a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 6px;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body class="auth-page">

<div class="auth-container">
    <div class="auth-card">

        <h1>Car Owner Login</h1>
        <p>Manage your vehicles and bookings</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email"
                   value="<?= htmlspecialchars($email) ?>" required autofocus>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Login</button>
        </form>

        <div class="auth-links">
            <p>
                Don’t have an account?
                <a href="lessor_register.php">Register</a>
            </p>
            <p>
                <a href="forgot_password.php">Forgot Password?</a>
            </p>
            <p>
                <a href="renter_login.php">Customer Login</a> |
                <a href="admin_login.php">Admin Login</a>
            </p>
        </div>

    </div>
</div>

</body>
</html>
