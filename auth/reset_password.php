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

        // Update RENTER table
        $sql_renter = "UPDATE CAR.RENTER SET PASSWORD = :pass WHERE EMAIL = :email";
        $stmt_renter = oci_parse($conn, $sql_renter);
        oci_bind_by_name($stmt_renter, ':pass', $hashed);
        oci_bind_by_name($stmt_renter, ':email', $email);
        oci_execute($stmt_renter);

        // Update LESSOR table
        $sql_lessor = "UPDATE CAR.LESSOR SET PASSWORD = :pass WHERE EMAIL = :email";
        $stmt_lessor = oci_parse($conn, $sql_lessor);
        oci_bind_by_name($stmt_lessor, ':pass', $hashed);
        oci_bind_by_name($stmt_lessor, ':email', $email);
        oci_execute($stmt_lessor);

        // Clear session reset info
        unset($_SESSION['reset_email'], $_SESSION['reset_token'], $_SESSION['reset_token_expiry']);

        $success = "Password reset successfully! <a href='renter_login.php'>Login here</a>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/sys_globals.css">
<link rel="stylesheet" href="../assets/css/sys_layout.css">
<link rel="stylesheet" href="../assets/css/sys_components.css">
</head>
<body>
    <h2>Set a New Password</h2>
    <?php if($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if($success): ?>
        <p style="color:green;"><?= $success ?></p>
    <?php else: ?>
    <form method="POST" action="">
        <label>New Password:</label><br>
        <input type="password" name="password" required><br><br>
        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required><br><br>
        <button type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
</body>
</html>
