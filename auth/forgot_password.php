<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../config/database.php';
require_once '../config/email_test.php'; // PHPMailer setup

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists in RENTER or LESSOR table
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

            // Redirect to code verification page
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
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/sys_globals.css">
<link rel="stylesheet" href="../assets/css/sys_layout.css">
<link rel="stylesheet" href="../assets/css/sys_components.css">
</head>
<body>
    <h2>Forgot Password</h2>
    <?php if($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <label for="email">Enter your email:</label><br>
        <input type="email" name="email" required><br><br>
        <button type="submit">Send Reset Code</button>
    </form>
</body>
</html>
